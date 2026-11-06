<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
function docente_cambiar_estado(mysqli $bd, int $id, string $estado, int $autor): void
{
    flujo_exigir_permiso($bd, $autor, 'Docentes', 'puede_cambiar_estado');
    if ($id <= 0 || !in_array($estado, ['Activo','Inactivo'], true)) throw new InvalidArgumentException('Revise el docente y el estado.');
    $bd->begin_transaction();
    try {
        $d = flujo_fila($bd,'SELECT estado FROM docentes WHERE cod_docente=? FOR UPDATE',[$id]);
        if (!$d) throw new InvalidArgumentException('Docente no encontrado.');
        flujo_ejecutar($bd,'UPDATE docentes SET estado=? WHERE cod_docente=?',[$estado,$id]);
        flujo_auditar($bd,$autor,'docentes','Estado',$id,$d['estado'].' → '.$estado.'; la cuenta conserva su estado');
        $bd->commit();
    } catch (Throwable $e) { $bd->rollback(); throw $e; }
}
function docente_conservar_autoria(mysqli $bd, int $id): void
{
    flujo_ejecutar($bd, 'UPDATE derivaciones d JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=doc.cod_usuario SET d.cod_usuario_autor=u.cod_usuario,d.autor_nombres=u.nombres,d.autor_apellidos=u.apellidos,d.fecha_actualizacion=d.fecha_actualizacion WHERE d.cod_docente=? AND d.cod_usuario_autor IS NULL', [$id]);
}
