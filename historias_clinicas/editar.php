<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('historias_clinicas/editar.php');

require_once '../config/conexion.php';

$rol = (int)($_SESSION['id_rol'] ?? 0);

if (!in_array($rol, [1, 2], true)) {
    header('Location: listar.php');
    exit;
}

function escapar($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: listar.php');
    exit;
}

/* HISTORIA PRINCIPAL */
$stmt = $conexion->prepare("
    SELECT
        h.*,
        h.talla_cm AS talla,
        h.peso_kg AS peso,
        h.valoracion_fisica AS valoracion,
        e.nombres,
        e.apellidos,
        e.curso,
        e.paralelo,
        e.fecha_nacimiento,
        e.lugar_nacimiento AS estudiante_lugar_nacimiento,
        e.lugar_nacimiento AS lugar_nacimiento,
        e.telefono AS celular_estudiante,
        e.telefono,
        (SELECT r.nombres FROM estudiante_responsables er
            INNER JOIN responsables r ON r.id_responsable=er.id_responsable
            WHERE er.id_estudiante=e.id_estudiante AND er.parentesco='Tutor'
            ORDER BY er.es_principal DESC LIMIT 1) AS nombre_tutor,
        m.nombre AS materia_derivacion,
        d.fecha AS fecha_derivacion,
        TRIM(CONCAT(COALESCE(dp.nombres,''),' ',COALESCE(dp.apellidos,''))) AS derivado_por,
        d.prioridad AS prioridad_derivacion,
        d.observaciones AS observaciones_derivacion
    FROM historias_clinicas h
    INNER JOIN vista_estudiantes e ON e.id_estudiante = h.id_estudiante
    LEFT JOIN derivaciones d ON d.id_derivacion = h.id_derivacion_origen
    LEFT JOIN docentes doc ON doc.id_docente = d.id_docente
    LEFT JOIN personas dp ON dp.id_persona = doc.id_persona
    LEFT JOIN materias m ON m.id_materia = d.id_materia
    WHERE h.id_historia = ?
    LIMIT 1
");

$stmt->bind_param('i', $id);
$stmt->execute();
$historia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$historia) {
    header('Location: listar.php');
    exit;
}

$historia['id_derivacion'] = (int)($historia['id_derivacion_origen'] ?? 0);
$historia['id_cita'] = (int)($historia['id_cita_origen'] ?? 0);
$historia['padre_madre'] = $historia['nombre_tutor'] ?? '';

/* ESTUDIANTE ACTUAL */
$estudiantes = [[
    'id_estudiante' => $historia['id_estudiante'],
    'nombres' => $historia['nombres'],
    'apellidos' => $historia['apellidos'],
    'curso' => $historia['curso'],
    'paralelo' => $historia['paralelo'],
    'fecha_nacimiento' => $historia['fecha_nacimiento'],
    'lugar_nacimiento' => $historia['estudiante_lugar_nacimiento'],
    'telefono' => $historia['telefono'],
    'nombre_tutor' => $historia['nombre_tutor']
]];

/* OPCIONES MARCADAS */
$gruposPermitidos = [
    'conductas_riesgo',
    'atencion_distraccion',
    'actividad_motora',
    'adaptacion_normas',
    'dificultades_socioemocionales',
    'estrategias_previas'
];

foreach ($gruposPermitidos as $grupo) {
    $historia[$grupo] = [];
}

$stmt = $conexion->prepare("
    SELECT g.codigo AS grupo,o.descripcion AS valor
    FROM historia_opciones ho
    INNER JOIN opciones_historia o ON o.id_opcion=ho.id_opcion
    INNER JOIN grupos_opciones_historia g ON g.id_grupo=o.id_grupo
    WHERE ho.id_historia = ?
    ORDER BY ho.id_opcion ASC
");

$stmt->bind_param('i', $id);
$stmt->execute();
$resultadoOpciones = $stmt->get_result();

while ($opcion = $resultadoOpciones->fetch_assoc()) {
    $grupo = $opcion['grupo'];

    if (in_array($grupo, $gruposPermitidos, true)) {
        $historia[$grupo][] = $opcion['valor'];
    }
}

$stmt->close();

/* FAMILIARES */
$stmt = $conexion->prepare("
    SELECT
        id_familiar,
        nombre,
        edad,
        relacion,
        profesion,
        ocupacion,
        observaciones
    FROM historia_familiares
    WHERE id_historia = ?
    ORDER BY id_familiar ASC
");

$stmt->bind_param('i', $id);
$stmt->execute();

$familiares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$historia['familiares'] = $familiares;

/* MENSAJES */
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo = $_SESSION['tipo_mensaje'] ?? 'danger';

unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

$tituloPagina = 'Editar historia clínica';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<main class="main-content historia-edicion">
    <div class="container-fluid">

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?= login_html(login_inicio_url()) ?>">Inicio</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="listar.php">Historias clínicas</a>
                </li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>

        <header class="historia-form-head">
            <div>
                <span>EXPEDIENTE #<?= (int)$id ?></span>
                <h1>Editar historia clínica</h1>
                <p>
                    Actualice la información del expediente psicológico del estudiante.
                </p>
            </div>

            <i class="bi bi-pencil-square"></i>
        </header>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= escapar($tipo) ?>">
                <?= nl2br(escapar($mensaje)) ?>
            </div>
        <?php endif; ?>

        <?php include 'formulario.php'; ?>

    </div>
</main>

<?php include '../includes/footer.php'; ?>
