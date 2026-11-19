<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estadisticas/individual.php');
requerir_acceso('historias_clinicas/ver.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/clinica_datos.php';
require_once __DIR__ . '/clinica_vista.php';
$tituloPagina = 'Seguimiento psicológico individual';
$error = ''; $estudiantes = []; $registros = []; $archivo = []; $comparacion = [];
$catalogos = ['cursos'=>[], 'paralelos'=>[], 'grupos'=>[]]; $f = clinica_filtros([]); $seleccionado = null; $a = ''; $b = '';
try {
    $f = clinica_filtros($_GET); $catalogos = clinica_catalogos($conexion);
    foreach (['curso'=>['cursos','cod_curso'],'paralelo'=>['paralelos','cod_paralelo']] as $campo=>[$tabla,$id]) {
        if ($f[$campo] && !in_array($f[$campo], array_map('intval',array_column($catalogos[$tabla],$id)), true)) throw new InvalidArgumentException('El curso o paralelo seleccionado no existe.');
    }
    seguridad_consulta($conexion, 'seguimiento_individual', $f['estudiante']);
    $estudiantes = clinica_estudiantes($conexion, $f);
    foreach ($estudiantes as $e) if ((int)$e['cod_estudiante'] === $f['estudiante']) $seleccionado = $e;
    if ($f['estudiante']) {
        if (!$seleccionado) { http_response_code(404); throw new OutOfBoundsException('No hay historias del estudiante para el curso y paralelo seleccionados.'); }
        $registros = clinica_registros(clinica_historias($conexion, $f, true), $f);
        if ($registros) {
            $a = $_GET['comparar_a'] ?? array_key_first($registros); $b = $_GET['comparar_b'] ?? array_key_last($registros);
            if (!is_string($a) || !is_string($b) || !isset($registros[$a], $registros[$b])) throw new InvalidArgumentException('Seleccione registros de este estudiante dentro del período.');
            $comparacion = clinica_comparar($registros[$a]['datos'], $registros[$b]['datos'], $catalogos['grupos']);
        } elseif (isset($_GET['comparar_a']) || isset($_GET['comparar_b'])) throw new InvalidArgumentException('No hay registros para comparar en este período.');
        $archivo = clinica_consultar($conexion, "SELECT s.cod_seguimiento,s.fecha FROM seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia
            WHERE h.cod_estudiante=? AND (?='' OR s.fecha>=?) AND s.fecha<=? ORDER BY s.fecha,s.cod_seguimiento", [$f['estudiante'],$f['desde'],$f['desde'],$f['hasta']]);
    }
} catch (InvalidArgumentException $e) { http_response_code(400); $error = $e->getMessage(); }
catch (OutOfBoundsException $e) { $error = $e->getMessage(); }
catch (Throwable $e) {
    http_response_code(503); error_log('Seguimiento individual: ' . get_class($e) . '; codigo=' . $e->getCode());
    $error = 'No se pudo consultar el seguimiento. Intente nuevamente.';
}
include __DIR__ . '/../includes/header.php'; include __DIR__ . '/../includes/sidebar.php'; include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content estadisticas-page"><div class="container-fluid">
    <header class="estadisticas-cabecera"><div><h1>Seguimiento psicológico individual</h1><p>Consulta y comparación de historias clínicas y sus versiones conservadas.</p></div></header>
    <?php clinica_navegacion(true); clinica_formulario($f, $catalogos, $estudiantes); ?>
    <?php if ($error): ?><p data-ui-aviso class="alert alert-danger" role="alert"><?= login_html($error) ?></p>
    <?php elseif (!$f['estudiante']): ?><p class="alert alert-info" role="status">Seleccione un estudiante para consultar su historial.</p>
    <?php else: ?>
    <h2 class="h4"><?= login_html(trim($seleccionado['apellidos'] . ' ' . $seleccionado['nombres'])) ?></h2>
    <p class="estadisticas-contexto">El período filtra la fecha de atención consignada en cada registro. Las versiones conservan estados anteriores de una historia; su fecha de edición no es una nueva atención. Los registros previos al inicio del versionado que no fueron conservados no pueden reconstruirse. La falta de una marca no confirma la ausencia de una condición.</p>
    <?php if (!$registros): ?><p class="alert alert-info" role="status">No hay registros clínicos en este período.</p><?php else: ?>
    <section class="estadisticas-panel" aria-labelledby="comparar-titulo"><h2 id="comparar-titulo">Comparar registros</h2>
        <form method="get" class="clinica-comparadores d-print-none">
            <?php foreach ($f as $campo=>$valor): ?><input type="hidden" name="<?= $campo ?>" value="<?= login_html((string)$valor) ?>"><?php endforeach; ?>
            <?php foreach (['comparar_a'=>['Primer registro',$a], 'comparar_b'=>['Segundo registro',$b]] as $campo=>[$titulo,$elegido]): ?>
            <div><label for="<?= $campo ?>"><?= $titulo ?></label><select class="form-select" id="<?= $campo ?>" name="<?= $campo ?>"><?php foreach ($registros as $clave=>$r): ?><option value="<?= login_html($clave) ?>" <?= $elegido === $clave ? 'selected' : '' ?>><?= login_html(clinica_rotulo_registro($r)) ?></option><?php endforeach; ?></select></div>
            <?php endforeach; ?><button class="btn btn-primary">Comparar</button>
        </form>
        <p><strong>Primer registro:</strong> <?= login_html(clinica_rotulo_registro($registros[$a])) ?><br><strong>Segundo registro:</strong> <?= login_html(clinica_rotulo_registro($registros[$b])) ?></p>
        <?php if ($a === $b): ?><p class="alert alert-info">Se está comparando el mismo registro. Seleccione otro para identificar diferencias.</p><?php endif; ?>
        <div class="estadisticas-tabla-scroll" tabindex="0" role="region" aria-label="Comparación clínica completa"><table class="table clinica-comparacion">
            <caption>Los cambios describen información registrada. No establecen aparición, desaparición, gravedad ni mejoría clínica.</caption>
            <thead><tr><th scope="col">Apartado</th><th scope="col">Primer registro</th><th scope="col">Segundo registro</th><th scope="col">Cambio observado en el registro</th></tr></thead><tbody>
            <?php foreach ($comparacion as $fila): ?><tr data-campo="<?= login_html($fila['campo']) ?>"><th scope="row"><?= login_html($fila['nombre']) ?></th><td><?= nl2br(login_html($fila['antes'] !== '' ? $fila['antes'] : 'Sin información registrada')) ?></td><td><?= nl2br(login_html($fila['despues'] !== '' ? $fila['despues'] : 'Sin información registrada')) ?></td><td><?= login_html($fila['estado']) ?><?php if (!empty($fila['nuevas'])): ?><p><strong>Nuevas marcas:</strong> <?= login_html(implode(', ', $fila['nuevas'])) ?></p><?php endif; ?><?php if (!empty($fila['sin_marca'])): ?><p><strong>Ya no marcadas:</strong> <?= login_html(implode(', ', $fila['sin_marca'])) ?>. No confirma que hayan desaparecido.</p><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
    </section>
    <section class="estadisticas-panel" aria-labelledby="cronologia-titulo"><h2 id="cronologia-titulo">Historial cronológico</h2>
        <p><?= count($registros) ?> registros disponibles, incluidas versiones. Las intervenciones realizadas solo pueden confirmarse mediante lo escrito por la profesional; un plan no acredita su ejecución.</p>
        <ol class="clinica-cronologia"><?php foreach ($registros as $r): $d = $r['datos']; ?><li>
            <details><summary><?= login_html(clinica_rotulo_registro($r)) ?></summary>
                <p>Registrado: <?= login_html($d['fecha_registro'] ?? 'Fecha no conservada') ?> · Última actualización de este estado: <?= login_html($d['fecha_actualizacion'] ?? 'Fecha no conservada') ?></p>
                <p><a href="<?= login_html(login_url('historias_clinicas/ver.php?id=' . (int)$d['cod_historia'])) ?>">Abrir historia actual #<?= (int)$d['cod_historia'] ?></a></p>
                <dl><?php foreach (['motivo_consulta'=>'Motivo de consulta', 'evaluacion_inicial'=>'Evaluación inicial', 'plan_intervencion'=>'Plan de intervención', 'observaciones'=>'Observaciones profesionales'] as $campo=>$titulo): ?><dt><?= $titulo ?></dt><dd><?= nl2br(login_html($d[$campo] ?? '')) ?: 'Sin información registrada' ?></dd><?php endforeach; ?></dl>
            </details>
        </li><?php endforeach; ?></ol>
    </section>
    <?php endif; ?>
    <?php if ($archivo): ?><section class="estadisticas-panel"><h2>Archivo de seguimientos anteriores</h2><p>Registros históricos de solo lectura. Se conservan sus técnicas, acuerdos y recomendaciones; no se suman como nuevas historias ni se transforman en versiones ficticias.</p><ul><?php foreach ($archivo as $s): ?><li><a href="<?= login_html(login_url('seguimientos/ver.php?id=' . (int)$s['cod_seguimiento'])) ?>"><?= login_html($s['fecha']) ?> · Registro histórico #<?= (int)$s['cod_seguimiento'] ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php endif; ?>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
