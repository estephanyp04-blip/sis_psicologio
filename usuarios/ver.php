<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/ver.php');

require_once __DIR__ . '/../config/conexion.php';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$rolActual = (int) ($_SESSION['cod_rol'] ?? 0);

if(!$id){
    header('Location: listar.php');
    exit;
}

$stmt=$conexion->prepare("
    SELECT
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
    WHERE u.cod_usuario=? AND (?=1 OR u.cod_rol=?)
    LIMIT 1
");

if(!$stmt){
    die('Error al preparar la consulta: '.$conexion->error);
}

$stmt->bind_param('iii',$id,$rolActual,$rolActual);
$stmt->execute();

$resultado=$stmt->get_result();
$usuario=$resultado->fetch_assoc();

if(!$usuario){
    $_SESSION['mensaje']='Usuario no encontrado.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}
?>

<div class="main-content">
    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
        </span>
        <span class="breadcrumb-item">
            <a href="listar.php">Usuarios</a>
        </span>
        <span class="breadcrumb-item active">Ver</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Detalle del Usuario</h2>
            <p class="text-muted mb-0">
                Información registrada de la cuenta.
            </p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">

            <div class="row g-4">

                <div class="col-md-6">
                    <label class="text-muted small">
                        Nombre
                    </label>
                    <div class="fw-semibold fs-5">
                        <?= htmlspecialchars($usuario['nombre']); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="text-muted small">
                        Apellido
                    </label>
                    <div class="fw-semibold fs-5">
                        <?= htmlspecialchars($usuario['apellido']); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="text-muted small">
                        Usuario
                    </label>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($usuario['usuario']); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="text-muted small">
                        Correo electrónico
                    </label>
                    <div class="fw-semibold">
                        <?= htmlspecialchars(
                            $usuario['correo']?:'No registrado'
                        ); ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="text-muted small">
                        Rol
                    </label>
                    <div>
                        <span class="badge bg-light text-dark border fs-6">
                            <?= htmlspecialchars($usuario['rol']?:'Sin rol'); ?>
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="text-muted small">
                        Estado
                    </label>
                    <div>
                        <?php if($usuario['estado']==='Activo'): ?>
                            <span class="badge bg-success fs-6">
                                Activo
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary fs-6">
                                Inactivo
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between">

                <a
                    href="listar.php"
                    class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i>
                    Volver
                </a>

                <a
                    href="editar.php?id=<?= (int)$usuario['cod_usuario']; ?>"
                    class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>
                    Editar Usuario
                </a>

            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
