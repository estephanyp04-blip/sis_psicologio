<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('roles/listar.php');
require_once __DIR__ . '/../config/conexion.php';

$roles = $conexion->query("SELECT r.cod_rol,r.nombre,r.descripcion,r.estado,r.fecha_registro,COUNT(u.cod_usuario) AS usuarios
    FROM roles r LEFT JOIN usuarios u ON u.cod_rol=r.cod_rol
    GROUP BY r.cod_rol,r.nombre,r.descripcion,r.estado,r.fecha_registro
    ORDER BY r.cod_rol");
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content">
    <nav class="breadcrumb mb-4"><span class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></span><span class="breadcrumb-item active">Roles y permisos</span></nav>
    <?php if ($mensaje !== ''): ?><div class="alert alert-<?= login_html($tipoMensaje) ?> alert-dismissible fade show" role="status"><?= login_html($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div><?php endif; ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="mb-1">Roles y permisos</h2><p class="text-muted mb-0">Crea perfiles y define qué módulos pueden consultar y qué acciones pueden realizar.</p></div>
        <a class="btn btn-primary" href="registrar.php"><i class="bi bi-shield-plus me-1"></i>Nuevo rol</a>
    </div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Rol</th><th>Descripción</th><th>Usuarios</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
        <?php while ($rol = $roles->fetch_assoc()): ?>
            <tr>
                <td><strong><?= login_html($rol['nombre']) ?></strong><?= (int)$rol['cod_rol'] === 1 ? '<div class="small text-muted">Rol administrador protegido</div>' : '' ?></td>
                <td><?= login_html($rol['descripcion'] ?: 'Sin descripción') ?></td>
                <td><?= (int)$rol['usuarios'] ?></td>
                <td><span class="badge <?= $rol['estado'] === 'Activo' ? 'bg-success' : 'bg-secondary' ?>"><?= login_html($rol['estado']) ?></span></td>
                <td class="text-end"><?php if ((int)$rol['cod_rol'] !== 1): ?><a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= (int)$rol['cod_rol'] ?>"><i class="bi bi-pencil me-1"></i>Editar permisos</a><?php else: ?><span class="text-muted small">Protegido</span><?php endif; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
