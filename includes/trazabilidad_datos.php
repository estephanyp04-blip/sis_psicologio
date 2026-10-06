<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

function flujo_fila(mysqli $bd, string $sql, array $valores = []): ?array
{
    $stmt = $bd->prepare($sql);
    if ($valores) $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila;
}

function flujo_ejecutar(mysqli $bd, string $sql, array $valores = []): void
{
    $stmt = $bd->prepare($sql);
    if ($valores) $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute(); $stmt->close();
}

function flujo_id($valor, bool $opcional = false): int
{
    if ($opcional && ($valor === '' || $valor === null)) return 0;
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $opcional ? 0 : 1, 'max_range' => 4294967295]]);
    if ($id === false) throw new InvalidArgumentException('El identificador no es válido.');
    return $id;
}

function flujo_texto($valor, int $maximo = 5000): string
{
    if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || mb_strlen($valor) > $maximo) {
        throw new InvalidArgumentException('Revise los textos y sus longitudes.');
    }
    return trim($valor);
}

function flujo_fecha($valor): string
{
    $valor = flujo_texto($valor, 10);
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) throw new InvalidArgumentException('La fecha no es válida.');
    return $valor;
}

function flujo_auditar(mysqli $bd, int $autor, string $modulo, string $accion, int $id, string $detalle): void
{
    flujo_ejecutar($bd, 'INSERT INTO auditoria (id_usuario,modulo,accion,registro_id,detalle) VALUES (?,?,?,?,?)', [$autor,$modulo,$accion,$id,$detalle]);
}

/** Los cambios de estado son explícitos; emitir un informe no cierra un caso. */
function derivacion_cambiar_estado(mysqli $bd, int $id, string $estado, int $autor): void
{
    $bd->begin_transaction();
    try {
        $d = flujo_fila($bd, 'SELECT * FROM derivaciones WHERE id_derivacion=? FOR UPDATE', [$id]);
        if (!$d) throw new InvalidArgumentException('La derivación no existe.');
        $transiciones = ['Pendiente' => ['En seguimiento'], 'En seguimiento' => ['Atendido'], 'Atendido' => ['En seguimiento']];
        if (!in_array($estado, $transiciones[$d['estado']] ?? [], true)) throw new InvalidArgumentException('La transición de estado no está permitida.');
        $historia = flujo_fila($bd, 'SELECT id_historia FROM historias_clinicas WHERE id_estudiante=?', [$d['id_estudiante']]);
        if (!$historia) throw new InvalidArgumentException('Registre primero la historia clínica del estudiante.');
        if ($estado === 'Atendido' && !flujo_fila($bd, 'SELECT s.id_seguimiento FROM seguimientos s JOIN historias_clinicas h ON h.id_historia=s.id_historia LEFT JOIN citas c ON c.id_cita=s.id_cita WHERE COALESCE(c.id_derivacion,h.id_derivacion_origen)=? LIMIT 1', [$id])) {
            throw new InvalidArgumentException('Registre un seguimiento de esta derivación antes de cerrar su atención.');
        }
        flujo_ejecutar($bd, 'UPDATE derivaciones SET estado=? WHERE id_derivacion=?', [$estado,$id]);
        flujo_auditar($bd, $autor, 'derivaciones', 'Estado', $id, $d['estado'] . ' → ' . $estado);
        $bd->commit();
    } catch (Throwable $error) { $bd->rollback(); throw $error; }
}

function seguimiento_guardar(mysqli $bd, array $entrada, int $autor): int
{
    $idHistoria = flujo_id($entrada['id_historia'] ?? null);
    $idCita = flujo_id($entrada['id_cita'] ?? 0, true);
    $fecha = flujo_fecha($entrada['fecha'] ?? '');
    if ($fecha > date('Y-m-d')) throw new InvalidArgumentException('El seguimiento no puede tener una fecha futura.');
    $datos = [];
    foreach (['descripcion','tecnicas_aplicadas','acuerdos','recomendaciones'] as $campo) $datos[$campo] = flujo_texto($entrada[$campo] ?? '');
    if ($datos['descripcion'] === '') throw new InvalidArgumentException('La descripción del seguimiento es obligatoria.');
    $proxima = flujo_texto($entrada['proxima_sesion'] ?? '', 10);
    if ($proxima !== '' && flujo_fecha($proxima) <= $fecha) throw new InvalidArgumentException('La próxima sesión debe ser posterior al seguimiento.');
    $bd->begin_transaction();
    try {
        $h = flujo_fila($bd, 'SELECT * FROM historias_clinicas WHERE id_historia=? FOR UPDATE', [$idHistoria]);
        if (!$h || $h['estado'] === 'Cerrada' || $fecha < $h['fecha_apertura']) throw new InvalidArgumentException('Revise la historia, su estado y la fecha de apertura.');
        $idDerivacion = (int)($h['id_derivacion_origen'] ?? 0);
        if ($idCita) {
            $c = flujo_fila($bd, 'SELECT * FROM citas WHERE id_cita=? FOR UPDATE', [$idCita]);
            if (!$c || (int)$c['id_estudiante'] !== (int)$h['id_estudiante'] || $c['estado'] === 'Cancelada' || $c['fecha'] !== $fecha) {
                throw new InvalidArgumentException('La cita debe pertenecer al estudiante, estar vigente y coincidir con la fecha del seguimiento.');
            }
            if (flujo_fila($bd, 'SELECT id_seguimiento FROM seguimientos WHERE id_cita=? LIMIT 1', [$idCita])) throw new InvalidArgumentException('La cita ya tiene un seguimiento registrado.');
            $idDerivacion = (int)($c['id_derivacion'] ?? $h['id_derivacion_origen'] ?? 0);
        }
        if ($idDerivacion) {
            $d = flujo_fila($bd, 'SELECT * FROM derivaciones WHERE id_derivacion=? FOR UPDATE', [$idDerivacion]);
            if (!$d || (int)$d['id_estudiante'] !== (int)$h['id_estudiante'] || $d['estado'] === 'Atendido') throw new InvalidArgumentException('Reabra la derivación antes de registrar otra atención.');
        }
        flujo_ejecutar($bd, 'INSERT INTO seguimientos (id_historia,id_psicologa,id_cita,fecha,descripcion,tecnicas_aplicadas,acuerdos,recomendaciones,proxima_sesion) VALUES (?,?,NULLIF(?,0),?,?,?,?,?,NULLIF(?,\'\'))',
            [$idHistoria,$autor,$idCita,$fecha,$datos['descripcion'],$datos['tecnicas_aplicadas'],$datos['acuerdos'],$datos['recomendaciones'],$proxima]);
        $id = (int)$bd->insert_id;
        if ($idCita) {
            flujo_ejecutar($bd, "UPDATE citas SET estado='Atendida' WHERE id_cita=?", [$idCita]);
            flujo_auditar($bd, $autor, 'citas', 'Atender', $idCita, 'Seguimiento #' . $id);
        }
        flujo_ejecutar($bd, "UPDATE historias_clinicas SET estado='En seguimiento' WHERE id_historia=?", [$idHistoria]);
        if ($idDerivacion && $d['estado'] !== 'En seguimiento') {
            flujo_ejecutar($bd, "UPDATE derivaciones SET estado='En seguimiento' WHERE id_derivacion=?", [$idDerivacion]);
            flujo_auditar($bd, $autor, 'derivaciones', 'Estado', $idDerivacion, 'Pendiente → En seguimiento; seguimiento #' . $id);
        }
        flujo_auditar($bd, $autor, 'historias_clinicas', 'Seguimiento', $idHistoria, 'Seguimiento #' . $id . '; estado En seguimiento');
        flujo_auditar($bd, $autor, 'seguimientos', 'Crear', $id, 'Historia #' . $idHistoria . '; cita #' . $idCita);
        $bd->commit();
        return $id;
    } catch (Throwable $error) { $bd->rollback(); throw $error; }
}
