<?php
// Ejecutar únicamente desde la terminal local; no permite cambiar roles.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/autenticacion.php';
if ($argc !== 2) exit("Uso: php herramientas/establecer_clave.php nombre_usuario\n");
try {
    $u = login_buscar_usuario($argv[1]);
    if (!$u) exit("No existe un usuario único con ese nombre. Revisa usuarios.usuario.\n");
    echo 'Cuenta: ' . $u['usuario'] . ' | ' . $u['nombre'] . ' ' . $u['apellido'] . ' | ' . $u['rol'] . "\n";
    echo "Nueva contraseña (12 a 72 bytes; será visible al escribir): ";
    $clave = rtrim(fgets(STDIN), "\r\n");
    echo "Repítela: ";
    $confirmacion = rtrim(fgets(STDIN), "\r\n");
    if (strlen($clave) < 12 || strlen($clave) > 72 || strpos($clave, "\0") !== false || !hash_equals($clave, $confirmacion)) exit("Las contraseñas no coinciden o no cumplen la longitud. No se cambió nada.\n");
    $hash = password_hash($clave, PASSWORD_DEFAULT);
    $s = login_bd()->prepare('UPDATE usuarios SET password=? WHERE id_usuario=?');
    $s->bind_param('si', $hash, $u['id_usuario']);
    $s->execute();
    $s->close();
    unset($clave, $confirmacion);
    echo "Contraseña guardada con hash. Ya puedes iniciar sesión si el usuario y su rol están activos.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo actualizar la contraseña. Revisa config/login.php y la estructura de usuarios y roles.\n");
    exit(1);
}
