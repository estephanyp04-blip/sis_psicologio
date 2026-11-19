<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estadisticas/index.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/clinica_datos.php';
require_once __DIR__ . '/clinica_vista.php';
$tituloPagina = 'Estadísticas y análisis';
$ocultarNotificacionesClinicas = true;
$error = ''; $datos = null; $catalogos = ['cursos'=>[], 'paralelos'=>[], 'grupos'=>[]];
$f = clinica_filtros([]);
try {
    $f = clinica_filtros($_GET); $f['estudiante'] = 0;
    $catalogos = clinica_catalogos($conexion);
    seguridad_consulta($conexion, 'estadisticas_generales', 0);
    $datos = clinica_generales($conexion, $f);
} catch (InvalidArgumentException $e) { http_response_code(400); $error = $e->getMessage(); }
catch (Throwable $e) {
    http_response_code(503); error_log('Estadísticas clínicas: ' . get_class($e) . '; codigo=' . $e->getCode());
    $error = 'No se pudieron consultar las estadísticas. Intente nuevamente.';
}
include __DIR__ . '/../includes/header.php'; include __DIR__ . '/../includes/sidebar.php'; include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content estadisticas-page"><div class="container-fluid">
    <header class="estadisticas-cabecera"><div><h1>Estadísticas y análisis</h1><p>Información registrada en las historias clínicas psicológicas.</p></div><button class="btn btn-outline-primary d-print-none" type="button" onclick="window.print()">Imprimir / guardar PDF</button></header>
    <?php clinica_navegacion(false); ?><h2 class="h4">Estadísticas generales</h2>
    <?php clinica_formulario($f, $catalogos); ?>
    <?php if ($error): ?><p data-ui-aviso class="alert alert-danger" role="alert"><?= login_html($error) ?></p><?php endif; ?>
    <?php if ($datos !== null): ?>
    <p class="estadisticas-contexto">Cada historia cuenta una vez según su fecha de atención, con sus datos actuales. Las ediciones y los seguimientos antiguos no suman atenciones. Curso y paralelo corresponden a la inscripción actual, incluidos estudiantes retirados o sin inscripción. Esta consulta no reconstruye cómo estaba la base en una fecha pasada.</p>
    <section class="estadisticas-tarjetas" aria-label="Resumen de historias clínicas">
        <article class="estadisticas-tarjeta"><h3>Historias en el período</h3><strong id="total-historias" data-valor="<?= $datos['historias'] ?>"><?= $datos['historias'] ?></strong><p>Registros clínicos; las versiones no se suman.</p></article>
        <article class="estadisticas-tarjeta"><h3>Estudiantes con historia</h3><strong id="total-estudiantes" data-valor="<?= $datos['estudiantes'] ?>"><?= $datos['estudiantes'] ?></strong><p>Cada estudiante cuenta una sola vez.</p></article>
        <article class="estadisticas-tarjeta"><h3>Con marcas de riesgo registradas</h3><strong id="total-estudiantes-riesgo" data-valor="<?= $datos['estudiantes_riesgo'] ?>"><?= $datos['estudiantes'] ? number_format($datos['estudiantes_riesgo'] * 100 / $datos['estudiantes'], 1, ',', '.') . '%' : '—' ?></strong><p><?= $datos['estudiantes_riesgo'] ?> de <?= $datos['estudiantes'] ?> estudiantes, con al menos una marca en alguna historia del período. No es una prevalencia ni un diagnóstico.</p></article>
    </section>
    <?php if (!$datos['historias']): ?><p class="alert alert-info" role="status">No hay historias clínicas para los filtros seleccionados.</p><?php endif; ?>
    <div class="estadisticas-clinicas-grid">
        <section class="estadisticas-panel"><h2>Atenciones documentadas por mes</h2><p>Fecha de atención de la historia. Una versión de edición no representa una nueva sesión.</p><?php clinica_barras($datos['meses'], $datos['historias'], 'Historias por mes'); ?></section>
        <section class="estadisticas-panel"><h2>Distribución por curso y paralelo</h2><p>Historias del período agrupadas por inscripción actual.</p><?php clinica_barras($datos['cursos'], $datos['historias'], 'Historias por curso'); ?></section>
        <section class="estadisticas-panel"><h2>Motivos de derivación</h2><p>Categorías actuales de las derivaciones de origen; una historia puede tener varias.</p><?php clinica_barras($datos['motivos_derivacion'], $datos['historias'], 'Motivos de derivación'); ?></section>
        <?php foreach ($catalogos['grupos'] as $grupo): $valores = $datos['distribuciones'][$grupo['codigo']] ?? []; ?>
        <section class="estadisticas-panel"><h2><?= login_html($grupo['nombre']) ?></h2><p>Historias con cada selección / <?= $datos['historias'] ?> historias. Las selecciones múltiples pueden sumar más del 100%.</p><?php clinica_barras($valores, $datos['historias'], $grupo['nombre']); ?><p class="clinica-ayuda"><?= $datos['sin_seleccion'][$grupo['codigo']] ?? $datos['historias'] ?> historias sin selección en este apartado. No se confirma ausencia de condiciones.</p></section>
        <?php endforeach; ?>
        <?php foreach (['situacion_escolar'=>'Situación escolar', 'valoracion_familiar'=>'Valoración familiar'] as $campo=>$titulo): ?>
        <section class="estadisticas-panel"><h2><?= $titulo ?></h2><p>Valoraciones registradas, incluidos los datos no registrados.</p><?php clinica_barras($datos['valoraciones'][$campo], $datos['historias'], $titulo); ?></section>
        <?php endforeach; ?>
    </div>
    <p class="estadisticas-nota">Estos indicadores describen registros. La valoración de evolución, mejoría o diagnóstico corresponde a la profesional y debe constar en la historia. El seguimiento individual permite comparar los datos completos.</p>
    <?php endif; ?>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
