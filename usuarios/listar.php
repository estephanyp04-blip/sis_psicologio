<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/listar.php');

require_once '../config/conexion.php';
$rolActual = (int) ($_SESSION['cod_rol'] ?? 0);
$filtroRol = $rolActual === 1 ? '' : 'WHERE u.cod_rol=' . $rolActual;

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

$sql="SELECT
        u.cod_usuario,
        u.nombres AS nombre,
        u.apellidos AS apellido,
        u.usuario,
        u.correo,
        u.estado,
        u.cod_rol,
        r.nombre AS rol
      FROM usuarios u
      LEFT JOIN roles r ON u.cod_rol=r.cod_rol
      $filtroRol
      ORDER BY u.cod_usuario DESC";

$resultado=$conexion->query($sql);

if(!$resultado){
    die("Error al consultar usuarios: ".$conexion->error);
}
?>

<div class="main-content">
    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
        </span>
        <span class="breadcrumb-item active">Usuarios</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Usuarios</h2>
            <p class="text-muted mb-0">Lista de usuarios registrados en el sistema.</p>
        </div>

        <?php if (login_puede('usuarios/registrar.php')): ?><a href="registrar.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</a><?php endif; ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($resultado->num_rows>0): ?>
                            <?php while($fila=$resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int)$fila['cod_usuario']; ?></td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $fila['nombre'].' '.$fila['apellido'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $fila['usuario'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $fila['correo'] ?: 'Sin correo',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars(
                                                $fila['rol'] ?: 'Sin rol',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if($fila['estado']==='Activo'): ?>
                                            <span class="badge bg-success">
                                                Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if (login_puede('usuarios/ver.php')): ?><a
                                            href="ver.php?id=<?= (int)$fila['cod_usuario']; ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a><?php endif; ?>

                                        <?php if (login_puede('usuarios/editar.php')): ?><a
                                            href="editar.php?id=<?= (int)$fila['cod_usuario']; ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a><?php endif; ?>
                                        
                                        <?php if (login_puede('usuarios/cambiar_estado.php')): ?>
                                        <form action="cambiar_estado.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado de este usuario?');">
                                            <?= login_campo_csrf() ?>
                                            <input type="hidden" name="cod_usuario" value="<?= (int)$fila['cod_usuario']; ?>">

                                            <?php if($fila['estado']==='Activo'): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Inactivar">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Activar">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <?php endif; ?>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No hay usuarios registrados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
