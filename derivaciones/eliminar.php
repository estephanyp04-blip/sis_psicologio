<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SESSION['id_rol'] ?? 0) !== 3) {
    header('Location: ../index.php');
    exit;
}

$idDerivacion = (int) ($_POST['id_derivacion'] ?? 0);
$idDocente = (int) ($_SESSION['id_docente'] ?? 0);

if ($idDerivacion <= 0 || $idDocente <= 0) {
    header('Location: mis_derivaciones.php');
    exit;
}

$stmt = $conexion->prepare("DELETE FROM derivaciones WHERE id_derivacion = ? AND id_docente = ? AND estado = 'Pendiente'");
$stmt->bind_param('ii', $idDerivacion, $idDocente);
$stmt->execute();
$eliminadas = $stmt->affected_rows;
$stmt->close();

$_SESSION['mensaje'] = $eliminadas === 1
    ? 'La derivación pendiente fue eliminada.'
    : 'La derivación no pudo eliminarse; puede haber sido atendida o no pertenecerle.';
$_SESSION['tipo_mensaje'] = $eliminadas === 1 ? 'success' : 'warning';
header('Location: mis_derivaciones.php');
exit;
