<?php
require_once __DIR__.'/../includes/autenticacion.php';requerir_acceso('informes/mensual.php');
require_once __DIR__.'/../config/conexion.php';
$mes=$_SESSION['mensual_entrada']['mes']??date('Y-m');
$cursos=$conexion->query("SELECT cod_curso,nombre FROM cursos WHERE estado='Activo' ORDER BY orden")->fetch_all(MYSQLI_ASSOC);
$informes=$conexion->query("SELECT cod_informe,numero_ficha,titulo,fecha_inicio,fecha_fin FROM informes WHERE tipo='Mensual' ORDER BY cod_informe DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';include '../includes/sidebar.php';include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><h1>Informes mensuales</h1><a href="listar.php" class="btn btn-outline-secondary mb-3">Informes individuales</a>
<?php if(!empty($_SESSION['mensual_error'])): ?><p class="alert alert-danger"><?= login_html($_SESSION['mensual_error']) ?></p><?php unset($_SESSION['mensual_error']);endif; ?>
<?php if(login_puede('informes/guardar_mensual.php')): ?><form id="form-mensual" method="post" action="guardar_mensual.php" class="card p-3 mb-4">
<?= login_campo_csrf() ?><div class="row g-3"><div class="col-md-4"><label for="mes" class="form-label">Mes</label><input type="month" class="form-control" name="mes" id="mes" value="<?= login_html($mes) ?>" max="<?= date('Y-m') ?>" required></div>
<div class="col-md-4"><label for="curso" class="form-label">Curso</label><select name="curso" id="curso" class="form-select"><option value="0">Todos los cursos</option><?php foreach($cursos as $c): ?><option value="<?= (int)$c['cod_curso'] ?>"><?= login_html($c['nombre']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label for="estado_historia" class="form-label">Estado de historia</label><select name="estado_historia" id="estado_historia" class="form-select"><option value="">Todos</option><?php foreach(['Activa','En seguimiento','Cerrada'] as $e): ?><option><?= login_html($e) ?></option><?php endforeach; ?></select></div></div>
<p class="mt-3">Cada seguimiento cuenta como una atención. El mes actual se corta en el día de hoy. Se conservarán los totales y filtros al generar; estados y curso corresponden al momento de generación.</p><div><button class="btn btn-primary">Generar informe mensual</button></div></form><?php endif; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Ficha</th><th>Informe</th><th>Período</th><th>Consulta</th></tr></thead><tbody><?php foreach($informes as $i): ?><tr><td><?= login_html($i['numero_ficha']) ?></td><td><?= login_html($i['titulo']) ?></td><td><?= login_html($i['fecha_inicio'].' a '.$i['fecha_fin']) ?></td><td><a href="ver_mensual.php?id=<?= (int)$i['cod_informe'] ?>">Ver e imprimir</a></td></tr><?php endforeach; ?><?php if(!$informes): ?><tr><td colspan="4">No hay informes mensuales registrados.</td></tr><?php endif; ?></tbody></table></div>
</div></main><?php include '../includes/footer.php'; ?>
