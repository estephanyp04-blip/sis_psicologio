<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/politica_acceso.php';

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

/** Historias vinculadas a esta cita o a su derivación; nunca otra historia del estudiante por azar. */
function flujo_historias_cita(mysqli $bd, array $cita): array
{
    $stmt = $bd->prepare('SELECT h.cod_historia,h.fecha_apertura,h.estado,h.cod_derivacion_origen
        FROM historias_clinicas h WHERE h.cod_estudiante=? AND (
            h.cod_cita_origen=? OR h.cod_derivacion_origen=NULLIF(?,0)
            OR EXISTS (SELECT 1 FROM historia_anexos s WHERE s.cod_historia=h.cod_historia AND s.cod_cita=?)
        ) ORDER BY h.fecha_apertura DESC,h.cod_historia DESC');
    $estudiante = (int)$cita['cod_estudiante'];
    $id = (int)$cita['cod_cita'];
    $derivacion = (int)($cita['cod_derivacion'] ?? 0);
    $stmt->bind_param('iiii', $estudiante, $id, $derivacion, $id);
    $stmt->execute();
    $historias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $historias;
}

function flujo_id(mixed $valor, bool $opcional = false): int
{
    if ($opcional && ($valor === '' || $valor === null)) return 0;
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $opcional ? 0 : 1, 'max_range' => 4294967295]]);
    if ($id === false) throw new InvalidArgumentException('El identificador no es válido.');
    return $id;
}

function flujo_texto(mixed $valor, int $maximo = 5000): string
{
    if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || mb_strlen($valor) > $maximo) {
        throw new InvalidArgumentException('Revise los textos y sus longitudes.');
    }
    return trim($valor);
}

function flujo_fecha(mixed $valor): string
{
    $valor = flujo_texto($valor, 10);
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) throw new InvalidArgumentException('La fecha no es válida.');
    return $valor;
}

function flujo_filtro_fechas(array $entrada): array
{
    $desde=($entrada['desde']??'')===''?'':flujo_fecha($entrada['desde']);
    $hasta=($entrada['hasta']??'')===''?'':flujo_fecha($entrada['hasta']);
    if($desde!=='' && $hasta!=='' && $desde>$hasta)throw new InvalidArgumentException('La fecha inicial debe ser anterior o igual a la final.');
    return [$desde,$hasta];
}

function flujo_auditar(mysqli $bd, int $autor, string $modulo, string $accion, int $id, string $detalle): void
{
    flujo_ejecutar($bd, 'INSERT INTO auditoria (cod_usuario,modulo,accion,registro_id,detalle) VALUES (?,?,?,?,?)', [$autor,$modulo,$accion,$id,$detalle]);
}

function flujo_exigir_permiso(mysqli $bd, int $autor, string $modulo, string $accion): void
{
    if (!in_array($accion, ['puede_ver','puede_crear','puede_editar','puede_cambiar_estado'], true)) throw new LogicException('Acción desconocida.');
    $cuenta = flujo_fila($bd, "SELECT u.cod_rol FROM usuarios u JOIN roles r ON r.cod_rol=u.cod_rol WHERE u.cod_usuario=? AND u.estado='Activo' AND r.estado='Activo'", [$autor]);
    if (!$cuenta) throw new InvalidArgumentException('La cuenta no está habilitada.');
    if (!politica_modulo_permitido($modulo, (int)$cuenta['cod_rol'])) throw new InvalidArgumentException('Su función no autoriza el acceso a este módulo.');
    if ((int)$cuenta['cod_rol'] === 1) return;
    if (!flujo_fila($bd, "SELECT p.cod_permiso FROM permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo WHERE p.cod_rol=? AND m.nombre=? AND m.estado='Activo' AND p.$accion=1", [$cuenta['cod_rol'],$modulo])) throw new InvalidArgumentException('No tiene permiso para ' . ($accion === 'puede_cambiar_estado' ? 'cambiar el estado o reprogramar' : 'realizar esta operación') . '.');
}

/** Los cambios de estado son explícitos; emitir un informe no cierra un caso. */
function derivacion_cambiar_estado(mysqli $bd, int $id, string $estado, int $autor): void
{
    $bd->begin_transaction();
    try {
        $d = flujo_fila($bd, 'SELECT * FROM derivaciones WHERE cod_derivacion=? FOR UPDATE', [$id]);
        if (!$d) throw new InvalidArgumentException('La derivación no existe.');
        $transiciones = ['Pendiente' => ['En seguimiento'], 'En seguimiento' => ['Atendido'], 'Atendido' => ['En seguimiento']];
        if (!in_array($estado, $transiciones[$d['estado']] ?? [], true)) throw new InvalidArgumentException('La transición de estado no está permitida.');
        $historia = flujo_fila($bd, 'SELECT h.cod_historia FROM historias_clinicas h WHERE h.cod_estudiante=?
            AND (h.cod_derivacion_origen=? OR EXISTS (SELECT 1 FROM historia_anexos s
                LEFT JOIN citas c ON c.cod_cita=s.cod_cita WHERE s.cod_historia=h.cod_historia
                AND COALESCE(s.cod_derivacion,c.cod_derivacion)=?)) LIMIT 1', [$d['cod_estudiante'],$id,$id]);
        if (!$historia) throw new InvalidArgumentException('Registre primero una historia clínica vinculada a esta derivación.');
        // La historia vinculada documenta la atención. El archivo de anexos
        // solo resuelve relaciones históricas; no es requisito para cerrar casos nuevos.
        flujo_ejecutar($bd, 'UPDATE derivaciones SET estado=? WHERE cod_derivacion=?', [$estado,$id]);
        flujo_auditar($bd, $autor, 'derivaciones', 'Estado', $id, $d['estado'] . ' → ' . $estado);
        $bd->commit();
    } catch (Throwable $error) { $bd->rollback(); throw $error; }
}
