<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/eliminar.php');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$idDerivacion = (int)filter_var($_POST['cod_derivacion'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$idDocente = (int) ($_SESSION['cod_docente'] ?? 0);

if ($idDerivacion <= 0) {
    header('Location: listar.php');
    exit;
}

$eliminadas = 0;
try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare('SELECT d.cod_derivacion FROM derivaciones d WHERE d.cod_derivacion=?
        AND (? <= 0 OR (d.cod_docente=? AND (d.cod_usuario_autor IS NULL OR d.cod_usuario_autor=(SELECT cod_usuario FROM docentes WHERE cod_docente=d.cod_docente)))) FOR UPDATE');
    $stmt->bind_param('iii', $idDerivacion, $idDocente, $idDocente); $stmt->execute();
    $propia = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$propia) throw new InvalidArgumentException('Registro no disponible.');
    $stmt=$conexion->prepare('SELECT ruta FROM derivacion_evidencias WHERE cod_derivacion=?');
    $stmt->bind_param('i',$idDerivacion);$stmt->execute();
    $rutasEvidencia=array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'ruta');$stmt->close();
    $stmt = $conexion->prepare("DELETE FROM derivaciones WHERE cod_derivacion=? AND estado='Pendiente'
        AND NOT EXISTS (SELECT 1 FROM citas c WHERE c.cod_derivacion=derivaciones.cod_derivacion)
        AND NOT EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.cod_derivacion_origen=derivaciones.cod_derivacion)
        AND NOT EXISTS (SELECT 1 FROM seguimientos s WHERE s.cod_derivacion=derivaciones.cod_derivacion)
        AND NOT EXISTS (SELECT 1 FROM informe_individual i WHERE i.cod_derivacion=derivaciones.cod_derivacion)");
    $stmt->bind_param('i',$idDerivacion);$stmt->execute();$eliminadas=$stmt->affected_rows;$stmt->close();
    if ($eliminadas===1) seguridad_evento($conexion,(int)$_SESSION['cod_usuario'],'derivaciones','Eliminar',$idDerivacion);
    $conexion->commit();
    if ($eliminadas===1) foreach($rutasEvidencia as $ruta) derivacion_imagen_eliminar((string)$ruta);
} catch(Throwable $error) {
    $conexion->rollback();$eliminadas=0;
    error_log('Eliminar derivación: '.get_class($error).'; codigo='.$error->getCode());
}

$_SESSION['mensaje'] = $eliminadas === 1
    ? 'La derivación pendiente fue eliminada.'
    : 'La derivación no pudo eliminarse; puede tener registros vinculados, haber sido atendida o no pertenecerle.';
$_SESSION['tipo_mensaje'] = $eliminadas === 1 ? 'success' : 'warning';
header('Location: listar.php');
exit;
