<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/procesar_editar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';
$id = (int)filter_var($_POST['id_cita'] ?? null, FILTER_VALIDATE_INT);
try {
    if ($id <= 0) throw new InvalidArgumentException('La cita no es válida.');
    cita_guardar($conexion, $_POST, (int)$_SESSION['id_usuario'], $id);
    unset($_SESSION['datos_cita']);
    header('Location: editar.php?id=' . $id . '&exito=1');
} catch (Throwable $error) {
    $_SESSION['datos_cita'] = ['id_cita' => $id] + array_filter(array_intersect_key($_POST, array_flip(['fecha','hora','estado','observaciones'])), 'is_scalar');
    $_SESSION['mensaje_cita'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar la cita.';
    header('Location: ' . ($id > 0 ? 'editar.php?id=' . $id : 'listar.php?error=2'));
}
exit;
