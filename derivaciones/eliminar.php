<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/eliminar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/evidencias.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SESSION['id_rol'] ?? 0) !== 3) {
    header('Location: ../index.php');
    exit;
}

$idDerivacion = (int) ($_POST['id_derivacion'] ?? 0);
$idDocente = (int) ($_SESSION['id_docente'] ?? 0);

if ($idDerivacion <= 0 || $idDocente <= 0) {
    header('Location: listar.php');
    exit;
}

$stmt = $conexion->prepare('SELECT e.ruta FROM derivacion_evidencias e
    INNER JOIN derivaciones d ON d.id_derivacion=e.id_derivacion
    WHERE d.id_derivacion=? AND d.id_docente=?');
$stmt->bind_param('ii', $idDerivacion, $idDocente);
$stmt->execute();
$rutasEvidencia = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ruta');
$stmt->close();

$stmt = $conexion->prepare("DELETE FROM derivaciones WHERE id_derivacion = ? AND id_docente = ? AND estado = 'Pendiente'
    AND NOT EXISTS (SELECT 1 FROM citas c WHERE c.id_derivacion=derivaciones.id_derivacion)
    AND NOT EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.id_derivacion_origen=derivaciones.id_derivacion)
    AND NOT EXISTS (SELECT 1 FROM informe_individual i WHERE i.id_derivacion=derivaciones.id_derivacion)");
$stmt->bind_param('ii', $idDerivacion, $idDocente);
$stmt->execute();
$eliminadas = $stmt->affected_rows;
$stmt->close();

if ($eliminadas === 1) {
    foreach ($rutasEvidencia as $ruta) derivacion_imagen_eliminar((string)$ruta);
}

$_SESSION['mensaje'] = $eliminadas === 1
    ? 'La derivación pendiente fue eliminada.'
    : 'La derivación no pudo eliminarse; puede tener registros vinculados, haber sido atendida o no pertenecerle.';
$_SESSION['tipo_mensaje'] = $eliminadas === 1 ? 'success' : 'warning';
header('Location: listar.php');
exit;
