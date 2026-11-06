<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/registrar.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
require_once __DIR__ . '/../citas/datos.php';
$idHistoria = (int)filter_var($_GET['cod_historia'] ?? null, FILTER_VALIDATE_INT);
$h = flujo_fila($conexion, 'SELECT h.*,CONCAT(e.nombres,\' \',e.apellidos) estudiante FROM historias_clinicas h JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante WHERE h.cod_historia=?', [$idHistoria]);
if (!$h) { http_response_code(404); exit('Historia no encontrada.'); }
$datos = ['cod_cita' => (int)filter_var($_GET['cod_cita'] ?? 0,FILTER_VALIDATE_INT), 'fecha' => date('Y-m-d'), 'descripcion' => '', 'tecnicas_aplicadas' => '', 'acuerdos' => '', 'recomendaciones' => '', 'proxima_sesion' => ''];
$stmt = $conexion->prepare("SELECT c.* FROM citas c WHERE c.cod_estudiante=? AND c.estado <> 'Cancelada' AND NOT EXISTS (SELECT 1 FROM seguimientos s WHERE s.cod_cita=c.cod_cita) ORDER BY fecha DESC,hora DESC");
$stmt->bind_param('i',$h['cod_estudiante']); $stmt->execute(); $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$datos['cod_derivacion'] = (int)($h['cod_derivacion_origen'] ?? 0);
foreach ($citas as $c) if ((int)$c['cod_cita']===$datos['cod_cita']) {
    $datos['fecha']=$c['fecha'];
    $datos['cod_derivacion']=(int)($c['cod_derivacion'] ?? $h['cod_derivacion_origen'] ?? 0);
}
if ((int)($_SESSION['datos_seguimiento']['cod_historia'] ?? 0)===$idHistoria) $datos=array_replace($datos,$_SESSION['datos_seguimiento']);
$mensaje = $_SESSION['mensaje_seguimiento'] ?? '';
unset($_SESSION['datos_seguimiento'],$_SESSION['mensaje_seguimiento']);
$episodios=$conexion->prepare("SELECT cod_derivacion,fecha,estado FROM derivaciones WHERE cod_estudiante=? AND estado<>'Atendido' ORDER BY fecha DESC,cod_derivacion DESC");
$episodios->bind_param('i',$h['cod_estudiante']);$episodios->execute();$derivaciones=$episodios->get_result()->fetch_all(MYSQLI_ASSOC);$episodios->close();
$profesionales=$conexion->query("SELECT cod_usuario,nombres,apellidos FROM usuarios WHERE cod_rol=2 AND estado='Activo' ORDER BY apellidos,nombres")->fetch_all(MYSQLI_ASSOC);
$tituloPagina='Registrar seguimiento';
include '../includes/header.php'; include '../includes/sidebar.php'; include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><h1>Registrar seguimiento</h1>
<p><?= login_html($h['estudiante']) ?> · Historia #<?= $idHistoria ?></p>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= login_html($mensaje) ?></div><?php endif; ?>
<div class="alert alert-info">Al guardar, la historia y la derivación seleccionada pasarán a En seguimiento. La cita seleccionada se marcará Atendida. Una fecha orientativa no reserva horario: marque «Confirmar próxima cita» para reservarla.</div>
<?php if ($h['estado']==='Cerrada'): ?><div class="alert alert-warning">La historia está cerrada. Reábrala desde su edición antes de registrar otra atención.</div><?php endif; ?>
<form id="form-seguimiento" action="guardar.php" method="POST" class="card p-4">
<?= login_campo_csrf() ?><input type="hidden" name="cod_historia" value="<?= $idHistoria ?>">
<label for="cod_cita" class="form-label">Cita</label><select id="cod_cita" name="cod_cita" class="form-select mb-3"><option value="0">Sin cita</option>
<?php foreach ($citas as $c): ?><option value="<?= (int)$c['cod_cita'] ?>" data-derivacion="<?= (int)$c['cod_derivacion'] ?>" data-fecha="<?= login_html($c['fecha']) ?>" <?= (int)$datos['cod_cita']===(int)$c['cod_cita'] ? 'selected' : '' ?>>#<?= (int)$c['cod_cita'] ?> · <?= login_html($c['fecha'].' '.$c['hora'].' · '.$c['estado']) ?></option><?php endforeach; ?></select>
<label for="cod_derivacion" class="form-label">Derivación de esta atención</label><select id="cod_derivacion" name="cod_derivacion" class="form-select mb-3"><option value="0"><?= $derivaciones ? 'Seleccione la derivación' : 'Atención sin derivación' ?></option>
<?php foreach($derivaciones as $d): ?><option value="<?= (int)$d['cod_derivacion'] ?>" <?= (int)($datos['cod_derivacion']??(count($derivaciones)===1?$d['cod_derivacion']:0))===(int)$d['cod_derivacion']?'selected':'' ?>>#<?= (int)$d['cod_derivacion'] ?> · <?= login_html($d['fecha'].' · '.$d['estado']) ?></option><?php endforeach; ?></select>
<label for="fecha" class="form-label">Fecha de atención</label><input id="fecha" type="date" name="fecha" class="form-control mb-3" value="<?= login_html($datos['fecha']) ?>" max="<?= date('Y-m-d') ?>" required>
<?php foreach (['descripcion'=>'Descripción de la atención','tecnicas_aplicadas'=>'Técnicas aplicadas','acuerdos'=>'Acuerdos','recomendaciones'=>'Recomendaciones'] as $campo=>$titulo): ?>
<label for="<?= $campo ?>" class="form-label"><?= $titulo ?></label><textarea id="<?= $campo ?>" name="<?= $campo ?>" class="form-control mb-3" rows="3" maxlength="5000" <?= $campo==='descripcion' ? 'required' : '' ?>><?= login_html($datos[$campo]) ?></textarea>
<?php endforeach; ?>
<label for="proxima_sesion" class="form-label">Próxima sesión: fecha orientativa</label><input id="proxima_sesion" type="date" name="proxima_sesion" class="form-control mb-3" value="<?= login_html($datos['proxima_sesion']) ?>">
<?php if(login_puede('citas/registrar.php')): ?><fieldset class="border rounded p-3 mb-3"><legend class="h6">Reserva de próxima cita</legend>
<label class="form-check"><input class="form-check-input" type="checkbox" name="reservar_proxima" value="1" <?= !empty($datos['reservar_proxima'])?'checked':'' ?>> Confirmar próxima cita al guardar la atención</label>
<div class="row g-3 mt-1"><div class="col-md-6"><label for="proxima_hora" class="form-label">Hora</label><select id="proxima_hora" name="proxima_hora" class="form-select"><option value="">Seleccione</option><?php foreach(cita_horas() as $hora): ?><option <?= ($datos['proxima_hora']??'')===$hora?'selected':'' ?>><?= $hora ?></option><?php endforeach; ?></select></div>
<?php if((int)$_SESSION['cod_rol']===1): ?><div class="col-md-6"><label for="proxima_psicologa" class="form-label">Psicóloga responsable</label><select id="proxima_psicologa" name="proxima_psicologa" class="form-select"><option value="">Seleccione</option><?php foreach($profesionales as $p): ?><option value="<?= (int)$p['cod_usuario'] ?>" <?= (int)($datos['proxima_psicologa']??0)===(int)$p['cod_usuario']?'selected':'' ?>><?= login_html($p['nombres'].' '.$p['apellidos']) ?></option><?php endforeach; ?></select></div><?php endif; ?></div><p class="small text-muted mt-2 mb-0">La reserva se confirma junto con el seguimiento. Si hay conflicto de horario, no se guardará ninguno de los dos.</p></fieldset><?php endif; ?>
<div><button class="btn btn-primary">Registrar atención</button> <a class="btn btn-light" href="../historias_clinicas/ver.php?id=<?= $idHistoria ?>">Volver a la historia</a></div>
</form></div></main>
<script>const citaSeguimiento=document.getElementById('cod_cita');function elegirCita(){const o=citaSeguimiento.selectedOptions[0];if(o.dataset.fecha)document.getElementById('fecha').value=o.dataset.fecha;if(Number(o.dataset.derivacion))document.getElementById('cod_derivacion').value=o.dataset.derivacion;}citaSeguimiento.addEventListener('change',elegirCita);if(citaSeguimiento.value!=='0')elegirCita();</script>
<?php include '../includes/footer.php'; ?>
