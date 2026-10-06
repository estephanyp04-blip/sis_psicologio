<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/ver.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_vista.php';
$id = (int)filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT);
$s = flujo_fila($conexion, 'SELECT s.*,h.id_estudiante,COALESCE(c.id_derivacion,h.id_derivacion) id_derivacion,CONCAT(u.nombre,\' \',u.apellido) profesional FROM seguimientos s JOIN historias_clinicas h ON h.id_historia=s.id_historia JOIN usuarios u ON u.id_usuario=s.id_usuario LEFT JOIN citas c ON c.id_cita=s.id_cita WHERE s.id_seguimiento=?', [$id]);
if (!$s) { http_response_code(404); exit('Seguimiento no encontrado.'); }
$tituloPagina='Seguimiento #' . $id;
include '../includes/header.php'; include '../includes/sidebar.php'; include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><h1><?= login_html($tituloPagina) ?></h1>
<p><?= login_html($s['fecha'] . ' · ' . $s['profesional']) ?></p><div class="card p-4">
<?php foreach (['descripcion'=>'Descripción','tecnicas_aplicadas'=>'Técnicas aplicadas','acuerdos'=>'Acuerdos','recomendaciones'=>'Recomendaciones','proxima_sesion'=>'Próxima sesión'] as $campo=>$titulo): ?>
<h2 class="h5"><?= $titulo ?></h2><p><?= nl2br(login_html($s[$campo] ?: 'No registrado')) ?></p>
<?php endforeach; ?></div>
<?php flujo_panel($conexion,'seguimiento',$s); ?>
<a class="btn btn-primary mt-3" href="../informes/registrar.php?id_estudiante=<?= (int)$s['id_estudiante'] ?>&amp;id_seguimiento=<?= $id ?>">Crear informe desde este seguimiento</a>
</div></main>
<?php include '../includes/footer.php'; ?>
