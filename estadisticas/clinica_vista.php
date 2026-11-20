<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

function clinica_navegacion(bool $individual): void
{
    echo '<nav class="clinica-navegacion d-print-none" aria-label="Estadísticas y análisis">';
    foreach (['estadisticas/index.php'=>'Estadísticas generales', 'estadisticas/individual.php'=>'Seguimiento psicológico individual'] as $ruta=>$titulo) {
        if (!login_puede($ruta)) continue;
        $activo = $individual === ($ruta === 'estadisticas/individual.php');
        echo '<a class="btn ' . ($activo ? 'btn-primary' : 'btn-outline-primary') . '" href="' . login_html(login_url($ruta)) . '"' . ($activo ? ' aria-current="page"' : '') . '>' . $titulo . '</a>';
    }
    echo '</nav>';
}

function clinica_formulario(array $f, array $catalogos, ?array $estudiantes = null): void
{
    $individual = $estudiantes !== null;
    $url = login_url('estadisticas/' . ($individual ? 'individual' : 'index') . '.php');
    echo '<form method="get" class="clinica-filtros d-print-none" id="filtros-clinica" action="' . login_html($url) . '">';
    if ($individual) {
        echo '<div class="clinica-selector"><label for="estudiante">Estudiante</label><select class="form-select" name="estudiante" id="estudiante"><option value="0">Seleccione un estudiante</option>';
        foreach ($estudiantes as $e) echo '<option value="' . (int)$e['cod_estudiante'] . '"' . ((int)$e['cod_estudiante'] === $f['estudiante'] ? ' selected' : '') . '>' . login_html(trim($e['apellidos'] . ' ' . $e['nombres']) . ' · ' . trim(($e['curso'] ?? 'Sin curso') . ' ' . ($e['paralelo'] ?? ''))) . '</option>';
        echo '</select></div>';
    }
    foreach (['desde'=>'Atenciones desde', 'hasta'=>'Atenciones hasta'] as $campo=>$titulo) echo '<div><label for="' . $campo . '">' . $titulo . '</label><input class="form-control" type="date" id="' . $campo . '" name="' . $campo . '" min="1900-01-01" max="' . date('Y-m-d') . '" value="' . login_html($f[$campo]) . '"></div>';
    echo '<div><label for="gestion">Gestión de atención</label><select class="form-select" name="gestion" id="gestion"><option value="0">Todas</option>';
    foreach ($catalogos['gestiones'] ?? [] as $fila) {
        $anio = (int)$fila['gestion'];
        if ($anio < 1900 || $anio > (int)date('Y')) continue;
        echo '<option value="'.$anio.'"'.($f['gestion'] === $anio ? ' selected' : '').'>'.$anio.'</option>';
    }
    echo '</select></div>';
    foreach (['curso'=>['Curso de referencia','cursos','cod_curso'], 'paralelo'=>['Paralelo de referencia','paralelos','cod_paralelo']] as $campo=>[$titulo,$tabla,$id]) {
        echo '<div><label for="' . $campo . '">' . $titulo . '</label><select class="form-select" name="' . $campo . '" id="' . $campo . '"><option value="0">Todos</option>';
        foreach ($catalogos[$tabla] as $fila) echo '<option value="' . (int)$fila[$id] . '"' . ($f[$campo] === (int)$fila[$id] ? ' selected' : '') . '>' . login_html($fila['nombre']) . '</option>';
        echo '</select></div>';
    }
    echo '<div><label for="estado">Estado de la historia</label><select class="form-select" name="estado" id="estado"><option value="">Todos</option>';
    foreach (['Activa', 'En seguimiento', 'Cerrada'] as $estado) echo '<option' . ($estado === $f['estado'] ? ' selected' : '') . '>' . $estado . '</option>';
    echo '</select></div><div class="clinica-acciones"><button class="btn btn-primary">Aplicar filtros</button><a href="' . login_html($url) . '">Restablecer</a></div></form>';
}

function clinica_barras(array $valores, int $denominador, string $etiqueta): void
{
    if (!$valores) { echo '<p class="estadisticas-vacio">Sin registros para estos filtros.</p>'; return; }
    $maximo = max(1, ...array_values($valores));
    echo '<ul class="estadisticas-distribucion" aria-label="' . login_html($etiqueta) . '">';
    foreach ($valores as $nombre=>$cantidad) {
        $porcentaje = $denominador ? number_format($cantidad * 100 / $denominador, 1, ',', '.') . '%' : '—';
        echo '<li><div class="estadisticas-distribucion-rotulo"><span>' . login_html((string)$nombre) . '</span><strong>' . (int)$cantidad . ' · ' . $porcentaje . '</strong></div><span class="estadisticas-distribucion-pista" aria-hidden="true"><span style="width:' . round($cantidad * 100 / $maximo, 2) . '%"></span></span></li>';
    }
    echo '</ul>';
}

function clinica_rotulo_registro(array $r): string
{
    return $r['datos']['fecha_apertura'] . ' · Historia #' . (int)$r['datos']['cod_historia'] . ' · ' . $r['tipo']
        . ($r['conservada_el'] ? ' antes de edición ' . $r['conservada_el'] . ' (' . $r['clave'] . ')' : '');
}
