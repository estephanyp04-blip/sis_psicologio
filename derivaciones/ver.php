<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/ver.php');


require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$baseUrl = rtrim(login_config()['base_url'], '/');

if (!function_exists('escapar')) {
}

/* El acceso se autoriza al inicio; las cuentas ligadas a docentes ven sus propios registros. */

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

/* Consultar únicamente las derivaciones del docente cuando corresponde. */
$idDocenteSesion = (int) ($_SESSION['cod_docente'] ?? 0);
$sql = "SELECT
            d.cod_derivacion,
            d.fecha,
            d.cod_estudiante,
            d.cod_docente AS derivacion_id_docente,
            m.nombre AS materia,
            d.motivo,
            d.observaciones,
            d.prioridad,
            d.estado,
            d.solicitar_cita,
            d.cod_psicologa_solicitada,
            psicologa_usuario.nombres AS psicologa_solicitada_nombres,
            psicologa_usuario.apellidos AS psicologa_solicitada_apellidos,
            e.nombres AS estudiante_nombres,
            e.apellidos AS estudiante_apellidos,
            e.curso,
            e.paralelo,
            COALESCE(d.autor_nombres,persona.nombres) AS docente_nombres,
            COALESCE(d.autor_apellidos,persona.apellidos) AS docente_apellidos
        FROM derivaciones d
        LEFT JOIN vista_estudiantes e
            ON e.cod_estudiante = d.cod_estudiante
        LEFT JOIN materias m
            ON m.cod_materia = d.cod_materia
        LEFT JOIN docentes doc
            ON doc.cod_docente = d.cod_docente
        LEFT JOIN usuarios persona
            ON persona.cod_usuario = COALESCE(d.cod_usuario_autor,doc.cod_usuario)
        LEFT JOIN usuarios psicologa_usuario
            ON psicologa_usuario.cod_usuario = d.cod_psicologa_solicitada
        WHERE d.cod_derivacion = ?
          AND (? <= 0 OR (d.cod_docente = ? AND (d.cod_usuario_autor IS NULL OR d.cod_usuario_autor=(SELECT cod_usuario FROM docentes WHERE cod_docente=d.cod_docente))))
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

$stmt->bind_param('iii', $idDerivacion, $idDocenteSesion, $idDocenteSesion);
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

$stmt = $conexion->prepare("SELECT cod_cita,fecha,hora,estado FROM citas
    WHERE cod_derivacion=? AND estado<>'Cancelada'
    ORDER BY fecha DESC,hora DESC,cod_cita DESC LIMIT 1");
$stmt->bind_param('i', $idDerivacion);
$stmt->execute();
$citaVinculada = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$solicitudCitaActiva = (int) ($derivacion['solicitar_cita'] ?? 0) === 1;
$nombrePsicologaSolicitada = trim(
    ($derivacion['psicologa_solicitada_nombres'] ?? '') . ' ' .
    ($derivacion['psicologa_solicitada_apellidos'] ?? '')
);
$observacionesTexto = (string) ($derivacion['observaciones'] ?? '');
$observacionesSeparadas = derivacion_desglosar($observacionesTexto);
$categoriasObservadas = derivacion_categorias_registradas($conexion, $idDerivacion, $observacionesTexto);
if ($nombrePsicologaSolicitada === '') {
    foreach ($observacionesSeparadas['metadatos'] as $metadato) {
        if (str_starts_with($metadato, 'Profesional solicitado:')) {
            $nombrePsicologaSolicitada = trim(substr($metadato, strlen('Profesional solicitado:')));
            break;
        }
    }
}

$esPropietario = $idDocenteSesion > 0 &&
    $idDocenteSesion ===
    (int) $derivacion['derivacion_id_docente'];

/* Un permiso de edición autoriza la acción; un perfil ligado a docente conserva alcance propio. */
$puedeEditar = $derivacion['estado'] === 'Pendiente'
    && login_puede('derivaciones/editar.php')
    && ($idDocenteSesion === 0 || $esPropietario);

$stmt = $conexion->prepare('SELECT cod_evidencia,nombre_original,descripcion FROM derivacion_evidencias
    WHERE cod_derivacion=? ORDER BY cod_evidencia DESC LIMIT 1');
$stmt->bind_param('i', $idDerivacion);
$stmt->execute();
$evidencia = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

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
    'En seguimiento' => 'bg-primary',
    'Atendido' => 'bg-success',
    default => 'bg-secondary'
};
$etiquetaEstado = match ($derivacion['estado'] ?? '') {
    'Pendiente' => 'Pendiente de revision',
    'En seguimiento' => 'En seguimiento',
    'Atendido' => 'Atendido',
    default => (string)($derivacion['estado'] ?? 'Sin estado')
};

/* El diseño se carga después de todas las redirecciones */
$tituloPagina = 'Detalle de derivación';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

?>

<main class="main-content">
    <div class="container-fluid">
        <?php if (!empty($_SESSION['mensaje'])): ?>
            <div class="alert alert-info"><?= login_html($_SESSION['mensaje']) ?></div>
            <?php unset($_SESSION['mensaje'],$_SESSION['tipo_mensaje']); ?>
        <?php endif; ?>
        <?php if (login_puede('derivaciones/cambiar_estado.php')): ?>
        <?php require_once __DIR__ . '/../includes/trazabilidad_vista.php'; flujo_panel($conexion,'derivacion',$derivacion); ?>
        <div class="card p-3 my-3 d-print-none">
            <div class="d-flex gap-2 mb-3">
                <?php if (login_puede('citas/registrar.php') && !$citaVinculada): ?>
                <a class="btn btn-primary" href="../citas/registrar.php?cod_derivacion=<?= (int)$idDerivacion ?>"><?= $solicitudCitaActiva ? 'Atender solicitud de cita' : 'Programar cita' ?></a>
                <?php elseif ($citaVinculada && login_puede('citas/editar.php')): ?>
                <a class="btn btn-primary" href="../citas/editar.php?id=<?= (int)$citaVinculada['cod_cita'] ?>">Ver cita programada</a>
                <?php endif; ?>
                <?php if (login_puede('historias_clinicas/registrar.php')): ?>
                <a class="btn btn-outline-primary" href="../historias_clinicas/registrar.php?cod_derivacion=<?= (int)$idDerivacion ?>">Crear nueva historia clínica</a>
                <?php endif; ?>
            </div>
            <form action="cambiar_estado.php" method="POST">
                <?= login_campo_csrf() ?><input type="hidden" name="cod_derivacion" value="<?= (int)$idDerivacion ?>">
                <input type="hidden" name="estado" value="<?= $derivacion['estado']==='En seguimiento' ? 'Atendido' : 'En seguimiento' ?>">
                <p>Para cerrar la atención se requiere un seguimiento registrado de esta derivación. Emitir un informe no cambia su estado.</p>
                <button class="btn btn-outline-success"><?= $derivacion['estado']==='En seguimiento' ? 'Cerrar atención: Atendido' : ($derivacion['estado']==='Atendido' ? 'Reabrir seguimiento' : 'Iniciar seguimiento') ?></button>
            </form>
        </div>
        <?php endif; ?>

        <nav aria-label="breadcrumb" class="d-print-none mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
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
                                <?= login_html($fechaMostrar) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Prioridad
                            </small>
                            <span class="badge <?= $clasePrioridad ?>">
                                <?= login_html(
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
                                <?= login_html(
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
                                <?= login_html($nombreEstudiante) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Curso
                            </small>
                            <strong>
                                <?= login_html($curso) ?>
                            </strong>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">
                                Paralelo
                            </small>
                            <strong>
                                <?= login_html($paralelo) ?>
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
                                <?= login_html($nombreDocente) ?>
                            </strong>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Materia
                            </small>
                            <strong>
                                <?= login_html($materia) ?>
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
                                login_html($derivacion['motivo'])
                            ) ?>
                        </p>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No se registró un motivo.
                        </p>

                    <?php endif; ?>

                    <?php if ($evidencia): ?>
                        <div class="mt-4">
                            <h6>Imagen adjunta</h6>
                            <a href="<?= $baseUrl ?>/derivaciones/evidencia.php?id=<?= (int)$evidencia['cod_evidencia'] ?>"
                               target="_blank" rel="noopener">
                                <img src="<?= $baseUrl ?>/derivaciones/evidencia.php?id=<?= (int)$evidencia['cod_evidencia'] ?>"
                                     class="img-fluid rounded border" style="max-height: 420px"
                                     alt="<?= login_html($evidencia['descripcion'] ?: 'Imagen de referencia') ?>">
                            </a>
                            <?php if (trim((string)($evidencia['descripcion'] ?? '')) !== ''): ?>
                                <p class="mt-2 mb-0"><strong>Qué se desea destacar:</strong><br><?= nl2br(login_html($evidencia['descripcion'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CATEGORÍAS OBSERVADAS -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-tags me-2"></i>
                        Categorías observadas
                    </h5>

                    <?php if ($categoriasObservadas): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($categoriasObservadas as $categoria): ?>
                                <span class="badge bg-light text-dark border"><?= login_html($categoria) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            No se registraron categorías.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- OBSERVACIONES ADICIONALES -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-card-text me-2"></i>
                        Observaciones adicionales
                    </h5>

                    <?php if ($observacionesSeparadas['adicionales'] !== ''): ?>
                        <p class="mb-0" style="white-space: pre-line;"><?= login_html($observacionesSeparadas['adicionales']) ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">No se registraron observaciones adicionales.</p>
                    <?php endif; ?>
                </div>

                <!-- SOLICITUD DE ATENCIÓN -->
                <div>
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-calendar-check me-2"></i>
                        Solicitud de atención psicológica
                    </h5>

                    <?php if ($solicitudCitaActiva || $citaVinculada): ?>
                        <div class="alert <?= $citaVinculada ? 'alert-success' : 'alert-info' ?> mb-0">
                            <?php if ($citaVinculada): ?>
                                <strong>Cita programada:</strong>
                                <?= login_html(date('d/m/Y', strtotime($citaVinculada['fecha'])) . ' a las ' . substr($citaVinculada['hora'], 0, 5)) ?>
                                (<?= login_html($citaVinculada['estado']) ?>).
                            <?php else: ?>
                                <strong>Solicitud de cita pendiente de programación.</strong>
                            <?php endif; ?>
                            <?php if ($solicitudCitaActiva): ?>
                                <span class="d-block mt-1">Psicóloga solicitada: <?= login_html($nombrePsicologaSolicitada !== '' ? $nombrePsicologaSolicitada : 'Sin asignar') ?>.</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No se solicitó una cita psicológica.</p>
                    <?php endif; ?>

                </div>

            </div>
        </section>

    </div>
</main>
<?php include '../includes/footer.php'; ?>
