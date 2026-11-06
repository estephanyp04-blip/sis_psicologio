<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('roles/actualizar.php');
require_once __DIR__ . '/../config/conexion.php';

function roles_actualizar_error(string $mensaje, int $idRol): void
{
    $_SESSION['mensaje'] = $mensaje;
    header('Location: editar.php?id=' . $idRol, true, 303);
    exit;
}

$idRol = filter_input(INPUT_POST, 'cod_rol', FILTER_VALIDATE_INT);
$idRol = $idRol ? (int)$idRol : 0;
$nombre = trim(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '');
$descripcion = trim(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : '');
$estado = $_POST['estado'] ?? '';
$entrada = is_array($_POST['permisos'] ?? null) ? $_POST['permisos'] : [];
$longitudNombre = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
$longitudDescripcion = function_exists('mb_strlen') ? mb_strlen($descripcion, 'UTF-8') : strlen($descripcion);
if ($idRol <= 1) { http_response_code(403); exit('No se puede modificar el rol administrador.'); }
if ($nombre === '' || $longitudNombre > 40 || $longitudDescripcion > 255 || !in_array($estado, ['Activo', 'Inactivo'], true)) {
    roles_actualizar_error('Revisa el nombre, la descripción y el estado del rol.', $idRol);
}

$stmt = $conexion->prepare('SELECT cod_rol FROM roles WHERE nombre=? AND cod_rol<>? LIMIT 1');
$stmt->bind_param('si', $nombre, $idRol);
$stmt->execute();
$duplicado = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($duplicado) roles_actualizar_error('Ya existe otro rol con ese nombre.', $idRol);

$modulos = $conexion->query('SELECT cod_modulo,nombre FROM modulos ORDER BY cod_modulo')->fetch_all(MYSQLI_ASSOC);
$modulosConCambioEstado = ['Usuarios', 'Estudiantes', 'Derivaciones', 'Citas', 'Docentes'];
$modulosBloqueadosRolesBase = ['Dashboard', 'Estadísticas', 'Estudiantes'];
try {
    $conexion->begin_transaction();
    $descripcionDb = $descripcion === '' ? null : $descripcion;
    $stmt = $conexion->prepare('UPDATE roles SET nombre=?,descripcion=?,estado=? WHERE cod_rol=?');
    $stmt->bind_param('sssi', $nombre, $descripcionDb, $estado, $idRol);
    $stmt->execute();
    $stmt->close();

    $stmtPermiso = $conexion->prepare('INSERT INTO permisos (cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar,puede_cambiar_estado)
        VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE puede_ver=VALUES(puede_ver),puede_crear=VALUES(puede_crear),puede_editar=VALUES(puede_editar),puede_eliminar=VALUES(puede_eliminar),puede_cambiar_estado=VALUES(puede_cambiar_estado)');
    foreach ($modulos as $modulo) {
        $idModulo = (int)$modulo['cod_modulo'];
        $bloqueadoPorPolitica = in_array($idRol, [3, 4], true) && in_array($modulo['nombre'], $modulosBloqueadosRolesBase, true);
        $marcados = $modulo['nombre'] === 'Roles y permisos' || $bloqueadoPorPolitica ? [] : (is_array($entrada[$idModulo] ?? null) ? $entrada[$idModulo] : []);
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
    error_log('Actualizar rol: ' . $e->getMessage());
    roles_actualizar_error('No se pudieron guardar los cambios del rol.', $idRol);
}

$_SESSION['mensaje'] = 'Los permisos del rol se actualizaron.';
$_SESSION['tipo_mensaje'] = 'success';
header('Location: listar.php', true, 303);
exit;
