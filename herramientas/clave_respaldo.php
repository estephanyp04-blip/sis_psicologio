<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/respaldo_lib.php';
try {
    $opciones = getopt('', ['destino:']);
    $ruta = $opciones['destino'] ?? '';
    if (!is_string($ruta) || $ruta === '') throw new RuntimeException('Uso: php -d extension=sodium herramientas/clave_respaldo.php --destino=RUTA_EXTERNA');
    $carpeta = respaldo_fuera_web(dirname($ruta));
    if (!extension_loaded('sodium')) throw new RuntimeException('Habilite sodium.');
    $f = fopen($carpeta . '/' . basename($ruta), 'xb');
    if (!$f) throw new RuntimeException('La clave ya existe o no puede crearse.');
    chmod($ruta, 0600);
    respaldo_escribir($f, sodium_crypto_secretstream_xchacha20poly1305_keygen()); fclose($f);
    echo "Clave creada. Proteja su ACL y conserve otra copia bajo custodia separada. No se muestra su contenido.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
