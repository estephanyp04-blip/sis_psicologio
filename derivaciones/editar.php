<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/proyecto_vercionII';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$modoDesarrollo = !isset($_SESSION['id_rol']);
$rolActual = isset($_SESSION['id_rol']) ? (int) $_SESSION['id_rol'] : 0;
$puedeEditar = $modoDesarrollo || in_array($rolActual, [1,2,3], true);

if (!$puedeEditar) {
    $_SESSION['mensaje'] = 'No tiene permiso para editar derivaciones.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: ' . $baseUrl . '/derivaciones/listar.php');
    exit;
}

$idDerivacion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idDerivacion || $idDerivacion <= 0) {
    $_SESSION['mensaje'] = 'El identificador de la derivación no es válido.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: ' . $baseUrl . '/derivaciones/listar.php');
    exit;
}

$sql = "SELECT
            d.id_derivacion,
            d.fecha,
            d.id_estudiante,
            d.id_docente,
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
        FROM derivaciones AS d
        LEFT JOIN estudiantes AS e
            ON d.id_estudiante = e.id_estudiante
        LEFT JOIN docentes AS doc
            ON d.id_docente = doc.id_docente
        WHERE d.id_derivacion = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die('Error al preparar la consulta: ' . escapar($conexion->error));
}

$stmt->bind_param('i', $idDerivacion);
$stmt->execute();

$resultado = $stmt->get_result();
$derivacion = $resultado->fetch_assoc();

$stmt->close();

if (!$derivacion) {
    $_SESSION['mensaje'] = 'La derivación solicitada no existe.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: ' . $baseUrl . '/derivaciones/listar.php');
    exit;
}

if ($derivacion['estado'] !== 'Pendiente') {
    $_SESSION['mensaje'] = 'Solamente se pueden editar derivaciones pendientes.';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: ' . $baseUrl . '/derivaciones/listar.php');
    exit;
}

$nombreEstudiante = trim(
    ($derivacion['estudiante_apellidos'] ?? '') . ' ' .
    ($derivacion['estudiante_nombres'] ?? '')
);

$cursoEstudiante = trim(
    ($derivacion['curso'] ?? '') . ' ' .
    ($derivacion['paralelo'] ?? '')
);

$nombreDocente = trim(
    ($derivacion['docente_apellidos'] ?? '') . ' ' .
    ($derivacion['docente_nombres'] ?? '')
);

if ($nombreDocente === '') {
    $nombreDocente = 'Sin docente asociado';
}

if ($cursoEstudiante === '') {
    $cursoEstudiante = 'Sin curso';
}

if ($nombreDocente === '') {
    $nombreDocente = 'Docente no disponible';
}

$tituloPagina = 'Editar derivación';
$textoObservaciones = trim((string) ($derivacion['observaciones'] ?? ''));

$categoriasSeleccionadas = [];
$observacionAdicional = '';
$solicitudCita = '';
$profesionalSolicitado = '';

$bloquesObservaciones = preg_split(
    '/\R{2,}/',
    $textoObservaciones
);

foreach ($bloquesObservaciones as $bloque) {
    $bloque = trim($bloque);

    if (str_starts_with($bloque, 'Categorías observadas:')) {
        $texto = trim(
            substr(
                $bloque,
                strlen('Categorías observadas:')
            )
        );

        if ($texto !== '') {
            $categoriasSeleccionadas = array_map(
                'trim',
                explode(',', $texto)
            );
        }
    }

    if (str_starts_with($bloque, 'Observaciones adicionales:')) {
        $observacionAdicional = trim(
            substr(
                $bloque,
                strlen('Observaciones adicionales:')
            )
        );
    }

    if (str_starts_with($bloque, 'Solicitud de cita psicológica:')) {
        $solicitudCita = trim($bloque);
    }

    if (str_starts_with($bloque, 'Profesional solicitado:')) {
        $profesionalSolicitado = trim($bloque);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?= $baseUrl; ?>/index.php">Inicio</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= $baseUrl; ?>/derivaciones/listar.php">
                        Derivaciones
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    Editar
                </li>
            </ol>
        </nav>

        <section class="encabezado-modulo mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-1">Editar derivación</h2>
                    <p class="mb-0">
                        Actualice la información antes de que sea atendida.
                    </p>
                </div>

                <a href="<?= $baseUrl; ?>/derivaciones/listar.php"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Volver
                </a>
            </div>
        </section>

        <section class="card shadow-sm">
            <div class="card-body p-4">
                <form action="<?= $baseUrl; ?>/derivaciones/actualizar.php"
                      method="POST">

                    <input type="hidden"
                           name="id_derivacion"
                           value="<?= (int) $idDerivacion; ?>">

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Estudiante</label>
                            <input type="text"
                                   class="form-control"
                                   value="<?= escapar(
                                       $nombreEstudiante . ' - ' .
                                       $cursoEstudiante
                                   ); ?>"
                                   readonly>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Docente</label>
                            <input type="text"
                                   class="form-control"
                                   value="<?= escapar($nombreDocente); ?>"
                                   readonly>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="fecha" class="form-label">
                                Fecha de reporte
                                <span class="text-danger">*</span>
                            </label>

                            <input type="date"
                                   id="fecha"
                                   name="fecha"
                                   class="form-control"
                                   value="<?= escapar($derivacion['fecha']); ?>"
                                   max="<?= date('Y-m-d'); ?>"
                                   required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="prioridad" class="form-label">
                                Prioridad
                                <span class="text-danger">*</span>
                            </label>

                            <select id="prioridad"
                                    name="prioridad"
                                    class="form-select"
                                    required>

                                <?php foreach (['Alta', 'Media', 'Baja'] as $prioridad): ?>
                                    <option value="<?= escapar($prioridad); ?>"
                                        <?= $derivacion['prioridad'] === $prioridad
                                            ? 'selected'
                                            : ''; ?>>
                                        <?= escapar($prioridad); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="materia" class="form-label">
                                Materia
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   id="materia"
                                   name="materia"
                                   class="form-control"
                                   maxlength="100"
                                   value="<?= escapar($derivacion['materia']); ?>"
                                   required>
                        </div>

                        <div class="col-12">
                            <label for="motivo" class="form-label">
                                Descripción de la situación
                                <span class="text-danger">*</span>
                            </label>

                            <textarea id="motivo"
                                      name="motivo"
                                      class="form-control"
                                      rows="4"
                                      maxlength="2000"
                                      required><?= escapar(
                                          $derivacion['motivo']
                                      ); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label d-block">
                                Categorías observadas
                                <span class="text-danger">*</span>
                            </label>

                            <div class="row g-3">

                                <?php
                                $categoriasEditar = [
                                    [
                                        'valor' => 'Rendimiento Académico',
                                        'titulo' => 'Rendimiento Académico',
                                        'detalle' => 'Baja de notas, falta de atención'
                                    ],
                                    [
                                        'valor' => 'Conducta en Aula',
                                        'titulo' => 'Conducta en Aula',
                                        'detalle' => 'Indisciplina, agresividad, interrupciones'
                                    ],
                                    [
                                        'valor' => 'Social / Emocional',
                                        'titulo' => 'Social / Emocional',
                                        'detalle' => 'Aislamiento, tristeza, llanto recurrente'
                                    ],
                                    [
                                        'valor' => 'Dinámica Familiar',
                                        'titulo' => 'Dinámica Familiar',
                                        'detalle' => 'Problemas en el hogar, negligencia'
                                    ],
                                    [
                                        'valor' => 'Acoso escolar / Acoso',
                                        'titulo' => 'Acoso escolar / Acoso',
                                        'detalle' => 'Víctima o agresor, ciberacoso'
                                    ],
                                    [
                                        'valor' => 'Otro',
                                        'titulo' => 'Otro',
                                        'detalle' => 'Situación no especificada'
                                    ]
                                ];
                                ?>

                                <?php foreach ($categoriasEditar as $indice => $categoria): ?>

                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="form-check categoria-box">

                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="categorias[]"
                                                id="categoria<?= $indice ?>"
                                                value="<?= escapar($categoria['valor']) ?>"
                                                <?= in_array(
                                                    $categoria['valor'],
                                                    $categoriasSeleccionadas,
                                                    true
                                                ) ? 'checked' : '' ?>
                                            >

                                            <label
                                                class="form-check-label"
                                                for="categoria<?= $indice ?>"
                                            >
                                                <strong>
                                                    <?= escapar($categoria['titulo']) ?>
                                                </strong>

                                                <br>

                                                <small class="text-muted">
                                                    <?= escapar($categoria['detalle']) ?>
                                                </small>
                                            </label>

                                        </div>
                                    </div>

                                <?php endforeach; ?>

                            </div>
                        </div>

                        <div class="col-12">
                            <label
                                for="observaciones_adicionales"
                                class="form-label"
                            >
                                Observaciones adicionales
                            </label>

                            <textarea
                                id="observaciones_adicionales"
                                class="form-control"
                                rows="3"
                                maxlength="3000"
                                placeholder="Observaciones adicionales (opcional)"
                            ><?= escapar($observacionAdicional) ?></textarea>
                        </div>

                        <input
                            type="hidden"
                            name="observaciones"
                            id="observaciones"
                        >

                    
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="<?= $baseUrl; ?>/derivaciones/listar.php"
                           class="btn btn-outline-secondary">
                            Cancelar
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.querySelector('form');
    const observaciones = document.getElementById('observaciones');
    const observacionesAdicionales =
        document.getElementById('observaciones_adicionales');

    formulario.addEventListener('submit', function (event) {
        const categorias = [
            ...document.querySelectorAll(
                'input[name="categorias[]"]:checked'
            )
        ].map(input => input.value);

        if (categorias.length === 0) {
            event.preventDefault();
            alert('Debe seleccionar al menos una categoría.');
            return;
        }

        const partes = [];

        partes.push(
            'Categorías observadas: ' +
            categorias.join(', ')
        );

        const textoObservacion =
            observacionesAdicionales.value.trim();

        if (textoObservacion !== '') {
            partes.push(
                'Observaciones adicionales: ' +
                textoObservacion
            );
        }

        const solicitudCita =
            <?= json_encode(
                $solicitudCita,
                JSON_UNESCAPED_UNICODE
            ) ?>;

        const profesional =
            <?= json_encode(
                $profesionalSolicitado,
                JSON_UNESCAPED_UNICODE
            ) ?>;

        if (solicitudCita !== '') {
            partes.push(solicitudCita);
        }

        if (profesional !== '') {
            partes.push(profesional);
        }

        observaciones.value =
            partes.join("\n\n");
    });
});
</script>

<?php include '../includes/footer.php'; ?>  