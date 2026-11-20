<?php
/** Prueba local aislada: puente PHP y navegador real; no usa la base de datos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Reutilizar el escape real sin conectar a la BD ni duplicar login_html().
require_once dirname(__DIR__) . '/includes/autenticacion.php';
$total = 0;
function comprobar_aviso(bool $ok, string $mensaje): void {
    global $total; ++$total;
    if (!$ok) throw new RuntimeException($mensaje);
}
$raiz = dirname(__DIR__);
$temporal = sys_get_temp_dir() . '/psicologia-avisos-' . bin2hex(random_bytes(6));
mkdir($temporal, 0700);
$servidor = null;
$fallo = false;
try {
    $_SESSION = [];
    $_POST = ['nombre' => '</script><script>prueba</script>', 'password' => 'secreto', 'csrf' => 'secreto', 'permisos' => ['3' => ['puede_ver' => 1, 'inventado' => 1]]];
    ui_recordar_entrada('rol', ['nombre','permisos','password','csrf'], 7);
    comprobar_aviso(!isset($_SESSION['ui_entrada']['rol']['datos']['password']) && !isset($_SESSION['ui_entrada']['rol']['datos']['csrf']), 'Se guardaron secretos');
    $datos = ui_entrada_recuperar('rol',7);
    comprobar_aviso($datos['permisos'] === [3 => ['puede_ver' => '1']], 'Permisos no filtrados');
    comprobar_aviso(ui_entrada_recuperar('rol',7) === null, 'Recuperación no es de un solo uso');
    ui_recordar_entrada('rol',['nombre'],7);
    comprobar_aviso(ui_entrada_recuperar('rol',8) === null, 'Datos restaurados a otro registro');
    ui_recordar_entrada('rol',['nombre'],7); $_SESSION['ui_entrada']['rol']['hasta'] = time()-1;
    comprobar_aviso(ui_entrada_recuperar('rol',7) === null, 'Entrada caducada restaurada');
    ob_start(); ui_entrada_emitir($datos); $json = ob_get_clean();
    comprobar_aviso(substr_count($json,'</script>') === 1 && str_contains($json,'\\u003C'), 'JSON permite cierre de script');
    $_POST=[]; ui_recordar_entrada('rol',['permisos'],7);
    comprobar_aviso(ui_entrada_recuperar('rol',7)['permisos'] === [], 'Casillas desmarcadas no recuperadas');
    $_SESSION=['mensaje'=>'<dato sintético>', 'tipo_mensaje'=>'invalido'];
    ob_start(); ui_aviso_pendiente(); $flash=ob_get_clean();
    comprobar_aviso(str_contains($flash,'alert-info') && str_contains($flash,'&lt;dato') && !isset($_SESSION['mensaje']), 'Flash no escapado o no consumido');

    $chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
    if (!is_file($chrome)) throw new RuntimeException('Chrome no disponible para las pruebas de interfaz.');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    $puerto=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1); fclose($socket);
    $router= <<<'PHP'
<?php
$raiz=__RAIZ__;
$ruta=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:");
if ($ruta==='/guardar' && $_SERVER['REQUEST_METHOD']==='POST') {
    $archivo=__DIR__.'/contador'; $n=is_file($archivo)?(int)file_get_contents($archivo):0;
    file_put_contents($archivo,(string)++$n);
    header('Content-Type: text/html; charset=utf-8');
    echo htmlspecialchars(json_encode(['contador'=>$n,'datos'=>$_POST]),ENT_QUOTES,'UTF-8'); return;
}
if ($ruta==='/') {
    if (isset($_GET['fixture'])) { define('PRUEBA_AVISOS',true); require $raiz.'/tests/avisos_fixture.php'; return; }
    $ancho=(int)($_GET['ancho']??1440);
    echo '<!doctype html><iframe id="app" style="border:0;width:'.$ancho.'px;height:1800px" src="/?fixture=1"></iframe><pre id="resultado">Pendiente</pre><script>setInterval(()=>{const r=document.getElementById("app").contentDocument?.getElementById("resultado");if(r)document.getElementById("resultado").textContent=r.textContent},50)</script>'; return;
}
if (in_array($ruta,['/asset/css/avisos.css','/asset/css/estilos.css','/asset/js/avisos.js'],true)) {
    header('Content-Type: '.(str_ends_with($ruta,'.js')?'application/javascript':'text/css')); readfile($raiz.$ruta); return;
}
http_response_code(404);
PHP;
    file_put_contents($temporal.'/router.php',str_replace('__RAIZ__',var_export($raiz,true),$router));
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,$temporal.'/router.php'],[0=>['pipe','r'],1=>['file',$temporal.'/servidor.log','a'],2=>['file',$temporal.'/servidor.log','a']],$pipes,$raiz);
    if (!is_resource($servidor)) throw new RuntimeException('No se pudo iniciar la fixture.');
    fclose($pipes[0]); usleep(300000);
    foreach ([1440,768,390,320] as $ancho) {
        file_put_contents($temporal.'/contador','0');
        $args=[$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--user-data-dir='.$temporal.'/chrome-'.$ancho,'--window-size=1600,2200','--dump-dom','--virtual-time-budget=16000'];
        $capturas = getenv('CAPTURAS_AVISOS');
        if ($capturas && is_dir($capturas)) $args[]='--screenshot='.$capturas.'/avisos-'.$ancho.'.png';
        $args[]='http://127.0.0.1:'.$puerto.'/?ancho='.$ancho;
        $proceso=proc_open($args,[0=>['pipe','r'],1=>['file',$temporal.'/dom.html','w'],2=>['file',$temporal.'/chrome.log','w']],$pipes);
        if (!is_resource($proceso)) throw new RuntimeException('No se pudo iniciar Chrome.');
        fclose($pipes[0]);
        $hasta=microtime(true)+45;
        do { $estado=proc_get_status($proceso); if (!$estado['running']) break; usleep(100000); } while(microtime(true)<$hasta);
        if ($estado['running']) proc_terminate($proceso);
        proc_close($proceso);
        $dom=new DOMDocument(); @$dom->loadHTML(file_get_contents($temporal.'/dom.html'));
        $resultado=json_decode($dom->getElementById('resultado')?->textContent??'',true);
        comprobar_aviso(($resultado['ok']??false)===true, 'Chrome '.$ancho.' px: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
        echo 'Chrome '.$ancho.' px: '.$resultado['total']." comprobaciones correctas.\n";
    }
    echo "OK: $total controles PHP/navegador; cuatro tamaños; sin datos reales ni acceso a BD.\n";
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n"); $fallo=true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    $ruta=realpath($temporal);
    // Verifica el destino absoluto antes de retirar exclusivamente nuestra fixture.
    if ($ruta && dirname($ruta)===realpath(sys_get_temp_dir()) && preg_match('/^psicologia-avisos-[a-f0-9]{12}$/D',basename($ruta))) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            if ($archivo->isDir()) @rmdir($archivo->getPathname()); else @unlink($archivo->getPathname());
        }
        if (!@rmdir($ruta)) fwrite(STDERR,"Chrome retuvo archivos temporales: $ruta\n");
    }
}
exit($fallo?1:0);
