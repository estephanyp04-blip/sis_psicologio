<?php
require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

$idRol = isset($_SESSION['id_rol'])
    ? (int) $_SESSION['id_rol']
    : 0;

$idDocente = isset($_SESSION['id_docente']) && (int) $_SESSION['id_docente'] > 0
    ? (int) $_SESSION['id_docente']
    : null;

$idEstudiante = isset($_POST['id_estudiante'])
    ? (int) $_POST['id_estudiante']
    : 0;

$fecha = isset($_POST['fecha'])
    ? trim($_POST['fecha'])
    : '';

$materia = isset($_POST['materia'])
    ? trim($_POST['materia'])
    : '';

$motivo = isset($_POST['motivo'])
    ? trim($_POST['motivo'])
    : '';

$observaciones = isset($_POST['observaciones'])
    ? trim($_POST['observaciones'])
    : '';

$prioridad = isset($_POST['prioridad'])
    ? trim($_POST['prioridad'])
    : '';

$categorias = isset($_POST['categorias']) && is_array($_POST['categorias'])
    ? $_POST['categorias']
    : [];

$solicitarCita = isset($_POST['solicitar_cita']) ? 1 : 0;

$idProfesional = isset($_POST['id_profesional'])
    ? (int) $_POST['id_profesional']
    : 0;

$errores = [];

if ($idEstudiante <= 0) {
    $errores[] = 'Debe seleccionar un estudiante.';
}

if ($fecha === '') {
    $errores[] = 'Debe seleccionar la fecha del reporte.';
}

if ($materia === '') {
    $errores[] = 'Debe seleccionar una materia.';
}

if (empty($categorias)) {
    $errores[] = 'Debe seleccionar al menos una categoría observada.';
}

if ($motivo === '') {
    $errores[] = 'Debe escribir la descripción detallada de la situación.';
}

$prioridadesPermitidas = [
    'Alta',
    'Media',
    'Baja'
];

if (!in_array($prioridad, $prioridadesPermitidas, true)) {
    $errores[] = 'La prioridad seleccionada no es válida.';
}

$materiasPermitidas = [
    'Matemática',
    'Lenguaje y Comunicación',
    'Ciencias Naturales',
    'Ciencias Sociales',
    'Biología',
    'Física',
    'Química',
    'Inglés',
    'Educación Física',
    'Artes Plásticas',
    'Música',
    'Tecnología',
    'Valores',
    'Otra'
];

if ($materia !== '' && !in_array($materia, $materiasPermitidas, true)) {
    $errores[] = 'La materia seleccionada no es válida.';
}

if ($fecha !== '') {
    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        $errores[] = 'La fecha ingresada no es válida.';
    }

    if ($fecha > date('Y-m-d')) {
        $errores[] = 'La fecha del reporte no puede ser futura.';
    }
}

if (mb_strlen($materia) > 100) {
    $errores[] = 'La materia no puede superar los 100 caracteres.';
}

if (mb_strlen($motivo) > 2000) {
    $errores[] = 'La descripción no puede superar los 2000 caracteres.';
}

if (mb_strlen($observaciones) > 3000) {
    $errores[] = 'Las observaciones no pueden superar los 3000 caracteres.';
}

if (!empty($errores)) {
    $_SESSION['mensaje'] = implode("\n", $errores);
    $_SESSION['tipo_mensaje'] = 'danger';

    $_SESSION['datos_derivacion'] = [
        'id_estudiante' => $idEstudiante,
        'fecha' => $fecha,
        'materia' => $materia,
        'motivo' => $motivo,
        'observaciones' => $observaciones,
        'prioridad' => $prioridad,
        'categorias' => $categorias,
        'solicitar_cita' => $solicitarCita,
        'id_profesional' => $idProfesional
    ];

    header('Location: registrar.php');
    exit;
}

$sqlEstudiante = "
    SELECT id_estudiante
    FROM estudiantes
    WHERE id_estudiante = ?
    AND estado = 'Activo'
    LIMIT 1
";

$stmtEstudiante = $conexion->prepare($sqlEstudiante);

if (!$stmtEstudiante) {
    $_SESSION['mensaje'] = 'No se pudo verificar al estudiante.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

$stmtEstudiante->bind_param('i', $idEstudiante);
$stmtEstudiante->execute();

$resultadoEstudiante = $stmtEstudiante->get_result();

if ($resultadoEstudiante->num_rows === 0) {
    $stmtEstudiante->close();

    $_SESSION['mensaje'] =
        'El estudiante seleccionado no existe o no está activo.';

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}

$stmtEstudiante->close();

if ($idDocente !== null) {
    $sqlDocente = "
        SELECT id_docente
        FROM docentes
        WHERE id_docente = ?
        LIMIT 1
    ";

    $stmtDocente = $conexion->prepare($sqlDocente);

    if (!$stmtDocente) {
        $_SESSION['mensaje'] = 'No se pudo verificar al docente.';
        $_SESSION['tipo_mensaje'] = 'danger';

        header('Location: registrar.php');
        exit;
    }

    $stmtDocente->bind_param('i', $idDocente);
    $stmtDocente->execute();

    $resultadoDocente = $stmtDocente->get_result();

    if ($resultadoDocente->num_rows === 0) {
        $idDocente = null;
    }

    $stmtDocente->close();
}

$categoriasPermitidas = [
    'Rendimiento Académico',
    'Conducta en Aula',
    'Social / Emocional',
    'Dinámica Familiar',
    'Acoso escolar / Acoso',
    'Otro'
];

$categoriasLimpias = [];

foreach ($categorias as $categoria) {
    $categoria = trim($categoria);

    if (in_array($categoria, $categoriasPermitidas, true)) {
        $categoriasLimpias[] = $categoria;
    }
}

$categoriasLimpias = array_unique($categoriasLimpias);

if (empty($categoriasLimpias)) {
    $_SESSION['mensaje'] =
        'Las categorías seleccionadas no son válidas.';

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}

$textoCategorias = implode(', ', $categoriasLimpias);

$nombreProfesional = '';

if ($solicitarCita === 1 && $idProfesional > 0) {
    $sqlProfesional = "
        SELECT nombre
        FROM usuarios
        WHERE id_usuario = ?
        AND id_rol = 2
        AND estado = 'Activo'
        LIMIT 1
    ";

    $stmtProfesional = $conexion->prepare($sqlProfesional);

    if ($stmtProfesional) {
        $stmtProfesional->bind_param('i', $idProfesional);
        $stmtProfesional->execute();

        $resultadoProfesional = $stmtProfesional->get_result();

        if ($resultadoProfesional->num_rows > 0) {
            $profesional = $resultadoProfesional->fetch_assoc();
            $nombreProfesional = trim($profesional['nombre']);
        } else {
            $idProfesional = 0;
        }

        $stmtProfesional->close();
    }
}

$partesObservaciones = [];
$partesObservaciones[] =
    'Categorías observadas: ' . $textoCategorias;

if ($observaciones !== '') {
    $partesObservaciones[] =
        'Observaciones adicionales: ' . $observaciones;
}

if ($solicitarCita === 1) {
    $partesObservaciones[] =
        'Solicitud de cita psicológica: Sí';

    if ($nombreProfesional !== '') {
        $partesObservaciones[] =
            'Profesional solicitado: ' . $nombreProfesional;
    }
} else {
    $partesObservaciones[] =
        'Solicitud de cita psicológica: No';
}

$observacionesFinales = implode(
    PHP_EOL . PHP_EOL,
    $partesObservaciones
);

$estado = 'Pendiente';

$sql = "
    INSERT INTO derivaciones (
        fecha,
        id_estudiante,
        id_docente,
        materia,
        motivo,
        observaciones,
        prioridad,
        estado
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    $_SESSION['mensaje'] =
        'No se pudo preparar el registro de la derivación.';

    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}

$stmt->bind_param(
    'siisssss',
    $fecha,
    $idEstudiante,
    $idDocente,
    $materia,
    $motivo,
    $observacionesFinales,
    $prioridad,
    $estado
);

if ($stmt->execute()) {
    $stmt->close();

    unset($_SESSION['datos_derivacion']);

    $_SESSION['mensaje'] =
        'La derivación se registró correctamente.';

    $_SESSION['tipo_mensaje'] = 'success';

    header('Location: listar.php');
    exit;
}

$_SESSION['mensaje'] =
    'No se pudo registrar la derivación.';

$_SESSION['tipo_mensaje'] = 'danger';

$stmt->close();

header('Location: registrar.php');
exit;
?>s