<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estudiantes/cambiar_estado.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$id = filter_var($_POST['id_estudiante'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$accion = is_string($_POST['accion'] ?? null) ? trim($_POST['accion']) : '';
try {
    $cambio = estudiante_cambiar_estado($conexion, $id ?: 0, $accion);
    $_SESSION['mensaje'] = $cambio
        ? ($accion === 'retirar' ? 'El estudiante se retiró correctamente.' : 'El estudiante se reactivó correctamente.')
        : 'El estudiante ya tiene el estado solicitado.';
    $_SESSION['tipo_mensaje'] = $cambio ? 'success' : 'info';
} catch (Throwable $error) {
    error_log('Error al cambiar estado del estudiante: ' . $error->getMessage());
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException
        ? $error->getMessage() : 'No se pudo actualizar el estado del estudiante.';
    $_SESSION['tipo_mensaje'] = 'danger';
}
header('Location: listar.php');
exit;
