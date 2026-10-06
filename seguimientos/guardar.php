<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/guardar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
try {
    $id = seguimiento_guardar($conexion, $_POST, (int)$_SESSION['id_usuario']);
    unset($_SESSION['datos_seguimiento']);
    header('Location: ver.php?id=' . $id);
} catch (Throwable $error) {
    $_SESSION['datos_seguimiento'] = array_filter(array_intersect_key($_POST,array_flip(['id_historia','id_cita','fecha','descripcion','tecnicas_aplicadas','acuerdos','recomendaciones','proxima_sesion'])), 'is_scalar');
    $_SESSION['mensaje_seguimiento'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo guardar el seguimiento.';
    header('Location: registrar.php?id_historia=' . (int)filter_var($_POST['id_historia'] ?? 0,FILTER_VALIDATE_INT));
}
exit;
