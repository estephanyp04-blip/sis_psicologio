<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/registrar.php');

require_once '../config/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/*
 * Obtener usuarios con rol Docente que todavía
 * no estén relacionados con otro docente.
 */
$sqlUsuarios = "
    SELECT
        u.id_usuario,
        p.nombres AS nombre,
        p.apellidos AS apellido,
        u.usuario,
        p.telefono,
        p.correo
    FROM usuarios u
    INNER JOIN personas p ON p.id_persona=u.id_persona
    LEFT JOIN docentes d ON d.id_persona=u.id_persona
    WHERE u.id_rol = 3
      AND u.estado = 'Activo'
      AND d.id_docente IS NULL
    ORDER BY p.apellidos ASC,p.nombres ASC
";

$resultadoUsuarios = $conexion->query($sqlUsuarios);
$materias = array_column(
    $conexion->query("SELECT nombre FROM materias WHERE estado='Activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC),
    'nombre'
);

if (!$resultadoUsuarios) {
    die('Error al consultar usuarios: ' . $conexion->error);
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
            <a href="listar.php">Docentes</a>
        </span>

        <span class="breadcrumb-item active">
            Registrar
        </span>
    </nav>

    <div class="mb-4">
        <h1 class="h3 mb-1">Registrar docente</h1>

        <p class="text-muted mb-0">
            Complete los datos del docente y asigne su cuenta de usuario.
        </p>
    </div>

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= escapar(
            $_SESSION['tipo_mensaje'] ?? 'danger'
        ); ?> alert-dismissible fade show"
             role="alert">

            <?= escapar($_SESSION['mensaje']); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Cerrar">
            </button>
        </div>

        <?php
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
        ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <?php if ($resultadoUsuarios->num_rows === 0): ?>
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    No existen cuentas disponibles con el rol Docente.
                    Primero debe registrar una cuenta de usuario con ese rol.
                </div>
            <?php endif; ?>

            <form action="guardar.php"
                  method="POST"
                  id="formDocente"
                  autocomplete="off">
                <?= login_campo_csrf() ?>

                <div class="mb-4">
                    <h5 class="border-bottom pb-3">
                        <i class="bi bi-person-badge text-primary me-2"></i>
                        1. Cuenta del docente
                    </h5>

                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label for="id_usuario" class="form-label">
                                Cuenta de usuario
                                <span class="text-danger">*</span>
                            </label>

                            <select name="id_usuario"
                                    id="id_usuario"
                                    class="form-select"
                                    required
                                    <?= $resultadoUsuarios->num_rows === 0
                                        ? 'disabled'
                                        : ''; ?>>

                                <option value="">
                                    Seleccionar cuenta del docente...
                                </option>

                                <?php while (
                                    $usuario = $resultadoUsuarios->fetch_assoc()
                                ): ?>
                                    <option
                                        value="<?= (int)
                                            $usuario['id_usuario']; ?>"
                                        data-nombres="<?= escapar(
                                            $usuario['nombre']
                                        ); ?>"
                                        data-apellidos="<?= escapar(
                                            $usuario['apellido']
                                        ); ?>"
                                        data-correo="<?= escapar(
                                            $usuario['correo']
                                        ); ?>"
                                        data-telefono="<?= escapar(
                                            $usuario['telefono']
                                        ); ?>">

                                        <?= escapar(
                                            $usuario['apellido'] . ' ' .
                                            $usuario['nombre'] . ' (' .
                                            $usuario['usuario'] . ')'
                                        ); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <div class="form-text">
                                Solo aparecen usuarios activos con rol Docente
                                que todavía no están asignados.
                                Al elegir una cuenta se completan sus datos personales;
                                puede revisarlos y corregirlos antes de guardar.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="border-bottom pb-3">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>
                        2. Datos personales
                    </h5>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="nombres" class="form-label">
                                Nombres
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="nombres"
                                   id="nombres"
                                   class="form-control"
                                   maxlength="60"
                                   placeholder="Ingrese los nombres"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label for="apellidos" class="form-label">
                                Apellidos
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="apellidos"
                                   id="apellidos"
                                   class="form-control"
                                   maxlength="60"
                                   placeholder="Ingrese los apellidos"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label for="telefono" class="form-label">
                                Teléfono
                            </label>

                            <input type="tel"
                                   name="telefono"
                                   id="telefono"
                                   class="form-control"
                                   maxlength="20"
                                   placeholder="Ejemplo: 70000000"
                                   pattern="[0-9+\-\s]{7,20}">
                        </div>

                        <div class="col-md-6">
                            <label for="correo" class="form-label">
                                Correo electrónico
                            </label>

                            <input type="email"
                                   name="correo"
                                   id="correo"
                                   class="form-control"
                                   maxlength="100"
                                   placeholder="docente@correo.com">
                        </div>
                    </div>
                </div>

               <div class="mb-4">
                    <h5 class="border-bottom pb-3">
                        <i class="bi bi-book text-primary me-2"></i>
                        3. Información académica
                    </h5>

                    <div class="mt-3">
                        <label class="form-label d-block">
                            Materias que imparte
                            <span class="text-danger">*</span>
                        </label>

                        <p class="text-muted small">
                            Puede seleccionar una o varias materias.
                        </p>

                        <div class="row g-3">
            <?php foreach ($materias as $indice => $materia): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="form-check border rounded p-3 h-100">
                       <input
                            type="checkbox"
                            name="materias[]"
                            value="<?= escapar($materia); ?>"
                            id="materia<?= $indice; ?>"
                            class="form-check-input materia-check">

                        <label
                            for="materia<?= $indice; ?>"
                            class="form-check-label ms-1">

                            <?= escapar($materia); ?>
                        </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                        <div id="errorMaterias"
                            class="text-danger small mt-2 d-none">
                            Debe seleccionar al menos una materia.
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row
                            justify-content-end gap-2">

                    <a href="listar.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>
                        Cancelar
                    </a>

                    <button type="submit"
                            class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>
                        Guardar docente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= login_html(login_url('asset/js/docentes.js?v=1')) ?>" defer></script>

<?php include '../includes/footer.php'; ?>
