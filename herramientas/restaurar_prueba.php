<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/respaldo_lib.php';
$proceso = null;
try {
    $o = getopt('', ['copia:', 'clave:', 'base:', 'archivos:']);
    $base = $o['base'] ?? '';
    if (!is_string($base) || !preg_match('/^psicologia_restore_[a-f0-9]{12}$/D', $base)) throw new RuntimeException('Use una base nueva psicologia_restore_ seguida de 12 caracteres hexadecimales.');
    $copia = respaldo_fuera_web($o['copia'] ?? '');
    $destino = respaldo_fuera_web($o['archivos'] ?? '');
    if (count(scandir($destino)) !== 2) throw new RuntimeException('La carpeta para adjuntos restaurados debe estar vacía.');
    $clave = respaldo_clave($o['clave'] ?? '', $copia);
    $f = fopen('php://memory','w+'); respaldo_descifrar($copia.'/manifest.enc',$f,$clave); rewind($f);
    $manifest = json_decode(stream_get_contents($f),true,512,JSON_THROW_ON_ERROR); fclose($f);
    if (($manifest['formato']??null)!==1 || !isset($manifest['archivos']['base.enc'])) throw new RuntimeException('Manifiesto no válido.');
    // Autenticar TODOS los marcos antes de escribir siquiera el esquema de destino.
    foreach ($manifest['archivos'] as $nombre=>$hash) {
        if (!preg_match('/^(base|anexo-\d+|codigo-\d+)\.enc$/D',$nombre) || !hash_equals($hash,hash_file('sha256',$copia.'/'.$nombre))) throw new RuntimeException('Integridad del archivo incorrecta.');
        $nulo = fopen(PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','wb');
        try { respaldo_descifrar($copia.'/'.$nombre,$nulo,$clave); } finally { fclose($nulo); }
    }
    $config = require __DIR__ . '/../config/login.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $bd = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    // CREATE sin IF NOT EXISTS: una restauración nunca pisa una base preexistente.
    $bd->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    [$args,$env] = respaldo_comando($config,'mysql',[$base]);
    $errorArchivo = $destino.'/restauracion.tmp';
    $proceso = proc_open($args,[0=>['pipe','r'],1=>['file',$errorArchivo,'a'],2=>['file',$errorArchivo,'a']],$pipes,null,$env);
    if (!is_resource($proceso)) throw new RuntimeException('No se pudo iniciar mysql.');
    respaldo_descifrar($copia.'/base.enc',$pipes[0],$clave); fclose($pipes[0]);
    $codigo=proc_close($proceso);$proceso=null;unlink($errorArchivo);
    if ($codigo!==0) throw new RuntimeException('Restauración fallida. Conservar el destino aislado para diagnóstico.');
    foreach ($manifest['anexos'] as $nombre=>$anexo) {
        if (!preg_match('#^derivaciones/evidencia_[a-f0-9]{32}\.(jpg|png|webp)$#D',$anexo['ruta']) || !isset($manifest['archivos'][$nombre])) throw new RuntimeException('Ruta de adjunto no válida.');
        $archivo = $destino.'/'.basename($anexo['ruta']); $f=fopen($archivo,'xb');
        if (!$f) throw new RuntimeException('No se pudo recuperar el adjunto.');
        chmod($archivo,0600);
        try { respaldo_descifrar($copia.'/'.$nombre,$f,$clave); } finally { fclose($f); }
        if (!hash_equals($anexo['sha256'],hash_file('sha256',$archivo))) throw new RuntimeException('Adjunto restaurado diferente.');
    }
    foreach ($manifest['codigo']??[] as $nombre=>$entrada) {
        $ruta=$entrada['ruta'];
        if (!is_string($ruta) || str_contains($ruta,'..') || !preg_match('#^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+$#D',$ruta) || !isset($manifest['archivos'][$nombre])) throw new RuntimeException('Ruta de código no válida.');
        $archivo=$destino.'/codigo/'.$ruta;
        if (!is_dir(dirname($archivo)) && !mkdir(dirname($archivo),0700,true)) throw new RuntimeException('No se pudo crear la carpeta de código.');
        $f=fopen($archivo,'xb');if(!$f)throw new RuntimeException('No se pudo recuperar el código.');chmod($archivo,0600);
        try { respaldo_descifrar($copia.'/'.$nombre,$f,$clave); } finally { fclose($f); }
        if (!hash_equals($entrada['sha256'],hash_file('sha256',$archivo))) throw new RuntimeException('Código restaurado diferente.');
    }
    sodium_memzero($clave);
    echo "Restauración aislada completada: $base. Falta validación funcional antes de su uso.\n";
} catch (Throwable $error) {
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    fwrite(STDERR,$error instanceof mysqli_sql_exception ? "Destino existente o error de base de datos. No se sobrescribe.\n" : $error->getMessage()."\n"); exit(1);
}
