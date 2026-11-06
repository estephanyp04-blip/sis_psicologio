<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/listar.php');

require_once __DIR__ . '/../config/conexion.php';


$busqueda = trim($_GET['buscar'] ?? '');

$sql = "
    SELECT
        d.cod_docente,
        d.estado AS estado_perfil,
        u.estado AS estado_cuenta,
        u.nombres,
        u.apellidos,
        u.telefono,
        u.correo,
        GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ') AS materia,
        u.usuario
    FROM docentes d
    INNER JOIN usuarios u ON u.cod_usuario=d.cod_usuario
    LEFT JOIN docente_materias dm ON dm.cod_docente=d.cod_docente
    LEFT JOIN materias m ON m.cod_materia=dm.cod_materia
";

$parametros = [];
$tipos = '';

if ($busqueda !== '') {
    $sql .= "
        WHERE u.nombres LIKE ?
        OR u.apellidos LIKE ?
        OR u.telefono LIKE ?
        OR u.correo LIKE ?
        OR EXISTS (
            SELECT 1 FROM docente_materias dm_busqueda
            INNER JOIN materias m_busqueda ON m_busqueda.cod_materia=dm_busqueda.cod_materia
            WHERE dm_busqueda.cod_docente=d.cod_docente AND m_busqueda.nombre LIKE ?
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

$sql .= " GROUP BY d.cod_docente,d.estado,u.estado,u.nombres,u.apellidos,u.telefono,u.correo,u.usuario ORDER BY u.apellidos ASC,u.nombres ASC";

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
        <div class="alert alert-<?= login_html($_SESSION['tipo_mensaje'] ?? 'success'); ?>
                    alert-dismissible fade show"
             role="alert">

            <?= login_html($_SESSION['mensaje']); ?>

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

        <?php if (login_puede('docentes/registrar.php')): ?><a href="registrar.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Registrar docente
        </a><?php endif; ?>
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
                               value="<?= login_html($busqueda); ?>"
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
                                            <?= login_html(
                                                $docente['nombres'] . ' ' .
                                                $docente['apellidos']
                                            ); ?>
                                        </div>

                                        <p class="mb-1">Perfil: <?= login_html($docente['estado_perfil']) ?> · Cuenta: <?= login_html($docente['estado_cuenta']) ?></p>
                                        <small class="text-muted">
                                            Código: DOC-<?= str_pad(
                                                (string) $docente['cod_docente'],
                                                4,
                                                '0',
                                                STR_PAD_LEFT
                                            ); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= login_html(
                                            $docente['materia'] ?: 'Sin asignar'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= login_html(
                                            $docente['telefono'] ?: 'Sin registrar'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($docente['correo'])): ?>
                                            <a href="mailto:<?= login_html(
                                                $docente['correo']
                                            ); ?>">
                                                <?= login_html($docente['correo']); ?>
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
                                                <?= login_html($docente['usuario']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin cuenta
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <?php if (login_puede('docentes/cambiar_estado.php')): ?>
                                            <form action="cambiar_estado.php" method="post">
                                                <?= login_campo_csrf() ?><input type="hidden" name="cod_docente" value="<?= (int)$docente['cod_docente'] ?>">
                                                <input type="hidden" name="estado" value="<?= $docente['estado_perfil']==='Activo' ? 'Inactivo' : 'Activo' ?>">
                                                <button class="btn btn-sm btn-outline-secondary"><?= $docente['estado_perfil']==='Activo' ? 'Dar de baja' : 'Reactivar' ?></button>
                                            </form><?php endif; ?>
                                            <a href="ver.php?id=<?= (int)
                                                $docente['cod_docente']; ?>"
                                               class="btn btn-sm btn-outline-info"
                                               title="Ver docente">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <?php if (login_puede('docentes/editar.php')): ?><a href="editar.php?id=<?= (int)
                                                $docente['cod_docente']; ?>"
                                               class="btn btn-sm btn-outline-warning"
                                               title="Editar docente">
                                                <i class="bi bi-pencil"></i>
                                            </a><?php endif; ?>

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
