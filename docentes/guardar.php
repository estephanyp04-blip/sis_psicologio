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
            'La materia "' .
            $materiaSeleccionada .
            '" no es válida.'
        );
    }
}

/* COMPROBAR USUARIO */

$sqlUsuario = "
    SELECT u.id_persona
    FROM usuarios u
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

if ($resultadoUsuario->num_rows !== 1) {
    $stmtUsuario->close();

    regresarConError(
        'La cuenta seleccionada no existe, está inactiva ' .
        'o no tiene el rol Docente.'
    );
}
$idPersona = (int)$resultadoUsuario->fetch_assoc()['id_persona'];
$stmtUsuario->close();

/* COMPROBAR QUE LA CUENTA NO ESTÉ ASIGNADA */
$correoDb = $correo !== '' ? $correo : null;
$telefonoDb = $telefono !== '' ? $telefono : null;
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare('SELECT id_docente FROM docentes WHERE id_persona=? FOR UPDATE');
    $stmt->bind_param('i', $idPersona);
    $stmt->execute();
    $ocupado = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($ocupado) throw new InvalidArgumentException('La cuenta seleccionada ya está asignada a un docente.');

    $stmt = $conexion->prepare('UPDATE personas SET nombres=?,apellidos=?,telefono=?,correo=? WHERE id_persona=?');
    $stmt->bind_param('ssssi', $nombres, $apellidos, $telefonoDb, $correoDb, $idPersona);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare('INSERT INTO docentes (id_persona) VALUES (?)');
    $stmt->bind_param('i', $idPersona);
    $stmt->execute();
    $idDocente = (int)$conexion->insert_id;
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
} catch (Throwable $error) {
    $conexion->rollback();
    error_log('Error al registrar docente: ' . $error->getMessage());
    regresarConError($error instanceof InvalidArgumentException
        ? $error->getMessage()
        : 'No se pudo registrar el docente. Verifique la cuenta y las materias seleccionadas.');
}

$_SESSION['mensaje'] =
    'El docente fue registrado correctamente.';

$_SESSION['tipo_mensaje'] = 'success';

header('Location: listar.php');
exit;