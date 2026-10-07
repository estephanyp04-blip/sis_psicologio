<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/guardar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

try {
    $id = historia_guardar($conexion, $_POST, (int)$_SESSION['id_usuario']);
    unset($_SESSION['datos_historia']);
    $_SESSION['mensaje'] = 'La historia clínica se creó correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: ver.php?id=' . $id);
} catch (Throwable $error) {
    $_SESSION['datos_historia'] = ['id' => 0, 'datos' => historia_recuperar_entrada($_POST)];
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage()
        : ($error instanceof mysqli_sql_exception && $error->getCode() === 1062
            ? 'El estudiante ya tiene una historia clínica.' : 'No se pudo guardar la historia clínica.');
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
}
exit;
