<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/actualizar.php');

require_once '../config/conexion.php';
require_once __DIR__ . '/../includes/claves.php';

function regresarAEdicion(string $mensaje, int $idUsuario): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = 'danger';

    $destino = $idUsuario > 0
        ? 'editar.php?id=' . $idUsuario
        : 'listar.php';

    header('Location: ' . $destino);
    exit;
}

function longitudTexto(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$idUsuario = filter_input(INPUT_POST, 'cod_usuario', FILTER_VALIDATE_INT);
$idUsuario = $idUsuario !== false && $idUsuario !== null
    ? (int) $idUsuario
    : 0;

if ($idUsuario <= 0) {
    regresarAEdicion('El usuario seleccionado no es válido.', 0);
}

$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$idRol = filter_input(INPUT_POST, 'cod_rol', FILTER_VALIDATE_INT);
$idRol = $idRol !== false && $idRol !== null ? (int) $idRol : 0;
$estado = trim($_POST['estado'] ?? '');
$password = $_POST['password'] ?? '';
$confirmarPassword = $_POST['confirmar_password'] ?? '';

if (
    $nombre === ''
    || $apellido === ''
    || $usuario === ''
    || $idRol <= 0
    || $estado === ''
) {
    regresarAEdicion(
        'Complete todos los campos obligatorios.',
        $idUsuario
    );
}

if (longitudTexto($nombre) > 50) {
    regresarAEdicion(
        'El nombre no puede superar los 50 caracteres.',
        $idUsuario
    );
}

if (longitudTexto($apellido) > 50) {
    regresarAEdicion(
        'El apellido no puede superar los 50 caracteres.',
        $idUsuario
    );
}

if (longitudTexto($usuario) > 30) {
    regresarAEdicion(
        'El usuario no puede superar los 30 caracteres.',
        $idUsuario
    );
}

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    regresarAEdicion('El correo electrónico no es válido.', $idUsuario);
}

if (longitudTexto($correo) > 100) {
    regresarAEdicion(
        'El correo no puede superar los 100 caracteres.',
        $idUsuario
    );
}

if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
    regresarAEdicion('El estado seleccionado no es válido.', $idUsuario);
}

$cambiarPassword = $password !== '' || $confirmarPassword !== '';

if ($cambiarPassword) {
    if ($password === '' || $confirmarPassword === '') {
        regresarAEdicion(
            'Complete y confirme la nueva contraseña.',
            $idUsuario
        );
    }

    if (!login_clave_valida($password)) {
        regresarAEdicion(
            'La contraseña debe tener al menos 8 caracteres y no superar 72 bytes.',
            $idUsuario
        );
    }

    if ($password !== $confirmarPassword) {
        regresarAEdicion('Las contraseñas no coinciden.', $idUsuario);
    }
}

try {
    $stmtUsuario = $conexion->prepare(
        'SELECT cod_usuario,cod_rol FROM usuarios WHERE cod_usuario = ? LIMIT 1'
    );
    $stmtUsuario->bind_param('i', $idUsuario);
    $stmtUsuario->execute();

    $usuarioObjetivo = $stmtUsuario->get_result()->fetch_assoc();
    if (!$usuarioObjetivo) {
        regresarAEdicion('El usuario que desea actualizar no existe.', 0);
    }
    if ((int)($_SESSION['cod_rol'] ?? 0) !== 1
        && ((int)$usuarioObjetivo['cod_rol'] === 1
            || (int)$usuarioObjetivo['cod_rol'] !== (int)($_SESSION['cod_rol'] ?? 0)
            || $idRol !== (int)($_SESSION['cod_rol'] ?? 0))) {
        http_response_code(403);
        exit('Solo puedes editar cuentas de tu mismo rol.');
    }

    $stmtRol = $conexion->prepare(
        "SELECT cod_rol FROM roles WHERE cod_rol = ? AND estado='Activo' LIMIT 1"
    );
    $stmtRol->bind_param('i', $idRol);
    $stmtRol->execute();

    if ($stmtRol->get_result()->num_rows === 0) {
        regresarAEdicion('El rol seleccionado no existe.', $idUsuario);
    }

    $stmtDuplicado = $conexion->prepare(
        'SELECT cod_usuario
         FROM usuarios
         WHERE usuario = ? AND cod_usuario <> ?
         LIMIT 1'
    );
    $stmtDuplicado->bind_param('si', $usuario, $idUsuario);
    $stmtDuplicado->execute();

    if ($stmtDuplicado->get_result()->num_rows > 0) {
        regresarAEdicion(
            'El nombre de usuario ya está registrado.',
            $idUsuario
        );
    }

    $correoBaseDatos = $correo !== '' ? $correo : null;

    $conexion->begin_transaction();
    $stmtPersona = $conexion->prepare(
        'UPDATE personas p
         INNER JOIN usuarios u ON u.cod_persona = p.cod_persona
         SET p.nombres = ?, p.apellidos = ?, p.correo = ?
         WHERE u.cod_usuario = ?'
    );
    $stmtPersona->bind_param('sssi', $nombre, $apellido, $correoBaseDatos, $idUsuario);
    $stmtPersona->execute();
    $stmtPersona->close();

    if ($cambiarPassword) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            regresarAEdicion(
                'No se pudo proteger la nueva contraseña.',
                $idUsuario
            );
        }

        $stmtActualizar = $conexion->prepare(
            'UPDATE usuarios
             SET usuario = ?, password = ?, estado = ?, cod_rol = ?
             WHERE cod_usuario = ?'
        );
        $stmtActualizar->bind_param(
            'sssii',
            $usuario,
            $passwordHash,
            $estado,
            $idRol,
            $idUsuario
        );
    } else {
        $stmtActualizar = $conexion->prepare(
            'UPDATE usuarios
             SET usuario = ?, estado = ?, cod_rol = ?
             WHERE cod_usuario = ?'
        );
        $stmtActualizar->bind_param(
            'ssii',
            $usuario,
            $estado,
            $idRol,
            $idUsuario
        );
    }

    $stmtActualizar->execute();
    $stmtActualizar->close();
    $conexion->commit();
} catch (mysqli_sql_exception $error) {
    $conexion->rollback();
    error_log('Error al actualizar usuario: ' . $error->getMessage());
    regresarAEdicion(
        'No se pudo actualizar el usuario. Inténtelo nuevamente.',
        $idUsuario
    );
}

if ((int) ($_SESSION['cod_usuario'] ?? 0) === $idUsuario) {
    $_SESSION['nombre'] = $nombre;
    $_SESSION['apellido'] = $apellido;
    $_SESSION['nombre_completo'] = trim($nombre . ' ' . $apellido);
    $_SESSION['cod_rol'] = $idRol;
}

$_SESSION['mensaje'] = 'Usuario actualizado correctamente.';
$_SESSION['tipo_mensaje'] = 'success';

if (login_puede('usuarios/listar.php')) {
    header('Location: listar.php');
} else {
    login_ir(login_destino_ruta());
}
exit;
