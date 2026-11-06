<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('roles/guardar.php');
require_once __DIR__ . '/../config/conexion.php';

function roles_registro_error(string $mensaje): never
{
    $_SESSION['mensaje'] = $mensaje;
    header('Location: registrar.php', true, 303);
    exit;
}

$nombre = trim(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '');
$descripcion = trim(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : '');
$entrada = is_array($_POST['permisos'] ?? null) ? $_POST['permisos'] : [];
$longitudNombre = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
$longitudDescripcion = function_exists('mb_strlen') ? mb_strlen($descripcion, 'UTF-8') : strlen($descripcion);
if ($nombre === '' || $longitudNombre > 40 || $longitudDescripcion > 255) {
    roles_registro_error('Escribe un nombre de hasta 40 caracteres y una descripción de hasta 255.');
}

$stmt = $conexion->prepare('SELECT cod_rol FROM roles WHERE nombre=? LIMIT 1');
$stmt->bind_param('s', $nombre);
$stmt->execute();
$duplicado = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($duplicado) roles_registro_error('Ya existe un rol con ese nombre.');

$modulos = $conexion->query("SELECT cod_modulo,nombre FROM modulos WHERE estado='Activo' ORDER BY cod_modulo")->fetch_all(MYSQLI_ASSOC);
$modulosConCambioEstado = ['Usuarios', 'Estudiantes', 'Derivaciones', 'Citas', 'Docentes'];
try {
    $conexion->begin_transaction();
    $estado = 'Activo';
    $descripcionDb = $descripcion === '' ? null : $descripcion;
    $stmt = $conexion->prepare('INSERT INTO roles (nombre,descripcion,estado) VALUES (?,?,?)');
    $stmt->bind_param('sss', $nombre, $descripcionDb, $estado);
    $stmt->execute();
    $idRol = (int) $conexion->insert_id;
    $stmt->close();

    $stmtPermiso = $conexion->prepare('INSERT INTO permisos (cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar,puede_cambiar_estado) VALUES (?,?,?,?,?,?,?)');
    foreach ($modulos as $modulo) {
        $idModulo = (int) $modulo['cod_modulo'];
        $marcados = $modulo['nombre'] === 'Roles y permisos' ? [] : (is_array($entrada[$idModulo] ?? null) ? $entrada[$idModulo] : []);
        $ver = isset($marcados['puede_ver']) ? 1 : 0;
        $crear = isset($marcados['puede_crear']) ? 1 : 0;
        $editar = isset($marcados['puede_editar']) ? 1 : 0;
        $eliminar = isset($marcados['puede_eliminar']) ? 1 : 0;
        $cambiarEstado = in_array($modulo['nombre'], $modulosConCambioEstado, true) && isset($marcados['puede_cambiar_estado']) ? 1 : 0;
        $stmtPermiso->bind_param('iiiiiii', $idRol, $idModulo, $ver, $crear, $editar, $eliminar, $cambiarEstado);
        $stmtPermiso->execute();
    }
    $stmtPermiso->close();
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    error_log('Crear rol: ' . $e->getMessage());
    roles_registro_error('No se pudo guardar el rol. Revisa los datos e inténtalo de nuevo.');
}

$_SESSION['mensaje'] = 'El rol se creó con sus permisos.';
$_SESSION['tipo_mensaje'] = 'success';
header('Location: listar.php', true, 303);
exit;
