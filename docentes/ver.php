<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$idDocente = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idDocente || $idDocente <= 0) {
    $_SESSION['mensaje'] = 'El docente solicitado no es válido.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

$sql = "
    SELECT
        d.id_docente,
        d.id_usuario,
        d.nombres,
        d.apellidos,
        d.telefono,
        d.correo,
        d.materia,
        u.usuario,
        u.estado AS estado_usuario,
        r.nombre AS nombre_rol,
        COUNT(de.id_derivacion) AS total_derivaciones
    FROM docentes d
    LEFT JOIN usuarios u
        ON u.id_usuario = d.id_usuario
    LEFT JOIN roles r
        ON r.id_rol = u.id_rol
    LEFT JOIN derivaciones de
        ON de.id_docente = d.id_docente
    WHERE d.id_docente = ?
    GROUP BY
        d.id_docente,
        d.id_usuario,
        d.nombres,
        d.apellidos,
        d.telefono,
        d.correo,
        d.materia,
        u.usuario,
        u.estado,
        r.nombre
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die('Error al preparar la consulta: ' . $conexion->error);
}

$stmt->bind_param('i', $idDocente);
$stmt->execute();

$resultado = $stmt->get_result();
$docente = $resultado->fetch_assoc();

if (!$docente) {
    $stmt->close();

    $_SESSION['mensaje'] = 'No se encontró el docente solicitado.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="main-content">
    <nav class="breadcrumb mb-4">
        <span class="breadcrumb-item">
            <a href="../index.php">Inicio</a>
        </span>

        <span class="breadcrumb-item">
            <a href="listar.php">Docentes</a>
        </span>

        <span class="breadcrumb-item active">
            Ver docente
        </span>
    </nav>

    <div class="d-flex flex-column flex-md-row
                justify-content-between align-items-md-center
                gap-3 mb-4">

        <div>
            <h1 class="h3 mb-1">Información del docente</h1>

            <p class="text-muted mb-0">
                Datos personales, académicos y cuenta del sistema.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="listar.php"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>
                Volver
            </a>

            <a href="editar.php?id=<?= (int) $docente['id_docente']; ?>"
               class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i>
                Editar
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row
                        align-items-md-center gap-3">

                <div class="bg-primary text-white rounded-circle
                            d-flex align-items-center justify-content-center"
                     style="width: 72px; height: 72px; flex-shrink: 0;">

                    <i class="bi bi-person fs-2"></i>
                </div>

                <div>
                    <h2 class="h4 mb-1">
                        <?= escapar(
                            $docente['nombres'] . ' ' .
                            $docente['apellidos']
                        ); ?>
                    </h2>

                    <div class="text-muted">
                        Código:
                        DOC-<?= str_pad(
                            (string) $docente['id_docente'],
                            4,
                            '0',
                            STR_PAD_LEFT
                        ); ?>
                    </div>

                    <div class="mt-2">
                        <span class="badge text-bg-primary">
                            <?= escapar(
                                $docente['materia'] ?: 'Sin materia'
                            ); ?>
                        </span>

                        <?php if (
                            $docente['estado_usuario'] === 'Activo'
                        ): ?>
                            <span class="badge text-bg-success">
                                Cuenta activa
                            </span>
                        <?php elseif (
                            $docente['estado_usuario'] === 'Inactivo'
                        ): ?>
                            <span class="badge text-bg-secondary">
                                Cuenta inactiva
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">
                                Sin cuenta
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-person-lines-fill
                                  text-primary me-2"></i>
                        Datos del docente
                    </h5>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Nombres
                            </small>

                            <div class="fw-semibold">
                                <?= escapar($docente['nombres']); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Apellidos
                            </small>

                            <div class="fw-semibold">
                                <?= escapar($docente['apellidos']); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Teléfono
                            </small>

                            <div class="fw-semibold">
                                <?= escapar(
                                    $docente['telefono']
                                        ?: 'Sin registrar'
                                ); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Correo electrónico
                            </small>

                            <div class="fw-semibold">
                                <?php if (!empty($docente['correo'])): ?>
                                    <a href="mailto:<?= escapar(
                                        $docente['correo']
                                    ); ?>">
                                        <?= escapar($docente['correo']); ?>
                                    </a>
                                <?php else: ?>
                                    Sin registrar
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <small class="text-muted d-block">
                                Materia que imparte
                            </small>

                            <div class="fw-semibold">
                                <?= escapar(
                                    $docente['materia']
                                        ?: 'Sin asignar'
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge
                                  text-primary me-2"></i>
                        Cuenta del sistema
                    </h5>
                </div>

                <div class="card-body p-4">
                    <div class="mb-3">
                        <small class="text-muted d-block">
                            Nombre de usuario
                        </small>

                        <div class="fw-semibold">
                            <?= escapar(
                                $docente['usuario']
                                    ?: 'Sin cuenta asignada'
                            ); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">
                            Rol
                        </small>

                        <div class="fw-semibold">
                            <?= escapar(
                                $docente['nombre_rol']
                                    ?: 'Sin rol'
                            ); ?>
                        </div>
                    </div>

                    <div>
                        <small class="text-muted d-block">
                            Estado de la cuenta
                        </small>

                        <div class="fw-semibold">
                            <?= escapar(
                                $docente['estado_usuario']
                                    ?: 'Sin cuenta'
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-file-earmark-text
                              text-primary fs-1"></i>

                    <div class="display-6 fw-bold mt-2">
                        <?= (int) $docente['total_derivaciones']; ?>
                    </div>

                    <div class="text-muted">
                        Derivaciones registradas
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
include '../includes/footer.php';
?>