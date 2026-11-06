<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/trazabilidad_datos.php';

const INFORME_ESTADOS = ['Borrador', 'Finalizado'];
const INFORME_ATENCIONES = ['Evaluación', 'Consejería', 'Orientación', 'Terapia', 'Acompañamiento pedagógico'];

function informe_datos(array $entrada): array
{
    $datos = [];
    foreach (['titulo', 'fecha', 'motivo', 'diagnostico', 'aspecto_cognitivo', 'aspectos_afectivos',
        'diagnostico_acuerdos', 'recomendaciones', 'recibido_por', 'referido_por', 'estado'] as $campo) {
        $datos[$campo] = is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
    }
    foreach (['cod_estudiante', 'cod_historia', 'numero_atenciones'] as $campo) {
        $valor = filter_var($entrada[$campo] ?? 0, FILTER_VALIDATE_INT);
        $datos[$campo] = $valor === false ? -1 : $valor;
    }
    $tipos = $entrada['tipo_atencion'] ?? [];
    $datos['tipo_atencion'] = is_array($tipos) ? array_values(array_unique(array_filter($tipos, 'is_string'))) : [];
    // NULL significa que un cliente anterior no envió el vínculo; 0 lo deja sin seguimiento.
    $datos['cod_seguimiento'] = array_key_exists('cod_seguimiento',$entrada)
        ? (filter_var($entrada['cod_seguimiento'] === '' ? 0 : $entrada['cod_seguimiento'], FILTER_VALIDATE_INT) === false ? -1 : (int)$entrada['cod_seguimiento']) : null;
    return $datos;
}

function informe_validar(array $datos): void
{
    if (($datos['cod_seguimiento'] ?? 0) < 0) throw new InvalidArgumentException('El seguimiento no es válido.');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha']);
    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha'] || $datos['fecha'] > date('Y-m-d')) {
        throw new InvalidArgumentException('La fecha del informe no es válida.');
    }
    if ($datos['cod_estudiante'] <= 0 || $datos['cod_historia'] < 0
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

/** Historias disponibles para el estudiante en la fecha del informe. */
function informe_historias(mysqli $bd, int $estudiante, string $fecha): array
{
    $stmt = $bd->prepare('SELECT * FROM historias_clinicas WHERE cod_estudiante=? AND fecha_apertura<=?
        ORDER BY fecha_apertura DESC,cod_historia DESC');
    $stmt->bind_param('is', $estudiante, $fecha);
    $stmt->execute();
    $historias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $historias;
}

function informe_elegir_historia(mysqli $bd, array $historias, int $idHistoria, int $seguimiento, string $fecha): ?array
{
    if ($seguimiento) {
        $origen = flujo_fila($bd, 'SELECT cod_historia,fecha FROM seguimientos WHERE cod_seguimiento=?', [$seguimiento]);
        if (!$origen || $origen['fecha'] > $fecha || ($idHistoria && $idHistoria !== (int)$origen['cod_historia'])) {
            throw new InvalidArgumentException('El seguimiento no corresponde a la historia o a la fecha del informe.');
        }
        $idHistoria = (int)$origen['cod_historia'];
    }
    if ($idHistoria) {
        foreach ($historias as $historia) if ((int)$historia['cod_historia'] === $idHistoria) return $historia;
        throw new InvalidArgumentException('La historia clínica no corresponde al estudiante o es posterior al informe.');
    }
    return count($historias) === 1 ? $historias[0] : null;
}

function informe_crear(mysqli $conexion, array $datos, int $idUsuario): string
{
    informe_validar($datos);
    $datos['numero_atenciones'] = informe_total_atenciones($conexion,$datos['cod_estudiante'],$datos['fecha']);
    if ($idUsuario <= 0) throw new InvalidArgumentException('No se identificó al autor del informe.');
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("SELECT cod_estudiante FROM estudiantes WHERE cod_estudiante = ? AND estado = 'Activo'");
        $stmt->bind_param('i', $datos['cod_estudiante']);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El estudiante no existe o no está activo.');

        $historias = informe_historias($conexion, $datos['cod_estudiante'], $datos['fecha']);
        $historia = informe_elegir_historia($conexion, $historias, $datos['cod_historia'], (int)($datos['cod_seguimiento'] ?? 0), $datos['fecha']);
        if (!$historia && count($historias) > 1) {
            throw new InvalidArgumentException('El estudiante tiene varias historias clínicas. Seleccione la historia de este informe.');
        }
        $idHistoria = (int)($historia['cod_historia'] ?? 0);
        $idDerivacion = (int)($historia['cod_derivacion_origen'] ?? 0);
        $idSeguimiento = $datos['cod_seguimiento'] ?? null;
        if ($idSeguimiento === null && $idHistoria) {
            $ultimo = flujo_fila($conexion,'SELECT cod_seguimiento FROM seguimientos WHERE cod_historia=? AND fecha<=? ORDER BY fecha DESC,cod_seguimiento DESC LIMIT 1',[$idHistoria,$datos['fecha']]);
            $idSeguimiento = (int)($ultimo['cod_seguimiento'] ?? 0);
        }
        if ($idSeguimiento) {
            $origen = flujo_fila($conexion,'SELECT s.*,COALESCE(s.cod_derivacion,c.cod_derivacion) cod_derivacion FROM seguimientos s LEFT JOIN citas c ON c.cod_cita=s.cod_cita WHERE s.cod_seguimiento=?',[$idSeguimiento]);
            if (!$origen || (int)$origen['cod_historia'] !== $idHistoria || $origen['fecha'] > $datos['fecha']) throw new InvalidArgumentException('El seguimiento no corresponde a la historia o es posterior al informe.');
            $idDerivacion = (int)($origen['cod_derivacion'] ?? $idDerivacion);
        }
        if (!$historia) {
            $stmt = $conexion->prepare('SELECT cod_derivacion FROM derivaciones WHERE cod_estudiante = ? AND fecha<=? ORDER BY fecha DESC, cod_derivacion DESC LIMIT 1');
            $stmt->bind_param('is', $datos['cod_estudiante'],$datos['fecha']);
            $stmt->execute();
            $idDerivacion = (int)($stmt->get_result()->fetch_assoc()['cod_derivacion'] ?? 0);
            $stmt->close();
        }
        // La fila única serializa la numeración incluso si todavía no hay informes.
        $secuencia = $conexion->query('SELECT ultimo FROM informes_secuencia WHERE cod_secuencia = 1 FOR UPDATE')->fetch_assoc();
        if (!$secuencia) throw new RuntimeException('Falta la migración de informes.');
        $numero = (int)$secuencia['ultimo'] + 1;
        $conexion->query('UPDATE informes_secuencia SET ultimo = ' . $numero . ' WHERE cod_secuencia = 1');
        $ficha = 'INF-' . str_pad((string)$numero, 4, '0', STR_PAD_LEFT);
        $tipoAtencion = implode(', ', $datos['tipo_atencion']);
        $descripcion = $datos['motivo'];
        $conclusiones = $datos['diagnostico'];
        $stmt = $conexion->prepare("INSERT INTO informes
            (numero_ficha,cod_elaborado_por,tipo,titulo,fecha_inicio,fecha_fin,descripcion,conclusiones,recomendaciones,estado)
            VALUES (?,?,'Individual',?,?,?,?,?,?,?)");
        $stmt->bind_param('sisssssss', $ficha, $idUsuario, $datos['titulo'], $datos['fecha'], $datos['fecha'],
            $descripcion, $conclusiones, $datos['recomendaciones'], $datos['estado']);
        $stmt->execute();
        $idInforme = (int)$stmt->insert_id;
        $stmt->close();

        $idHistoriaDb = $idHistoria ?: null;
        $idDerivacionDb = $idDerivacion ?: null;
        $idSeguimientoDb = $idSeguimiento ?: null;
        $stmt = $conexion->prepare('INSERT INTO informe_individual
            (cod_informe,cod_estudiante,cod_historia,cod_derivacion,cod_seguimiento,numero_atenciones,referido_por,
             tipo_atencion,motivo,diagnostico,aspecto_cognitivo,aspectos_afectivos,diagnostico_acuerdos,recibido_por)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('iiiiiissssssss', $idInforme, $datos['cod_estudiante'], $idHistoriaDb, $idDerivacionDb,
            $idSeguimientoDb, $datos['numero_atenciones'], $datos['referido_por'], $tipoAtencion, $datos['motivo'],
            $datos['diagnostico'], $datos['aspecto_cognitivo'], $datos['aspectos_afectivos'],
            $datos['diagnostico_acuerdos'], $datos['recibido_por']);
        $stmt->execute();
        $stmt->close();
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
    $datos['numero_atenciones'] = informe_total_atenciones($conexion,$datos['cod_estudiante'],$datos['fecha']);
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare('SELECT i.*,d.cod_estudiante,d.cod_historia,d.cod_derivacion,d.cod_seguimiento
            FROM informes i INNER JOIN informe_individual d ON d.cod_informe=i.cod_informe
            WHERE i.cod_informe = ? AND d.cod_estudiante = ? FOR UPDATE');
        $stmt->bind_param('ii', $idInforme, $datos['cod_estudiante']);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) throw new InvalidArgumentException('El informe no existe o no corresponde al estudiante.');
        if (($datos['cod_seguimiento'] ?? null) !== null && (int)$datos['cod_seguimiento'] !== (int)$existe['cod_seguimiento']) throw new InvalidArgumentException('El seguimiento de origen no puede cambiarse.');
        if ($existe['cod_seguimiento']) {
            $seguimiento = flujo_fila($conexion,'SELECT fecha FROM seguimientos WHERE cod_seguimiento=?',[$existe['cod_seguimiento']]);
            if ($seguimiento['fecha'] > $datos['fecha']) throw new InvalidArgumentException('El informe no puede ser anterior a su seguimiento.');
        }
        $tipos = implode(', ', $datos['tipo_atencion']);
        $descripcion = $datos['motivo'];
        $conclusiones = $datos['diagnostico'];
        // Autor y relaciones de origen se conservan al editar.
        $stmt = $conexion->prepare("UPDATE informes SET titulo=?,fecha_inicio=?,fecha_fin=?,descripcion=?,
            conclusiones=?,recomendaciones=?,estado=? WHERE cod_informe=?");
        $stmt->bind_param('sssssssi', $datos['titulo'], $datos['fecha'], $datos['fecha'], $descripcion,
            $conclusiones, $datos['recomendaciones'], $datos['estado'], $idInforme);
        $stmt->execute();
        $stmt->close();
        $stmt = $conexion->prepare("UPDATE informe_individual SET numero_atenciones=?,referido_por=NULLIF(?,''),
            tipo_atencion=?,motivo=?,diagnostico=NULLIF(?,''),
            aspecto_cognitivo=?,aspectos_afectivos=?,diagnostico_acuerdos=NULLIF(?,''),
            recibido_por=NULLIF(?,'')
            WHERE cod_informe=? AND cod_estudiante=?");
        $stmt->bind_param('issssssssii', $datos['numero_atenciones'], $datos['referido_por'], $tipos,
            $datos['motivo'], $datos['diagnostico'], $datos['aspecto_cognitivo'], $datos['aspectos_afectivos'],
            $datos['diagnostico_acuerdos'], $datos['recibido_por'], $idInforme, $datos['cod_estudiante']);
        $stmt->execute();
        $stmt->close();
        if ($editor !== null) flujo_auditar($conexion,$editor,'informes','Actualizar',$idInforme,'Edición; se conservan autor y vínculos de origen');
        $conexion->commit();
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}

/** Una atención es un seguimiento registrado, con o sin cita; corte inclusivo. */
function informe_total_atenciones(mysqli $bd, int $estudiante, string $hasta): int
{
    return (int)flujo_fila($bd,'SELECT COUNT(*) total FROM seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia WHERE h.cod_estudiante=? AND s.fecha<=?',[$estudiante,$hasta])['total'];
}

function informe_antecedentes(mysqli $bd, int $estudiante, string $fecha, int $seguimiento = 0, int $historia = 0): array
{
    $fecha=flujo_fecha($fecha);
    if ($fecha>date('Y-m-d')) throw new InvalidArgumentException('La fecha del informe no puede ser futura.');
    $historias = informe_historias($bd, $estudiante, $fecha);
    $h = informe_elegir_historia($bd, $historias, $historia, $seguimiento, $fecha);
    $s=$h ? flujo_fila($bd,'SELECT s.*,COALESCE(s.cod_derivacion,c.cod_derivacion) episodio FROM seguimientos s LEFT JOIN citas c ON c.cod_cita=s.cod_cita WHERE s.cod_historia=? AND s.fecha<=? ORDER BY (s.cod_seguimiento=?) DESC,s.fecha DESC,s.cod_seguimiento DESC LIMIT 1',[$h['cod_historia'],$fecha,$seguimiento]) : null;
    $d=flujo_fila($bd,"SELECT d.*,TRIM(CONCAT(COALESCE(d.autor_nombres,u.nombres,''),' ',COALESCE(d.autor_apellidos,u.apellidos,''))) docente_referente
        FROM derivaciones d LEFT JOIN docentes doc ON doc.cod_docente=d.cod_docente
        LEFT JOIN usuarios u ON u.cod_usuario=COALESCE(d.cod_usuario_autor,doc.cod_usuario)
        WHERE d.cod_estudiante=? AND d.fecha<=? AND d.cod_derivacion=?",[$estudiante,$fecha,$s['episodio']??$h['cod_derivacion_origen']??0]);
    $historiaAlCorte = $h && substr($h['fecha_actualizacion']??'',0,10)<=$fecha;
    $derivacionAlCorte = $d && substr($d['fecha_actualizacion']??'',0,10)<=$fecha;
    return ['cod_historia'=>(int)($h['cod_historia']??0),'cod_seguimiento'=>(int)($s['cod_seguimiento']??0),'cod_derivacion'=>(int)($d['cod_derivacion']??0),
        'requiere_historia'=>!$h && count($historias)>1,
        'docente_referente'=>$d['docente_referente']??'', 'referido_por'=>$d?'Profesor':'Voluntario',
        'historias'=>array_map(static fn(array $fila): array => [
            'cod_historia'=>(int)$fila['cod_historia'], 'fecha_apertura'=>$fila['fecha_apertura'],
            'estado'=>$fila['estado'], 'cod_derivacion'=>(int)($fila['cod_derivacion_origen'] ?? 0),
        ], $historias),
        'numero_atenciones'=>informe_total_atenciones($bd,$estudiante,$fecha), 'motivo'=>$derivacionAlCorte?$d['motivo']:($historiaAlCorte?$h['motivo_consulta']:''),
        'diagnostico'=>$historiaAlCorte?($h['impresion_diagnostica']??''):'','recomendaciones'=>$s['recomendaciones']??'',
        'limitacion'=>'La fecha de atención determina el corte de sesiones, incluso si se registraron después. Historias y derivaciones no tienen versiones: se omiten sus textos si su última modificación es posterior al corte. Los estados y datos escolares son actuales.'];
}

function informe_mensual_crear(mysqli $bd, array $entrada, int $autor): int
{
    require_once __DIR__.'/../estadisticas/datos.php';
    flujo_exigir_permiso($bd,$autor,'Informes','puede_crear');
    $mes=flujo_texto($entrada['mes']??'',7);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$mes) || $mes>date('Y-m')) throw new InvalidArgumentException('Seleccione un mes válido, sin superar el actual.');
    $inicio=$mes.'-01';$fin=min((new DateTimeImmutable($inicio))->format('Y-m-t'),date('Y-m-d'));
    $f=estadisticas_filtros(['desde'=>$inicio,'hasta'=>$fin,'curso'=>$entrada['curso']??0,'estado'=>$entrada['estado_historia']??'']);
    $datos=estadisticas_datos($bd,$f);
    $snapshot=['filtros'=>$f,'resumen'=>$datos['resumen'],'atencion'=>$datos['atencion'],'meses'=>$datos['meses'],'estados'=>$datos['estados']];
    $bd->begin_transaction();
    try {
        $secuencia=flujo_fila($bd,'SELECT ultimo FROM informes_secuencia WHERE cod_secuencia=1 FOR UPDATE');
        if (!$secuencia) throw new RuntimeException('Falta la secuencia de informes.');
        $numero=(int)$secuencia['ultimo']+1;
        flujo_ejecutar($bd,'UPDATE informes_secuencia SET ultimo=? WHERE cod_secuencia=1',[$numero]);
        flujo_ejecutar($bd,"INSERT INTO informes(numero_ficha,cod_elaborado_por,tipo,titulo,fecha_inicio,fecha_fin,descripcion,estado) VALUES (?,?,'Mensual',?,?,?,?,'Finalizado')",['INF-'.str_pad((string)$numero,4,'0',STR_PAD_LEFT),$autor,'Informe mensual '.$mes,$inicio,$fin,'Atención = seguimiento registrado. Estados y curso actuales al generar; totales conservados como instantánea.']);
        $id=(int)$bd->insert_id;
        $dc=$datos['atencion']['derivaciones'];$cc=$datos['atencion']['citas'];
        flujo_ejecutar($bd,'INSERT INTO informe_resumen(cod_informe,total_estudiantes,total_derivaciones,derivaciones_pendientes,derivaciones_atendidas,total_citas,citas_atendidas,total_historias,total_sesiones,filtros_json) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$datos['resumen']['con_sesiones'],array_sum($dc),$dc['Pendiente'],$dc['Atendido'],array_sum($cc),$cc['Atendida'],$datos['resumen']['historias'],$datos['resumen']['sesiones'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        flujo_auditar($bd,$autor,'informes','Crear mensual',$id,$inicio.' a '.$fin);
        $bd->commit();return $id;
    } catch(Throwable $e){$bd->rollback();throw $e;}
}
