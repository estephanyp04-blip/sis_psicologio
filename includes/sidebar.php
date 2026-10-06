<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

$baseUrl = login_config()['base_url'];
$rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$gruposMenu = [
    'Principal' => [
        ['index.php', 'Panel principal', 'bi-grid-1x2-fill'],
    ],
    'Gestión psicológica' => [
        ['estudiantes/listar.php', 'Estudiantes', 'bi-people-fill'],
        ['derivaciones/listar.php', 'Derivaciones', 'bi-send-fill'],
        ['citas/listar.php', 'Citas', 'bi-calendar2-week-fill'],
        ['historias_clinicas/listar.php', 'Historias clínicas', 'bi-file-earmark-medical-fill'],
    ],
    'Administración' => [
        ['docentes/listar.php', 'Docentes', 'bi-person-badge-fill'],
        ['usuarios/listar.php', 'Usuarios', 'bi-person-gear'],
    ],
    'Resultados' => [
        ['informes/listar.php', 'Informes', 'bi-file-earmark-bar-graph-fill'],
    ],
];
?>

<aside class="sidebar">
    <a href="<?= login_html(login_inicio_url()) ?>" class="logo">
        <i class="bi bi-heart-pulse"></i>
        <h5>Sistema Psicológico</h5>
    </a>

    <nav class="sidebar-menu" aria-label="Menú principal">
        <?php foreach ($gruposMenu as $tituloGrupo => $enlaces): ?>
            <?php
            $enlaces = array_filter($enlaces, static fn(array $enlace): bool => login_puede($enlace[0]));
            if (!$enlaces) continue;
            ?>
            <span class="sidebar-section-title"><?= login_html($tituloGrupo) ?></span>
            <ul class="nav flex-column">
                <?php foreach ($enlaces as [$ruta, $etiqueta, $icono]): ?>
                    <?php
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
                <?php if ($tituloGrupo === 'Resultados'): ?>
                    <li class="nav-item">
                        <span class="nav-link text-muted" aria-disabled="true">
                            <i class="bi bi-bar-chart-line-fill"></i>
                            <span>Estadísticas</span>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
