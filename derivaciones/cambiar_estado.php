<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/cambiar_estado.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
$id = (int)filter_var($_POST['cod_derivacion'] ?? null, FILTER_VALIDATE_INT);
try {
    derivacion_cambiar_estado($conexion, flujo_id($id), flujo_texto($_POST['estado'] ?? '', 20), (int)$_SESSION['cod_usuario']);
    $_SESSION['mensaje'] = 'Estado de la derivación actualizado.';
    $_SESSION['tipo_mensaje'] = 'success';
} catch (Throwable $error) {
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo cambiar el estado.';
    $_SESSION['tipo_mensaje'] = 'danger';
}
header('Location: ' . ($id > 0 ? 'ver.php?id=' . $id : 'listar.php'));
exit;
