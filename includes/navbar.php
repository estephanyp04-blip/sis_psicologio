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
    <div class="d-flex align-items-center justify-content-between w-100">
        <span class="fw-bold text-primary">
            <i class="bi bi-heart-pulse"></i>
            Sistema Psicológico Estudiantil
        </span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <form action="<?= login_html(login_url('cerrar_sesion.php')) ?>" method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= login_html(login_token()) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-right"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>