<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/trazabilidad_datos.php';

const INFORME_ESTADOS = ['Borrador', 'Finalizado'];
const INFORME_ATENCIONES = ['Evaluación', 'Consejería', 'Orientación', 'Terapia', 'Acompañamiento pedagógico'];

function informe_datos(array $entrada): array
{
    $datos = [];
    foreach (['titulo', 'fecha', 'motivo', 'diagnostico', 'aspecto_cognitivo', 'aspectos_afectivos',
        'diagnostico_acuerdos', 'recomendaciones', 'recibido_por', 'referido_por', 'estado'] as $campo) {
        $datos[$campo] = is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
    }
    foreach (['id_estudiante', 'id_historia', 'numero_atenciones'] as $campo) {
        $valor = filter_var($entrada[$campo] ?? 0, FILTER_VALIDATE_INT);
        $datos[$campo] = $valor === false ? -1 : $valor;
    }
    $tipos = $entrada['tipo_atencion'] ?? [];
    $datos['tipo_atencion'] = is_array($tipos) ? array_values(array_unique(array_filter($tipos, 'is_string'))) : [];
    // NULL significa que un cliente anterior no envió el vínculo; 0 lo deja sin seguimiento.
    $datos['id_seguimiento'] = array_key_exists('id_seguimiento',$entrada)
        ? (filter_var($entrada['id_seguimiento'] === '' ? 0 : $entrada['id_seguimiento'], FILTER_VALIDATE_INT) === false ? -1 : (int)$entrada['id_seguimiento']) : null;
    return $datos;
}

function informe_validar(array $datos): void
{
    if (($datos['id_seguimiento'] ?? 0) < 0) throw new InvalidArgumentException('El seguimiento no es válido.');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha']);
    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha'] || $datos['fecha'] > date('Y-m-d')) {
        throw new InvalidArgumentException('La fecha del informe no es válida.');
    }
    if ($datos['id_estudiante'] <= 0 || $datos['id_historia'] < 0
        || $datos['numero_atenciones'] < 0 || $datos['numero_atenciones'] > 2147483647) {
        throw new InvalidArgumentException('Revise el estudiante, la historia y el número de atenciones.');
    }
    if (!$datos['tipo_atencion'] || array_diff($datos['tipo_atencion'], INFORME_ATENCIONES)) {
        throw new InvalidArgumentException('Seleccione al menos un tipo de atención válido.');
    }
    if (!in_array($datos['estado'], INFORME_ESTADOS, true)) {
        throw new InvalidArgumentException('El estado seleccionado no es válido.');
    }
    foreach (['titulo', 'motivo', 'aspecto_cognitivo', 'aspectos_afectivos'] as $campo) {
        if ($datos[$campo] === '') throw new InvalidArgumentException('Complete los campos obligatorios del informe.');
    }
    foreach (['titulo' => 180, 'referido_por' => 150, 'recibido_por' => 150, 'motivo' => 5000,
        'diagnostico' => 5000, 'aspecto_cognitivo' => 5000, 'aspectos_afectivos' => 5000,
        'diagnostico_acuerdos' => 5000, 'recomendaciones' => 5000] as $campo => $maximo) {
        if (!mb_check_encoding($datos[$campo], 'UTF-8') || mb_strlen($datos[$campo]) > $maximo) {
            throw new InvalidArgumentException("El campo $campo admite hasta $maximo caracteres UTF-8.");
        }
    }
}

function informe_crear(mysqli $conexion, array $datos, int $idUsuario): string
{
    informe_validar($datos);
    if ($idUsuario <= 0) throw new InvalidArgumentException('No se identificó al autor del informe.');
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ? AND estado = 'Activo'");
        $stmt->bind_param('i', $datos['id_estudiante']);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El estudiante no existe o no está activo.');

        $stmt = $conexion->prepare('SELECT id_historia, id_derivacion FROM historias_clinicas WHERE id_estudiante = ? ORDER BY id_historia DESC LIMIT 1');
        $stmt->bind_param('i', $datos['id_estudiante']);
        $stmt->execute();
        $historia = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $idHistoria = (int)($historia['id_historia'] ?? 0);
        if ($datos['id_historia'] > 0 && $datos['id_historia'] !== $idHistoria) {
            throw new InvalidArgumentException('La historia clínica no corresponde al estudiante.');
        }
        $idDerivacion = (int)($historia['id_derivacion'] ?? 0);
        $idSeguimiento = $datos['id_seguimiento'] ?? null;
        if ($idSeguimiento === null && $idHistoria) {
            $ultimo = flujo_fila($conexion,'SELECT id_seguimiento FROM seguimientos WHERE id_historia=? AND fecha<=? ORDER BY fecha DESC,id_seguimiento DESC LIMIT 1',[$idHistoria,$datos['fecha']]);
            $idSeguimiento = (int)($ultimo['id_seguimiento'] ?? 0);
        }
        if ($idSeguimiento) {
            $origen = flujo_fila($conexion,'SELECT s.*,c.id_derivacion FROM seguimientos s LEFT JOIN citas c ON c.id_cita=s.id_cita WHERE s.id_seguimiento=?',[$idSeguimiento]);
            if (!$origen || (int)$origen['id_historia'] !== $idHistoria || $origen['fecha'] > $datos['fecha']) throw new InvalidArgumentException('El seguimiento no corresponde a la historia o es posterior al informe.');
            $idDerivacion = (int)($origen['id_derivacion'] ?? $idDerivacion);
        }
        if (!$historia) {
            $stmt = $conexion->prepare('SELECT id_derivacion FROM derivaciones WHERE id_estudiante = ? ORDER BY fecha DESC, id_derivacion DESC LIMIT 1');
            $stmt->bind_param('i', $datos['id_estudiante']);
            $stmt->execute();
            $idDerivacion = (int)($stmt->get_result()->fetch_assoc()['id_derivacion'] ?? 0);
            $stmt->close();
        }
        // La fila única serializa la numeración incluso si todavía no hay informes.
        $secuencia = $conexion->query('SELECT ultimo FROM informes_secuencia WHERE id = 1 FOR UPDATE')->fetch_assoc();
        if (!$secuencia) throw new RuntimeException('Falta la migración de informes.');
        $numero = (int)$secuencia['ultimo'] + 1;
        $conexion->query('UPDATE informes_secuencia SET ultimo = ' . $numero . ' WHERE id = 1');
        $ficha = 'INF-' . str_pad((string)$numero, 4, '0', STR_PAD_LEFT);
        $tipoAtencion = implode(', ', $datos['tipo_atencion']);
        $stmt = $conexion->prepare("INSERT INTO informes (numero_ficha, fecha, id_estudiante, id_usuario, elaborado_por,
            titulo, tipo, numero_atenciones, referido_por, id_historia, id_derivacion, tipo_atencion,
            motivo, diagnostico, aspecto_cognitivo, aspectos_afectivos, diagnostico_acuerdos, recomendaciones, recibido_por, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'Individual', ?, NULLIF(?, ''), NULLIF(?, 0), NULLIF(?, 0), ?, ?,
            NULLIF(?, ''), ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");
        $stmt->bind_param('ssiiisisiisssssssss', $ficha, $datos['fecha'], $datos['id_estudiante'], $idUsuario,
            $idUsuario, $datos['titulo'], $datos['numero_atenciones'], $datos['referido_por'], $idHistoria,
            $idDerivacion, $tipoAtencion, $datos['motivo'], $datos['diagnostico'], $datos['aspecto_cognitivo'],
            $datos['aspectos_afectivos'], $datos['diagnostico_acuerdos'], $datos['recomendaciones'], $datos['recibido_por'], $datos['estado']);
        $stmt->execute();
        $idInforme = (int)$stmt->insert_id;
        $stmt->close();
        flujo_ejecutar($conexion,'UPDATE informes SET id_seguimiento=NULLIF(?,0) WHERE id_informe=?',[$idSeguimiento ?? 0,$idInforme]);
        flujo_auditar($conexion,$idUsuario,'informes','Crear',$idInforme,'Historia #' . $idHistoria . '; seguimiento #' . ($idSeguimiento ?? 0));
        $conexion->commit();
        return $ficha;
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}

function informe_actualizar(mysqli $conexion, int $idInforme, array $datos, ?int $editor = null): void
{
    informe_validar($datos);
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('SELECT * FROM informes WHERE id_informe = ? AND id_estudiante = ? FOR UPDATE');
        $stmt->bind_param('ii', $idInforme, $datos['id_estudiante']);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El informe no existe o no corresponde al estudiante.');
        if (($datos['id_seguimiento'] ?? null) !== null && (int)$datos['id_seguimiento'] !== (int)$existe['id_seguimiento']) throw new InvalidArgumentException('El seguimiento de origen no puede cambiarse.');
        if ($existe['id_seguimiento']) {
            $seguimiento = flujo_fila($conexion,'SELECT fecha FROM seguimientos WHERE id_seguimiento=?',[$existe['id_seguimiento']]);
            if ($seguimiento['fecha'] > $datos['fecha']) throw new InvalidArgumentException('El informe no puede ser anterior a su seguimiento.');
        }
        $tipos = implode(', ', $datos['tipo_atencion']);
        // Autor y relaciones de origen se conservan al editar.
        $stmt = $conexion->prepare("UPDATE informes SET titulo = ?, fecha = ?, numero_atenciones = ?,
            referido_por = NULLIF(?, ''), tipo_atencion = ?, motivo = ?, diagnostico = NULLIF(?, ''),
            aspecto_cognitivo = ?, aspectos_afectivos = ?, diagnostico_acuerdos = NULLIF(?, ''),
            recomendaciones = NULLIF(?, ''), recibido_por = NULLIF(?, ''), estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_informe = ? AND id_estudiante = ?");
        $stmt->bind_param('ssissssssssssii', $datos['titulo'], $datos['fecha'], $datos['numero_atenciones'],
            $datos['referido_por'], $tipos, $datos['motivo'], $datos['diagnostico'], $datos['aspecto_cognitivo'],
            $datos['aspectos_afectivos'], $datos['diagnostico_acuerdos'], $datos['recomendaciones'], $datos['recibido_por'],
            $datos['estado'], $idInforme, $datos['id_estudiante']);
        $stmt->execute();
        $stmt->close();
        if ($editor !== null) flujo_auditar($conexion,$editor,'informes','Actualizar',$idInforme,'Edición; se conservan autor y vínculos de origen');
        $conexion->commit();
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}
