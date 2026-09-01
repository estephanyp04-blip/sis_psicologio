<?php

require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/proyecto_vercionII';

if (!function_exists('escapar')) {
    function escapar(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* Permisos temporales mientras no existe el login */
$modoDesarrollo = !isset($_SESSION['id_rol']);
$rolActual = isset($_SESSION['id_rol'])
    ? (int) $_SESSION['id_rol']
    : 0;

if (
    !$modoDesarrollo &&
    !in_array($rolActual, [1, 2, 3, 4], true)
) {
    $_SESSION['mensaje'] = 'No tiene permiso para ver derivaciones.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

/* Validar ID */
$idDerivacion = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idDerivacion || $idDerivacion <= 0) {
    $_SESSION['mensaje'] = 'La derivación seleccionada no es válida.';
    $_SESSION['tipo_mensaje'] = 'warning';

    header(
        'Location: ' .
        $baseUrl .
        '/derivaciones/listar.php'
    );
    exit;
}

/* Consultar derivación */
$sql = "SELECT
            d.id_derivacion,
            d.fecha,
            d.id_estudiante,
            d.id_docente AS derivacion_id_docente,
            d.materia,
            d.motivo,
            d.observaciones,
            d.prioridad,
            d.estado,
            e.nombres AS estudiante_nombres,
            e.apellidos AS estudiante_apellidos,
            e.curso,
            e.paralelo,
            doc.nombres AS docente_nombres,
            doc.apellidos AS docente_apellidos
        FROM derivaciones d
        LEFT JOIN estudiantes e
            ON e.id_estudiante = d.id_estudiante
        LEFT JOIN docentes doc
            ON doc.id_docente = d.id_docente
        WHERE d.id_derivacion = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    $_SESSION['mensaje'] = 'Error al consultar la derivación.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header(
        'Location: ' .
        $baseUrl .
        '/derivaciones/listar.php'
    );
    exit;
}

$stmt->bind_param('i', $idDerivacion);
$stmt->execute();

$resultado = $stmt->get_result();
$derivacion = $resultado->fetch_assoc();

$stmt->close();

if (!$derivacion) {
    $_SESSION['mensaje'] = 'La derivación solicitada no existe.';
    $_SESSION['tipo_mensaje'] = 'warning';

    header(
        'Location: ' .
        $baseUrl .
        '/derivaciones/listar.php'
    );
    exit;
}

/* Validar que el docente solo vea sus derivaciones */
$idDocenteSesion = (int) ($_SESSION['id_docente'] ?? 0);

$esPropietario = $idDocenteSesion > 0 &&
    $idDocenteSesion ===
    (int) $derivacion['derivacion_id_docente'];

if (
    !$modoDesarrollo &&
    $rolActual === 3 &&
    !$esPropietario
) {
    $_SESSION['mensaje'] = 'No tiene permiso para ver esta derivación.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header(
        'Location: ' .
        $baseUrl .
        '/derivaciones/listar.php'
    );
    exit;
}

/* Administrador o docente propietario pueden editar */
$puedeEditar =
    $derivacion['estado'] === 'Pendiente' &&
    (
        $modoDesarrollo ||
        $rolActual === 1 ||
        ($rolActual === 3 && $esPropietario)
    );

/* Preparar información */
$nombreEstudiante = trim(
    ($derivacion['estudiante_apellidos'] ?? '') .
    ' ' .
    ($derivacion['estudiante_nombres'] ?? '')
);

if ($nombreEstudiante === '') {
    $nombreEstudiante = 'Estudiante no disponible';
}

$nombreDocente = trim(
    ($derivacion['docente_apellidos'] ?? '') .
    ' ' .
    ($derivacion['docente_nombres'] ?? '')
);

if ($nombreDocente === '') {
    $nombreDocente = 'sin docente asociado';
}

$curso = trim((string) ($derivacion['curso'] ?? ''));

if ($curso === '') {
    $curso = 'No registrado';
}

$paralelo = trim((string) ($derivacion['paralelo'] ?? ''));

if ($paralelo === '') {
    $paralelo = 'No registrado';
}

$materia = trim((string) ($derivacion['materia'] ?? ''));

if ($materia === '') {
    $materia = 'No registrada';
}

$fechaMostrar = 'No registrada';

if (!empty($derivacion['fecha'])) {
    $marcaTiempo = strtotime($derivacion['fecha']);

    if ($marcaTiempo !== false) {
        $fechaMostrar = date('d/m/Y', $marcaTiempo);
    }
}

$clasePrioridad = match ($derivacion['prioridad'] ?? '') {
    'Alta' => 'bg-danger',
    'Media' => 'bg-warning text-dark',
    'Baja' => 'bg-success',
    default => 'bg-secondary'
};

$claseEstado = match ($derivacion['estado'] ?? '') {
    'Pendiente' => 'bg-warning text-dark',
    'Atendido' => 'bg-success',
    'En atención' => 'bg-primary',
    'Finalizado' => 'bg-success',
    'Rechazado' => 'bg-danger',
    default => 'bg-secondary'
};

/* El diseño se carga después de todas las redirecciones */
$tituloPagina = 'Detalle de derivación';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

?>

<<main class="main-content">
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="d-print-none mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= $baseUrl ?>/index.php">Inicio</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= $baseUrl ?>/derivaciones/listar.php">
                        Derivaciones
                    </a>
                </li>
                <li class="breadcrumb-item active">
                    Ver
                </li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="mb-1">Detalle de la derivación</h2>
                <p class="text-muted mb-0">
                    Información registrada del estudiante.
                </p>
            </div>

            <div class="d-flex gap-2 d-print-none">
                <a
                    href="<?= $baseUrl ?>/derivaciones/listar.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Volver
                </a>

                <?php if ($puedeEditar): ?>
                    <a
                        href="<?= $baseUrl ?>/derivaciones/editar.php?id=<?= $idDerivacion ?>"
                        class="btn btn-warning"
                    >
                        <i class="bi bi-pencil me-1"></i>
                        Editar
                    </a>
                <?php endif; ?>

                <button
                    type="button"
                    class="btn btn-outline-primary"
                    onclick="window.print()"
                >
                    <i class="bi bi-printer me-1"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <section class="card shadow-sm">
            <div class="card-body p-4">

                <!-- INFORMACIÓN GENERAL -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Información general
                    </h5>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                N.º de derivación
                            </small>
                            <strong>#<?= (int) $idDerivacion ?></strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Fecha
                            </small>
                            <strong>
                                <?= escapar($fechaMostrar) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Prioridad
                            </small>
                            <span class="badge <?= $clasePrioridad ?>">
                                <?= escapar(
                                    $derivacion['prioridad'] ??
                                    'Sin prioridad'
                                ) ?>
                            </span>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Estado
                            </small>
                            <span class="badge <?= $claseEstado ?>">
                                <?= escapar(
                                    $derivacion['estado'] ??
                                    'Sin estado'
                                ) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ESTUDIANTE -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-person me-2"></i>
                        Estudiante
                    </h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Nombre completo
                            </small>
                            <strong>
                                <?= escapar($nombreEstudiante) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Curso
                            </small>
                            <strong>
                                <?= escapar($curso) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Paralelo
                            </small>
                            <strong>
                                <?= escapar($paralelo) ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- DATOS DE LA DERIVACIÓN -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-person-workspace me-2"></i>
                        Datos de la derivación
                    </h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Docente que realizó la derivación
                            </small>
                            <strong>
                                <?= escapar($nombreDocente) ?>
                            </strong>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Materia
                            </small>
                            <strong>
                                <?= escapar($materia) ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- MOTIVO -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        Motivo de la derivación
                    </h5>

                    <?php if (
                        trim(
                            (string) ($derivacion['motivo'] ?? '')
                        ) !== ''
                    ): ?>

                        <p class="mb-0">
                            <?= nl2br(
                                escapar($derivacion['motivo'])
                            ) ?>
                        </p>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No se registró un motivo.
                        </p>

                    <?php endif; ?>
                </div>

                <!-- CATEGORÍAS Y OBSERVACIONES -->
                <div>
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-card-text me-2"></i>
                        Categorías y observaciones
                    </h5>

                    <?php if (
                        trim(
                            (string) ($derivacion['observaciones'] ?? '')
                        ) !== ''
                    ): ?>

                        <div style="white-space: pre-line;">
                            <?= escapar(
                                $derivacion['observaciones']
                            ) ?>
                        </div>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No se registraron observaciones.
                        </p>

                    <?php endif; ?>
                </div>

            </div>
        </section>

    </div>
</main>
<?php include '../includes/footer.php'; ?>