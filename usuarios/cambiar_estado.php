<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('usuarios/cambiar_estado.php');
require_once __DIR__ . '/../config/conexion.php';
$id = filter_input(INPUT_POST, 'cod_usuario', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(422); exit('Usuario no válido.'); }
try {
    $conexion->begin_transaction();
    $administradores = $conexion->query("SELECT cod_usuario FROM usuarios WHERE cod_rol=1 AND estado='Activo' ORDER BY cod_usuario FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
    $stmt = $conexion->prepare('SELECT estado,cod_rol FROM usuarios WHERE cod_usuario=? FOR UPDATE');
    $stmt->bind_param('i', $id); $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$usuario) throw new InvalidArgumentException('Usuario no encontrado.');
    $rol = (int)$_SESSION['cod_rol'];
    if ($rol !== 1 && ((int)$usuario['cod_rol'] === 1 || (int)$usuario['cod_rol'] !== $rol)) {
        $conexion->rollback(); http_response_code(403); exit('Solo puedes cambiar cuentas de tu mismo rol.');
    }
    if ($usuario['estado'] === 'Activo' && (int)$usuario['cod_rol'] === 1 && count($administradores) <= 1) {
        throw new InvalidArgumentException('Debe conservarse al menos un administrador activo.');
    }
    $estado = $usuario['estado'] === 'Activo' ? 'Inactivo' : 'Activo';
    $stmt = $conexion->prepare('UPDATE usuarios SET estado=? WHERE cod_usuario=?');
    $stmt->bind_param('si', $estado, $id); $stmt->execute(); $stmt->close();
    seguridad_evento($conexion, (int)$_SESSION['cod_usuario'], 'usuarios', $estado === 'Activo' ? 'Activar' : 'Desactivar', $id);
    $conexion->commit();
    $_SESSION['mensaje'] = 'Estado del usuario actualizado.';
    $_SESSION['tipo_mensaje'] = 'success';
} catch (Throwable $error) {
    $conexion->rollback();
    $_SESSION['mensaje'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo actualizar la cuenta.';
    $_SESSION['tipo_mensaje'] = 'danger';
}
login_ir(login_puede('usuarios/listar.php') ? 'usuarios/listar.php' : login_destino_ruta());