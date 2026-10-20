<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estadisticas/index.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$tituloPagina = 'Estadísticas clínicas';
$datos = null;
$error = '';
$cursos = [];
$filtros = estadisticas_filtros([]);
$pagina = 1;
$paginaEstudiantes = 1;
$porPagina = 20;
try {
    $cursos = $conexion->query('SELECT cod_curso,nombre FROM cursos ORDER BY orden,nombre')->fetch_all(MYSQLI_ASSOC);
    $filtros = estadisticas_filtros($_GET);
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($pagina === false) throw new InvalidArgumentException('La página solicitada no es válida.');
    $paginaEstudiantes = filter_var($_GET['pagina_estudiante'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($paginaEstudiantes === false) throw new InvalidArgumentException('La página de estudiantes solicitada no es válida.');
    $datos = estadisticas_datos($conexion, $filtros);
    $paginas = max(1, (int)ceil(count($datos['historias']) / $porPagina));
    $pagina = min($pagina, $paginas);
    $filas = array_slice($datos['historias'], ($pagina - 1) * $porPagina, $porPagina);
    $paginasEstudiantes = max(1, (int)ceil(count($datos['estudiantes']) / $porPagina));
    $paginaEstudiantes = min($paginaEstudiantes, $paginasEstudiantes);
    $filasEstudiantes = array_slice($datos['estudiantes'], ($paginaEstudiantes - 1) * $porPagina, $porPagina);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $error = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(503);
    error_log('Estadísticas: ' . $e->getMessage());
    $error = 'No se pudieron consultar las estadísticas. Intente nuevamente.';
}
$fechaVisible = static fn(?string $fecha): string => $fecha ? date('d/m/Y', strtotime($fecha)) : 'Sin registro';
$porcentaje = static fn(int $cantidad, int $total): float => $total > 0 ? round($cantidad * 100 / $total, 1) : 0.0;
$renderDistribucion = static function (array $items, int $totalHistorias): void {
    $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['historias'] ?? 0) > 0));
    if (!$items) {
        echo '<div class="estadisticas-vacio">No hay selecciones registradas en esta sección.</div>';
        return;
    }
    $maximo = max(1, ...array_map(static fn(array $item): int => (int)$item['historias'], $items));
    echo '<ul class="estadisticas-distribucion">';
    foreach ($items as $item) {
        $nombre = login_html((string)$item['nombre']);
        $cantidad = (int)$item['historias'];
        $proporcion = $totalHistorias > 0 ? round($cantidad * 100 / $totalHistorias, 1) : 0;
        $ancho = round($cantidad * 100 / $maximo, 2);
        echo '<li><div class="estadisticas-distribucion-rotulo"><span>' . $nombre . '</span>'
            . '<strong>' . number_format($cantidad, 0, ',', '.') . ' · ' . number_format($proporcion, 1, ',', '.') . '%</strong></div>'
            . '<span class="estadisticas-pista" aria-hidden="true"><span style="width:' . $ancho . '%"></span></span></li>';
    }
    echo '</ul>';
};
$mesesNombres = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
$clasesEstado = ['Activa' => 'activa', 'En seguimiento' => 'seguimiento', 'Cerrada' => 'cerrada'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content estadisticas-page">
    <div class="container-fluid">
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Estadísticas</li>
        </ol></nav>
        <header class="estadisticas-cabecera">
            <div><h1>Estadísticas clínicas</h1><p>Indicadores resumidos de las secciones estructuradas de las historias clínicas.</p></div>
            <div class="estadisticas-cabecera-acciones">
                <a class="btn btn-outline-primary" href="<?= login_html(login_url('historias_clinicas/listar.php')) ?>">Ver historias clínicas</a>
                <button class="btn btn-primary d-print-none" type="button" onclick="window.print()"><i class="bi bi-download me-1"></i>Descargar PDF</button>
            </div>
        </header>

        <form id="filtros-estadisticas" class="estadisticas-filtros d-print-none" method="get" action="<?= login_html(login_url('estadisticas/index.php')) ?>">
            <div><label for="desde">Sesiones desde</label><input class="form-control" id="desde" name="desde" type="date" max="<?= date('Y-m-d') ?>" value="<?= login_html($filtros['desde']) ?>" required></div>
            <div><label for="hasta">Fecha de corte</label><input class="form-control" id="hasta" name="hasta" type="date" max="<?= date('Y-m-d') ?>" value="<?= login_html($filtros['hasta']) ?>" required></div>
            <div><label for="curso">Curso actual</label><select class="form-select" id="curso" name="curso">
                <option value="0">Todos los cursos</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?= (int)$curso['cod_curso'] ?>" <?= $filtros['curso'] === (int)$curso['cod_curso'] ? 'selected' : '' ?>><?= login_html($curso['nombre']) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div><label for="estado">Estado actual de la historia</label><select class="form-select" id="estado" name="estado">
                <option value="">Todos los estados</option>
                <?php foreach (array_keys($clasesEstado) as $estado): ?>
                    <option value="<?= login_html($estado) ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>><?= login_html($estado) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="estadisticas-acciones"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a id="restablecer-estadisticas" href="<?= login_html(login_url('estadisticas/index.php')) ?>">Restablecer</a></div>
        </form>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= login_html($error) ?></div>
        <?php endif; ?>
        <?php if ($datos !== null): ?>
            <p class="estadisticas-contexto">Las secciones clínicas cuentan historias abiertas hasta el <?= $fechaVisible($filtros['hasta']) ?>. Curso y estado usan sus valores actuales; el rango <?= $fechaVisible($filtros['desde']) ?>–<?= $fechaVisible($filtros['hasta']) ?> se aplica a las sesiones. Las opciones de conducta permiten varias selecciones por historia.</p>

            <section class="estadisticas-tarjetas estadisticas-clinicas-kpi" aria-label="Resumen clínico">
                <article class="estadisticas-tarjeta">
                    <h2>Historias clínicas registradas</h2>
                    <strong id="total-historias" data-valor="<?= $datos['resumen']['historias'] ?>"><?= number_format($datos['resumen']['historias'], 0, ',', '.') ?></strong>
                    <p>En la población seleccionada.</p>
                </article>
                <article class="estadisticas-tarjeta">
                    <h2>Estudiantes con conductas de riesgo marcadas</h2>
                    <strong id="total-estudiantes-riesgo" data-valor="<?= $datos['resumen_clinico']['estudiantes_riesgo'] ?>"><?= $datos['resumen_clinico']['estudiantes'] > 0 ? number_format($porcentaje($datos['resumen_clinico']['estudiantes_riesgo'], $datos['resumen_clinico']['estudiantes']), 1, ',', '.') . '%' : '—' ?></strong>
                    <p><?= $datos['resumen_clinico']['estudiantes'] > 0 ? number_format($datos['resumen_clinico']['estudiantes_riesgo'], 0, ',', '.') . ' de ' . number_format($datos['resumen_clinico']['estudiantes'], 0, ',', '.') . ' estudiantes con historia.' : 'Sin estudiantes para calcular el porcentaje.' ?></p>
                </article>
                <article class="estadisticas-tarjeta">
                    <h2>Estudiantes con dificultades socioemocionales marcadas</h2>
                    <strong id="total-estudiantes-socioemocionales" data-valor="<?= $datos['resumen_clinico']['estudiantes_socioemocionales'] ?>"><?= $datos['resumen_clinico']['estudiantes'] > 0 ? number_format($porcentaje($datos['resumen_clinico']['estudiantes_socioemocionales'], $datos['resumen_clinico']['estudiantes']), 1, ',', '.') . '%' : '—' ?></strong>
                    <p><?= $datos['resumen_clinico']['estudiantes'] > 0 ? number_format($datos['resumen_clinico']['estudiantes_socioemocionales'], 0, ',', '.') . ' de ' . number_format($datos['resumen_clinico']['estudiantes'], 0, ',', '.') . ' estudiantes con historia.' : 'Sin estudiantes para calcular el porcentaje.' ?></p>
                </article>
            </section>

            <section class="estadisticas-panel estadisticas-resumen-sesiones" aria-labelledby="titulo-resumen-sesiones">
                <h2 id="titulo-resumen-sesiones">Continuidad de atención</h2>
                <p>Seguimientos de la misma población de historias.</p>
                <div class="estadisticas-tarjetas estadisticas-tarjetas-secundarias" aria-label="Resumen de sesiones">
                    <?php foreach ([
                        'sesiones' => ['Sesiones en el período', 'Seguimientos entre las fechas seleccionadas'],
                        'acumuladas' => ['Sesiones acumuladas', 'Hasta la fecha de corte'],
                        'con_sesiones' => ['Historias con sesiones', 'Al menos un seguimiento en el período'],
                        'sin_sesiones' => ['Historias sin sesiones', 'Sin seguimiento en estas fechas'],
                    ] as $campo => [$etiqueta, $detalle]): ?>
                        <article class="estadisticas-tarjeta"><h3><?= $etiqueta ?></h3><strong id="total-<?= $campo ?>" data-valor="<?= $datos['resumen'][$campo] ?>"><?= number_format($datos['resumen'][$campo], 0, ',', '.') ?></strong><p><?= $detalle ?></p></article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php
            $motivosDistribucion = array_map(static fn(array $fila): array => [
                'nombre' => $fila['motivo'], 'historias' => (int)$fila['historias'],
            ], $datos['motivos_derivacion']);
            $gruposClinicos = [
                'dificultades_socioemocionales' => 'Dificultades socioemocionales',
                'adaptacion_normas' => 'Problemas de adaptación a normas',
                'conductas_riesgo' => 'Conductas de riesgo detectadas',
                'atencion_distraccion' => 'Atención y distracción',
                'actividad_motora' => 'Actividad motora',
                'estrategias_previas' => 'Estrategias previas a la derivación',
            ];
            ?>
            <div class="estadisticas-clinicas-grid">
                <section class="estadisticas-panel" aria-labelledby="titulo-motivos">
                    <h2 id="titulo-motivos">Motivos de derivación frecuentes</h2>
                    <p>Número y porcentaje de historias incluidas; una puede aparecer en varias categorías.</p>
                    <?php if ($motivosDistribucion): ?>
                        <?php $renderDistribucion($motivosDistribucion, $datos['resumen']['historias']); ?>
                    <?php else: ?>
                        <div class="estadisticas-vacio">No hay categorías de derivación vinculadas a estas historias.</div>
                    <?php endif; ?>
                </section>

                <?php foreach ($gruposClinicos as $codigoGrupo => $tituloGrupo): ?>
                    <section class="estadisticas-panel" aria-labelledby="titulo-grupo-<?= login_html($codigoGrupo) ?>">
                        <h2 id="titulo-grupo-<?= login_html($codigoGrupo) ?>"><?= login_html($tituloGrupo) ?></h2>
                        <p>Número y porcentaje sobre las historias incluidas; una puede tener varias selecciones.</p>
                        <?php $renderDistribucion(array_values($datos['distribuciones'][$codigoGrupo]['opciones'] ?? []), $datos['resumen']['historias']); ?>
                    </section>
                <?php endforeach; ?>

                <section class="estadisticas-panel" aria-labelledby="titulo-valoracion-escolar">
                    <h2 id="titulo-valoracion-escolar">Valoración escolar y familiar</h2>
                    <p>Distribución de las valoraciones guardadas en la historia.</p>
                    <?php foreach ([
                        'situacion_escolar' => 'Situación escolar',
                        'valoracion_familiar' => 'Valoración familiar',
                    ] as $campoValoracion => $tituloValoracion): ?>
                        <div class="estadisticas-subdistribucion">
                            <h3><?= login_html($tituloValoracion) ?></h3>
                            <?php
                            $itemsValoracion = [];
                            foreach ($datos['valoraciones'][$campoValoracion] as $nombreValor => $cantidadValor) {
                                $itemsValoracion[] = ['nombre' => $nombreValor, 'historias' => $cantidadValor];
                            }
                            $renderDistribucion($itemsValoracion, $datos['resumen']['historias']);
                            ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            </div>

            <section id="estadisticas-por-estudiante" class="estadisticas-panel" aria-labelledby="titulo-por-estudiante">
                <h2 id="titulo-por-estudiante">Estadísticas por estudiante</h2>
                <p>Opciones acumuladas en las historias incluidas; las valoraciones corresponden a la historia más reciente. Abra una historia para revisar sus notas clínicas completas.</p>
                <?php if (!$filasEstudiantes): ?>
                    <div class="estadisticas-vacio" role="status">No hay estudiantes con historias para los filtros seleccionados.</div>
                <?php else: ?>
                    <div class="estadisticas-tabla-scroll" tabindex="0" role="region" aria-label="Estadísticas clínicas por estudiante">
                        <table class="table estadisticas-tabla estadisticas-tabla-clinica">
                            <thead><tr><th scope="col">Estudiante / curso</th><th scope="col">Historias</th><th scope="col">Conductas de riesgo</th><th scope="col">Dificultades socioemocionales</th><th scope="col">Situación escolar</th><th scope="col">Valoración familiar</th><th scope="col">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($filasEstudiantes as $filaEstudiante): ?>
                                    <tr data-estudiante="<?= (int)$filaEstudiante['cod_estudiante'] ?>">
                                        <th scope="row"><span><?= login_html(trim($filaEstudiante['apellidos'] . ' ' . $filaEstudiante['nombres'])) ?></span><small><?= login_html(trim(($filaEstudiante['curso'] ?? 'Sin curso registrado') . ' ' . ($filaEstudiante['paralelo'] ?? ''))) ?></small></th>
                                        <td><?= (int)$filaEstudiante['historias'] ?></td>
                                        <td><?= $filaEstudiante['conductas_riesgo'] ? login_html(implode(', ', $filaEstudiante['conductas_riesgo'])) : '<span class="text-muted">Sin marcas registradas</span>' ?></td>
                                        <td><?= $filaEstudiante['dificultades_socioemocionales'] ? login_html(implode(', ', $filaEstudiante['dificultades_socioemocionales'])) : '<span class="text-muted">Sin marcas registradas</span>' ?></td>
                                        <td><?= login_html($filaEstudiante['situacion_escolar'] ?? 'Sin valoración') ?></td>
                                        <td><?= login_html($filaEstudiante['valoracion_familiar'] ?? 'Sin valoración') ?></td>
                                        <td><a href="<?= login_html(login_url('historias_clinicas/ver.php?id=' . (int)$filaEstudiante['cod_historia'])) ?>">Ver historia</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="estadisticas-paginacion" aria-label="Páginas de estadísticas por estudiante">
                        <span><?= ($paginaEstudiantes - 1) * $porPagina + 1 ?>–<?= min($paginaEstudiantes * $porPagina, count($datos['estudiantes'])) ?> de <?= count($datos['estudiantes']) ?> estudiantes</span>
                        <div>
                            <?php if ($paginaEstudiantes > 1): ?><a class="btn btn-outline-secondary" href="<?= login_html('?' . http_build_query($filtros + ['pagina_estudiante' => $paginaEstudiantes - 1])) ?>#estadisticas-por-estudiante">Anterior</a><?php endif; ?>
                            <?php if ($paginaEstudiantes < $paginasEstudiantes): ?><a class="btn btn-outline-secondary" href="<?= login_html('?' . http_build_query($filtros + ['pagina_estudiante' => $paginaEstudiantes + 1])) ?>#estadisticas-por-estudiante">Siguiente</a><?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </section>

            <div class="estadisticas-graficos">
                <section class="estadisticas-panel" aria-labelledby="titulo-meses">
                    <h2 id="titulo-meses">Sesiones por mes</h2>
                    <p>Conteo de seguimientos dentro de las fechas seleccionadas.</p>
                    <?php if (!$datos['resumen']['sesiones']): ?><p class="estadisticas-vacio">No hay sesiones registradas en este período.</p><?php endif; ?>
                    <ul class="estadisticas-barras" aria-label="Sesiones mensuales">
                        <?php $maximo = max(1, ...array_values($datos['meses'])); foreach ($datos['meses'] as $mes => $cantidad): ?>
                            <li class="estadisticas-barra" data-mes="<?= $mes ?>" data-total="<?= $cantidad ?>">
                                <span><?= $mesesNombres[substr($mes, 5, 2)] . ' ' . substr($mes, 0, 4) ?></span>
                                <span class="estadisticas-pista" aria-hidden="true"><span style="width:<?= round($cantidad * 100 / $maximo, 2) ?>%"></span></span>
                                <strong><?= $cantidad ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <section class="estadisticas-panel" aria-labelledby="titulo-estados">
                    <h2 id="titulo-estados">Estado actual de las historias</h2>
                    <p>Estado guardado en cada historia incluida.</p>
                    <ul class="estadisticas-barras estadisticas-estados">
                        <?php foreach ($datos['estados'] as $estado => $cantidad): ?>
                            <li class="estadisticas-barra" data-estado="<?= login_html($estado) ?>" data-total="<?= $cantidad ?>">
                                <span><?= login_html($estado) ?></span>
                                <span class="estadisticas-pista <?= $clasesEstado[$estado] ?>" aria-hidden="true"><span style="width:<?= round($cantidad * 100 / max(1, $datos['resumen']['historias']), 2) ?>%"></span></span>
                                <strong><?= $cantidad ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="estadisticas-nota">Cada seguimiento guardado cuenta como una sesión. Para valorar la evolución clínica, revise las observaciones y acuerdos de la historia.</div>
                </section>
            </div>

            <section id="detalle-estudiantes" class="estadisticas-panel" aria-labelledby="titulo-estudiantes">
                <h2 id="titulo-estudiantes">Seguimiento por historia clínica</h2>
                <p>Última sesión y total acumulado hasta el <?= $fechaVisible($filtros['hasta']) ?>. La próxima fecha es la prevista en ese seguimiento.</p>
                <?php if (!$filas): ?>
                    <div class="estadisticas-vacio" role="status">No hay historias clínicas para los filtros seleccionados.</div>
                <?php else: ?>
                    <div class="estadisticas-tabla-scroll" tabindex="0" role="region" aria-label="Detalle de sesiones por historia clínica">
                        <table class="table estadisticas-tabla">
                            <thead><tr><th scope="col">Estudiante / curso actual</th><th scope="col">Estado de la historia</th><th scope="col">Sesiones en el período</th><th scope="col">Última sesión</th><th scope="col">Próxima prevista</th><th scope="col">Consultar</th></tr></thead>
                            <tbody>
                                <?php foreach ($filas as $fila): ?>
                                    <tr data-historia="<?= (int)$fila['cod_historia'] ?>">
                                        <th scope="row"><span><?= login_html(trim($fila['apellidos'] . ' ' . $fila['nombres'])) ?></span><small><?= login_html(trim(($fila['curso'] ?? 'Sin curso registrado') . ' ' . ($fila['paralelo'] ?? ''))) ?></small><?php if ($fila['estado_estudiante'] === 'Retirado'): ?><small>Estudiante retirado</small><?php endif; ?></th>
                                        <td><span class="estadisticas-estado <?= $clasesEstado[$fila['estado']] ?>"><?= login_html($fila['estado']) ?></span></td>
                                        <td><strong><?= $fila['sesiones_periodo'] ?></strong><small><?= $fila['total_sesiones'] ?> acumuladas</small></td>
                                        <td><?= $fechaVisible($fila['ultima_sesion']) ?></td>
                                        <td><?= $fila['proxima_sesion'] ? $fechaVisible($fila['proxima_sesion']) : 'Sin fecha prevista' ?><small>Según último seguimiento</small></td>
                                        <td><a href="<?= login_html(login_url('historias_clinicas/ver.php?id=' . (int)$fila['cod_historia'])) ?>">Ver historia</a><?php if ($fila['cod_seguimiento']): ?><a class="estadisticas-enlace-secundario" href="<?= login_html(login_url('seguimientos/ver.php?id=' . (int)$fila['cod_seguimiento'])) ?>">Último seguimiento</a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="estadisticas-paginacion" aria-label="Páginas de estudiantes">
                        <span><?= ($pagina - 1) * $porPagina + 1 ?>–<?= min($pagina * $porPagina, $datos['resumen']['historias']) ?> de <?= $datos['resumen']['historias'] ?> historias</span>
                        <div>
                            <?php if ($pagina > 1): ?><a class="btn btn-outline-secondary" href="<?= login_html('?' . http_build_query($filtros + ['pagina' => $pagina - 1])) ?>#detalle-estudiantes">Anterior</a><?php endif; ?>
                            <?php if ($pagina < $paginas): ?><a class="btn btn-outline-secondary" href="<?= login_html('?' . http_build_query($filtros + ['pagina' => $pagina + 1])) ?>#detalle-estudiantes">Siguiente</a><?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
