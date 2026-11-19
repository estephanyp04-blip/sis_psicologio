<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function respaldo_fuera_web(string $ruta): string
{
    $real = realpath($ruta);
    if ($real === false || !is_dir($real) || is_link($ruta)) throw new RuntimeException('La carpeta debe existir y ser una ruta real.');
    $web = getenv('APP_WEB_ROOT') ?: dirname(__DIR__, 2); // XAMPP: htdocs. En hosting configurarlo explícitamente.
    $web = realpath($web);
    if ($web === false) throw new RuntimeException('APP_WEB_ROOT no es válido.');
    $normalizar = static fn(string $p): string => strtolower(str_replace('\\', '/', rtrim($p, '/\\')));
    $realNormal = $normalizar($real); $webNormal = $normalizar($web);
    if ($realNormal === $webNormal || str_starts_with($realNormal . '/', $webNormal . '/')) throw new RuntimeException('Se rechaza una ubicación dentro de la raíz web.');
    return $real;
}

function respaldo_clave(string $archivo, string $destino): string
{
    if (!extension_loaded('sodium')) throw new RuntimeException('Habilite la extensión sodium para respaldar y restaurar.');
    $real = realpath($archivo);
    if ($real === false || !is_file($real)) throw new RuntimeException('No se encontró la clave externa.');
    $carpeta = respaldo_fuera_web(dirname($real));
    $copia = strtolower(str_replace('\\','/',realpath($destino) ?: $destino));
    $claveDir = strtolower(str_replace('\\','/',$carpeta));
    if ($copia === $claveDir || str_starts_with($claveDir . '/', $copia . '/') || str_starts_with($copia . '/', $claveDir . '/')) {
        throw new RuntimeException('La clave y las copias deben estar en carpetas separadas, sin anidamiento.');
    }
    $clave = file_get_contents($real);
    if (strlen($clave) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) throw new RuntimeException('Clave de respaldo no válida.');
    return $clave;
}

function respaldo_escribir($salida, string $datos): void
{
    while ($datos !== '') {
        $n = fwrite($salida, $datos);
        if ($n === false || $n === 0) throw new RuntimeException('No se pudo escribir la copia.');
        $datos = substr($datos, $n);
    }
}

/** Secretstream autenticado de libsodium; marcos de hasta 64 KiB, terminador obligatorio. */
function respaldo_cifrar($entrada, string $archivo, #[SensitiveParameter] string $clave): void
{
    $salida = fopen($archivo, 'xb');
    if (!$salida) throw new RuntimeException('La copia ya existe o no puede crearse.');
    chmod($archivo, 0600);
    try {
        [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clave);
        respaldo_escribir($salida, "PSICOBK1\n" . $cabecera);
        while (!feof($entrada)) {
            $trozo = fread($entrada, 65536);
            if ($trozo === false) throw new RuntimeException('No se pudo leer el origen.');
            if ($trozo === '') continue;
            $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push($estado, $trozo);
            respaldo_escribir($salida, pack('N', strlen($cifrado)) . $cifrado);
        }
        $final = sodium_crypto_secretstream_xchacha20poly1305_push($estado, '', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        respaldo_escribir($salida, pack('N', strlen($final)) . $final);
        fflush($salida);
    } finally { fclose($salida); }
}

function respaldo_leer($entrada, int $n): string
{
    $datos = '';
    while (strlen($datos) < $n) {
        $trozo = fread($entrada, $n - strlen($datos));
        if ($trozo === false || $trozo === '') throw new RuntimeException('Copia truncada.');
        $datos .= $trozo;
    }
    return $datos;
}

function respaldo_descifrar(string $archivo, $salida, #[SensitiveParameter] string $clave): void
{
    $entrada = fopen($archivo, 'rb');
    if (!$entrada) throw new RuntimeException('No se pudo leer la copia.');
    try {
        if (respaldo_leer($entrada, 9) !== "PSICOBK1\n") throw new RuntimeException('Formato de copia desconocido.');
        $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull(respaldo_leer($entrada, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $clave);
        do {
            $n = unpack('N', respaldo_leer($entrada, 4))[1];
            if ($n < 17 || $n > 65553) throw new RuntimeException('Marco de copia no válido.');
            $abierto = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, respaldo_leer($entrada, $n));
            if ($abierto === false) throw new RuntimeException('Integridad o clave de copia incorrecta.');
            [$texto, $tag] = $abierto;
            respaldo_escribir($salida, $texto);
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        if (fread($entrada, 1) !== '') throw new RuntimeException('Contenido adicional en la copia.');
    } finally { fclose($entrada); }
}

function respaldo_cliente(string $nombre): string
{
    $carpeta = getenv('DB_CLIENT_DIR') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin' : '/usr/bin');
    $ruta = $carpeta . '/' . $nombre . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    if (!is_file($ruta)) throw new RuntimeException('Configure DB_CLIENT_DIR con los clientes de MariaDB/MySQL.');
    return $ruta;
}

function respaldo_comando(array $config, string $cliente, array $argumentos): array
{
    // Contraseña solo en el entorno del subproceso, nunca en argumentos ni archivos.
    $entorno = getenv(); $entorno['MYSQL_PWD'] = $config['clave_bd'];
    $args = [respaldo_cliente($cliente), '--no-defaults', '--host=' . $config['host'], '--port=' . $config['puerto'],
        '--user=' . $config['usuario_bd'], '--default-character-set=utf8mb4'];
    return [array_merge($args, $argumentos), $entorno];
}
