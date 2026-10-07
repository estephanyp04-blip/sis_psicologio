<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('informes/actualizar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$idInforme = filter_var($_POST['id_informe'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
if ($idInforme <= 0) { header('Location: listar.php'); exit; }
$datos = informe_datos($_POST);
try {
    informe_actualizar($conexion, $idInforme, $datos, (int)$_SESSION['id_usuario']);
    unset($_SESSION['edicion_informe']);
    $_SESSION['mensaje'] = 'Informe actualizado correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: ver.php?id=' . $idInforme);
} catch (Throwable $error) {
    $_SESSION['edicion_informe'] = ['id' => $idInforme, 'datos' => $datos];
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar el informe. Intente nuevamente.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . $idInforme);
}
exit;
