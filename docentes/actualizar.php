<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('docentes/actualizar.php');

require_once '../config/conexion.php';

function regresarConError(
    string $mensaje,
    int $idDocente
): void {
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: editar.php?id=' . $idDocente);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$idDocente = filter_input(
    INPUT_POST,
    'id_docente',
    FILTER_VALIDATE_INT
);

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

if (!$idDocente || $idDocente <= 0) {
    $_SESSION['mensaje'] = 'El docente seleccionado no es válido.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

if (!$idUsuario || $idUsuario <= 0) {
    regresarConError(
        'Debe seleccionar una cuenta de usuario.',
        $idDocente
    );
}

if ($nombres === '') {
    regresarConError(
        'Debe ingresar los nombres del docente.',
        $idDocente
    );
}

if ($apellidos === '') {
    regresarConError(
        'Debe ingresar los apellidos del docente.',
        $idDocente
    );
}

/* PROCESAR MATERIAS */

if (!is_array($materiasRecibidas)) {
    $materiasRecibidas = [];
}

$materiasRecibidas = array_map(
    static fn($materia) => trim((string) $materia),
    $materiasRecibidas
);

$materiasRecibidas = array_filter(
    $materiasRecibidas,
    static fn($materia) => $materia !== ''
);

$materiasRecibidas = array_values(
    array_unique($materiasRecibidas)
);

if (empty($materiasRecibidas)) {
    regresarConError(
        'Debe seleccionar al menos una materia.',
        $idDocente
    );
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

foreach ($materiasRecibidas as $materiaSeleccionada) {
    if (
        !in_array(
            $materiaSeleccionada,
            $materiasPermitidas,
            true
        )
    ) {
        regresarConError(
            'Una de las materias seleccionadas no es válida.',
            $idDocente
        );
    }
}

$materia = implode(', ', $materiasRecibidas);

/* VALIDAR LONGITUDES */

if (mb_strlen($nombres) > 60) {
    regresarConError(
        'Los nombres no pueden superar los 60 caracteres.',
        $idDocente
    );
}

if (mb_strlen($apellidos) > 60) {
    regresarConError(
        'Los apellidos no pueden superar los 60 caracteres.',
        $idDocente
    );
}

if (mb_strlen($telefono) > 20) {
    regresarConError(
        'El teléfono no puede superar los 20 caracteres.',
        $idDocente
    );
}

if (mb_strlen($correo) > 100) {
    regresarConError(
        'El correo no puede superar los 100 caracteres.',
        $idDocente
    );
}

if (mb_strlen($materia) > 500) {
    regresarConError(
        'La lista de materias es demasiado extensa.',
        $idDocente
    );
}

/* VALIDAR NOMBRES */

$patronNombre = "/^[\p{L}\s.'-]+$/u";

if (!preg_match($patronNombre, $nombres)) {
    regresarConError(
        'Los nombres contienen caracteres no permitidos.',
        $idDocente
    );
}

if (!preg_match($patronNombre, $apellidos)) {
    regresarConError(
        'Los apellidos contienen caracteres no permitidos.',
        $idDocente
    );
}

/* VALIDAR TELÉFONO */

if (
    $telefono !== '' &&
    !preg_match('/^[0-9+\-\s]{7,20}$/', $telefono)
) {
    regresarConError(
        'El teléfono no tiene un formato válido.',
        $idDocente
    );
}

/* VALIDAR CORREO */

if (
    $correo !== '' &&
    !filter_var($correo, FILTER_VALIDATE_EMAIL)
) {
    regresarConError(
        'El correo electrónico no tiene un formato válido.',
        $idDocente
    );
}

/* COMPROBAR QUE EL DOCENTE EXISTE */

$sqlDocente = "
    SELECT id_docente
    FROM docentes
    WHERE id_docente = ?
    LIMIT 1
";

$stmtDocente = $conexion->prepare($sqlDocente);
$stmtDocente->bind_param('i', $idDocente);
$stmtDocente->execute();

if ($stmtDocente->get_result()->num_rows === 0) {
    $stmtDocente->close();

    $_SESSION['mensaje'] = 'El docente no existe.';
    $_SESSION['tipo_mensaje'] = 'danger';

    header('Location: listar.php');
    exit;
}

$stmtDocente->close();

/* VALIDAR CUENTA DE USUARIO */

$sqlUsuario = "
    SELECT id_usuario
    FROM usuarios
    WHERE id_usuario = ?
      AND id_rol = 3
    LIMIT 1
";

$stmtUsuario = $conexion->prepare($sqlUsuario);
$stmtUsuario->bind_param('i', $idUsuario);
$stmtUsuario->execute();

if ($stmtUsuario->get_result()->num_rows === 0) {
    $stmtUsuario->close();

    regresarConError(
        'La cuenta seleccionada no pertenece a un docente.',
        $idDocente
    );
}

$stmtUsuario->close();

/* COMPROBAR QUE LA CUENTA NO ESTÉ OCUPADA */

$sqlExiste = "
    SELECT id_docente
    FROM docentes
    WHERE id_usuario = ?
      AND id_docente <> ?
    LIMIT 1
";

$stmtExiste = $conexion->prepare($sqlExiste);

$stmtExiste->bind_param(
    'ii',
    $idUsuario,
    $idDocente
);

$stmtExiste->execute();

if ($stmtExiste->get_result()->num_rows > 0) {
    $stmtExiste->close();

    regresarConError(
        'La cuenta seleccionada ya pertenece a otro docente.',
        $idDocente
    );
}

$stmtExiste->close();

/* ACTUALIZAR DOCENTE */

$sqlActualizar = "
    UPDATE docentes
    SET
        id_usuario = ?,
        nombres = ?,
        apellidos = ?,
        telefono = ?,
        correo = ?,
        materia = ?
    WHERE id_docente = ?
";

$stmtActualizar = $conexion->prepare($sqlActualizar);

if (!$stmtActualizar) {
    regresarConError(
        'No se pudo preparar la actualización: ' .
        $conexion->error,
        $idDocente
    );
}

$stmtActualizar->bind_param(
    'isssssi',
    $idUsuario,
    $nombres,
    $apellidos,
    $telefono,
    $correo,
    $materia,
    $idDocente
);

if ($stmtActualizar->execute()) {
    $stmtActualizar->close();

    $_SESSION['mensaje'] =
        'Los datos del docente fueron actualizados correctamente.';

    $_SESSION['tipo_mensaje'] = 'success';

    header('Location: ver.php?id=' . $idDocente);
    exit;
}

$error = $stmtActualizar->error;
$stmtActualizar->close();

regresarConError(
    'No se pudo actualizar el docente: ' . $error,
    $idDocente
);