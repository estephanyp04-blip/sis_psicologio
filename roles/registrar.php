<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('roles/registrar.php');
require_once __DIR__ . '/../config/conexion.php';
$modulos = $conexion->query("SELECT cod_modulo,nombre,orden FROM modulos WHERE estado='Activo' ORDER BY orden,cod_modulo")->fetch_all(MYSQLI_ASSOC);
$modulosConCambioEstado = ['Usuarios', 'Estudiantes', 'Derivaciones', 'Citas'];
$acciones = ['puede_ver' => 'Ver', 'puede_crear' => 'Crear', 'puede_editar' => 'Editar', 'puede_cambiar_estado' => 'Cambiar estado', 'puede_eliminar' => 'Eliminar'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content">
    <nav class="breadcrumb mb-4"><span class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></span><span class="breadcrumb-item"><a href="listar.php">Roles y permisos</a></span><span class="breadcrumb-item active">Nuevo rol</span></nav>
    <?php if (!empty($_SESSION['mensaje'])): ?><div class="alert alert-danger"><?= login_html($_SESSION['mensaje']) ?></div><?php unset($_SESSION['mensaje']); endif; ?>
    <div class="card shadow-sm"><div class="card-body p-4">
        <h2 class="mb-1">Crear rol</h2><p class="text-muted mb-4">Marca los módulos y acciones que podrán usar las cuentas con este rol.</p>
        <form action="guardar.php" method="post">
            <?= login_campo_csrf() ?>
            <div class="row g-3 mb-4">
                <div class="col-md-5"><label class="form-label" for="nombre">Nombre del rol</label><input class="form-control" id="nombre" name="nombre" maxlength="40" required></div>
                <div class="col-md-7"><label class="form-label" for="descripcion">Descripción</label><input class="form-control" id="descripcion" name="descripcion" maxlength="255"></div>
            </div>
            <div class="table-responsive"><table class="table table-bordered align-middle">
                <thead class="table-light"><tr><th>Módulo</th><?php foreach ($acciones as $etiqueta): ?><th class="text-center"><?= login_html($etiqueta) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($modulos as $modulo): $soloAdmin = $modulo['nombre'] === 'Roles y permisos'; ?>
                    <tr><th scope="row"><?= login_html($modulo['nombre']) ?></th>
                    <?php foreach ($acciones as $campo => $etiqueta): $estadoNoDisponible = $campo === 'puede_cambiar_estado' && !in_array($modulo['nombre'], $modulosConCambioEstado, true); ?>
                        <td class="text-center"><?php if ($soloAdmin || $estadoNoDisponible): ?><span class="text-muted" aria-label="No disponible">—</span><?php else: ?><input class="form-check-input" type="checkbox" name="permisos[<?= (int)$modulo['cod_modulo'] ?>][<?= $campo ?>]" value="1" aria-label="<?= login_html($etiqueta . ' en ' . $modulo['nombre']) ?>"><?php endif; ?></td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="small text-muted">El acceso a administrar roles queda reservado al administrador. Para que un docente registre cuentas, marca <strong>Ver</strong> y <strong>Crear</strong> en Usuarios.</p>
            <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar rol</button><a class="btn btn-outline-secondary" href="listar.php">Cancelar</a></div>
        </form>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
