<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/autenticacion.php';
$baseUrl = login_config()['base_url'];
?>
<!-- Barra superior -->
<nav class="navbar navbar-top">
    <div class="navbar-top-content d-flex align-items-center justify-content-between w-100">
        <button class="btn btn-menu-toggle" type="button" data-sidebar-toggle aria-controls="sidebar-navigation" aria-expanded="false" aria-label="Abrir menú de navegación">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <span class="fw-bold text-primary navbar-system-title">
            <i class="bi bi-heart-pulse"></i>
            Sistema Psicológico Estudiantil
        </span>
        <div class="navbar-user-actions d-flex align-items-center gap-3">
            <span class="text-muted navbar-user-name">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <form action="<?= login_html(login_url('cerrar_sesion.php')) ?>" method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary navbar-logout" aria-label="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>
