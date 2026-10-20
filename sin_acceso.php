<?php
require_once __DIR__ . '/includes/autenticacion.php';
requerir_roles();
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/navbar.php';
?>
<main class="main-content">
    <div class="card shadow-sm"><div class="card-body p-5 text-center">
        <i class="bi bi-shield-lock display-4 text-secondary" aria-hidden="true"></i>
        <h1 class="h3 mt-3">Sin módulos asignados</h1>
        <p class="text-muted mb-0">Tu rol aún no tiene acceso a una sección del sistema. Solicita al administrador que configure tus permisos.</p>
    </div></div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
