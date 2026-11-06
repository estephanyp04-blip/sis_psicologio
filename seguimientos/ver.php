<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/ver.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/trazabilidad_vista.php';
$id = (int)filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT);
$s = flujo_fila($conexion, 'SELECT s.*,h.cod_estudiante,COALESCE(s.cod_derivacion,c.cod_derivacion,h.cod_derivacion_origen) cod_derivacion,
    CONCAT(u.nombres,\' \',u.apellidos) profesional,c.fecha AS fecha_cita,c.hora AS hora_cita
    FROM seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia
    JOIN usuarios u ON u.cod_usuario=s.cod_psicologa

    LEFT JOIN citas c ON c.cod_cita=s.cod_cita WHERE s.cod_seguimiento=?', [$id]);
if (!$s) { http_response_code(404); exit('Seguimiento no encontrado.'); }
$tituloPagina='Seguimiento #' . $id;
include '../includes/header.php'; include '../includes/sidebar.php'; include '../includes/navbar.php';
?>
<main class="main-content"><div class="container-fluid"><h1><?= login_html($tituloPagina) ?></h1>
<p><?= login_html($s['fecha'] . ' · ' . $s['profesional']) ?></p><div class="card p-4">
<p><a href="../historias_clinicas/ver.php?id=<?= (int)$s['cod_historia'] ?>">Historia #<?= (int)$s['cod_historia'] ?></a> · Registrado: <?= login_html($s['fecha_registro']) ?></p>
<p><?php if ($s['cod_cita']): ?><a href="../citas/editar.php?id=<?= (int)$s['cod_cita'] ?>">Cita #<?= (int)$s['cod_cita'] ?></a><?= $s['fecha_cita'] ? ' · ' . login_html($s['fecha_cita'] . ' ' . substr($s['hora_cita'], 0, 5)) : '' ?><?php else: ?>Sin cita asociada<?php endif; ?></p>
<?php foreach (['descripcion'=>'Descripción','tecnicas_aplicadas'=>'Técnicas aplicadas','acuerdos'=>'Acuerdos','recomendaciones'=>'Recomendaciones','proxima_sesion'=>'Próxima sesión'] as $campo=>$titulo): ?>
<h2 class="h5"><?= $titulo ?></h2><p><?= nl2br(login_html($s[$campo] ?: 'No registrado')) ?></p>
<?php endforeach; ?></div>
<?php if($s['cod_proxima_cita']): ?><p class="alert alert-success mt-3">Próxima cita confirmada: <a href="../citas/editar.php?id=<?= (int)$s['cod_proxima_cita'] ?>">Cita #<?= (int)$s['cod_proxima_cita'] ?></a>. Consulte su estado actual antes de la atención.</p><?php elseif($s['proxima_sesion']): ?><p class="alert alert-info mt-3">La próxima fecha es orientativa; no tiene una cita reservada desde este seguimiento.</p><?php endif; ?>
<?php flujo_panel($conexion,'seguimiento',$s); ?>
<a class="btn btn-primary mt-3" href="../informes/registrar.php?cod_estudiante=<?= (int)$s['cod_estudiante'] ?>&amp;cod_seguimiento=<?= $id ?>">Crear informe desde este seguimiento</a>
</div></main>
<?php include '../includes/footer.php'; ?>
