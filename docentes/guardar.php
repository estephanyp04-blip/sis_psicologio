<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/guardar.php');

require_once '../config/conexion.php';

function regresarConError(string $mensaje): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: registrar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

/* RECIBIR DATOS */

$idUsuario = filter_input(
    INPUT_POST,
    'id_usuario',
    FILTER_VALIDATE_INT
);

$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim($_POST['correo'] ?? '');

$materiasRecibidas = $_POST['materias'] ?? [];

/* VALIDAR QUE MATERIAS SEA UN ARREGLO */

if (!is_array($materiasRecibidas)) {
    $materiasRecibidas = [];
}

/* LIMPIAR MATERIAS */

$materiasLimpias = [];

foreach ($materiasRecibidas as $materiaRecibida) {
    $materiaRecibida = trim((string) $materiaRecibida);

    if ($materiaRecibida !== '') {
        $materiasLimpias[] = $materiaRecibida;
    }
}

$materiasRecibidas = array_values(
    array_unique($materiasLimpias)
);

/* VALIDAR CAMPOS OBLIGATORIOS */

if (!$idUsuario || $idUsuario <= 0) {
    regresarConError(
        'Debe seleccionar una cuenta de usuario para el docente.'
    );
}

if ($nombres === '') {
    regresarConError(
        'Debe ingresar los nombres del docente.'
    );
}

if ($apellidos === '') {
    regresarConError(
        'Debe ingresar los apellidos del docente.'
    );
}

if (empty($materiasRecibidas)) {
    regresarConError(
        'Debe seleccionar al menos una materia.'
    );
}

/* VALIDAR LONGITUDES */

if (mb_strlen($nombres) > 60) {
    regresarConError(
        'Los nombres no pueden superar los 60 caracteres.'
    );
}

if (mb_strlen($apellidos) > 60) {
    regresarConError(
        'Los apellidos no pueden superar los 60 caracteres.'
    );
}

if (mb_strlen($telefono) > 20) {
    regresarConError(
        'El teléfono no puede superar los 20 caracteres.'
    );
}

if (mb_strlen($correo) > 100) {
    regresarConError(
        'El correo no puede superar los 100 caracteres.'
    );
}

/* VALIDAR NOMBRES Y APELLIDOS */

$patronNombre = "/^[\p{L}\s.'-]+$/u";

if (!preg_match($patronNombre, $nombres)) {
    regresarConError(
        'Los nombres contienen caracteres no permitidos.'
    );
}

if (!preg_match($patronNombre, $apellidos)) {
    regresarConError(
        'Los apellidos contienen caracteres no permitidos.'
    );
}

/* VALIDAR TELÉFONO */

if (
    $telefono !== '' &&
    !preg_match('/^[0-9+\-\s]{7,20}$/', $telefono)
) {
    regresarConError(
        'El número de teléfono no tiene un formato válido.'
    );
}

/* VALIDAR CORREO */

if (
    $correo !== '' &&
    !filter_var($correo, FILTER_VALIDATE_EMAIL)
) {
    regresarConError(
        'El correo electrónico no tiene un formato válido.'
    );
}

/* VALIDAR MATERIAS */

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

foreach ($materiasRecibidas as $materiaSeleccionada) {
    if (
        !in_array(
            $materiaSeleccionada,
            $materiasPermitidas,
            true
        )
    ) {
        regresarConError(
            'La materia "' .
            $materiaSeleccionada .
            '" no es válida.'
        );
    }
}

/* UNIR MATERIAS PARA GUARDARLAS */

$materia = implode(', ', $materiasRecibidas);

if (mb_strlen($materia) > 500) {
    regresarConError(
        'La lista de materias seleccionadas es demasiado extensa.'
    );
}

/* COMPROBAR USUARIO */

$sqlUsuario = "
    SELECT
        id_usuario
    FROM usuarios
    WHERE id_usuario = ?
      AND id_rol = 3
      AND estado = 'Activo'
    LIMIT 1
";

$stmtUsuario = $conexion->prepare($sqlUsuario);

if (!$stmtUsuario) {
    regresarConError(
        'No se pudo validar la cuenta del docente: ' .
        $conexion->error
    );
}

$stmtUsuario->bind_param('i', $idUsuario);
$stmtUsuario->execute();

$resultadoUsuario = $stmtUsuario->get_result();

if ($resultadoUsuario->num_rows === 0) {
    $stmtUsuario->close();

    regresarConError(
        'La cuenta seleccionada no existe, está inactiva ' .
        'o no tiene el rol Docente.'
    );
}

$stmtUsuario->close();

/* COMPROBAR QUE LA CUENTA NO ESTÉ ASIGNADA */

$sqlExiste = "
    SELECT
        id_docente
    FROM docentes
    WHERE id_usuario = ?
    LIMIT 1
";

$stmtExiste = $conexion->prepare($sqlExiste);

if (!$stmtExiste) {
    regresarConError(
        'No se pudo comprobar la cuenta seleccionada: ' .
        $conexion->error
    );
}

$stmtExiste->bind_param('i', $idUsuario);
$stmtExiste->execute();

$resultadoExiste = $stmtExiste->get_result();

if ($resultadoExiste->num_rows > 0) {
    $stmtExiste->close();

    regresarConError(
        'La cuenta seleccionada ya está asignada a otro docente.'
    );
}

$stmtExiste->close();

/* GUARDAR DOCENTE */

$sqlGuardar = "
    INSERT INTO docentes (
        id_usuario,
        nombres,
        apellidos,
        telefono,
        correo,
        materia
    )
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmtGuardar = $conexion->prepare($sqlGuardar);

if (!$stmtGuardar) {
    regresarConError(
        'No se pudo preparar el registro: ' .
        $conexion->error
    );
}

$stmtGuardar->bind_param(
    'isssss',
    $idUsuario,
    $nombres,
    $apellidos,
    $telefono,
    $correo,
    $materia
);

if (!$stmtGuardar->execute()) {
    $error = $stmtGuardar->error;
    $stmtGuardar->close();

    regresarConError(
        'No se pudo registrar el docente: ' . $error
    );
}

$stmtGuardar->close();

$_SESSION['mensaje'] =
    'El docente fue registrado correctamente.';

$_SESSION['tipo_mensaje'] = 'success';

header('Location: listar.php');
exit;