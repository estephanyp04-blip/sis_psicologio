<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/procesar_registrar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';
try {
    $id = cita_guardar($conexion, $_POST, (int)$_SESSION['cod_usuario']);
    unset($_SESSION['datos_cita']);
    header('Location: editar.php?id=' . $id . '&exito=1');
} catch (Throwable $error) {
    $_SESSION['datos_cita'] = array_filter(array_intersect_key($_POST, array_flip(['cod_estudiante','cod_derivacion','cod_psicologa','fecha','hora','estado','observaciones'])), 'is_scalar');
    $_SESSION['mensaje_cita'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo registrar la cita.';
    $idDerivacion = filter_var($_POST['cod_derivacion'] ?? 0, FILTER_VALIDATE_INT);
    header('Location: registrar.php' . ($idDerivacion ? '?cod_derivacion=' . (int)$idDerivacion : ''));
}
exit;
