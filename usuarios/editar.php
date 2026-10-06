<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/editar.php');

require_once '../config/conexion.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);

if(!$id){
    header('Location: listar.php');
    exit;
}

$stmt=$conexion->prepare("SELECT id_usuario,nombre,apellido,usuario,correo,estado,id_rol FROM usuarios WHERE id_usuario=? LIMIT 1");

if(!$stmt){
    die('Error al preparar la consulta: '.$conexion->error);
}

$stmt->bind_param('i',$id);
$stmt->execute();
$resultado=$stmt->get_result();
$datos=$resultado->fetch_assoc();

if(!$datos){
    $_SESSION['mensaje']='Usuario no encontrado.';
    $_SESSION['tipo_mensaje']='danger';
    header('Location: listar.php');
    exit;
}

$resultadoRoles=$conexion->query("SELECT id_rol,nombre FROM roles ORDER BY id_rol ASC");

if(!$resultadoRoles){
    die('Error al consultar roles: '.$conexion->error);
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">
    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
        </span>
        <span class="breadcrumb-item">
            <a href="listar.php">Usuarios</a>
        </span>
        <span class="breadcrumb-item active">Editar</span>
    </nav>

    <?php if(isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['tipo_mensaje']??'danger'); ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
        ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="mb-4">
                <h2 class="mb-1">Editar Usuario</h2>
                <p class="text-muted mb-0">Modifique los datos de la cuenta seleccionada.</p>
            </div>

            <form action="actualizar.php" method="POST" autocomplete="off">
                <?= login_campo_csrf() ?>
                <input type="hidden" name="id_usuario" value="<?= (int)$datos['id_usuario']; ?>">

                <div class="form-section mb-4">
                    <h5 class="section-title">
                        <i class="bi bi-person text-primary me-2"></i>
                        Datos personales
                    </h5>
                    <hr>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="nombre"
                                id="nombre"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars($datos['nombre']); ?>"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label for="apellido" class="form-label">
                                Apellido <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="apellido"
                                id="apellido"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars($datos['apellido']); ?>"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label for="correo" class="form-label">Correo electrónico</label>
                            <input
                                type="email"
                                name="correo"
                                id="correo"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars($datos['correo']??''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section mb-4">
                    <h5 class="section-title">
                        <i class="bi bi-person-lock text-primary me-2"></i>
                        Datos de acceso
                    </h5>
                    <hr>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="usuario" class="form-label">
                                Usuario <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="usuario"
                                id="usuario"
                                class="form-control"
                                maxlength="30"
                                value="<?= htmlspecialchars($datos['usuario']); ?>"
                                required>
                        </div>

                        <div class="col-md-3">
                            <label for="id_rol" class="form-label">
                                Rol <span class="text-danger">*</span>
                            </label>
                            <select name="id_rol" id="id_rol" class="form-select" required>
                                <option value="">Seleccionar rol...</option>

                                <?php while($rol=$resultadoRoles->fetch_assoc()): ?>
                                    <option
                                        value="<?= (int)$rol['id_rol']; ?>"
                                        <?= (int)$datos['id_rol']===(int)$rol['id_rol']?'selected':''; ?>>
                                        <?= htmlspecialchars($rol['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="estado" class="form-label">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select name="estado" id="estado" class="form-select" required>
                                <option value="Activo" <?= $datos['estado']==='Activo'?'selected':''; ?>>
                                    Activo
                                </option>
                                <option value="Inactivo" <?= $datos['estado']==='Inactivo'?'selected':''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                Nueva contraseña
                            </label>

                            <div class="input-group">
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    minlength="6"
                                    placeholder="Dejar vacío para mantener la actual"
                                    autocomplete="new-password">

                                <button class="btn btn-outline-secondary" type="button" id="verPassword">
                                    <i class="bi bi-eye" id="iconoPassword"></i>
                                </button>
                            </div>

                            <small class="text-muted">
                                Solo complete este campo si desea cambiar la contraseña.
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label for="confirmar_password" class="form-label">
                                Confirmar nueva contraseña
                            </label>

                            <div class="input-group">
                                <input
                                    type="password"
                                    name="confirmar_password"
                                    id="confirmar_password"
                                    class="form-control"
                                    minlength="6"
                                    placeholder="Repita la nueva contraseña"
                                    autocomplete="new-password">

                                <button class="btn btn-outline-secondary" type="button" id="verConfirmar">
                                    <i class="bi bi-eye" id="iconoConfirmar"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="listar.php" class="btn btn-light border px-4">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>
                        Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    const password=document.getElementById('password');
    const confirmar=document.getElementById('confirmar_password');
    const verPassword=document.getElementById('verPassword');
    const verConfirmar=document.getElementById('verConfirmar');
    const iconoPassword=document.getElementById('iconoPassword');
    const iconoConfirmar=document.getElementById('iconoConfirmar');

    verPassword.addEventListener('click',function(){
        if(password.type==='password'){
            password.type='text';
            iconoPassword.classList.replace('bi-eye','bi-eye-slash');
        }else{
            password.type='password';
            iconoPassword.classList.replace('bi-eye-slash','bi-eye');
        }
    });

    verConfirmar.addEventListener('click',function(){
        if(confirmar.type==='password'){
            confirmar.type='text';
            iconoConfirmar.classList.replace('bi-eye','bi-eye-slash');
        }else{
            confirmar.type='password';
            iconoConfirmar.classList.replace('bi-eye-slash','bi-eye');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>