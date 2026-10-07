<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/editar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/datos.php';

$baseUrl = rtrim(login_config()['base_url'], '/');

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$rolActual = isset($_SESSION['id_rol']) ? (int) $_SESSION['id_rol'] : 0;
$puedeEditar = login_puede('derivaciones/editar.php');
$idDocenteActual = (int) ($_SESSION['id_docente'] ?? 0);

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
            d.id_materia,
            m.nombre AS materia,
            d.motivo,
            d.observaciones,
            d.prioridad,
            d.estado,
            e.nombres AS estudiante_nombres,
            e.apellidos AS estudiante_apellidos,
            e.curso,
            e.paralelo,
            persona.nombres AS docente_nombres,
            persona.apellidos AS docente_apellidos
        FROM derivaciones AS d
        LEFT JOIN vista_estudiantes AS e
            ON d.id_estudiante = e.id_estudiante
        LEFT JOIN materias AS m
            ON m.id_materia = d.id_materia
        LEFT JOIN docentes AS doc
            ON d.id_docente = doc.id_docente
        LEFT JOIN personas AS persona
            ON persona.id_persona = doc.id_persona
        WHERE d.id_derivacion = ?
          AND (? <> 3 OR d.id_docente = ?)
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die('Error al preparar la consulta: ' . escapar($conexion->error));
}

$stmt->bind_param('iii', $idDerivacion, $rolActual, $idDocenteActual);
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

$desglose = derivacion_desglosar($textoObservaciones);
$categoriasSeleccionadas = derivacion_categorias_registradas($conexion, $idDerivacion, $textoObservaciones);
$catalogoCategorias = derivacion_catalogo_categorias($conexion);
$categoriasEditar = [];
foreach (array_unique(array_merge(array_keys($catalogoCategorias), $categoriasSeleccionadas)) as $nombre) {
    $activa = ($catalogoCategorias[$nombre]['estado'] ?? '') === 'Activo';
    if (!$activa && !in_array($nombre, $categoriasSeleccionadas, true)) continue;
    $categoriasEditar[] = ['valor' => $nombre, 'titulo' => $nombre,
        'detalle' => $activa ? '' : 'Categoría conservada del registro'];
}
$stmt = $conexion->prepare("SELECT nombre,estado FROM materias WHERE estado='Activo' OR id_materia=? ORDER BY nombre");
$stmt->bind_param('i', $derivacion['id_materia']); $stmt->execute();
$materiasEditar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$observacionAdicional = $desglose['adicionales'];
$recuperacion = $_SESSION['edicion_derivacion'] ?? [];
unset($_SESSION['edicion_derivacion']);
if (($recuperacion['id'] ?? 0) === $idDerivacion) {
    $datos = $recuperacion['datos'];
    foreach (['fecha', 'materia', 'motivo', 'prioridad'] as $campo) {
        if (array_key_exists($campo, $datos)) $derivacion[$campo] = $datos[$campo];
    }
    $categoriasSeleccionadas = $datos['categorias'] ?? $categoriasSeleccionadas;
    $observacionAdicional = $datos['observaciones_adicionales'] ?? $observacionAdicional;
}
$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escapar($mensaje) ?></div>
        <?php endif; ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
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
                <form id="formEditarDerivacion" action="<?= $baseUrl; ?>/derivaciones/actualizar.php"
                      method="POST">
                    <?= login_campo_csrf() ?>

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

                            <select id="materia" name="materia" class="form-select" required>
                                <option value="">Seleccione una materia</option>
                                <?php foreach ($materiasEditar as $materia): ?>
                                    <option value="<?= escapar($materia['nombre']) ?>" <?= $derivacion['materia'] === $materia['nombre'] ? 'selected' : '' ?>>
                                        <?= escapar($materia['nombre']) ?><?= $materia['estado'] === 'Activo' ? '' : ' (conservada del registro)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                id="observaciones_adicionales" name="observaciones_adicionales"
                                class="form-control"
                                rows="3"
                                maxlength="3000"
                                placeholder="Observaciones adicionales (opcional)"
                            ><?= escapar($observacionAdicional) ?></textarea>
                        </div>

                        <input type="hidden" name="observaciones_presentes" value="1">


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


<?php include '../includes/footer.php'; ?>
