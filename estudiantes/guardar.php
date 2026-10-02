<?php
/**
 * estudiantes/guardar.php
 * Registra un nuevo estudiante resolviendo automáticamente
 * id_curso e id_paralelo para respetar las llaves foráneas.
 */

require_once '../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

/* =========================================================
   HELPERS
   ========================================================= */
function textoPost(string $campo): string {
    return trim((string)($_POST[$campo] ?? ''));
}

/* =========================================================
   LECTURA DE POST
   ========================================================= */
$campos = [
    'codigo', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'genero',
    'curso', 'paralelo', 'turno', 'estado', 'padre', 'madre', 'tutor',
    'telefono', 'direccion',
];

$datos = [];
foreach ($campos as $campo) {
    $datos[$campo] = textoPost($campo);
}

/* =========================================================
   VALIDACIONES BÁSICAS
   ========================================================= */
$generos = ['Masculino', 'Femenino'];
$turnos  = ['Mañana', 'Tarde'];
$estados = ['Activo', 'En seguimiento', 'Baja'];

if (
    $datos['codigo'] === '' ||
    $datos['ci'] === '' ||
    $datos['nombres'] === '' ||
    $datos['apellidos'] === '' ||
    $datos['fecha_nacimiento'] === '' ||
    !in_array($datos['genero'], $generos, true) ||
    !in_array($datos['curso'], ['1','2','3','4','5','6'], true) ||
    !in_array($datos['paralelo'], ['A','B','C'], true) ||
    !in_array($datos['turno'], $turnos, true) ||
    !in_array($datos['estado'], $estados, true)
) {
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje']      = 'Revise los datos: hay campos inválidos.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

$fecha = DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento']);
if (
    !$fecha ||
    $fecha->format('Y-m-d') !== $datos['fecha_nacimiento'] ||
    $datos['fecha_nacimiento'] > date('Y-m-d')
) {
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje']      = 'La fecha de nacimiento no es válida.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

/* =========================================================
   DUPLICADOS (código o CI)
   ========================================================= */
$duplicado = $conexion->prepare('
    SELECT id_estudiante
    FROM estudiantes
    WHERE codigo = ? OR ci = ?
    LIMIT 1
');
$duplicado->bind_param('ss', $datos['codigo'], $datos['ci']);
$duplicado->execute();

if ($duplicado->get_result()->num_rows > 0) {
    $duplicado->close();
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje']      = 'Ya existe un estudiante con ese código o CI.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}
$duplicado->close();

/* =========================================================
   RESOLVER id_curso
   ========================================================= */
$idCurso = 0;

/* 1. Buscar por nombre exacto en formatos comunes */
$formatos = [
    $datos['curso'],                    // "1"
    $datos['curso'] . '°',              // "1°"
    $datos['curso'] . 'ro',             // "1ro"
    $datos['curso'] . 'to',             // "1to"
    'Curso ' . $datos['curso'],         // "Curso 1"
    'Curso ' . $datos['curso'] . '°',   // "Curso 1°"
    $datos['curso'] . '° Año',          // "1° Año"
    $datos['curso'] . 'ro Año',         // "1ro Año"
    $datos['curso'] . ' Año',           // "1 Año"
];

$placeholders = implode(',', array_fill(0, count($formatos), '?'));

$stmt = $conexion->prepare("
    SELECT id_curso, nombre
    FROM cursos
    WHERE nombre IN ($placeholders)
    LIMIT 1
");

if ($stmt) {
    $tipos = str_repeat('s', count($formatos));
    $stmt->bind_param($tipos, ...$formatos);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $idCurso = (int)$row['id_curso'];
    }
}

/* 2. Fallback: intentar como id_curso numérico directo */
if ($idCurso === 0 && ctype_digit($datos['curso'])) {
    $stmt = $conexion->prepare('SELECT id_curso FROM cursos WHERE id_curso = ? LIMIT 1');
    $stmt->bind_param('i', $datos['curso']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $idCurso = (int)$row['id_curso'];
    }
}

/* 3. Fallback final: tomar el primer curso disponible */
if ($idCurso === 0) {
    $rsPrimero = $conexion->query('SELECT id_curso FROM cursos ORDER BY id_curso ASC LIMIT 1');
    if ($rsPrimero && $row = $rsPrimero->fetch_assoc()) {
        $idCurso = (int)$row['id_curso'];
    }
}

/* 4. Si no hay ningún curso → error legible */
if ($idCurso === 0) {
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje'] = 'La tabla "cursos" está vacía. Cree al menos un curso antes de registrar estudiantes.';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

/* =========================================================
   RESOLVER id_paralelo
   ========================================================= */
$idParalelo = 0;

/* 1. Buscar por nombre + id_curso (más preciso) */
$stmt = $conexion->prepare("
    SELECT id_paralelo
    FROM paralelos
    WHERE nombre = ?
      AND id_curso = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param('si', $datos['paralelo'], $idCurso);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $idParalelo = (int)$row['id_paralelo'];
    }
}

/* 2. Fallback: buscar solo por nombre */
if ($idParalelo === 0) {
    $stmt = $conexion->prepare("
        SELECT id_paralelo
        FROM paralelos
        WHERE nombre = ?
        ORDER BY id_paralelo ASC
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('s', $datos['paralelo']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $idParalelo = (int)$row['id_paralelo'];
        }
    }
}

/* 3. Fallback: primer paralelo del curso */
if ($idParalelo === 0) {
    $stmt = $conexion->prepare("
        SELECT id_paralelo
        FROM paralelos
        WHERE id_curso = ?
        ORDER BY id_paralelo ASC
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $idCurso);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $idParalelo = (int)$row['id_paralelo'];
        }
    }
}

/* 4. Si no se encontró ningún paralelo → error legible */
if ($idParalelo === 0) {
    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje'] = 'No se pudo asociar el paralelo "' . $datos['paralelo']
                         . '" al curso. Verifique la tabla "paralelos".';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: registrar.php');
    exit;
}

/* =========================================================
   INSERT
   ========================================================= */
try {
    $sql = 'INSERT INTO estudiantes (
                codigo, ci, nombres, apellidos, fecha_nacimiento, genero,
                curso, id_curso, paralelo, id_paralelo, turno, estado,
                padre, madre, tutor, telefono, direccion
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare falló: ' . $conexion->error);
    }

    /**
     * Tipos del bind_param (17):
     *   s  codigo
     *   s  ci
     *   s  nombres
     *   s  apellidos
     *   s  fecha_nacimiento
     *   s  genero
     *   s  curso
     *   i  id_curso
     *   s  paralelo
     *   i  id_paralelo
     *   s  turno
     *   s  estado
     *   s  padre
     *   s  madre
     *   s  tutor
     *   s  telefono
     *   s  direccion
     */
    $stmt->bind_param(
        'sssssssisisssssss',
        $datos['codigo'],
        $datos['ci'],
        $datos['nombres'],
        $datos['apellidos'],
        $datos['fecha_nacimiento'],
        $datos['genero'],
        $datos['curso'],
        $idCurso,
        $datos['paralelo'],
        $idParalelo,
        $datos['turno'],
        $datos['estado'],
        $datos['padre'],
        $datos['madre'],
        $datos['tutor'],
        $datos['telefono'],
        $datos['direccion']
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute falló: ' . $stmt->error);
    }

    $stmt->close();

    unset($_SESSION['datos_estudiante']);

    $_SESSION['mensaje']      = 'El estudiante se registró correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';

    header('Location: listar.php');
    exit;

} catch (Throwable $e) {
    error_log('Error en estudiantes/guardar.php: ' . $e->getMessage());

    $debugInfo = sprintf(
        ' [DEBUG] curso="%s" id_curso=%d paralelo="%s" id_paralelo=%d',
        $datos['curso'],
        $idCurso,
        $datos['paralelo'],
        $idParalelo
    );

    $_SESSION['datos_estudiante'] = $datos;
    $_SESSION['mensaje']      = 'No se pudo guardar: ' . $e->getMessage() . $debugInfo;
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}