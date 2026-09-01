<?php

/* Ruta principal del proyecto */
$baseUrl = '/proyecto_vercionII';

/* Detectar la página actual */
$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
);

$rutaActual = is_string($rutaActual)
    ? $rutaActual
    : '';

/* Marcar el enlace seleccionado */
$claseActiva = static function (string $seccion) use ($rutaActual): string {
    return str_contains($rutaActual, '/' . $seccion . '/')
        ? ' active'
        : '';
};

$inicioActivo = in_array(
    rtrim($rutaActual, '/'),
    [
        $baseUrl,
        $baseUrl . '/index.php'
    ],
    true
) ? ' active' : '';

?>

<aside class="sidebar">

    <!-- Logo -->
    <a href="<?= $baseUrl; ?>/index.php" class="logo">
        <i class="bi bi-heart-pulse"></i>

        <h5>Sistema Psicológico</h5>
    </a>

    <!-- Menú lateral -->
    <nav class="sidebar-menu" aria-label="Menú principal">

        <span class="sidebar-section-title">
            Principal
        </span>

        <ul class="nav flex-column">

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/index.php"
                    class="nav-link<?= $inicioActivo; ?>">

                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Panel principal</span>
                </a>
            </li>

        </ul>

        <span class="sidebar-section-title">
            Gestión psicológica
        </span>

        <ul class="nav flex-column">

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/estudiantes/listar.php"
                    class="nav-link<?= $claseActiva('estudiantes'); ?>">

                    <i class="bi bi-people-fill"></i>
                    <span>Estudiantes</span>
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/derivaciones/listar.php"
                    class="nav-link<?= $claseActiva('derivaciones'); ?>">

                    <i class="bi bi-send-fill"></i>
                    <span>Derivaciones</span>
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/citas/listar.php"
                    class="nav-link<?= $claseActiva('citas'); ?>">

                    <i class="bi bi-calendar2-week-fill"></i>
                    <span>Citas</span>
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/historias_clinicas/listar.php"
                    class="nav-link<?= $claseActiva('historias_clinicas'); ?>">

                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Historias clínicas</span>
                </a>
            </li>

        </ul>

        <span class="sidebar-section-title">
            Administración
        </span>

        <ul class="nav flex-column">

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/docentes/listar.php"
                    class="nav-link<?= $claseActiva('docentes'); ?>">

                    <i class="bi bi-person-badge-fill"></i>
                    <span>Docentes</span>
                </a>
            </li>

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/usuarios/listar.php"
                    class="nav-link<?= $claseActiva('usuarios'); ?>">

                    <i class="bi bi-person-gear"></i>
                    <span>Usuarios</span>
                </a>
            </li>

        </ul>

        <span class="sidebar-section-title">
            Resultados
        </span>

        <ul class="nav flex-column">

            <li class="nav-item">
                <a
                    href="<?= $baseUrl; ?>/informes/listar.php"
                    class="nav-link<?= $claseActiva('informes'); ?>">

                    <i class="bi bi-file-earmark-bar-graph-fill"></i>
                    <span>Informes</span>
                </a>
            </li>

            <li class="nav-item">
                <span class="nav-link text-muted" aria-disabled="true">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Estadísticas</span>
                </span>
            </li>

        </ul>

    </nav>

</aside>