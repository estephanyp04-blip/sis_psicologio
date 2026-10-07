<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('estadisticas/index.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$tituloPagina = 'Estadísticas de seguimiento';
$datos = null;
$error = '';
$cursos = [];
$filtros = estadisticas_filtros([]);
$pagina = 1;
$porPagina = 20;
try {
    $cursos = $conexion->query('SELECT id_curso,nombre FROM cursos ORDER BY orden,nombre')->fetch_all(MYSQLI_ASSOC);
    $filtros = estadisticas_filtros($_GET);
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($pagina === false) throw new InvalidArgumentException('La página solicitada no es válida.');
    $datos = estadisticas_datos($conexion, $filtros);
    $paginas = max(1, (int)ceil(count($datos['historias']) / $porPagina));
    $pagina = min($pagina, $paginas);
    $filas = array_slice($datos['historias'], ($pagina - 1) * $porPagina, $porPagina);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $error = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(503);
    error_log('Estadísticas: ' . $e->getMessage());
    $error = 'No se pudieron consultar las estadísticas. Intente nuevamente.';
}
$fechaVisible = static fn(?string $fecha): string => $fecha ? date('d/m/Y', strtotime($fecha)) : 'Sin registro';
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
            <div><h1>Estadísticas de seguimiento</h1><p>Sesiones y continuidad de atención por estudiante.</p></div>
            <a class="btn btn-outline-primary" href="<?= login_html(login_url('historias_clinicas/listar.php')) ?>">Ver historias clínicas</a>
        </header>

        <form id="filtros-estadisticas" class="estadisticas-filtros" method="get" action="<?= login_html(login_url('estadisticas/index.php')) ?>">
            <div><label for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" max="<?= date('Y-m-d') ?>" value="<?= login_html($filtros['desde']) ?>" required></div>
            <div><label for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" max="<?= date('Y-m-d') ?>" value="<?= login_html($filtros['hasta']) ?>" required></div>
            <div><label for="curso">Curso actual</label><select class="form-select" id="curso" name="curso">
                <option value="0">Todos los cursos</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?= (int)$curso['id_curso'] ?>" <?= $filtros['curso'] === (int)$curso['id_curso'] ? 'selected' : '' ?>><?= login_html($curso['nombre']) ?></option>
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
            <p class="estadisticas-contexto">Sesiones del <?= $fechaVisible($filtros['desde']) ?> al <?= $fechaVisible($filtros['hasta']) ?>. Se incluyen historias abiertas hasta esa fecha, aunque no tengan sesiones en el período.</p>
            <section class="estadisticas-tarjetas" aria-label="Resumen del período">
                <?php foreach ([
                    'historias' => ['Historias clínicas', 'Estudiantes con historia incluida'],
                    'sesiones' => ['Sesiones registradas', 'Seguimientos guardados en el período'],
                    'con_sesiones' => ['Estudiantes con sesiones', 'Al menos un seguimiento en el período'],
                    'sin_sesiones' => ['Sin sesiones en el período', 'Historias sin seguimiento en estas fechas'],
                ] as $campo => [$etiqueta, $detalle]): ?>
                    <article class="estadisticas-tarjeta"><h2><?= $etiqueta ?></h2><strong id="total-<?= $campo ?>" data-valor="<?= $datos['resumen'][$campo] ?>"><?= number_format($datos['resumen'][$campo], 0, ',', '.') ?></strong><p><?= $detalle ?></p></article>
                <?php endforeach; ?>
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
                <h2 id="titulo-estudiantes">Seguimiento por estudiante</h2>
                <p>Última sesión y total acumulado hasta el <?= $fechaVisible($filtros['hasta']) ?>. La próxima fecha es la prevista en ese seguimiento.</p>
                <?php if (!$filas): ?>
                    <div class="estadisticas-vacio" role="status">No hay historias clínicas para los filtros seleccionados.</div>
                <?php else: ?>
                    <div class="estadisticas-tabla-scroll" tabindex="0" role="region" aria-label="Detalle de sesiones por estudiante">
                        <table class="table estadisticas-tabla">
                            <thead><tr><th scope="col">Estudiante / curso actual</th><th scope="col">Estado de la historia</th><th scope="col">Sesiones en el período</th><th scope="col">Última sesión</th><th scope="col">Próxima prevista</th><th scope="col">Consultar</th></tr></thead>
                            <tbody>
                                <?php foreach ($filas as $fila): ?>
                                    <tr data-historia="<?= (int)$fila['id_historia'] ?>">
                                        <th scope="row"><span><?= login_html(trim($fila['apellidos'] . ' ' . $fila['nombres'])) ?></span><small><?= login_html(trim(($fila['curso'] ?? 'Sin curso registrado') . ' ' . ($fila['paralelo'] ?? ''))) ?></small><?php if ($fila['estado_estudiante'] === 'Retirado'): ?><small>Estudiante retirado</small><?php endif; ?></th>
                                        <td><span class="estadisticas-estado <?= $clasesEstado[$fila['estado']] ?>"><?= login_html($fila['estado']) ?></span></td>
                                        <td><strong><?= $fila['sesiones_periodo'] ?></strong><small><?= $fila['total_sesiones'] ?> acumuladas</small></td>
                                        <td><?= $fechaVisible($fila['ultima_sesion']) ?></td>
                                        <td><?= $fila['proxima_sesion'] ? $fechaVisible($fila['proxima_sesion']) : 'Sin fecha prevista' ?><small>Según último seguimiento</small></td>
                                        <td><a href="<?= login_html(login_url('historias_clinicas/ver.php?id=' . (int)$fila['id_historia'])) ?>">Ver historia</a><?php if ($fila['id_seguimiento']): ?><a class="estadisticas-enlace-secundario" href="<?= login_html(login_url('seguimientos/ver.php?id=' . (int)$fila['id_seguimiento'])) ?>">Último seguimiento</a><?php endif; ?></td>
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
