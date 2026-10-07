<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/trazabilidad_datos.php';

function cita_horas(): array
{
    $horas = [];
    for ($minuto = 7 * 60; $minuto <= 18 * 60; $minuto += 30) $horas[] = sprintf('%02d:%02d', intdiv($minuto,60), $minuto % 60);
    return $horas;
}

function cita_guardar(mysqli $bd, array $entrada, int $autor, ?int $id = null): int
{
    $bd->begin_transaction();
    try {
        $anterior = $id ? flujo_fila($bd, 'SELECT * FROM citas WHERE id_cita=? FOR UPDATE', [$id]) : null;
        if ($id && !$anterior) throw new InvalidArgumentException('La cita no existe.');
        $estudiante = flujo_id($entrada['id_estudiante'] ?? $anterior['id_estudiante'] ?? null);
        $derivacion = flujo_id($entrada['id_derivacion'] ?? $anterior['id_derivacion'] ?? 0, true);
        if ($anterior && ($estudiante !== (int)$anterior['id_estudiante'] || $derivacion !== (int)($anterior['id_derivacion'] ?? 0))) throw new InvalidArgumentException('El estudiante y la derivación de origen no pueden cambiarse.');
        $fecha = flujo_fecha($entrada['fecha'] ?? $anterior['fecha'] ?? '');
        $hora = flujo_texto($entrada['hora'] ?? substr($anterior['hora'] ?? '',0,5), 5);
        $estado = flujo_texto($entrada['estado'] ?? 'Pendiente', 20);
        $observaciones = flujo_texto($entrada['observaciones'] ?? $anterior['observaciones'] ?? '');
        if (!in_array($hora, cita_horas(), true) || !in_array($estado, ['Pendiente','Reprogramada','Atendida','Cancelada'], true)) throw new InvalidArgumentException('El horario o estado no es válido.');
        if (!$anterior && ($fecha < date('Y-m-d') || $estado !== 'Pendiente')) throw new InvalidArgumentException('La nueva cita debe estar pendiente y no tener fecha pasada.');
        if ($anterior && $fecha !== $anterior['fecha'] && $fecha < date('Y-m-d')) throw new InvalidArgumentException('No se puede reprogramar una cita hacia una fecha pasada.');
        if ($estado === 'Atendida' && $fecha > date('Y-m-d')) throw new InvalidArgumentException('No se puede atender una cita futura.');
        if ($anterior && ($fecha !== $anterior['fecha'] || $hora !== substr($anterior['hora'],0,5) || $estado === 'Cancelada')
            && (flujo_fila($bd,'SELECT id_historia FROM historias_clinicas WHERE id_cita_origen=? LIMIT 1',[$id])
                || flujo_fila($bd,'SELECT id_seguimiento FROM seguimientos WHERE id_cita=? LIMIT 1',[$id]))) {
            throw new InvalidArgumentException('La cita ya tiene atención clínica vinculada y conserva su horario.');
        }
        if ($anterior && in_array($anterior['estado'], ['Atendida','Cancelada'], true)
            && ($estado !== $anterior['estado'] || $fecha !== $anterior['fecha'] || $hora !== substr($anterior['hora'],0,5))) {
            throw new InvalidArgumentException('Una cita atendida o cancelada conserva su fecha, hora y estado. Registre una nueva cita cuando corresponda.');
        }
        if (!$anterior && !flujo_fila($bd, "SELECT id_estudiante FROM estudiantes WHERE id_estudiante=? AND estado='Activo'", [$estudiante])) throw new InvalidArgumentException('El estudiante no existe o no está activo.');
        if ($derivacion) {
            $d = flujo_fila($bd, 'SELECT * FROM derivaciones WHERE id_derivacion=? FOR UPDATE', [$derivacion]);
            if (!$d || (int)$d['id_estudiante'] !== $estudiante) throw new InvalidArgumentException('La derivación no corresponde al estudiante.');
            if (!$anterior && $d['estado'] === 'Atendido') throw new InvalidArgumentException('Reabra la derivación antes de programar otra cita.');
        }
        if ($anterior) {
            flujo_ejecutar($bd, 'UPDATE citas SET fecha=?,hora=?,estado=?,observaciones=? WHERE id_cita=?', [$fecha,$hora,$estado,$observaciones,$id]);
        } else {
            flujo_ejecutar($bd, 'INSERT INTO citas (id_estudiante,id_psicologa,id_derivacion,fecha,hora,estado,observaciones) VALUES (?,?,NULLIF(?,0),?,?,?,?)', [$estudiante,$autor,$derivacion,$fecha,$hora,$estado,$observaciones]);
            $id = (int)$bd->insert_id;
        }
        flujo_auditar($bd, $autor, 'citas', $anterior ? 'Actualizar' : 'Crear', $id, ($anterior['estado'] ?? 'Nueva') . ' → ' . $estado);
        $bd->commit(); return $id;
    } catch (Throwable $error) {
        $bd->rollback();
        if ($error instanceof mysqli_sql_exception && $error->getCode() === 1062) throw new InvalidArgumentException('Ya existe una cita en ese horario.');
        throw $error;
    }
}
