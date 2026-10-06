<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/procesar_registrar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/citas_datos.php';
try {
    $id = cita_guardar($conexion, $_POST, (int)$_SESSION['id_usuario']);
    unset($_SESSION['datos_cita']);
    header('Location: editar.php?id=' . $id . '&exito=1');
} catch (Throwable $error) {
    $_SESSION['datos_cita'] = array_filter(array_intersect_key($_POST, array_flip(['id_estudiante','id_derivacion','fecha','hora','estado','observaciones'])), 'is_scalar');
    $_SESSION['mensaje_cita'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo registrar la cita.';
    header('Location: registrar.php');
}
exit;