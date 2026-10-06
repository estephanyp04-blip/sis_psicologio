<?php
/** Punto 5: trazabilidad y permisos, exclusivamente con datos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/includes/historias_datos.php';
require_once dirname(__DIR__) . '/includes/derivaciones_datos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$sufijo = bin2hex(random_bytes(6));
$base = 'psicologia_test_flujo_' . $sufijo;
$temporal = sys_get_temp_dir() . '/psicologia-flujo-' . $sufijo;
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
function foto(mysqli $bd): array
{
    $datos = [];
    foreach (['historias_clinicas', 'historia_opciones', 'historia_familiares', 'auditoria', 'derivaciones'] as $tabla) {
        $datos[$tabla] = $bd->query("SELECT * FROM $tabla ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    }
    return $datos;
}
function http(string $ruta, ?array $datos = null, string $usuario = 'prueba_admin'): array
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
function formulario(string $html, string $id): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($doc);
    $pares = [];
    foreach ($xpath->query('//form[@id="' . $id . '"]//*[self::input or self::select or self::textarea][@name]') as $campo) {
        if ($campo->hasAttribute('disabled')) continue;
        $tipo = $campo->getAttribute('type');
        if (in_array($tipo, ['checkbox', 'radio'], true) && !$campo->hasAttribute('checked')) continue;
        $valor = $campo->getAttribute('value');
        if ($campo->tagName === 'textarea') $valor = $campo->textContent;
        if ($campo->tagName === 'select') {
            $opcion = $xpath->query('.//option[@selected]', $campo)->item(0) ?? $xpath->query('.//option', $campo)->item(0);
            $valor = $opcion ? $opcion->getAttribute('value') : '';
        }
        $pares[] = rawurlencode($campo->getAttribute('name')) . '=' . rawurlencode($valor);
    }
    parse_str(implode('&', $pares), $datos);
    verificar($datos !== [], "No se encontró el formulario $id");
    return $datos;
}

require_once $raiz . '/includes/citas_datos.php';
require_once $raiz . '/includes/informes_datos.php';
try {
    $admin = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    ejecutar([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$base,'--aplicar','--instalar']);
    $bd = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$base,$config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $bd->query("INSERT INTO usuarios (id_usuario,nombre,apellido,usuario,password,id_rol) VALUES
        (101,'Admin','Prueba','prueba_admin','hash-prueba',1),(102,'Psicóloga','Prueba','prueba_psicologa','hash-prueba',2),
        (103,'Docente','Prueba','prueba_docente','hash-prueba',3),(104,'Director','Prueba','prueba_director','hash-prueba',4)");
    $bd->query("INSERT INTO docentes (id_docente,id_usuario,nombres,apellidos) VALUES (201,103,'Docente','Prueba'),(202,NULL,'Otro','Docente')");
    for ($i=1;$i<=4;$i++) $bd->query("INSERT INTO estudiantes (id_estudiante,codigo,ci,nombres,apellidos,id_curso,id_paralelo,curso,paralelo) VALUES ($i,'FLUJO$i','FLUJO$i','Estudiante','Prueba $i',1,1,'1','A')");
    $hoy=date('Y-m-d');
    $bd->query("INSERT INTO derivaciones (id_derivacion,fecha,id_estudiante,id_docente,motivo) VALUES (1,'$hoy',1,201,'Origen primero'),(2,'$hoy',1,201,'Nuevo motivo distinto'),(3,'$hoy',2,202,'Ajena')");
    mkdir($temporal . '/app', 0700); mkdir($temporal . '/sesiones', 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->getExtension() !== 'php') continue;
        $relativa = substr($archivo->getPathname(), strlen($raiz) + 1);
        if (str_starts_with($relativa, 'tests' . DIRECTORY_SEPARATOR)) continue;
        $destino = $temporal . '/app/' . $relativa;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0700, true);
        copy($archivo->getPathname(), $destino);
    }
    $configPrueba = $config; $configPrueba['base_datos'] = $base; $configPrueba['base_url'] = '';
    file_put_contents($temporal . '/app/config/login.php', '<?php return ' . var_export($configPrueba, true) . ';');
    $secreto = bin2hex(random_bytes(32));
    file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'cuatro_roles' => true, 'secreto' => $secreto], true) . ';');
    copy(__DIR__ . '/integracion_router.php', $temporal . '/router.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $puerto = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1); fclose($socket);
    $servidor = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-S', '127.0.0.1:' . $puerto, '-t', $temporal . '/app', $temporal . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/servidor.log', 'a'], 2 => ['file', $temporal . '/servidor.log', 'a']], $pipes, $temporal);
    fclose($pipes[0]);
    for ($i = 0; $i < 40; ++$i) { $s = @fsockopen('127.0.0.1', $puerto, $errno, $error, 0.1); if ($s) { fclose($s); break; } usleep(100000); }

    $token=['csrf'=>str_repeat('c',64)];
    $r=http('citas/registrar.php?id_derivacion=1');
    $post=formulario($r['cuerpo'],'form-cita');
    verificar((int)$post['id_estudiante']===1 && (int)$post['id_derivacion']===1,'No transfiere la derivación a la cita.');
    $post['hora']='09:00'; $post['id_usuario']=104;
    $r=http('citas/procesar_registrar.php',$post,'prueba_psicologa');
    $c=fila($bd,'SELECT * FROM citas ORDER BY id_cita DESC LIMIT 1'); $idCita=(int)$c['id_cita'];
    verificar((int)$c['id_usuario']===102 && (int)$c['id_derivacion']===1,'Pierde responsable o acepta autor del POST.');
    verificar(str_contains($r['cabeceras'],'editar.php?id='.$idCita),'No vuelve al detalle de la cita.');
    $r=http('citas/editar.php?id='.$idCita);
    verificar(str_contains($r['cuerpo'],'historias_clinicas/registrar.php?id_cita='.$idCita),'Falta enlace de cita a historia.');
    $r=http('historias_clinicas/registrar.php?id_cita='.$idCita);
    $historia=formulario($r['cuerpo'],'formHistoriaClinica');
    verificar((int)$historia['id_cita']===$idCita && (int)$historia['id_derivacion']===1,'No precarga el origen de la historia.');
    $historia['motivo_consulta']='Atención clínica <privada>';
    $r=http('historias_clinicas/guardar.php',$historia,'prueba_psicologa');
    $h=fila($bd,'SELECT * FROM historias_clinicas WHERE id_estudiante=1'); $idHistoria=(int)$h['id_historia'];
    verificar((int)$h['id_cita']===$idCita && (int)$h['id_derivacion']===1,'Historia sin origen exacto.');
    $r=http('seguimientos/registrar.php?id_historia='.$idHistoria.'&id_cita='.$idCita);
    $seguimiento=formulario($r['cuerpo'],'form-seguimiento');
    $seguimiento['descripcion']='Evolución <privada>'; $seguimiento['tecnicas_aplicadas']='Técnica de prueba';
    $seguimiento['acuerdos']='Acuerdo verificable'; $seguimiento['recomendaciones']='Recomendación primera';
    $seguimiento['proxima_sesion']=date('Y-m-d',strtotime('+7 days'));
    $seguimiento['id_usuario']=104;
    $r=http('seguimientos/guardar.php',$seguimiento,'prueba_psicologa');
    $s=fila($bd,'SELECT * FROM seguimientos ORDER BY id_seguimiento DESC LIMIT 1'); $idSeguimiento=(int)$s['id_seguimiento'];
    foreach (['descripcion','tecnicas_aplicadas','acuerdos','recomendaciones','proxima_sesion'] as $campo) verificar($s[$campo]===$seguimiento[$campo],'Seguimiento pierde '.$campo);
    verificar((int)$s['id_usuario']===102 && (int)$s['id_cita']===$idCita && (int)$s['id_historia']===$idHistoria,'Seguimiento pierde vínculos o autor.');
    verificar(fila($bd,'SELECT estado FROM citas WHERE id_cita='.$idCita)['estado']==='Atendida','Seguimiento no atiende la cita.');
    verificar(fila($bd,'SELECT estado FROM derivaciones WHERE id_derivacion=1')['estado']==='En seguimiento','Seguimiento no actualiza la derivación.');
    verificar(fila($bd,'SELECT estado FROM historias_clinicas WHERE id_historia='.$idHistoria)['estado']==='En seguimiento','Seguimiento no actualiza la historia.');
    $r=http('seguimientos/ver.php?id='.$idSeguimiento);
    verificar(str_contains($r['cuerpo'],'Evolución &lt;privada&gt;') && str_contains($r['cuerpo'],'Acuerdo verificable') && str_contains($r['cuerpo'],$seguimiento['proxima_sesion']),'Detalle no recupera o escapa los datos.');
    foreach (['derivaciones/ver.php?id=1','citas/editar.php?id='.$idCita,'historias_clinicas/ver.php?id='.$idHistoria] as $enlace) verificar(str_contains($r['cuerpo'],$enlace),'Falta enlace de origen '.$enlace);
    $r=http('informes/registrar.php?id_estudiante=1&id_seguimiento='.$idSeguimiento);
    verificar(str_contains($r['cuerpo'],'data-seguimiento="'.$idSeguimiento.'"') && str_contains($r['cuerpo'],'Origen primero'),'Informe precarga una derivación distinta al seguimiento.');
    $informe=['id_estudiante'=>1,'id_historia'=>$idHistoria,'id_seguimiento'=>$idSeguimiento,'fecha'=>$hoy,'titulo'=>'Informe del flujo','tipo_atencion'=>['Evaluación'],
        'numero_atenciones'=>1,'motivo'=>'Origen primero','aspecto_cognitivo'=>'Cognitivo','aspectos_afectivos'=>'Afectivo','estado'=>'Borrador','recomendaciones'=>'Recomendación primera'];
    $r=http('informes/guardar.php',$informe+$token,'prueba_psicologa');
    $i=fila($bd,'SELECT * FROM informes ORDER BY id_informe DESC LIMIT 1'); $idInforme=(int)$i['id_informe'];
    verificar((int)$i['id_seguimiento']===$idSeguimiento && (int)$i['id_historia']===$idHistoria && (int)$i['id_derivacion']===1,'Informe pierde cadena de origen.');
    verificar((int)$i['id_usuario']===102,'Informe pierde autor.');

    // Nuevo episodio en el mismo expediente, sin reemplazar su derivación original.
    $segunda=cita_guardar($bd,['id_estudiante'=>1,'id_derivacion'=>2,'fecha'=>$hoy,'hora'=>'10:00','estado'=>'Pendiente'],101);
    $seguimiento2=seguimiento_guardar($bd,array_replace($seguimiento,['id_cita'=>$segunda,'recomendaciones'=>'Recomendación segunda']),101);
    informe_crear($bd,informe_datos(array_replace($informe,['id_seguimiento'=>$seguimiento2])),101);
    verificar((int)fila($bd,'SELECT id_derivacion FROM informes ORDER BY id_informe DESC LIMIT 1')['id_derivacion']===2,'Informe del segundo episodio usa la primera derivación.');
    verificar((int)fila($bd,'SELECT id_derivacion FROM historias_clinicas WHERE id_historia='.$idHistoria)['id_derivacion']===1,'Segundo episodio reemplaza origen del expediente.');
    $r=http('informes/actualizar.php',array_replace($informe,['id_informe'=>$idInforme,'estado'=>'Finalizado'])+$token);
    $actual=fila($bd,'SELECT * FROM informes WHERE id_informe='.$idInforme);
    verificar($actual['estado']==='Finalizado' && (int)$actual['id_seguimiento']===$idSeguimiento && (int)$actual['id_usuario']===102,'Editar informe cambia autor/origen o no guarda estado.');
    verificar(fila($bd,'SELECT estado FROM derivaciones WHERE id_derivacion=1')['estado']==='En seguimiento','Emitir informe cierra atención implícitamente.');
    rechaza(fn()=>informe_actualizar($bd,$idInforme,informe_datos(array_replace($informe,['id_seguimiento'=>$seguimiento2])),101),'Permite cambiar seguimiento de origen.');
    $r=http('derivaciones/cambiar_estado.php',['id_derivacion'=>1,'estado'=>'Atendido']+$token,'prueba_psicologa');
    verificar(fila($bd,'SELECT estado FROM derivaciones WHERE id_derivacion=1')['estado']==='Atendido','No cierra atención explícitamente.');
    rechaza(fn()=>seguimiento_guardar($bd,array_replace($seguimiento,['id_cita'=>0]),101),'Admite seguimiento de derivación cerrada.');
    derivacion_cambiar_estado($bd,1,'En seguimiento',101);
    rechaza(fn()=>derivacion_cambiar_estado($bd,1,'Pendiente',101),'Regresa una atención a pendiente.');
    rechaza(fn()=>derivacion_cambiar_estado($bd,3,'Atendido',101),'Cierra una derivación sin historia/seguimiento.');

    // Errores de relación, duplicación, fechas y rollback de todos los efectos.
    $otra=cita_guardar($bd,['id_estudiante'=>2,'id_derivacion'=>3,'fecha'=>$hoy,'hora'=>'11:00','estado'=>'Pendiente'],101);
    foreach ([['id_cita'=>$otra],['id_cita'=>$idCita],['id_cita'=>0,'fecha'=>'2099-01-01'],['id_cita'=>0,'proxima_sesion'=>$hoy],['id_cita'=>0,'descripcion'=>''],['id_historia'=>999999]] as $cambio) rechaza(fn()=>seguimiento_guardar($bd,array_replace($seguimiento,$cambio),101),'Acepta seguimiento inválido.');
    foreach ([['id_derivacion'=>3],['hora'=>'09:15'],['hora'=>['09:00']],['fecha'=>'2026-02-30'],['fecha'=>'2020-01-01']] as $cambio) rechaza(fn()=>cita_guardar($bd,array_replace($post,$cambio),101),'Acepta cita inválida.');
    rechaza(fn()=>cita_guardar($bd,['estado'=>'Cancelada'],101,$idCita),'Cancela cita atendida.');
    rechaza(fn()=>cita_guardar($bd,['id_derivacion'=>3,'estado'=>'Atendida'],101,$idCita),'Cambia derivación de cita.');
    rechaza(fn()=>cita_guardar($bd,['estado'=>'Pendiente'],101,999999),'Edita cita inexistente.');
    rechaza(fn()=>historia_guardar($bd,array_replace($historia,['id_cita'=>$otra]),101,$idHistoria),'Cambia cita original de historia.');
    rechaza(fn()=>informe_crear($bd,informe_datos(array_replace($informe,['id_estudiante'=>2])),101),'Asocia seguimiento a otro estudiante.');
    $antes=$bd->query('SELECT * FROM seguimientos ORDER BY 1')->fetch_all(MYSQLI_ASSOC);
    rechaza(fn()=>seguimiento_guardar($bd,array_replace($seguimiento,['id_cita'=>0]),99999),'Acepta profesional inexistente.');
    verificar($antes===$bd->query('SELECT * FROM seguimientos ORDER BY 1')->fetch_all(MYSQLI_ASSOC),'Fallo SQL deja seguimiento parcial.');
    $h2=historia_guardar($bd,['id_estudiante'=>2,'id_derivacion'=>3,'id_cita'=>$otra,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Motivo','estado'=>'Activa','formulario_completo'=>'1'],101);
    $fotoAntes=foto($bd); $citaAntes=fila($bd,'SELECT * FROM citas WHERE id_cita='.$otra);
    $bd->query("CREATE TRIGGER fallo_auditoria BEFORE INSERT ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo intencional de prueba'");
    try {
        rechaza(fn()=>seguimiento_guardar($bd,array_replace($seguimiento,['id_historia'=>$h2,'id_cita'=>$otra]),101),'No propaga el fallo de auditoría.');
        verificar($fotoAntes===foto($bd) && $citaAntes===fila($bd,'SELECT * FROM citas WHERE id_cita='.$otra)
            && $antes===$bd->query('SELECT * FROM seguimientos ORDER BY 1')->fetch_all(MYSQLI_ASSOC),'No revierte seguimiento, cita, historia y derivación juntos.');
    } finally { $bd->query('DROP TRIGGER fallo_auditoria'); }

    $reserva=['id_estudiante'=>4,'fecha'=>$hoy,'hora'=>'13:00','estado'=>'Pendiente'];
    $cancelable=cita_guardar($bd,$reserva,101);
    cita_guardar($bd,['estado'=>'Cancelada'],102,$cancelable);
    $reutilizada=cita_guardar($bd,$reserva,101);
    verificar($reutilizada!==$cancelable,'No libera horario de cita cancelada.');
    rechaza(fn()=>cita_guardar($bd,$reserva,102),'Dos citas ocupan el mismo turno.');
    rechaza(fn()=>cita_guardar($bd,['estado'=>'Pendiente'],101,$cancelable),'Reactiva una cita cancelada.');
    // Cuatro procesos y conexiones compiten por un turno, sin serialización HTTP.
    $codigo='date_default_timezone_set("America/La_Paz"); require '.var_export($raiz.'/includes/citas_datos.php',true).'; mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT); $c='.var_export($config,true).'; $b=new mysqli($c["host"],$c["usuario_bd"],$c["clave_bd"],'.var_export($base,true).',$c["puerto"]); $b->set_charset("utf8mb4"); try {cita_guardar($b,'.var_export(array_replace($reserva,['hora'=>'14:00']),true).',101); echo "creada";} catch (InvalidArgumentException $e) {echo "ocupada";}';
    $trabajadores=[];
    for ($j=0;$j<4;$j++) {
        $proceso=proc_open([PHP_BINARY,'-r',$codigo],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$canales);
        fclose($canales[0]); $trabajadores[]=[$proceso,$canales];
    }
    $salidas=[];
    foreach ($trabajadores as [$proceso,$canales]) {
        $salidas[]=stream_get_contents($canales[1]); fclose($canales[1]);
        $errorProceso=stream_get_contents($canales[2]); fclose($canales[2]);
        verificar(proc_close($proceso)===0 && $errorProceso==='','Fallo en concurrencia de citas.');
    }
    verificar(count(array_filter($salidas,fn($salida)=>$salida==='creada'))===1 && count(array_filter($salidas,fn($salida)=>$salida==='ocupada'))===3,'Reserva concurrente no es atómica.');
    verificar((int)fila($bd,"SELECT COUNT(*) n FROM citas WHERE hora='14:00' AND estado<>'Cancelada'")['n']===1,'La base contiene citas duplicadas.');

    // Recuperación tras error HTTP y permisos con consultas reales, además de la matriz central.
    $r=http('seguimientos/guardar.php',array_replace($seguimiento,['id_cita'=>0,'descripcion'=>'Recuperar <texto>','proxima_sesion'=>'2000-01-01']));
    $r=http('seguimientos/registrar.php?id_historia='.$idHistoria);
    verificar(str_contains($r['cuerpo'],'Recuperar &lt;texto&gt;'),'Pierde texto tras error de seguimiento.');
    foreach (['prueba_docente','prueba_director'] as $rol) {
        foreach (['seguimientos/registrar.php?id_historia='.$idHistoria,'seguimientos/ver.php?id='.$idSeguimiento,'historias_clinicas/ver.php?id='.$idHistoria,'citas/editar.php?id='.$idCita] as $ruta) {
            verificar(http($ruta,null,$rol)['codigo']===403,'Rol restringido accede a '.$ruta);
        }
        verificar(http('seguimientos/guardar.php',$seguimiento,$rol)['codigo']===403,'Rol restringido registra atención.');
        verificar(http('derivaciones/cambiar_estado.php',['id_derivacion'=>1,'estado'=>'Atendido']+$token,$rol)['codigo']===403,'Rol restringido cambia estado.');
    }
    $r=http('derivaciones/ver.php?id=1',null,'prueba_docente');
    verificar($r['codigo']===200 && !str_contains($r['cuerpo'],'seguimientos/') && !str_contains($r['cuerpo'],'cambiar_estado.php') && !str_contains($r['cuerpo'],'Evolución'),'Docente recibe enlaces o contenido clínico.');
    verificar(http('derivaciones/ver.php?id=3',null,'prueba_docente')['codigo']===302,'Docente consulta derivación ajena.');
    $r=http('informes/ver.php?id='.$idInforme,null,'prueba_director');
    verificar($r['codigo']===200 && !str_contains($r['cuerpo'],'seguimientos/') && !str_contains($r['cuerpo'],'historias_clinicas/ver.php'),'Director recibe enlaces clínicos.');
    verificar(http('informes/guardar.php',$informe+$token,'prueba_director')['codigo']===403,'Director crea informe.');
    verificar(http('informes/ver.php?id='.$idInforme,null,'prueba_docente')['codigo']===403,'Docente consulta informe.');
    $r=http('derivaciones/eliminar.php',['id_derivacion'=>2]+$token,'prueba_docente');
    verificar(fila($bd,'SELECT id_derivacion FROM derivaciones WHERE id_derivacion=2')!==[],'Elimina una derivación vinculada.');
    $bd->query("INSERT INTO derivaciones (id_derivacion,fecha,id_estudiante,id_docente,motivo) VALUES (40,'$hoy',4,201,'Pendiente con cita')");
    cita_guardar($bd,['id_estudiante'=>4,'id_derivacion'=>40,'fecha'=>$hoy,'hora'=>'16:00','estado'=>'Pendiente'],101);
    $r=http('derivaciones/eliminar.php',['id_derivacion'=>40]+$token,'prueba_docente');
    verificar(fila($bd,'SELECT estado FROM derivaciones WHERE id_derivacion=40')===['estado'=>'Pendiente'],'Elimina una derivación pendiente con cita y rompe el origen.');
    verificar((int)fila($bd,"SELECT COUNT(*) n FROM auditoria WHERE modulo IN ('citas','seguimientos','informes','derivaciones')")['n']>=10,'Falta auditoría del flujo.');
    if (in_array('--navegador',$argv,true)) {
        $bd->query("INSERT INTO derivaciones (id_derivacion,fecha,id_estudiante,id_docente,motivo) VALUES (4,'$hoy',3,201,'Motivo navegador del flujo')");
        $chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome para --navegador.');
        file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto,'navegador'=>true,'cuatro_roles'=>true],true).';');
        file_put_contents($temporal.'/navegador.html',ejecutar([PHP_BINARY,__DIR__.'/trazabilidad_navegador.php']));
        $html=ejecutar([$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--user-data-dir='.$temporal.'/chrome','--dump-dom','--virtual-time-budget=20000','http://127.0.0.1:'.$puerto.'/__prueba_navegador?secreto='.$secreto]);
        $doc=new DOMDocument(); @$doc->loadHTML($html);
        $resultado=json_decode($doc->getElementById('resultado')?->textContent ?? '',true);
        verificar(($resultado['ok']??false)===true,'Navegador: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
        $cadena=fila($bd,'SELECT i.id_informe FROM informes i JOIN seguimientos s ON s.id_seguimiento=i.id_seguimiento JOIN historias_clinicas h ON h.id_historia=s.id_historia JOIN citas c ON c.id_cita=s.id_cita WHERE i.id_estudiante=3 AND i.id_derivacion=4 AND h.id_derivacion=4 AND h.id_cita=c.id_cita AND c.id_derivacion=4');
        verificar($cadena!==[],'El navegador no conservó toda la cadena al guardar.');
        echo 'Navegador Chrome: '.$resultado['total']." comprobaciones correctas.\n";
    }
    echo "OK: $total comprobaciones del flujo completo, validaciones y permisos. Sin escrituras en la base real.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    if (is_file($temporal . '/servidor.log')) fwrite(STDERR, implode('', array_slice(file($temporal . '/servidor.log'), -20)));
    $fallo = true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    if (!preg_match('/^psicologia_test_flujo_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Base de limpieza no permitida.');
    if ($admin instanceof mysqli) $admin->query("DROP DATABASE IF EXISTS `$base`");
    $ruta = realpath($temporal);
    if ($ruta && dirname($ruta) === realpath(sys_get_temp_dir()) && basename($ruta) === 'psicologia-flujo-' . $sufijo) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
        }
        rmdir($ruta);
    }
}
exit(isset($fallo) ? 1 : 0);
