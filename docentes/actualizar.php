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

if (!is_array($materiasRecibidas) || count(array_filter($materiasRecibidas, 'is_string')) !== count($materiasRecibidas)) {
    regresarConError('Seleccione las materias usando las casillas del formulario.', $idDocente);
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

$materiasPermitidas = array_column(
    $conexion->query("SELECT nombre FROM materias WHERE estado='Activo'")->fetch_all(MYSQLI_ASSOC),
    'nombre'
);

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

$correoDb = $correo !== '' ? $correo : null;
$telefonoDb = $telefono !== '' ? $telefono : null;
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare('SELECT id_docente FROM docentes WHERE id_docente=? FOR UPDATE');
    $stmt->bind_param('i', $idDocente);
    $stmt->execute();
    $docenteExiste = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$docenteExiste) throw new InvalidArgumentException('El docente no existe.');

    $stmt = $conexion->prepare("SELECT id_persona FROM usuarios WHERE id_usuario=? AND id_rol=3 AND estado='Activo' FOR UPDATE");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $cuenta = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cuenta) throw new InvalidArgumentException('La cuenta seleccionada no está activa o no pertenece a un docente.');
    $idPersona = (int)$cuenta['id_persona'];

    $stmt = $conexion->prepare('SELECT id_docente FROM docentes WHERE id_persona=? AND id_docente<>? LIMIT 1');
    $stmt->bind_param('ii', $idPersona, $idDocente);
    $stmt->execute();
    $ocupado = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($ocupado) throw new InvalidArgumentException('La cuenta seleccionada ya pertenece a otro docente.');

    $stmt = $conexion->prepare('UPDATE personas SET nombres=?,apellidos=?,telefono=?,correo=? WHERE id_persona=?');
    $stmt->bind_param('ssssi', $nombres, $apellidos, $telefonoDb, $correoDb, $idPersona);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare('UPDATE docentes SET id_persona=? WHERE id_docente=?');
    $stmt->bind_param('ii', $idPersona, $idDocente);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare('DELETE FROM docente_materias WHERE id_docente=?');
    $stmt->bind_param('i', $idDocente);
    $stmt->execute();
    $stmt->close();

    $stmtMateria = $conexion->prepare("SELECT id_materia FROM materias WHERE nombre=? AND estado='Activo' LIMIT 1");
    $stmtVinculo = $conexion->prepare('INSERT INTO docente_materias (id_docente,id_materia) VALUES (?,?)');
    foreach ($materiasRecibidas as $materia) {
        $stmtMateria->bind_param('s', $materia);
        $stmtMateria->execute();
        $fila = $stmtMateria->get_result()->fetch_assoc();
        if (!$fila) throw new InvalidArgumentException('Una de las materias dejó de estar activa.');
        $idMateria = (int)$fila['id_materia'];
        $stmtVinculo->bind_param('ii', $idDocente, $idMateria);
        $stmtVinculo->execute();
    }
    $stmtMateria->close();
    $stmtVinculo->close();
    $conexion->commit();
    $_SESSION['mensaje'] = 'Los datos del docente fueron actualizados correctamente.';
    $_SESSION['tipo_mensaje'] = 'success';
    header('Location: ver.php?id=' . $idDocente);
} catch (Throwable $error) {
    $conexion->rollback();
    error_log('Error al actualizar docente: ' . $error->getMessage());
    regresarConError(
        $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar el docente.',
        $idDocente
    );
}
exit;
