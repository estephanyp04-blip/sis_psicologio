<?php
require_once __DIR__.'/../includes/autenticacion.php';requerir_acceso('informes/ver_mensual.php');
require_once __DIR__.'/../config/conexion.php';require_once __DIR__.'/datos.php';
$id=(int)filter_var($_GET['id']??0,FILTER_VALIDATE_INT);
$informe=flujo_fila($conexion,"SELECT i.*,r.total_sesiones,r.filtros_json,r.total_citas,r.citas_atendidas,r.total_derivaciones,r.total_estudiantes,r.total_historias FROM informes i JOIN informe_resumen r ON r.cod_informe=i.cod_informe WHERE i.cod_informe=? AND i.tipo='Mensual'",[$id]);
if(!$informe){http_response_code(404);exit('Informe mensual no encontrado.');}
$snapshot=json_decode($informe['filtros_json']??'{}',true);$f=$snapshot['filtros']??[];
$curso=empty($f['curso'])?'Todos':(flujo_fila($conexion,'SELECT nombre FROM cursos WHERE cod_curso=?',[$f['curso']])['nombre']??'Curso #'.$f['curso']);
include '../includes/header.php';include '../includes/sidebar.php';include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><div class="d-print-none mb-3"><a href="mensual.php" class="btn btn-light">Volver</a> <button class="btn btn-primary" onclick="window.print()">Imprimir</button></div>
<article class="card p-4"><h1><?= login_html($informe['titulo']) ?></h1><p>U.E. Cañada Pailita «B» · <?= login_html($informe['numero_ficha']) ?></p><p>Período: <?= login_html($informe['fecha_inicio'].' a '.$informe['fecha_fin']) ?>. Curso: <?= login_html($curso) ?>. Estado de historia: <?= login_html($f['estado']??'')?:'Todos' ?>.</p>
<p>Atención = un seguimiento registrado. Las citas son controles de agenda y se muestran por separado.</p>
<div class="table-responsive"><table class="table"><tbody><?php foreach(['total_sesiones'=>'Atenciones del período','total_estudiantes'=>'Estudiantes con atenciones','total_historias'=>'Historias incluidas','total_derivaciones'=>'Derivaciones del período','total_citas'=>'Citas del período','citas_atendidas'=>'Citas marcadas atendidas'] as $campo=>$nombre): ?><tr><th><?= $nombre ?></th><td><?= $informe[$campo]===null?'Sin dato histórico':(int)$informe[$campo] ?></td></tr><?php endforeach; ?></tbody></table></div>
<p>Generado: <?= login_html($informe['fecha_generacion']) ?>. <?= login_html($informe['descripcion']) ?></p>
<p class="small">Estos totales conservan la consulta al generarse. El modelo no reconstruye estados anteriores ni versiones clínicas. Un cambio posterior de registros puede modificar estadísticas actuales, pero no este informe guardado.</p>
<?php if(login_puede('estadisticas/index.php')): ?><a class="d-print-none" href="../estadisticas/index.php?<?= login_html(http_build_query($f)) ?>">Consultar estadísticas con los mismos filtros</a><?php endif; ?></article></div></main>
<?php include '../includes/footer.php'; ?>
