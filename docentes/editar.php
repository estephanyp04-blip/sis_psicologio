<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/editar.php');

require_once '../config/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$idDocente = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idDocente || $idDocente <= 0) {
    $_SESSION['mensaje'] = 'El docente seleccionado no es válido.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

/* OBTENER DOCENTE */

$sqlDocente = "
    SELECT
        d.id_docente,u.id_usuario,d.id_persona,p.nombres,p.apellidos,p.telefono,p.correo,
        GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ') AS materia
    FROM docentes d
    INNER JOIN personas p ON p.id_persona=d.id_persona
    LEFT JOIN usuarios u ON u.id_persona=d.id_persona
    LEFT JOIN docente_materias dm ON dm.id_docente=d.id_docente
    LEFT JOIN materias m ON m.id_materia=dm.id_materia
    WHERE d.id_docente=?
    GROUP BY d.id_docente,u.id_usuario,d.id_persona,p.nombres,p.apellidos,p.telefono,p.correo
";

$stmtDocente = $conexion->prepare($sqlDocente);

if (!$stmtDocente) {
    die('Error al preparar la consulta: ' . $conexion->error);
}

$stmtDocente->bind_param('i', $idDocente);
$stmtDocente->execute();

$resultadoDocente = $stmtDocente->get_result();
$docente = $resultadoDocente->fetch_assoc();

$stmtDocente->close();

if (!$docente) {
    $_SESSION['mensaje'] = 'No se encontró el docente solicitado.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

/* USUARIOS DISPONIBLES */

$sqlUsuarios = "
    SELECT
        u.id_usuario,
        p.nombres AS nombre,
        p.apellidos AS apellido,
        u.usuario,
        p.correo
    FROM usuarios u
    INNER JOIN personas p ON p.id_persona=u.id_persona
    LEFT JOIN docentes d ON d.id_persona=u.id_persona
    WHERE u.id_rol = 3
      AND (
          u.id_usuario = ?
          OR (
              u.estado = 'Activo'
              AND d.id_docente IS NULL
          )
      )
    ORDER BY p.apellidos,p.nombres
";

$stmtUsuarios = $conexion->prepare($sqlUsuarios);

if (!$stmtUsuarios) {
    die('Error al consultar usuarios: ' . $conexion->error);
}

$stmtUsuarios->bind_param('i', $docente['id_usuario']);
$stmtUsuarios->execute();

$resultadoUsuarios = $stmtUsuarios->get_result();

$materiasDocente = array_map(
    'trim',
    explode(',', (string) $docente['materia'])
);

$materiasDisponibles = array_column(
    $conexion->query("SELECT nombre FROM materias WHERE estado='Activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC),
    'nombre'
);

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
            Editar
        </span>
    </nav>

    <div class="mb-4">
        <h1 class="h3 mb-1">Editar docente</h1>

        <p class="text-muted mb-0">
            Modifique los datos personales y académicos del docente.
        </p>
    </div>

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= escapar(
            $_SESSION['tipo_mensaje'] ?? 'danger'
        ); ?> alert-dismissible fade show">

            <?= escapar($_SESSION['mensaje']); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>
        </div>

        <?php
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
        ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="actualizar.php"
                  method="POST"
                  id="formDocente"
                  autocomplete="off">
                <?= login_campo_csrf() ?>

                <input type="hidden"
                       name="id_docente"
                       value="<?= (int) $docente['id_docente']; ?>">

                <div class="mb-4">
                    <h5 class="border-bottom pb-3">
                        <i class="bi bi-person-badge
                                  text-primary me-2"></i>
                        1. Cuenta del docente
                    </h5>

                    <div class="mt-3">
                        <label for="id_usuario" class="form-label">
                            Cuenta de usuario
                            <span class="text-danger">*</span>
                        </label>

                        <select name="id_usuario"
                                id="id_usuario"
                                class="form-select"
                                required>

                            <option value="">
                                Seleccionar cuenta...
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
                                    <?= (int) $usuario['id_usuario'] ===
                                        (int) $docente['id_usuario']
                                            ? 'selected'
                                            : ''; ?>>

                                    <?= escapar(
                                        $usuario['apellido'] . ' ' .
                                        $usuario['nombre'] . ' (' .
                                        $usuario['usuario'] . ')'
                                    ); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="border-bottom pb-3">
                        <i class="bi bi-person-lines-fill
                                  text-primary me-2"></i>
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
                                   value="<?= escapar(
                                       $docente['nombres']
                                   ); ?>"
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
                                   value="<?= escapar(
                                       $docente['apellidos']
                                   ); ?>"
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
                                   pattern="[0-9+\-\s]{7,20}"
                                   value="<?= escapar(
                                       $docente['telefono']
                                   ); ?>">
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
                                   value="<?= escapar(
                                       $docente['correo']
                                   ); ?>">
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
                            <?php foreach (
                                $materiasDisponibles as $indice => $materia
                            ): ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check
                                                border rounded p-3 h-100">

                                        <input
                                            type="checkbox"
                                            name="materias[]"
                                            value="<?= escapar($materia); ?>"
                                            id="materia<?= $indice; ?>"
                                            class="form-check-input materia-check"
                                            <?= in_array(
                                                $materia,
                                                $materiasDocente,
                                                true
                                            ) ? 'checked' : ''; ?>>

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

                    <a href="ver.php?id=<?= (int)
                        $docente['id_docente']; ?>"
                       class="btn btn-outline-secondary">

                        <i class="bi bi-x-circle me-1"></i>
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('formDocente');
    const materias = document.querySelectorAll('.materia-check');
    const errorMaterias = document.getElementById('errorMaterias');

    formulario.addEventListener('submit', function (evento) {
        const seleccionada = Array.from(materias).some(
            materia => materia.checked
        );

        if (!seleccionada) {
            evento.preventDefault();
            errorMaterias.classList.remove('d-none');
            materias[0].focus();
            return;
        }

        errorMaterias.classList.add('d-none');
    });

    materias.forEach(function (materia) {
        materia.addEventListener('change', function () {
            const seleccionada = Array.from(materias).some(
                elemento => elemento.checked
            );

            if (seleccionada) {
                errorMaterias.classList.add('d-none');
            }
        });
    });
});
</script>

<?php
$stmtUsuarios->close();
include '../includes/footer.php';
?>