<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/registrar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
$idHistoria = (int)filter_var($_GET['id_historia'] ?? null, FILTER_VALIDATE_INT);
$h = flujo_fila($conexion, 'SELECT h.*,CONCAT(e.nombres,\' \',e.apellidos) estudiante FROM historias_clinicas h JOIN estudiantes e ON e.id_estudiante=h.id_estudiante WHERE h.id_historia=?', [$idHistoria]);
if (!$h) { http_response_code(404); exit('Historia no encontrada.'); }
$datos = ['id_cita' => (int)filter_var($_GET['id_cita'] ?? 0,FILTER_VALIDATE_INT), 'fecha' => date('Y-m-d'), 'descripcion' => '', 'tecnicas_aplicadas' => '', 'acuerdos' => '', 'recomendaciones' => '', 'proxima_sesion' => ''];
$stmt = $conexion->prepare("SELECT c.* FROM citas c WHERE c.id_estudiante=? AND c.estado <> 'Cancelada' AND NOT EXISTS (SELECT 1 FROM seguimientos s WHERE s.id_cita=c.id_cita) ORDER BY fecha DESC,hora DESC");
$stmt->bind_param('i',$h['id_estudiante']); $stmt->execute(); $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
foreach ($citas as $c) if ((int)$c['id_cita']===$datos['id_cita']) $datos['fecha']=$c['fecha'];
if ((int)($_SESSION['datos_seguimiento']['id_historia'] ?? 0)===$idHistoria) $datos=array_replace($datos,$_SESSION['datos_seguimiento']);
$mensaje = $_SESSION['mensaje_seguimiento'] ?? '';
unset($_SESSION['datos_seguimiento'],$_SESSION['mensaje_seguimiento']);
$tituloPagina='Registrar seguimiento';
include '../includes/header.php'; include '../includes/sidebar.php'; include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><h1>Registrar seguimiento</h1>
<p><?= login_html($h['estudiante']) ?> · Historia #<?= $idHistoria ?></p>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= login_html($mensaje) ?></div><?php endif; ?>
<div class="alert alert-info">Al guardar, la historia y su derivación pasarán a En seguimiento. La cita seleccionada se marcará Atendida. Una derivación cerrada debe reabrirse antes. La próxima sesión es una fecha orientativa; programe su cita por separado.</div>
<form id="form-seguimiento" action="guardar.php" method="POST" class="card p-4">
<?= login_campo_csrf() ?><input type="hidden" name="id_historia" value="<?= $idHistoria ?>">
<label for="id_cita" class="form-label">Cita</label><select id="id_cita" name="id_cita" class="form-select mb-3"><option value="0">Sin cita</option>
<?php foreach ($citas as $c): ?><option value="<?= (int)$c['id_cita'] ?>" data-fecha="<?= login_html($c['fecha']) ?>" <?= (int)$datos['id_cita']===(int)$c['id_cita'] ? 'selected' : '' ?>>#<?= (int)$c['id_cita'] ?> · <?= login_html($c['fecha'].' '.$c['hora'].' · '.$c['estado']) ?></option><?php endforeach; ?></select>
<label for="fecha" class="form-label">Fecha de atención</label><input id="fecha" type="date" name="fecha" class="form-control mb-3" value="<?= login_html($datos['fecha']) ?>" max="<?= date('Y-m-d') ?>" required>
<?php foreach (['descripcion'=>'Descripción de la atención','tecnicas_aplicadas'=>'Técnicas aplicadas','acuerdos'=>'Acuerdos','recomendaciones'=>'Recomendaciones'] as $campo=>$titulo): ?>
<label for="<?= $campo ?>" class="form-label"><?= $titulo ?></label><textarea id="<?= $campo ?>" name="<?= $campo ?>" class="form-control mb-3" rows="3" maxlength="5000" <?= $campo==='descripcion' ? 'required' : '' ?>><?= login_html($datos[$campo]) ?></textarea>
<?php endforeach; ?>
<label for="proxima_sesion" class="form-label">Próxima sesión</label><input id="proxima_sesion" type="date" name="proxima_sesion" class="form-control mb-3" value="<?= login_html($datos['proxima_sesion']) ?>">
<div><button class="btn btn-primary">Registrar atención</button> <a class="btn btn-light" href="../historias_clinicas/ver.php?id=<?= $idHistoria ?>">Volver a la historia</a></div>
</form></div></main>
<script>document.getElementById('id_cita').addEventListener('change',function(){if(this.selectedOptions[0].dataset.fecha) document.getElementById('fecha').value=this.selectedOptions[0].dataset.fecha;});</script>
<?php include '../includes/footer.php'; ?>
