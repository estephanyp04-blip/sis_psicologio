<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('roles/editar.php');
require_once __DIR__ . '/../config/conexion.php';

$idRol = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idRol || $idRol <= 1) { http_response_code(404); exit('Rol no disponible para edición.'); }
$stmt = $conexion->prepare('SELECT cod_rol,nombre,descripcion,estado FROM roles WHERE cod_rol=? LIMIT 1');
$stmt->bind_param('i', $idRol);
$stmt->execute();
$rol = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rol) { http_response_code(404); exit('Rol no encontrado.'); }

$stmt = $conexion->prepare('SELECT cod_modulo,puede_ver,puede_crear,puede_editar,puede_cambiar_estado,puede_eliminar FROM permisos WHERE cod_rol=?');
$stmt->bind_param('i', $idRol);
$stmt->execute();
$permisos = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $permiso) $permisos[(int)$permiso['cod_modulo']] = $permiso;
$stmt->close();
$modulos = $conexion->query("SELECT cod_modulo,nombre,orden FROM modulos WHERE estado='Activo' ORDER BY orden,cod_modulo")->fetch_all(MYSQLI_ASSOC);
$modulosConCambioEstado = ['Usuarios', 'Estudiantes', 'Derivaciones', 'Citas'];
$modulosBloqueadosRolesBase = ['Dashboard', 'Estadísticas', 'Estudiantes'];
$acciones = ['puede_ver' => 'Ver', 'puede_crear' => 'Crear', 'puede_editar' => 'Editar', 'puede_cambiar_estado' => 'Cambiar estado', 'puede_eliminar' => 'Eliminar'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="main-content">
    <nav class="breadcrumb mb-4"><span class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></span><span class="breadcrumb-item"><a href="listar.php">Roles y permisos</a></span><span class="breadcrumb-item active">Editar</span></nav>
    <div class="card shadow-sm"><div class="card-body p-4">
        <h2 class="mb-1">Editar rol</h2><p class="text-muted mb-4">Actualiza el estado y los permisos de <strong><?= login_html($rol['nombre']) ?></strong>.</p>
        <form action="actualizar.php" method="post">
            <?= login_campo_csrf() ?><input type="hidden" name="cod_rol" value="<?= (int)$rol['cod_rol'] ?>">
            <div class="row g-3 mb-4">
                <div class="col-md-5"><label class="form-label" for="nombre">Nombre del rol</label><input class="form-control" id="nombre" name="nombre" maxlength="40" value="<?= login_html($rol['nombre']) ?>" required></div>
                <div class="col-md-5"><label class="form-label" for="descripcion">Descripción</label><input class="form-control" id="descripcion" name="descripcion" maxlength="255" value="<?= login_html($rol['descripcion'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado"><option value="Activo" <?= $rol['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option><option value="Inactivo" <?= $rol['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
            </div>
            <div class="table-responsive"><table class="table table-bordered align-middle">
                <thead class="table-light"><tr><th>Módulo</th><?php foreach ($acciones as $etiqueta): ?><th class="text-center"><?= login_html($etiqueta) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($modulos as $modulo): $idModulo = (int)$modulo['cod_modulo']; $datos = $permisos[$idModulo] ?? []; $soloAdmin = $modulo['nombre'] === 'Roles y permisos'; $bloqueadoPorPolitica = in_array((int)$idRol, [3, 4], true) && in_array($modulo['nombre'], $modulosBloqueadosRolesBase, true); ?>
                    <tr><th scope="row"><?= login_html($modulo['nombre']) ?></th>
                    <?php foreach ($acciones as $campo => $etiqueta): $estadoNoDisponible = $campo === 'puede_cambiar_estado' && !in_array($modulo['nombre'], $modulosConCambioEstado, true); ?>
                        <td class="text-center"><?php if ($bloqueadoPorPolitica): ?><span class="text-muted" aria-label="Restringido por política del sistema">—</span><?php elseif ($soloAdmin || $estadoNoDisponible): ?><span class="text-muted" aria-label="No disponible">—</span><?php else: ?><input class="form-check-input" type="checkbox" name="permisos[<?= $idModulo ?>][<?= $campo ?>]" value="1" aria-label="<?= login_html($etiqueta . ' en ' . $modulo['nombre']) ?>" <?= !empty($datos[$campo]) ? 'checked' : '' ?>><?php endif; ?></td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="small text-muted">La administración de roles está reservada al administrador. Un rol inactivo no podrá iniciar sesión.</p>
            <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline-secondary" href="listar.php">Cancelar</a></div>
        </form>
    </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
