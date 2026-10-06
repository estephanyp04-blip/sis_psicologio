<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/registrar.php');
require_once '../config/conexion.php';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

function escapar(mixed $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Datos anteriores (si hubo error al guardar)
$d = $_SESSION['datos_derivacion'] ?? [];
unset($_SESSION['datos_derivacion']);

$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'info';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

if (!in_array($tipoMensaje, ['success', 'danger', 'warning', 'info'], true)) {
    $tipoMensaje = 'info';
}

// Cursos disponibles (para el filtro)
$rsCursos = $conexion->query(
    "SELECT DISTINCT curso FROM estudiantes WHERE estado = 'Activo' ORDER BY curso ASC"
);
$cursos = [];
if ($rsCursos) {
    while ($c = $rsCursos->fetch_assoc()) {
        $cursos[] = $c['curso'];
    }
}

// Curso y paralelo elegidos en el filtro (recarga la página vía GET)
$cursoFiltro = $_GET['curso'] ?? '';
$paraleloFiltro = $_GET['paralelo'] ?? '';

$rsParalelos = $conexion->query(
    "SELECT DISTINCT paralelo FROM estudiantes WHERE estado = 'Activo'" .
    ($cursoFiltro !== '' ? " AND curso = '" . $conexion->real_escape_string($cursoFiltro) . "'" : '') .
    " ORDER BY paralelo ASC"
);
$paralelos = [];
if ($rsParalelos) {
    while ($p = $rsParalelos->fetch_assoc()) {
        $paralelos[] = $p['paralelo'];
    }
}

// Estudiantes activos, filtrados por curso y paralelo si se eligieron
$sqlEstudiantes = "SELECT id_estudiante, nombres, apellidos, curso, paralelo
                    FROM estudiantes
                    WHERE estado = 'Activo'";

if ($cursoFiltro !== '') {
    $sqlEstudiantes .= " AND curso = '" . $conexion->real_escape_string($cursoFiltro) . "'";
}

if ($paraleloFiltro !== '') {
    $sqlEstudiantes .= " AND paralelo = '" . $conexion->real_escape_string($paraleloFiltro) . "'";
}

$sqlEstudiantes .= " ORDER BY paralelo ASC, apellidos ASC, nombres ASC";

$rsEstudiantes = $conexion->query($sqlEstudiantes);
if (!$rsEstudiantes) {
    die('Error al consultar estudiantes: ' . $conexion->error);
}

// Psicólogas activas disponibles para la atención.
$rsProfesionales = $conexion->query(
    "SELECT id_usuario, nombre, apellido, usuario, correo FROM usuarios
     WHERE id_rol = 2 AND estado = 'Activo' ORDER BY apellido ASC, nombre ASC"
);
$profesionales = $rsProfesionales->fetch_all(MYSQLI_ASSOC);
$rsProfesionales->free();

// Valores anteriores
$idEstudianteAnt   = (int) ($d['id_estudiante'] ?? 0);
$fechaAnt          = $d['fecha'] ?? date('Y-m-d');
$materiaAnt        = $d['materia'] ?? '';
$motivoAnt         = $d['motivo'] ?? '';
$observacionesAnt  = $d['observaciones'] ?? '';
$prioridadAnt      = $d['prioridad'] ?? 'Media';
$categoriasAnt     = is_array($d['categorias'] ?? null) ? $d['categorias'] : [];
$solicitarCitaAnt  = !array_key_exists('solicitar_cita', $d) || (int) $d['solicitar_cita'] === 1;
$idProfesionalAnt  = (int) ($d['id_profesional'] ?? 0);
// Preseleccionar la cuenta indicada sin reemplazar una elección anterior.
if (!array_key_exists('id_profesional', $d)) {
    $coincidenciasMaria = array_filter($profesionales, static function (array $profesional): bool {
        return strtolower(trim((string) $profesional['usuario'])) === 'psicologa'
            && strtolower(trim((string) $profesional['correo'])) === 'psicologia@canadapailita.edu.bo';
    });
    if (count($coincidenciasMaria) === 1) {
        $idProfesionalAnt = (int) reset($coincidenciasMaria)['id_usuario'];
    }
}

$categorias = [
    ['Rendimiento Académico', 'Baja de notas o falta de atención.'],
    ['Conducta en Aula', 'Indisciplina, agresividad o interrupciones.'],
    ['Social / Emocional', 'Aislamiento, tristeza o llanto recurrente.'],
    ['Dinámica Familiar', 'Problemas en el hogar o negligencia.'],
    ['Acoso escolar / Acoso', 'Víctima, agresor o ciberacoso.'],
    ['Otro', 'Situación no especificada anteriormente.'],
];

$materias = [
    'Matemática', 'Lenguaje y Comunicación', 'Ciencias Naturales', 'Ciencias Sociales',
    'Biología', 'Física', 'Química', 'Inglés', 'Educación Física',
    'Artes Plásticas', 'Música', 'Tecnología', 'Valores', 'Otra',
];
?>

<div class="main-content">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= login_html(login_inicio_url()) ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="listar.php">Derivaciones</a></li>
            <li class="breadcrumb-item active" aria-current="page">Registrar</li>
        </ol>
    </nav>

    <div class="encabezado-modulo">
        <h2>Registrar derivación</h2>
        <p>Primero busque al estudiante y luego describa la situación para solicitar atención psicológica.</p>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= escapar($tipoMensaje) ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle-fill me-1"></i>
            <?= nl2br(escapar($mensaje)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <form action="registrar.php" method="GET" id="formBuscarEstudiante"></form>
            <form action="guardar.php" method="POST" id="formDerivacion">
                <?= login_campo_csrf() ?>

                <!-- 1. DATOS DEL ESTUDIANTE -->
                <div class="form-section mb-5">
                    <h5 class="section-title"><i class="bi bi-person text-danger me-2"></i>1. Datos del Estudiante</h5>
                    <hr>

                    <!-- Filtro por curso y paralelo: recarga la página vía GET, sin JS -->
                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-md-4">
                            <label for="curso" class="form-label">Curso</label>
                            <select name="curso" id="curso" class="form-select" form="formBuscarEstudiante">
                                <option value="">Todos los cursos</option>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?= escapar($c) ?>" <?= $cursoFiltro === $c ? 'selected' : '' ?>>
                                        Curso <?= escapar($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="paralelo" class="form-label">Paralelo</label>
                            <select name="paralelo" id="paralelo" class="form-select" form="formBuscarEstudiante">
                                <option value="">Todos</option>
                                <?php foreach ($paralelos as $p): ?>
                                    <option value="<?= escapar($p) ?>" <?= $paraleloFiltro === $p ? 'selected' : '' ?>>
                                        <?= escapar($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" form="formBuscarEstudiante" class="btn btn-outline-primary w-100">
                                <i class="bi bi-search me-1"></i> Buscar
                            </button>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-8">
                            <label for="id_estudiante" class="form-label">Estudiante <span class="text-danger">*</span></label>
                            <select name="id_estudiante" id="id_estudiante" class="form-select" required>
                                <option value="">
                                    <?= $cursoFiltro === ''
                                        ? 'Seleccione un curso arriba o elija de la lista completa...'
                                        : 'Seleccione un estudiante...'; ?>
                                </option>
                                <?php
                                $cursoActual = null;
                                while ($e = $rsEstudiantes->fetch_assoc()):
                                    $cursoLabel = 'Curso ' . escapar($e['curso']) . ' - Paralelo ' . escapar($e['paralelo']);
                                    if ($cursoLabel !== $cursoActual):
                                        if ($cursoActual !== null) echo '</optgroup>';
                                        echo '<optgroup label="' . $cursoLabel . '">';
                                        $cursoActual = $cursoLabel;
                                    endif;
                                    ?>
                                    <option value="<?= (int) $e['id_estudiante'] ?>" <?= $idEstudianteAnt === (int) $e['id_estudiante'] ? 'selected' : '' ?>>
                                        <?= escapar($e['apellidos'] . ' ' . $e['nombres']) ?>
                                    </option>
                                <?php endwhile; if ($cursoActual !== null) echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="fecha" class="form-label">Fecha de reporte <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" id="fecha" class="form-control"
                                   value="<?= escapar($fechaAnt) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="materia" class="form-label">Materia <span class="text-danger">*</span></label>
                            <input type="text" name="materia" id="materia" class="form-control" list="listaMaterias"
                                   value="<?= escapar($materiaAnt) ?>" placeholder="Escriba o seleccione una materia..."
                                   autocomplete="off" required>
                            <datalist id="listaMaterias">
                                <?php foreach ($materias as $m): ?>
                                    <option value="<?= escapar($m) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                </div>

                <!-- 2. MOTIVO DE DERIVACIÓN -->
                <div class="form-section mb-5">
                    <h5 class="section-title"><i class="bi bi-exclamation-circle text-danger me-2"></i>2. Motivo de Derivación</h5>
                    <hr>

                    <div class="mb-4">
                        <label class="form-label d-block">Categorías observadas (seleccione una o más) <span class="text-danger">*</span></label>
                        <div class="row g-3 mt-1" id="grupoCategorias" role="group" aria-describedby="ayudaCategorias">
                            <?php foreach ($categorias as $i => [$valor, $desc]): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="form-check categoria-box">
                                        <input class="form-check-input" type="checkbox" name="categorias[]"
                                               value="<?= escapar($valor) ?>" id="cat<?= $i ?>"
                                               <?= in_array($valor, $categoriasAnt, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="cat<?= $i ?>">
                                            <strong><?= escapar($valor) ?></strong><br>
                                            <small class="text-muted"><?= escapar($desc) ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="ayudaCategorias" class="form-text">Seleccione al menos una categoría.</div>
                        <div id="errorCategorias" class="invalid-feedback d-none">Seleccione al menos una categoría para continuar.</div>
                    </div>

                    <div class="mb-4">
                        <label for="motivo" class="form-label">Descripción detallada de la situación <span class="text-danger">*</span></label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="5" maxlength="2000"
                                  placeholder="Describa la situación observada del estudiante..."
                                  aria-describedby="contadorMotivo" required><?= escapar($motivoAnt) ?></textarea>
                        <div id="contadorMotivo" class="form-text text-end">0 / 2000 caracteres</div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="prioridad" class="form-label">Prioridad <span class="text-danger">*</span></label>
                            <select name="prioridad" id="prioridad" class="form-select" required>
                                <option value="">Seleccionar prioridad...</option>
                                <?php foreach (['Alta', 'Media', 'Baja'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $prioridadAnt === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <input type="text" class="form-control" value="Pendiente" readonly>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="observaciones" class="form-label">Observaciones adicionales</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="4" maxlength="3000"
                                  placeholder="Escriba información adicional si es necesario..." aria-describedby="contadorObservaciones"><?= escapar($observacionesAnt) ?></textarea>
                        <div id="contadorObservaciones" class="form-text text-end">0 / 3000 caracteres</div>
                    </div>
                </div>

                <!-- 3. SOLICITUD DE CITA -->
                <div class="form-section mb-4">
                    <h5 class="section-title"><i class="bi bi-calendar-event text-danger me-2"></i>3. Solicitud de Cita Psicológica</h5>
                    <hr>

                    <div class="cita-box p-4 rounded border">
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="solicitar_cita" value="1"
                                   id="solicitar_cita" <?= $solicitarCitaAnt ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="solicitar_cita">
                                Agendar cita de evaluación con el departamento de psicología
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="id_profesional" class="form-label">
                                    Psicóloga encargada <span class="text-muted">(opcional)</span>
                                </label>
                                <select name="id_profesional" id="id_profesional" class="form-select">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($profesionales as $p): ?>
                                        <option value="<?= (int) $p['id_usuario'] ?>"
                                            <?= $idProfesionalAnt === (int) $p['id_usuario'] ? 'selected' : '' ?>>
                                            <?= escapar(trim($p['nombre'] . ' ' . ($p['apellido'] ?? ''))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$profesionales): ?>
                                    <div class="form-text text-danger">No hay psicólogas activas disponibles. Solicite al administrador revisar las cuentas.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end flex-wrap gap-2 mt-4 pt-4 border-top">
                    <a href="listar.php" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-save me-1"></i> Guardar derivación
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formDerivacion');
    const categorias = Array.from(document.querySelectorAll('input[name="categorias[]"]'));
    const grupoCategorias = document.getElementById('grupoCategorias');
    const errorCategorias = document.getElementById('errorCategorias');
    const solicitarCita = document.getElementById('solicitar_cita');
    const profesional = document.getElementById('id_profesional');

    function actualizarContador(campoId, contadorId) {
        const campo = document.getElementById(campoId);
        const contador = document.getElementById(contadorId);
        contador.textContent = campo.value.length + ' / ' + campo.maxLength + ' caracteres';
    }

    function actualizarCategorias() {
        const haySeleccion = categorias.some(function (categoria) { return categoria.checked; });
        grupoCategorias.classList.toggle('border', !haySeleccion);
        grupoCategorias.classList.toggle('border-danger', !haySeleccion && form.classList.contains('was-validated'));
        errorCategorias.classList.toggle('d-none', haySeleccion || !form.classList.contains('was-validated'));
        return haySeleccion;
    }

    function actualizarProfesional() {
        profesional.disabled = !solicitarCita.checked;
    }

    ['motivo', 'observaciones'].forEach(function (campoId) {
        const contadorId = campoId === 'motivo' ? 'contadorMotivo' : 'contadorObservaciones';
        document.getElementById(campoId).addEventListener('input', function () {
            actualizarContador(campoId, contadorId);
        });
        actualizarContador(campoId, contadorId);
    });

    categorias.forEach(function (categoria) {
        categoria.addEventListener('change', actualizarCategorias);
    });
    solicitarCita.addEventListener('change', actualizarProfesional);
    actualizarProfesional();

    form.addEventListener('submit', function (event) {
        form.classList.add('was-validated');
        if (!actualizarCategorias() || !form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
