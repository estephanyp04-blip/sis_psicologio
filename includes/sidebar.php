<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

$baseUrl = login_config()['base_url'];
$rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$gruposMenu = [
    'Principal' => [
        ['index.php', 'Panel principal', 'bi-grid-1x2-fill', null],
    ],
    'Gestión psicológica' => [
        ['estudiantes/listar.php', 'Estudiantes', 'bi-people-fill', 'estudiantes/registrar.php'],
        ['derivaciones/listar.php', 'Derivaciones', 'bi-send-fill', 'derivaciones/registrar.php'],
        ['citas/listar.php', 'Citas', 'bi-calendar2-week-fill', 'citas/registrar.php'],
        ['historias_clinicas/listar.php', 'Historias clínicas', 'bi-file-earmark-medical-fill', 'historias_clinicas/registrar.php'],
    ],
    'Administración' => [
        ['docentes/listar.php', 'Docentes', 'bi-person-badge-fill', 'docentes/registrar.php'],
        ['usuarios/listar.php', 'Usuarios', 'bi-person-gear', 'usuarios/registrar.php'],
        ['roles/listar.php', 'Roles y permisos', 'bi-shield-lock', null],
    ],
    'Resultados' => [
        ['informes/listar.php', 'Informes', 'bi-file-earmark-bar-graph-fill', 'informes/registrar.php'],
        ['estadisticas/index.php', 'Estadísticas', 'bi-bar-chart-line-fill', null],
    ],
];
?>

<aside class="sidebar" id="sidebar-navigation" aria-label="Menú de navegación" tabindex="-1">
    <a href="<?= login_html(login_inicio_url()) ?>" class="logo">
        <img class="logo-marca" src="<?= login_html(login_url('asset/img/logo-sistema.svg')) ?>" alt="">
        <h5>Sistema Psicológico</h5>
    </a>

    <nav class="sidebar-menu" aria-label="Menú principal">
        <?php foreach ($gruposMenu as $tituloGrupo => $enlaces): ?>
            <?php
            $enlaces = array_values(array_filter($enlaces, static fn(array $enlace): bool => login_puede($enlace[0]) || (!empty($enlace[3]) && login_puede($enlace[3]))));
            if (!$enlaces) continue;
            ?>
            <span class="sidebar-section-title"><?= login_html($tituloGrupo) ?></span>
            <ul class="nav flex-column">
                <?php foreach ($enlaces as [$ruta, $etiqueta, $icono, $rutaAlternativa]): ?>
                    <?php
                    if (!login_puede($ruta) && $rutaAlternativa) $ruta = $rutaAlternativa;
                    $activo = $ruta === 'index.php'
                        ? in_array(rtrim($rutaActual, '/'), [$baseUrl, login_url($ruta)], true)
                        : str_starts_with($rutaActual, login_url(dirname($ruta)) . '/');
                    ?>
                    <li class="nav-item">
                        <a href="<?= login_html(login_url($ruta)) ?>" class="nav-link<?= $activo ? ' active' : '' ?>">
                            <i class="bi <?= login_html($icono) ?>"></i>
                            <span><?= login_html($etiqueta) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
<button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Cerrar menú de navegación" hidden></button>
