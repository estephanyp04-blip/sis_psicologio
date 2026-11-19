<?php
/** Estadísticas clínicas: cálculos, filtros, permisos e interfaz con datos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/estadisticas/datos.php';
require_once __DIR__ . '/datos_sinteticos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$sufijo = bin2hex(random_bytes(6));
$base = 'psicologia_test_clinica_' . $sufijo;
$temporal = sys_get_temp_dir() . '/psicologia-clinica-' . $sufijo;
mkdir($temporal, 0700, true);
$admin = null;
$servidor = null;
$total = 0;
$cookie = '';

function verificar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}
function rechaza(callable $accion, string $mensaje): void
{
    try { $accion(); } catch (InvalidArgumentException | mysqli_sql_exception $error) { verificar(true, $mensaje); return; }
    verificar(false, $mensaje);
}
function ejecutar(array $argumentos): string
{
    global $temporal;
    $p = proc_open($argumentos, [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/proceso.out', 'w'], 2 => ['file', $temporal . '/proceso.err', 'w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
    fclose($pipes[0]);
    $limite = microtime(true) + 45;
    do {
        $estado = proc_get_status($p);
        if (!$estado['running']) break;
        usleep(100000);
    } while (microtime(true) < $limite);
    if ($estado['running']) proc_terminate($p);
    proc_close($p);
    if ($estado['running'] || $estado['exitcode'] !== 0) throw new RuntimeException('Falló el proceso: ' . file_get_contents($temporal . '/proceso.err'));
    return file_get_contents($temporal . '/proceso.out');
}
function fila(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function http(string $ruta, ?array $datos = null, string $usuario = 'prueba_psicologa'): array
{
    global $puerto, $secreto, $cookie;
    $headers = ['X-Prueba-Secreto: ' . $secreto, 'X-Prueba-Usuario: ' . $usuario, 'Connection: close'];
    if ($cookie) $headers[] = 'Cookie: ' . $cookie;
    $opciones = ['method' => $datos === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
    if ($datos !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $opciones['content'] = http_build_query($datos);
    }
    $opciones['header'] = implode("\r\n", $headers);
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    foreach ($http_response_header as $cabecera) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecera, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP en $ruta");
    return ['codigo' => (int)$m[1], 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $http_response_header)];
}

try {
    $admin = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    ejecutar([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$base,'--aplicar','--instalar']);
    $bd = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$base,$config['puerto']);
    $bd->set_charset('utf8mb4'); $bd->query("SET time_zone='-04:00'");
    prueba_autores($bd); prueba_estudiantes($bd,3);
    verificar(!politica_modulo_permitido('Seguimientos',2),'Todavía admite permisos de un módulo independiente retirado.');
    require_once $raiz.'/informes/datos.php';
    $anio = (int)date('Y')-1;
    $entrada = ['cod_estudiante'=>1,'fecha_apertura'=>"$anio-01-01",'motivo_consulta'=>'MOTIVO_PRIVADO <script>prueba()</script>',
        'observaciones'=>'OBSERVACION_PRIVADA','estado'=>'Activa','formulario_completo'=>'1','situacion_escolar'=>'Regular',
        'conductas_riesgo'=>['Pandillaje'],'plan_intervencion'=>'Plan inicial',
        'familiares'=>[['nombre'=>'Familiar privado','relacion'=>'Tutor','observaciones'=>'Contexto original']]];
    $h1 = historia_guardar($bd,$entrada,102);
    historia_guardar($bd,array_replace($entrada,['conductas_riesgo'=>[],'plan_intervencion'=>'Plan revisado']),102,$h1);
    $h2 = historia_guardar($bd,array_replace($entrada,['fecha_apertura'=>"$anio-02-10",'conductas_riesgo'=>['Pandillaje','Conducta delictiva']]),102);
    $h3 = historia_guardar($bd,array_replace($entrada,['cod_estudiante'=>2,'fecha_apertura'=>"$anio-02-10",'conductas_riesgo'=>[],'situacion_escolar'=>'']),102);
    $bd->query("UPDATE estudiantes SET nombres='Nombre <privado>' WHERE cod_estudiante=1");
    $bd->query("UPDATE historial_academico SET estado='Finalizado' WHERE cod_estudiante=1");
    $bd->query("INSERT INTO historial_academico(cod_estudiante,cod_seccion,fecha_inicio,estado)
        SELECT 1,cod_seccion,CURRENT_DATE(),'Activo' FROM secciones WHERE cod_curso=2 AND cod_paralelo=2 LIMIT 1");
    $f = clinica_filtros(['desde'=>"$anio-01-01",'hasta'=>"$anio-02-28"]);
    $generales = clinica_generales($bd,$f);
    verificar($generales['historias']===3 && $generales['estudiantes']===2,'Duplica historias o estudiantes por inscripciones.');
    verificar($generales['estudiantes_riesgo']===1,'Duplica un estudiante con varias marcas.');
    verificar(array_sum($generales['meses'])===3 && $generales['meses']["$anio-02"]===2,'Período mensual inconsistente.');
    verificar($generales['sin_seleccion']['conductas_riesgo']===2,'Confunde falta de selecciones con ausencia clínica.');
    verificar($generales['valoraciones']['situacion_escolar']['Sin valoración registrada']===1,'Omite datos no registrados del denominador.');
    $curso = clinica_generales($bd,clinica_filtros(array_replace($f,['curso'=>2,'paralelo'=>2])));
    verificar($curso['historias']===2 && $curso['estudiantes']===1,'Curso/paralelo actuales no filtran de manera coherente.');
    $individual = clinica_filtros(array_replace($f,['estudiante'=>1]));
    $registros = clinica_registros(clinica_historias($bd,$individual,true),$individual);
    verificar(count($registros)===3,'No recupera la versión anterior junto a ambas historias.');
    $original = $registros[array_key_first($registros)]['datos'];
    verificar($original['plan_intervencion']==='Plan inicial' && $original['opciones']['conductas_riesgo']===['Pandillaje'],'Pierde texto o selecciones de la versión.');
    verificar($original['familiares'][0]['observaciones']==='Contexto original','Pierde contexto familiar histórico.');
    $actual = $registros['h'.$h1.'actual']['datos'];
    $diferencias = array_column(clinica_comparar($original,$actual,clinica_catalogos($bd)['grupos']),null,'campo');
    verificar($diferencias['conductas_riesgo']['sin_marca']===['Pandillaje'] && str_contains($diferencias['conductas_riesgo']['estado'],'Disminuye la cantidad de marcas'),'No identifica el cambio de selección.');
    verificar($diferencias['plan_intervencion']['estado']==='Registro modificado','No identifica cambios de intervención.');
    foreach (array_keys(HISTORIA_TEXTOS) as $campo) verificar(isset($diferencias[$campo]),'Falta un apartado clínico: '.$campo);
    $conteosAntes = $generales;
    flujo_ejecutar($bd,'INSERT INTO seguimientos(cod_historia,cod_psicologa,fecha,descripcion,tecnicas_aplicadas,acuerdos) VALUES (?,?,?,?,?,?)',[$h1,102,"$anio-01-15",'ARCHIVO_PRIVADO','Técnica anterior','Acuerdo anterior']);
    $legado = (int)$bd->insert_id;
    verificar(clinica_generales($bd,$f)===$conteosAntes,'Un seguimiento legado altera las estadísticas nuevas.');
    rechaza(fn()=>seguimiento_guardar($bd,['cod_historia'=>$h1,'fecha'=>date('Y-m-d'),'descripcion'=>'No insertar'],102),'Todavía escribe seguimientos independientes.');
    verificar((int)fila($bd,'SELECT COUNT(*) n FROM seguimientos')['n']===1,'El intento retirado insertó datos.');
    verificar(informe_total_atenciones($bd,1,"$anio-02-28")===2,'Informe individual y estadísticas cuentan fuentes distintas.');
    $mensual = informe_mensual_crear($bd,['mes'=>"$anio-02"],102);
    $resumen = fila($bd,'SELECT * FROM informe_resumen WHERE cod_informe='.$mensual);
    verificar((int)$resumen['total_sesiones']===2 && (int)$resumen['total_estudiantes']===2,'Mensual y estadísticas no reconcilian.');
    verificar(json_decode($resumen['filtros_json'],true)['version_semantica']===2,'No diferencia la definición nueva de los informes anteriores.');
    $antes = fila($bd,'SELECT * FROM historias_clinicas WHERE cod_historia='.$h1);
    $versionesAntes = (int)fila($bd,'SELECT COUNT(*) n FROM historia_versiones')['n'];
    $bd->query("CREATE TRIGGER fallo_prueba_clinica BEFORE INSERT ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo ficticio'");
    try { rechaza(fn()=>historia_guardar($bd,array_replace($entrada,['observaciones'=>'No conservar']),102,$h1),'No propaga fallo de auditoría.'); }
    finally { $bd->query('DROP TRIGGER fallo_prueba_clinica'); }
    verificar(fila($bd,'SELECT * FROM historias_clinicas WHERE cod_historia='.$h1)===$antes && (int)fila($bd,'SELECT COUNT(*) n FROM historia_versiones')['n']===$versionesAntes,'Edición fallida deja versión o cambios parciales.');
    $panel = estadisticas_panel($bd);
    verificar($panel['resumen']['sesiones']===3,'El panel aún cuenta la tabla retirada.');
    flujo_ejecutar($bd,"INSERT INTO derivaciones(cod_estudiante,cod_docente,fecha,motivo,prioridad) VALUES(1,201,?,'Origen ficticio','Alta'),(1,201,?,'Otro origen ficticio','Media'),(2,201,?,'Origen segundo','Media')",[date('Y-m-d'),date('Y-m-d'),date('Y-m-d')]);
    $panel=estadisticas_panel($bd);
    verificar(count($panel['prioritarios'])===2 && (int)$panel['prioritarios'][0]['cod_estudiante']===1,'Panel duplica estudiantes o pierde prioridad.');
    verificar($panel['prioritarios'][0]['ultima_sesion']==="$anio-02-10",'Última atención del panel sigue dependiendo del archivo.');
    $derivacion=(int)fila($bd,"SELECT MIN(cod_derivacion) id FROM derivaciones WHERE cod_estudiante=1")['id'];
    flujo_ejecutar($bd,'UPDATE historias_clinicas SET cod_derivacion_origen=? WHERE cod_historia=?',[$derivacion,$h2]);
    derivacion_cambiar_estado($bd,$derivacion,'En seguimiento',102);
    derivacion_cambiar_estado($bd,$derivacion,'Atendido',102);
    verificar(fila($bd,'SELECT estado FROM derivaciones WHERE cod_derivacion='.$derivacion)['estado']==='Atendido','El cierre todavía exige registrar un seguimiento independiente.');
    // Servidor aislado con las cuatro funciones reales y concesiones excesivas intencionales.
    $bd->query('UPDATE permisos SET puede_ver=1');
    mkdir($temporal.'/app',0700); mkdir($temporal.'/sesiones',0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz,FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (!in_array($archivo->getExtension(),['php','css','js','png','svg','woff','woff2'],true)) continue;
        $relativa = substr($archivo->getPathname(),strlen($raiz)+1);
        if (str_starts_with($relativa,'tests'.DIRECTORY_SEPARATOR)) continue;
        $destino=$temporal.'/app/'.$relativa;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino),0700,true);
        copy($archivo->getPathname(),$destino);
    }
    $configPrueba=$config; $configPrueba['base_datos']=$base; $configPrueba['base_url']='';
    file_put_contents($temporal.'/app/config/login.php','<?php return '.var_export($configPrueba,true).';');
    $secreto=bin2hex(random_bytes(32));
    file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','cuatro_roles'=>true,'navegador'=>in_array('--navegador',$argv,true),'secreto'=>$secreto],true).';');
    copy(__DIR__.'/integracion_router.php',$temporal.'/router.php');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    $puerto=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1); fclose($socket);
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',$temporal.'/app',$temporal.'/router.php'],[0=>['pipe','r'],1=>['file',$temporal.'/servidor.log','a'],2=>['file',$temporal.'/servidor.log','a']],$pipes,$temporal); fclose($pipes[0]);
    for ($i=0;$i<40;++$i) { $s=@fsockopen('127.0.0.1',$puerto,$errno,$error,0.1); if($s){fclose($s);break;} usleep(100000); }
    $url='estadisticas/index.php?'.http_build_query($f);
    $urlIndividual='estadisticas/individual.php?'.http_build_query($individual);
    $r=http($url);
    verificar($r['codigo']===200 && str_contains($r['cuerpo'],'data-valor="3"'),'No muestra estadísticas generales.');
    foreach (['MOTIVO_PRIVADO','OBSERVACION_PRIVADA','Familiar privado','Nombre &lt;privado&gt;'] as $privado) verificar(!str_contains($r['cuerpo'],$privado),'La vista general expone información individual.');
    $r=http($urlIndividual);
    verificar($r['codigo']===200 && str_contains($r['cuerpo'],'Plan inicial') && str_contains($r['cuerpo'],'Plan revisado'),'No compara versiones por HTTP.');
    verificar(str_contains($r['cuerpo'],'Nombre &lt;privado&gt;') && !str_contains($r['cuerpo'],'<script>prueba()</script>'),'No escapa contenido clínico.');
    verificar(str_contains($r['cuerpo'],'Archivo de seguimientos anteriores'),'Oculta información histórica.');
    verificar(http('seguimientos/ver.php?id='.$legado)['codigo']===200,'Rompe consulta del archivo.');
    verificar(http('seguimientos/listar.php?estudiante=1')['codigo']===303,'No redirige el enlace antiguo.');
    verificar(http('seguimientos/registrar.php?cod_historia='.$h1)['codigo']===303,'Formulario retirado sigue disponible.');
    verificar(http('seguimientos/guardar.php',[])['codigo']===403,'Retiro evita control CSRF.');
    verificar(http('seguimientos/guardar.php',['csrf'=>str_repeat('c',64),'cod_historia'=>$h1])['codigo']===410,'El POST antiguo no informa que fue retirado.');
    verificar((int)fila($bd,'SELECT COUNT(*) n FROM seguimientos')['n']===1,'HTTP escribe un seguimiento nuevo.');
    foreach (['prueba_admin','prueba_docente','prueba_director'] as $usuario) {
        foreach ([$url,$urlIndividual,'seguimientos/ver.php?id='.$legado] as $ruta) verificar(http($ruta,null,$usuario)['codigo']===403,'Acceso clínico indebido de '.$usuario);
    }
    foreach (['estudiante[]=1','estudiante=1&comparar_a=h'.$h3.'actual','desde=2025-02-30','desde=2025-02-10&hasta=2025-02-01','hasta=2099-01-01','estudiante=1&curso=999999'] as $invalido) verificar(http('estadisticas/individual.php?'.$invalido)['codigo']===400,'Acepta filtros o comparación manipulados.');
    verificar(http('estadisticas/individual.php?estudiante=999999')['codigo']===404,'Estudiante inexistente no devuelve 404.');
    $r=http('estadisticas/index.php?desde=1900-01-01&hasta=1900-01-31');
    verificar($r['codigo']===200 && str_contains($r['cuerpo'],'No hay historias clínicas'),'No diferencia vacío de fallo.');
    $bd->query('RENAME TABLE historia_versiones TO historia_versiones_prueba');
    try { verificar(http($urlIndividual)['codigo']===503,'Fallo de versiones aparece como consulta vacía.'); }
    finally { $bd->query('RENAME TABLE historia_versiones_prueba TO historia_versiones'); }
    verificar((int)fila($bd,"SELECT COUNT(*) n FROM auditoria WHERE modulo='seguimiento_individual' AND accion='Consultar'")['n']>0,'No audita consultas.');
    if (in_array('--navegador',$argv,true)) {
        $chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('Chrome no está disponible.');
        $plantilla=<<<'HTML'
<!doctype html><html lang="es"><meta charset="utf-8"><style>body{margin:0}iframe{display:block;width:100%;height:2100px;border:0}</style><pre id="resultado">Pendiente</pre><iframe id="app" title="Aplicación sintética"></iframe><script>
(async()=>{
    const app=document.getElementById('app'); let total=0;
    const abrir=url=>new Promise(resolve=>{app.onload=resolve;app.src=url});
    const comprobar=(ok,texto)=>{total++;if(!ok)throw new Error(texto)};
    const campo=id=>app.contentDocument.getElementById(id);
    const enviar=id=>new Promise(resolve=>{app.onload=resolve;campo(id).requestSubmit()});
    const ancho=Number(new URL(location.href).searchParams.get('ancho'));app.style.width=ancho+'px';
    const general=__GENERAL__,individual=__INDIVIDUAL__;
    try {
        await abrir(general);
        comprobar(campo('total-historias').dataset.valor==='3','Conteo general incorrecto');
        comprobar(!app.contentDocument.body.textContent.includes('MOTIVO_PRIVADO'),'Exposición de nota en general');
        comprobar(app.contentDocument.querySelector('.estadisticas-distribucion-pista>span').getBoundingClientRect().width>0,'Barras invisibles');
        comprobar(app.contentDocument.documentElement.scrollWidth<=app.contentWindow.innerWidth+1,'General desborda');
        campo('curso').value='2';campo('paralelo').value='2';await enviar('filtros-clinica');
        comprobar(campo('total-historias').dataset.valor==='2','No aplica curso y paralelo');
        campo('estado').value='Cerrada';await enviar('filtros-clinica');
        comprobar(campo('total-historias').dataset.valor==='0' && app.contentDocument.body.textContent.includes('No hay historias clínicas'),'Vacío incorrecto');
        await abrir(individual);
        comprobar(campo('estudiante').value==='1','No selecciona estudiante');
        comprobar(campo('comparar_a').options.length===3,'No hay tres registros conservados');
        comprobar(app.contentDocument.querySelector('[data-campo="plan_intervencion"]').textContent.includes('Plan inicial'),'No carga versión');
        comprobar(app.contentDocument.querySelector('[data-campo="conductas_riesgo"]').textContent.includes('Nuevas marcas'),'No compara selecciones');
        comprobar(app.contentDocument.querySelectorAll('.clinica-cronologia>li').length===3,'Cronología incompleta');
        comprobar(app.contentDocument.documentElement.scrollWidth<=app.contentWindow.innerWidth+1,'Individual desborda');
        const tabla=app.contentDocument.querySelector('.estadisticas-tabla-scroll');
        comprobar(tabla.getBoundingClientRect().right<=app.contentWindow.innerWidth+1,'Comparación fuera de pantalla');
        campo('comparar_b').value='h'+__H1__+'actual';
        await enviarComparacion();
        function enviarComparacion(){return new Promise(resolve=>{app.onload=resolve;app.contentDocument.querySelector('.clinica-comparadores').requestSubmit()})}
        comprobar(app.contentDocument.querySelector('[data-campo="plan_intervencion"]').textContent.includes('Plan revisado'),'Comparador no cambia');
        const detalle=app.contentDocument.querySelector('.clinica-cronologia details');detalle.open=true;
        comprobar(detalle.textContent.includes('Observaciones profesionales'),'No consulta intervenciones y observaciones');
        campo('desde').value=campo('hasta').value.slice(0,4)+'-03-01';campo('hasta').value=campo('hasta').value.slice(0,4)+'-03-31';await enviar('filtros-clinica');
        comprobar(app.contentDocument.body.textContent.includes('No hay registros clínicos'),'No filtra cronología por fechas');
        await abrir(individual);
        document.getElementById('resultado').textContent=JSON.stringify({ok:true,total,ancho});
    }catch(e){document.getElementById('resultado').textContent=JSON.stringify({ok:false,total,error:e.message})}
})();
</script></html>
HTML;
        $plantilla=str_replace(['__GENERAL__','__INDIVIDUAL__','__H1__'],[json_encode('/'.$url),json_encode('/'.$urlIndividual),(string)$h1],$plantilla);
        file_put_contents($temporal.'/navegador.html',$plantilla);
        foreach ([1440,390,360] as $ancho) {
            $args=[$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking',
                '--user-data-dir='.$temporal.'/chrome-'.$ancho,'--window-size='.max(500,$ancho).',2200','--dump-dom','--virtual-time-budget=20000'];
            $capturas=getenv('CAPTURAS_SEGUIMIENTO');
            if ($capturas && is_dir($capturas)) $args[]='--screenshot='.$capturas.'/individual-'.$ancho.'.png';
            $args[]='http://127.0.0.1:'.$puerto.'/__prueba_navegador?secreto='.$secreto.'&ancho='.$ancho;
            $html=ejecutar($args); $dom=new DOMDocument(); @$dom->loadHTML($html);
            $resultado=json_decode($dom->getElementById('resultado')?->textContent ?? '',true);
            verificar(($resultado['ok']??false)===true,'Chrome: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
            echo 'Chrome '.$ancho.' px: '.$resultado['total']." comprobaciones correctas.\n";
        }
    }
    echo "OK: $total comprobaciones de seguimiento, estadísticas, informes, integridad y permisos. Base sintética.\n";
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");
    if (is_file($temporal.'/servidor.log')) fwrite(STDERR,implode('',array_slice(file($temporal.'/servidor.log'),-12)));
    $fallo=true;
} finally {
    if (is_resource($servidor)) {proc_terminate($servidor);proc_close($servidor);}
    if (!preg_match('/^psicologia_test_clinica_[a-f0-9]{12}$/D',$base)) throw new RuntimeException('Base de limpieza no permitida.');
    if ($admin instanceof mysqli) $admin->query("DROP DATABASE IF EXISTS `$base`");
    $ruta=realpath($temporal);
    if ($ruta && dirname($ruta)===realpath(sys_get_temp_dir()) && basename($ruta)==='psicologia-clinica-'.$sufijo) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $archivo) $archivo->isDir()?rmdir($archivo->getPathname()):unlink($archivo->getPathname());
        rmdir($ruta);
    }
}
exit(isset($fallo)?1:0);

