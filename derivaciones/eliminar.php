<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/eliminar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/evidencias.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SESSION['cod_rol'] ?? 0) !== 3) {
    header('Location: ../index.php');
    exit;
}

$idDerivacion = (int) ($_POST['cod_derivacion'] ?? 0);
$idDocente = (int) ($_SESSION['cod_docente'] ?? 0);

if ($idDerivacion <= 0 || $idDocente <= 0) {
    header('Location: listar.php');
    exit;
}

$stmt = $conexion->prepare('SELECT e.ruta FROM derivacion_evidencias e
    INNER JOIN derivaciones d ON d.cod_derivacion=e.cod_derivacion
    WHERE d.cod_derivacion=? AND d.cod_docente=?');
$stmt->bind_param('ii', $idDerivacion, $idDocente);
$stmt->execute();
$rutasEvidencia = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ruta');
$stmt->close();

$stmt = $conexion->prepare("DELETE FROM derivaciones WHERE cod_derivacion = ? AND cod_docente = ? AND estado = 'Pendiente'
    AND NOT EXISTS (SELECT 1 FROM citas c WHERE c.cod_derivacion=derivaciones.cod_derivacion)
    AND NOT EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.cod_derivacion_origen=derivaciones.cod_derivacion)
    AND NOT EXISTS (SELECT 1 FROM informe_individual i WHERE i.cod_derivacion=derivaciones.cod_derivacion)");
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
