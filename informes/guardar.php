<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/guardar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$datos = informe_datos($_POST);
try {
    $ficha = informe_crear($conexion, $datos, (int)$_SESSION['id_usuario']);
    unset($_SESSION['datos_informe']);
    $_SESSION['mensaje'] = "Informe $ficha guardado correctamente.";
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: listar.php');
} catch (Throwable $error) {
    $_SESSION['datos_informe'] = $datos;
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo guardar el informe. Intente nuevamente.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
}
exit;
