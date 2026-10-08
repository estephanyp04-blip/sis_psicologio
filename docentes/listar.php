<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/listar.php');

require_once '../config/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$busqueda = trim($_GET['buscar'] ?? '');

$sql = "
    SELECT
        d.id_docente,
        d.id_persona,
        p.nombres,
        p.apellidos,
        p.telefono,
        p.correo,
        GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ') AS materia,
        u.usuario
    FROM docentes d
    INNER JOIN personas p ON p.id_persona=d.id_persona
    LEFT JOIN usuarios u ON u.id_persona=d.id_persona
    LEFT JOIN docente_materias dm ON dm.id_docente=d.id_docente
    LEFT JOIN materias m ON m.id_materia=dm.id_materia
";

$parametros = [];
$tipos = '';

if ($busqueda !== '') {
    $sql .= "
        WHERE p.nombres LIKE ?
        OR p.apellidos LIKE ?
        OR p.telefono LIKE ?
        OR p.correo LIKE ?
        OR EXISTS (
            SELECT 1 FROM docente_materias dm_busqueda
            INNER JOIN materias m_busqueda ON m_busqueda.id_materia=dm_busqueda.id_materia
            WHERE dm_busqueda.id_docente=d.id_docente AND m_busqueda.nombre LIKE ?
        )
        OR u.usuario LIKE ?
    ";

    $termino = '%' . $busqueda . '%';

    $parametros = [
        $termino,
        $termino,
        $termino,
        $termino,
        $termino,
        $termino
    ];

    $tipos = 'ssssss';
}

$sql .= " GROUP BY d.id_docente,p.id_persona,p.nombres,p.apellidos,p.telefono,p.correo,u.usuario ORDER BY p.apellidos ASC,p.nombres ASC";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die('Error al preparar la consulta: ' . $conexion->error);
}

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();
$resultado = $stmt->get_result();

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">
    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
        </span>

        <span class="breadcrumb-item active">
            Docentes
        </span>
    </nav>

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= escapar($_SESSION['tipo_mensaje'] ?? 'success'); ?>
                    alert-dismissible fade show"
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

    <div class="d-flex flex-column flex-md-row
                justify-content-between align-items-md-center
                gap-3 mb-4">

        <div>
            <h1 class="h3 mb-1">Gestión de Docentes</h1>

            <p class="text-muted mb-0">
                Registro y administración de los docentes del sistema.
            </p>
        </div>

        <a href="registrar.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Registrar docente
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="listar.php">
                <div class="row g-3">
                    <div class="col-md-9">
                        <label for="buscar" class="form-label">
                            Buscar docente
                        </label>

                        <input type="search"
                               name="buscar"
                               id="buscar"
                               class="form-control"
                               value="<?= escapar($busqueda); ?>"
                               placeholder="Buscar por nombre, materia, correo o teléfono">
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit"
                                class="btn btn-primary flex-fill">
                            <i class="bi bi-search me-1"></i>
                            Buscar
                        </button>

                        <?php if ($busqueda !== ''): ?>
                            <a href="listar.php"
                               class="btn btn-outline-secondary"
                               title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Materias</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Usuario</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($docente = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <?= escapar(
                                                $docente['nombres'] . ' ' .
                                                $docente['apellidos']
                                            ); ?>
                                        </div>

                                        <small class="text-muted">
                                            Código: DOC-<?= str_pad(
                                                (string) $docente['id_docente'],
                                                4,
                                                '0',
                                                STR_PAD_LEFT
                                            ); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= escapar(
                                            $docente['materia'] ?: 'Sin asignar'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= escapar(
                                            $docente['telefono'] ?: 'Sin registrar'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($docente['correo'])): ?>
                                            <a href="mailto:<?= escapar(
                                                $docente['correo']
                                            ); ?>">
                                                <?= escapar($docente['correo']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin registrar
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($docente['usuario'])): ?>
                                            <span class="badge text-bg-light">
                                                <?= escapar($docente['usuario']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin cuenta
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="ver.php?id=<?= (int)
                                                $docente['id_docente']; ?>"
                                               class="btn btn-sm btn-outline-info"
                                               title="Ver docente">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <a href="editar.php?id=<?= (int)
                                                $docente['id_docente']; ?>"
                                               class="btn btn-sm btn-outline-warning"
                                               title="Editar docente">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"
                                    class="text-center py-5 text-muted">

                                    <i class="bi bi-person-x fs-1 d-block mb-2"></i>

                                    <?php if ($busqueda !== ''): ?>
                                        No se encontraron docentes con esa búsqueda.
                                    <?php else: ?>
                                        Todavía no existen docentes registrados.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="border-top mt-3 pt-3 text-muted">
                Mostrando <?= (int) $resultado->num_rows; ?>
                docente<?= $resultado->num_rows === 1 ? '' : 's'; ?>.
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>
