<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/actualizar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$id = filter_var($_POST['id_historia'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: listar.php'); exit; }
try {
    historia_guardar($conexion, $_POST, (int)$_SESSION['id_usuario'], $id);
    unset($_SESSION['datos_historia']);
    $_SESSION['mensaje'] = 'La historia clínica se actualizó correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: ver.php?id=' . $id);
} catch (Throwable $error) {
    $_SESSION['datos_historia'] = ['id' => $id, 'datos' => historia_recuperar_entrada($_POST)];
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar la historia clínica.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: editar.php?id=' . $id);
}
exit;
