<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/respaldo_lib.php';
require __DIR__ . '/../derivaciones/datos.php';
$salida = null; $proceso = null;
try {
    $o = getopt('', ['destino:', 'clave:', 'base:']);
    $config = require __DIR__ . '/../config/login.php';
    $base = $o['base'] ?? $config['base_datos'];
    if (!is_string($base) || !preg_match('/^[a-zA-Z0-9_]+$/D', $base)) throw new RuntimeException('Nombre de base no válido.');
    $destino = respaldo_fuera_web($o['destino'] ?? '');
    $clave = respaldo_clave($o['clave'] ?? '', $destino);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $bd = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$base,$config['puerto']); $bd->set_charset('utf8mb4');
    if ($bd->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB'")->num_rows) throw new RuntimeException('La copia consistente exige tablas InnoDB.');
    $anexos = $bd->query('SELECT ruta FROM derivacion_evidencias ORDER BY cod_evidencia')->fetch_all(MYSQLI_ASSOC);
    $salida = $destino . '/respaldo-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
    if (!mkdir($salida, 0700)) throw new RuntimeException('No se pudo crear el destino.');
    [$args, $env] = respaldo_comando($config, 'mysqldump', ['--single-transaction','--quick','--skip-lock-tables', '--skip-add-locks', '--hex-blob','--routines','--events','--triggers',$base]);
    $proceso = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['file',$salida.'/error.tmp','w']], $pipes, null, $env);
    if (!is_resource($proceso)) throw new RuntimeException('No se pudo iniciar mysqldump.');
    fclose($pipes[0]);
    respaldo_cifrar($pipes[1], $salida.'/base.enc', $clave); fclose($pipes[1]);
    $codigo = proc_close($proceso); $proceso = null; unlink($salida.'/error.tmp');
    if ($codigo !== 0) throw new RuntimeException('mysqldump falló. La copia no se considera válida.');
    $manifest = ['formato'=>1,'base'=>$base,'fecha_utc'=>gmdate('c'),'php'=>PHP_VERSION,'archivos'=>['base.enc'=>hash_file('sha256',$salida.'/base.enc')], 'anexos'=>[], 'codigo'=>[],
        'codigo_sha256'=>hash_file('sha256',dirname(__DIR__).'/database/psicologia_db.sql')];
    foreach ($anexos as $i => $fila) {
        $local = derivacion_imagen_local($fila['ruta']);
        if ($local === null) throw new RuntimeException('Falta un adjunto o su ruta no es segura.');
        $nombre = 'anexo-' . $i . '.enc'; $f = fopen($local, 'rb');
        try { respaldo_cifrar($f, $salida.'/'.$nombre, $clave); } finally { fclose($f); }
        $manifest['archivos'][$nombre] = hash_file('sha256',$salida.'/'.$nombre);
        $manifest['anexos'][$nombre] = ['ruta'=>$fila['ruta'],'sha256'=>hash_file('sha256',$local)];
    }
    // Código, bibliotecas y recursos de ejecución. Nunca recorrer .git ni backups.
    $raiz = dirname(__DIR__);
    $directorios = ['asset','config','includes','citas','derivaciones','docentes','estadisticas','estudiantes','historias_clinicas','informes','roles','seguimientos','usuarios','herramientas','database/migrations','database/migrations_normalizadas'];
    $codigo = array_merge(glob($raiz.'/*.php'), [$raiz.'/.htaccess',$raiz.'/.user.ini',$raiz.'/database/migrar.php',$raiz.'/database/psicologia_db.sql']);
    foreach ($directorios as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if (!$archivo->isFile() || $archivo->isLink() || str_starts_with($archivo->getBasename(),'evidencia_')) continue;
            if (in_array(strtolower($archivo->getExtension()),['php','css','js','png','jpg','jpeg','svg','ico','webp','woff','woff2','ttf','eot'],true) || $archivo->getBasename()==='.htaccess') $codigo[]=$archivo->getPathname();
        }
    }
    foreach ($codigo as $i=>$archivo) {
        $rutaCodigo = str_replace('\\','/',substr($archivo,strlen($raiz)+1));
        $nombre='codigo-'.$i.'.enc';$f=fopen($archivo,'rb');
        try { respaldo_cifrar($f,$salida.'/'.$nombre,$clave); } finally { fclose($f); }
        $manifest['archivos'][$nombre]=hash_file('sha256',$salida.'/'.$nombre);
        $manifest['codigo'][$nombre]=['ruta'=>$rutaCodigo,'sha256'=>hash_file('sha256',$archivo)];
    }
    $f = fopen('php://memory','w+'); fwrite($f,json_encode($manifest,JSON_THROW_ON_ERROR)); rewind($f);
    respaldo_cifrar($f,$salida.'/manifest.enc',$clave); fclose($f); sodium_memzero($clave);
    echo "Copia cifrada creada: $salida\nDebe probar su restauración antes de declararla recuperable.\n";
} catch (Throwable $error) {
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    if ($salida !== null && is_file($salida.'/error.tmp')) unlink($salida.'/error.tmp');
    // No borrar copias automáticamente; una carpeta sin manifest.enc es incompleta.
    fwrite(STDERR, $error instanceof mysqli_sql_exception ? "Fallo de base de datos al respaldar.\n" : $error->getMessage()."\n"); exit(1);
} finally {
    if ($salida !== null && is_file($salida.'/error.tmp')) unlink($salida.'/error.tmp');
}
