<!-- Barra superior -->
<?php $baseUrl = '/proyecto_vercionII'; ?>
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
            <span class="btn btn-sm btn-outline-secondary text-muted"
                  aria-disabled="true"
                  title="Espacio reservado para cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar sesión
            </span>
        </div>
    </div>
</nav>
