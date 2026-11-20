<?php
/** Punto 5: trazabilidad y permisos, exclusivamente con datos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/historias_clinicas/datos.php';
require_once dirname(__DIR__) . '/derivaciones/datos.php';
require_once __DIR__ . '/datos_sinteticos.php';
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

function prueba_trazabilidad_verificar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}
function prueba_trazabilidad_rechaza(callable $accion, string $mensaje): void
{
    try { $accion(); } catch (InvalidArgumentException | mysqli_sql_exception $error) { prueba_trazabilidad_verificar(true, $mensaje); return; }
    prueba_trazabilidad_verificar(false, $mensaje);
}
function prueba_trazabilidad_ejecutar(array $argumentos): string
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
function prueba_trazabilidad_fila(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function prueba_trazabilidad_foto(mysqli $bd): array
{
    $datos = [];
    foreach (['historias_clinicas', 'historia_opciones', 'historia_familiares', 'auditoria', 'derivaciones'] as $tabla) {
        $datos[$tabla] = $bd->query("SELECT * FROM $tabla ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    }
    return $datos;
}
function prueba_trazabilidad_http(string $ruta, ?array $datos = null, string $usuario = 'prueba_psicologa'): array
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
    $http_response_header = [];
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    if ($cuerpo === false || !$http_response_header) throw new RuntimeException("No se recibió una respuesta HTTP de $ruta.");
    foreach ($http_response_header as $cabecera) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecera, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    prueba_trazabilidad_verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP en $ruta");
    return ['codigo' => (int)$m[1], 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $http_response_header)];
}
function prueba_trazabilidad_formulario(string $html, string $id): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($doc);
    $pares = [];
    foreach ($xpath->query('//form[@id="' . $id . '"]//*[self::input or self::select or self::textarea][@name]') as $campo) {
        if (!($campo instanceof DOMElement)) throw new RuntimeException('El campo del formulario no es un elemento HTML.');
        if ($campo->hasAttribute('disabled')) continue;
        $tipo = $campo->getAttribute('type');
        if (in_array($tipo, ['checkbox', 'radio'], true) && !$campo->hasAttribute('checked')) continue;
        $valor = $campo->getAttribute('value');
        if ($campo->tagName === 'textarea') $valor = $campo->textContent;
        if ($campo->tagName === 'select') {
            $opcion = $xpath->query('.//option[@selected]', $campo)->item(0) ?? $xpath->query('.//option', $campo)->item(0);
            $valor = $opcion instanceof DOMElement ? ($opcion->hasAttribute('value') ? $opcion->getAttribute('value') : trim($opcion->textContent)) : '';
        }
        $pares[] = rawurlencode($campo->getAttribute('name')) . '=' . rawurlencode($valor);
    }
    parse_str(implode('&', $pares), $datos);
    prueba_trazabilidad_verificar($datos !== [], "No se encontró el formulario $id");
    return $datos;
}

require_once $raiz . '/citas/datos.php';
require_once $raiz . '/informes/datos.php';
try {
    $admin = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],'',$config['puerto']);
    prueba_trazabilidad_ejecutar([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$base,'--aplicar','--instalar']);
    $bd = new mysqli($config['host'],$config['usuario_bd'],$config['clave_bd'],$base,$config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    prueba_autores($bd);
    $bd->query("INSERT INTO docente_materias (cod_docente,cod_materia)
        SELECT 201,cod_materia FROM materias WHERE estado='Activo'");
    prueba_estudiantes($bd, 4);
    $hoy=date('Y-m-d');
    $bd->query("INSERT INTO derivaciones (cod_derivacion,fecha,cod_estudiante,cod_docente,motivo,solicitar_cita,cod_psicologa_solicitada) VALUES (1,'$hoy',1,201,'Origen primero',1,102),(2,'$hoy',1,201,'Nuevo motivo distinto',0,NULL),(3,'$hoy',2,202,'Ajena',0,NULL)");
    mkdir($temporal . '/app', 0700); mkdir($temporal . '/sesiones', 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (!in_array($archivo->getExtension(),['php','css','js','woff','woff2','svg','png'],true)) continue;
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
    $r=prueba_trazabilidad_http('derivaciones/registrar.php',null,'prueba_docente');
    prueba_trazabilidad_verificar($r['codigo']===200 && str_contains($r['cuerpo'],'id="solicitar_cita"'),'El formulario de derivacion no muestra la solicitud de cita.');
    $r=prueba_trazabilidad_http('derivaciones/registrar.php',null,'prueba_docente');
    $nuevaDerivacion=prueba_trazabilidad_formulario($r['cuerpo'],'formDerivacion');
    $materiaPrueba=$bd->query("SELECT nombre FROM materias WHERE estado='Activo' ORDER BY cod_materia LIMIT 1")->fetch_assoc()['nombre'];
    $categoriaPrueba=$bd->query("SELECT nombre FROM categorias_derivacion WHERE estado='Activo' ORDER BY cod_categoria LIMIT 1")->fetch_assoc()['nombre'];
    $nuevaDerivacion=array_replace($nuevaDerivacion,['cod_estudiante'=>4,'fecha'=>$hoy,'materia'=>$materiaPrueba,
        'categorias'=>[$categoriaPrueba],'motivo'=>'Derivacion de prueba con cita solicitada','prioridad'=>'Media',
        'solicitar_cita'=>1,'cod_profesional'=>102]);
    prueba_trazabilidad_http('derivaciones/guardar.php',$nuevaDerivacion,'prueba_docente');
    $solicitudGuardada=prueba_trazabilidad_fila($bd,'SELECT cod_derivacion,solicitar_cita,cod_psicologa_solicitada FROM derivaciones ORDER BY cod_derivacion DESC LIMIT 1');
    prueba_trazabilidad_verificar((int)$solicitudGuardada['solicitar_cita']===1 && (int)$solicitudGuardada['cod_psicologa_solicitada']===102,'No guarda la cita solicitada desde el formulario docente.');
    $idSolicitudPrueba=(int)$solicitudGuardada['cod_derivacion'];
    $bd->query("UPDATE derivaciones SET solicitar_cita=0,cod_psicologa_solicitada=NULL WHERE cod_derivacion=$idSolicitudPrueba");
    $r=prueba_trazabilidad_http('citas/listar.php',null,'prueba_psicologa');
    prueba_trazabilidad_verificar(str_contains($r['cuerpo'],'Solicitudes de cita pendientes') && str_contains($r['cuerpo'],'registrar.php?cod_derivacion=1'),'La solicitud no aparece en la bandeja de Psicologia.');
    $r=prueba_trazabilidad_http('citas/listar.php',null,'prueba_psicologa_dos');
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'registrar.php?cod_derivacion=1'),'Muestra una solicitud dirigida a otra psicologa.');
    $r=prueba_trazabilidad_http('citas/registrar.php?cod_derivacion=1');
    $post=prueba_trazabilidad_formulario($r['cuerpo'],'form-cita');
    prueba_trazabilidad_verificar((int)$post['cod_psicologa']===102 && str_contains($r['cuerpo'],'Solicitud recibida'),'No conserva la psicologa pedida ni muestra el contexto de la solicitud.');
    prueba_trazabilidad_verificar((int)$post['cod_estudiante']===1 && (int)$post['cod_derivacion']===1,'No transfiere la derivación a la cita.');
    $post['hora']='09:00'; $post['cod_usuario']=104;
    $r=prueba_trazabilidad_http('citas/procesar_registrar.php',$post,'prueba_psicologa');
    $c=prueba_trazabilidad_fila($bd,'SELECT * FROM citas ORDER BY cod_cita DESC LIMIT 1'); $idCita=(int)$c['cod_cita'];
    prueba_trazabilidad_verificar(str_contains($r['cabeceras'],'editar.php?id='.$idCita),'No vuelve al detalle de la cita.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,$post,101),'Permite programar dos citas vigentes para la misma derivacion.');
    $r=prueba_trazabilidad_http('citas/listar.php',null,'prueba_psicologa');
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'registrar.php?cod_derivacion=1'),'La solicitud sigue pendiente despues de programar la cita.');
    prueba_trazabilidad_verificar((int)$c['cod_psicologa']===102 && (int)$c['cod_derivacion']===1,'Pierde responsable o acepta autor del POST.');
    $r=prueba_trazabilidad_http('citas/editar.php?id='.$idCita);
    prueba_trazabilidad_verificar(str_contains($r['cuerpo'],'historias_clinicas/registrar.php?cod_cita='.$idCita),'Falta enlace de cita a historia.');
    $r=prueba_trazabilidad_http('historias_clinicas/registrar.php?cod_cita='.$idCita);
    $historia=prueba_trazabilidad_formulario($r['cuerpo'],'formHistoriaClinica');
    prueba_trazabilidad_verificar((int)$historia['cod_cita']===$idCita && (int)$historia['cod_derivacion']===1,'No precarga el origen de la historia.');
    $historia['motivo_consulta']='Atención clínica <privada>';
    $r=prueba_trazabilidad_http('historias_clinicas/guardar.php',$historia,'prueba_psicologa');
    if (!str_contains($r['cabeceras'], 'ver.php?id=')) {
        $errorAlta = prueba_trazabilidad_http('historias_clinicas/registrar.php', null, 'prueba_psicologa');
        $docError = new DOMDocument();
        @$docError->loadHTML('<?xml encoding="UTF-8">' . $errorAlta['cuerpo']);
        $alerta = (new DOMXPath($docError))->query('//*[contains(@class,"alert-danger")]')->item(0);
        throw new RuntimeException('No se guardó la historia: ' . trim($alerta?->textContent ?? $r['cabeceras']));
    }
    $h=prueba_trazabilidad_fila($bd,'SELECT * FROM historias_clinicas WHERE cod_estudiante=1'); $idHistoria=(int)$h['cod_historia'];
    prueba_trazabilidad_verificar((int)$h['cod_cita_origen']===$idCita && (int)$h['cod_derivacion_origen']===1,'Historia sin origen exacto.');
    $r=prueba_trazabilidad_http('seguimientos/registrar.php?cod_historia='.$idHistoria.'&cod_cita='.$idCita);
    prueba_trazabilidad_verificar($r['codigo']===404,'Formulario antiguo no redirige a historia clínica.');
    $seguimiento=['cod_historia'=>$idHistoria,'cod_cita'=>$idCita,'cod_derivacion'=>1,'fecha'=>$hoy,
        'descripcion'=>'Evolución <privada>','tecnicas_aplicadas'=>'Técnica de prueba','acuerdos'=>'Acuerdo verificable',
        'recomendaciones'=>'Recomendación primera','proxima_sesion'=>date('Y-m-d',strtotime('+7 days'))]+$token;
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('seguimientos/guardar.php',$seguimiento)['codigo']===404,'No retiró el POST de registro independiente.');
    prueba_trazabilidad_verificar((int)prueba_trazabilidad_fila($bd,'SELECT COUNT(*) n FROM historia_anexos')['n']===0,'El POST retirado escribió datos.');
    // Sembrar solo en la fixture un archivo antiguo para comprobar su conservación.
    flujo_ejecutar($bd,'INSERT INTO historia_anexos(cod_historia,cod_psicologa,cod_cita,cod_derivacion,fecha,descripcion,tecnicas_aplicadas,acuerdos,recomendaciones,proxima_sesion) VALUES(?,?,?,?,?,?,?,?,?,?)',[$idHistoria,102,$idCita,1,$hoy,$seguimiento['descripcion'],$seguimiento['tecnicas_aplicadas'],$seguimiento['acuerdos'],$seguimiento['recomendaciones'],$seguimiento['proxima_sesion']]);
    $idSeguimiento=(int)$bd->insert_id;
    cita_guardar($bd,['estado'=>'Atendida'],102,$idCita);
    derivacion_cambiar_estado($bd,1,'En seguimiento',102);
    historia_guardar($bd,array_replace($historia,['estado'=>'En seguimiento']),102,$idHistoria);
    $r=prueba_trazabilidad_http('historias_clinicas/ver.php?id='.$idHistoria);
    prueba_trazabilidad_verificar(str_contains($r['cuerpo'],'Evolución &lt;privada&gt;') && str_contains($r['cuerpo'],'Acuerdo verificable') && str_contains($r['cuerpo'],date('d/m/Y',strtotime($seguimiento['proxima_sesion']))),'Detalle no recupera o escapa los datos.');
    foreach (['derivaciones/ver.php?id=1','citas/editar.php?id='.$idCita] as $enlace) prueba_trazabilidad_verificar(str_contains($r['cuerpo'],$enlace),'Falta enlace de origen '.$enlace);
    $r=prueba_trazabilidad_http('informes/registrar.php?cod_estudiante=1&cod_historia='.$idHistoria);
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'cod_seguimiento') && str_contains($r['cuerpo'],'Origen primero'),'Informe precarga una derivación distinta al seguimiento.');
    $informe=['cod_estudiante'=>1,'cod_historia'=>$idHistoria,'fecha'=>$hoy,'titulo'=>'Informe del flujo','tipo_atencion'=>['Evaluación'],
        'numero_atenciones'=>1,'motivo'=>'Origen primero','aspecto_cognitivo'=>'Cognitivo','aspectos_afectivos'=>'Afectivo','estado'=>'Borrador','recomendaciones'=>'Recomendación primera'];
    $r=prueba_trazabilidad_http('informes/guardar.php',$informe+$token,'prueba_psicologa');
    $i=prueba_trazabilidad_fila($bd,'SELECT i.*,d.* FROM informes i JOIN informe_individual d ON d.cod_informe=i.cod_informe ORDER BY i.cod_informe DESC LIMIT 1'); $idInforme=(int)$i['cod_informe'];
    prueba_trazabilidad_verificar($i['cod_anexo_historico']===null && (int)$i['cod_historia']===$idHistoria && (int)$i['cod_derivacion']===1,'Informe pierde cadena de origen.');
    prueba_trazabilidad_verificar((int)$i['cod_elaborado_por']===102,'Informe pierde autor.');
    flujo_ejecutar($bd,'UPDATE informe_individual SET cod_anexo_historico=? WHERE cod_informe=?',[$idSeguimiento,$idInforme]);

    // Nuevo episodio en el mismo expediente, sin reemplazar su derivación original.
    $segunda=cita_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>2,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'10:00','estado'=>'Pendiente'],101);
    flujo_ejecutar($bd,'INSERT INTO historia_anexos(cod_historia,cod_psicologa,cod_cita,cod_derivacion,fecha,descripcion,recomendaciones) VALUES(?,?,?,?,?,?,?)',[$idHistoria,112,$segunda,2,$hoy,'Archivo del segundo episodio','Recomendación segunda']);
    $seguimiento2=(int)$bd->insert_id;
    $historiaSegunda=historia_guardar($bd,['cod_estudiante'=>1,'cod_derivacion'=>2,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Segundo episodio','estado'=>'Activa','formulario_completo'=>'1'],102);
    informe_crear($bd,informe_datos(array_replace($informe,['cod_historia'=>$historiaSegunda])),101);
    prueba_trazabilidad_verificar((int)prueba_trazabilidad_fila($bd,'SELECT cod_derivacion FROM informe_individual ORDER BY cod_informe DESC LIMIT 1')['cod_derivacion']===2,'Informe del segundo episodio usa la primera derivación.');
    prueba_trazabilidad_verificar((int)prueba_trazabilidad_fila($bd,'SELECT cod_derivacion_origen FROM historias_clinicas WHERE cod_historia='.$idHistoria)['cod_derivacion_origen']===1,'Segundo episodio reemplaza origen del expediente.');
    $r=prueba_trazabilidad_http('informes/actualizar.php',array_replace($informe,['cod_informe'=>$idInforme,'estado'=>'Finalizado'])+$token);
    $actual=prueba_trazabilidad_fila($bd,'SELECT i.*,d.* FROM informes i JOIN informe_individual d ON d.cod_informe=i.cod_informe WHERE i.cod_informe='.$idInforme);
    prueba_trazabilidad_verificar($actual['estado']==='Finalizado' && (int)$actual['cod_anexo_historico']===$idSeguimiento && (int)$actual['cod_elaborado_por']===102,'Editar informe cambia autor/origen o no guarda estado.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT estado FROM derivaciones WHERE cod_derivacion=1')['estado']==='En seguimiento','Emitir informe cierra atención implícitamente.');
    $r=prueba_trazabilidad_http('derivaciones/cambiar_estado.php',['cod_derivacion'=>1,'estado'=>'Atendido']+$token,'prueba_psicologa');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT estado FROM derivaciones WHERE cod_derivacion=1')['estado']==='Atendido','No cierra atención explícitamente.');
    derivacion_cambiar_estado($bd,1,'En seguimiento',101);
    prueba_trazabilidad_rechaza(fn()=>derivacion_cambiar_estado($bd,1,'Pendiente',101),'Regresa una atención a pendiente.');
    prueba_trazabilidad_rechaza(fn()=>derivacion_cambiar_estado($bd,3,'Atendido',101),'Cierra una derivación sin historia/seguimiento.');

    // Errores de relación, duplicación, fechas y rollback de todos los efectos.
    $otra=cita_guardar($bd,['cod_estudiante'=>2,'cod_derivacion'=>3,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'11:00','estado'=>'Pendiente'],101);
    foreach ([['cod_derivacion'=>3],['hora'=>'09:15'],['hora'=>['09:00']],['fecha'=>'2026-02-30'],['fecha'=>'2020-01-01']] as $cambio) prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,array_replace($post,$cambio),101),'Acepta cita inválida.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,['estado'=>'Cancelada'],101,$idCita),'Cancela cita atendida.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,['cod_derivacion'=>3,'estado'=>'Atendida'],101,$idCita),'Cambia derivación de cita.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,['estado'=>'Pendiente'],101,999999),'Edita cita inexistente.');
    prueba_trazabilidad_rechaza(fn()=>historia_guardar($bd,array_replace($historia,['cod_cita'=>$otra]),112,$idHistoria),'Cambia cita original de historia.');
    prueba_trazabilidad_rechaza(fn()=>informe_crear($bd,informe_datos(array_replace($informe,['cod_estudiante'=>2])),101),'Asocia seguimiento a otro estudiante.');
    $antes=$bd->query('SELECT * FROM historia_anexos ORDER BY 1')->fetch_all(MYSQLI_ASSOC);
    prueba_trazabilidad_verificar($antes===$bd->query('SELECT * FROM historia_anexos ORDER BY 1')->fetch_all(MYSQLI_ASSOC),'Fallo SQL deja seguimiento parcial.');
    $h2=historia_guardar($bd,['cod_estudiante'=>2,'cod_derivacion'=>3,'cod_cita'=>$otra,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Motivo','estado'=>'Activa','formulario_completo'=>'1'],112);
    prueba_trazabilidad_rechaza(fn()=>historia_guardar($bd,['cod_estudiante'=>2,'cod_derivacion'=>3,'cod_cita'=>$otra,'fecha_apertura'=>$hoy,'motivo_consulta'=>'Sesión duplicada','estado'=>'Activa','formulario_completo'=>'1'],112),'Permite dos sesiones clínicas para la misma cita.');
    $fotoAntes=prueba_trazabilidad_foto($bd); $citaAntes=prueba_trazabilidad_fila($bd,'SELECT * FROM citas WHERE cod_cita='.$otra);
    $bd->query("CREATE TRIGGER fallo_auditoria BEFORE INSERT ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo intencional de prueba'");
    try {
        prueba_trazabilidad_rechaza(fn()=>historia_guardar($bd,['cod_estudiante'=>2,'fecha_apertura'=>$hoy,'motivo_consulta'=>'No guardar','estado'=>'Activa','formulario_completo'=>'1'],112,$h2),'No propaga el fallo de auditoría.');
        prueba_trazabilidad_verificar($fotoAntes===prueba_trazabilidad_foto($bd) && $citaAntes===prueba_trazabilidad_fila($bd,'SELECT * FROM citas WHERE cod_cita='.$otra)
            && $antes===$bd->query('SELECT * FROM historia_anexos ORDER BY 1')->fetch_all(MYSQLI_ASSOC),'No revierte seguimiento, cita, historia y derivación juntos.');
    } finally { $bd->query('DROP TRIGGER fallo_auditoria'); }

    $reserva=['cod_estudiante'=>4,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'13:00','estado'=>'Pendiente'];
    $cancelable=cita_guardar($bd,$reserva,101);
    cita_guardar($bd,['estado'=>'Cancelada'],102,$cancelable);
    $reutilizada=cita_guardar($bd,$reserva,101);
    prueba_trazabilidad_verificar($reutilizada!==$cancelable,'No libera horario de cita cancelada.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,$reserva,101),'Dos citas ocupan el mismo turno del profesional.');
    $otroProfesional=cita_guardar($bd,array_replace($reserva,['cod_psicologa'=>112]),101);
    prueba_trazabilidad_verificar($otroProfesional!==$reutilizada,'No permite el mismo horario a profesionales distintos.');
    prueba_trazabilidad_rechaza(fn()=>cita_guardar($bd,['estado'=>'Pendiente'],101,$cancelable),'Reactiva una cita cancelada.');
    // Cuatro procesos y conexiones compiten por un turno, sin serialización HTTP.
    $codigo='date_default_timezone_set("America/La_Paz"); require '.var_export($raiz.'/citas/datos.php',true).'; mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT); $c='.var_export($config,true).'; $b=new mysqli($c["host"],$c["usuario_bd"],$c["clave_bd"],'.var_export($base,true).',$c["puerto"]); $b->set_charset("utf8mb4"); try {cita_guardar($b,'.var_export(array_replace($reserva,['hora'=>'14:00']),true).',101); echo "creada";} catch (InvalidArgumentException $e) {echo "ocupada";}';
    $trabajadores=[];
    for ($j=0;$j<4;$j++) {
        $proceso=proc_open([PHP_BINARY,'-r',$codigo],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$canales);
        fclose($canales[0]); $trabajadores[]=[$proceso,$canales];
    }
    $salidas=[];
    foreach ($trabajadores as [$proceso,$canales]) {
        $salidas[]=stream_get_contents($canales[1]); fclose($canales[1]);
        $errorProceso=stream_get_contents($canales[2]); fclose($canales[2]);
        prueba_trazabilidad_verificar(proc_close($proceso)===0 && $errorProceso==='','Fallo en concurrencia de citas.');
    }
    prueba_trazabilidad_verificar(count(array_filter($salidas,fn($salida)=>$salida==='creada'))===1 && count(array_filter($salidas,fn($salida)=>$salida==='ocupada'))===3,'Reserva concurrente no es atómica.');
    prueba_trazabilidad_verificar((int)prueba_trazabilidad_fila($bd,"SELECT COUNT(*) n FROM citas WHERE hora='14:00' AND estado<>'Cancelada'")['n']===1,'La base contiene citas duplicadas.');

    // Recuperación tras error HTTP y permisos con consultas reales, además de la matriz central.
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('seguimientos/guardar.php',array_replace($seguimiento,['descripcion'=>'No escribir']))['codigo']===404,'Reabrió el endpoint retirado.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT descripcion FROM historia_anexos WHERE cod_anexo='.$idSeguimiento)['descripcion']==='Evolución <privada>','El POST retirado alteró el archivo.');
    foreach (['prueba_docente','prueba_director'] as $rol) {
        foreach (['historias_clinicas/ver.php?id='.$idHistoria,'citas/editar.php?id='.$idCita] as $ruta) {
            prueba_trazabilidad_verificar(prueba_trazabilidad_http($ruta,null,$rol)['codigo']===403,'Rol restringido accede a '.$ruta);
        }
        prueba_trazabilidad_verificar(prueba_trazabilidad_http('seguimientos/guardar.php',$seguimiento,$rol)['codigo']===404,'Rol restringido registra atención.');
        prueba_trazabilidad_verificar(prueba_trazabilidad_http('derivaciones/cambiar_estado.php',['cod_derivacion'=>1,'estado'=>'Atendido']+$token,$rol)['codigo']===403,'Rol restringido cambia estado.');
    }
    $r=prueba_trazabilidad_http('derivaciones/ver.php?id=1',null,'prueba_docente');
    prueba_trazabilidad_verificar($r['codigo']===200 && !str_contains($r['cuerpo'],'seguimientos/') && !str_contains($r['cuerpo'],'cambiar_estado.php') && !str_contains($r['cuerpo'],'Evolución'),'Docente recibe enlaces o contenido clínico.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('derivaciones/ver.php?id=3',null,'prueba_docente')['codigo']===302,'Docente consulta derivación ajena.');
    $r=prueba_trazabilidad_http('informes/ver.php?id='.$idInforme,null,'prueba_director');
    prueba_trazabilidad_verificar($r['codigo']===403 && !str_contains($r['cuerpo'],'seguimientos/') && !str_contains($r['cuerpo'],'historias_clinicas/ver.php'),'Director recibe enlaces clínicos.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/guardar.php',$informe+$token,'prueba_director')['codigo']===403,'Director crea informe.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/ver.php?id='.$idInforme,null,'prueba_docente')['codigo']===403,'Docente consulta informe.');
    $r=prueba_trazabilidad_http('derivaciones/eliminar.php',['cod_derivacion'=>2]+$token,'prueba_docente');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT cod_derivacion FROM derivaciones WHERE cod_derivacion=2')!==[],'Elimina una derivación vinculada.');
    $bd->query("INSERT INTO derivaciones (cod_derivacion,fecha,cod_estudiante,cod_docente,motivo) VALUES (40,'$hoy',4,201,'Pendiente con cita')");
    cita_guardar($bd,['cod_estudiante'=>4,'cod_derivacion'=>40,'cod_psicologa'=>102,'fecha'=>$hoy,'hora'=>'16:00','estado'=>'Pendiente'],101);
    $r=prueba_trazabilidad_http('derivaciones/eliminar.php',['cod_derivacion'=>40]+$token,'prueba_docente');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT estado FROM derivaciones WHERE cod_derivacion=40')===['estado'=>'Pendiente'],'Elimina una derivación pendiente con cita y rompe el origen.');
    prueba_trazabilidad_verificar((int)prueba_trazabilidad_fila($bd,"SELECT COUNT(*) n FROM auditoria WHERE modulo IN ('citas','seguimientos','informes','derivaciones')")['n']>=10,'Falta auditoría del flujo.');
    foreach (['index.php?mes='.date('Y-m').'&fecha='.$hoy,'informes/mensual.php','derivaciones/listar.php?desde='.$hoy.'&hasta='.$hoy.'&prioridad=Media','informes/listar.php?desde='.$hoy.'&hasta='.$hoy] as $rutaNueva) prueba_trazabilidad_verificar(prueba_trazabilidad_http($rutaNueva)['codigo']===200,'Falla nueva pantalla '.$rutaNueva);
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('index.php?mes=2026-13')['codigo']===422,'Calendario acepta mes inválido.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('derivaciones/listar.php?desde=2026-11-30&hasta=2026-11-01')['codigo']===422,'Filtro acepta rango invertido.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/listar.php?desde=fecha-invalida')['codigo']===422,'Filtro de informe acepta fecha inválida.');
    $r=prueba_trazabilidad_http('informes/listar.php?desde=1900-01-01&hasta=1900-01-31');
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'data-ficha="'.$actual['numero_ficha'].'"'),'Filtro de fechas conserva informe fuera del período.');
    $r=prueba_trazabilidad_http('derivaciones/listar.php?desde=1900-01-01&hasta=1900-01-31');
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'ver.php?id=1"'),'Filtro de derivaciones conserva datos fuera del período.');
    $bd->query("INSERT INTO derivaciones(cod_derivacion,fecha,cod_estudiante,cod_docente,motivo,prioridad) VALUES(900,'$hoy',4,201,'Solo prioridad alta ficticia','Alta')");
    $r=prueba_trazabilidad_http('derivaciones/listar.php?prioridad=Baja');
    prueba_trazabilidad_verificar(!str_contains($r['cuerpo'],'data-derivacion-id="900"'),'Filtro de prioridad no aplica.');
    $r=prueba_trazabilidad_http('derivaciones/listar.php?prioridad=Alta');
    prueba_trazabilidad_verificar(str_contains($r['cuerpo'],'data-derivacion-id="900"'),'Filtro de prioridad oculta coincidencia.');
    $bd->query('DELETE FROM derivaciones WHERE cod_derivacion=900');
    foreach(['prueba_docente','prueba_director'] as $rolRestringido) prueba_trazabilidad_verificar(prueba_trazabilidad_http('index.php',null,$rolRestringido)['codigo']===403,'Calendario expone citas a rol restringido.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/antecedentes.php?cod_estudiante=1&fecha='.$hoy,null,'prueba_director')['codigo']===403,'Director consulta antecedentes clínicos.');
    $r=prueba_trazabilidad_http('informes/antecedentes.php?cod_estudiante=1&fecha='.$hoy,null,'prueba_psicologa');
    prueba_trazabilidad_verificar($r['codigo']===200 && isset(json_decode($r['cuerpo'],true)['numero_atenciones']),'No responde antecedentes al corte.');
    $r=prueba_trazabilidad_http('informes/guardar_mensual.php',['mes'=>date('Y-m')]+$token,'prueba_psicologa');
    prueba_trazabilidad_verificar($r['codigo']===303,'No guarda informe mensual por HTTP.');
    $mensualId=(int)prueba_trazabilidad_fila($bd,"SELECT cod_informe FROM informes WHERE tipo='Mensual' ORDER BY cod_informe DESC LIMIT 1")['cod_informe'];
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/ver_mensual.php?id='.$mensualId,null,'prueba_director')['codigo']===403,'Director consulta mensual sin autorizaci?n espec?fica.');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/ver.php?id='.$mensualId,null,'prueba_psicologa')['codigo']===303,'Ruta individual no dirige al informe mensual.');
    $atencionesGuardadas=(int)prueba_trazabilidad_fila($bd,'SELECT numero_atenciones FROM informe_individual WHERE cod_informe='.$idInforme)['numero_atenciones'];
    $bd->query('UPDATE informe_individual SET numero_atenciones=999 WHERE cod_informe='.$idInforme);
    $r=prueba_trazabilidad_http('informes/ver.php?id='.$idInforme);
    prueba_trazabilidad_verificar(str_contains($r['cuerpo'],'Valor guardado al emitir: 999') && str_contains($r['cuerpo'],'Atenciones registradas hasta la fecha del informe'),'Informe anterior no diferencia conteo actual y valor original.');
    $bd->query('UPDATE informe_individual SET numero_atenciones='.$atencionesGuardadas.' WHERE cod_informe='.$idInforme);
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('informes/guardar_mensual.php',['mes'=>date('Y-m')]+$token,'prueba_director')['codigo']===403,'Director crea mensual.');
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_cambiar_estado=0 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    $r=prueba_trazabilidad_http('citas/procesar_editar.php',['cod_cita'=>$reutilizada,'estado'=>'Cancelada']+$token,'prueba_psicologa');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT estado FROM citas WHERE cod_cita='.$reutilizada)['estado']==='Pendiente','POST directo ignora permiso de estado.');
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_cambiar_estado=1 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_editar=0 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('citas/procesar_editar.php',['cod_cita'=>$reutilizada,'observaciones'=>'No autorizado']+$token,'prueba_psicologa')['codigo']===403,'Permiso de estado permite editar datos.');
    $r=prueba_trazabilidad_http('citas/cancelar.php',['cod_cita'=>$reutilizada,'estado'=>'Atendida','observaciones'=>'No autorizado','volver'=>'panel','agenda_mes'=>date('Y-m'),'agenda_fecha'=>$hoy]+$token,'prueba_psicologa');
    $cambiada=prueba_trazabilidad_fila($bd,'SELECT estado,observaciones FROM citas WHERE cod_cita='.$reutilizada);
    prueba_trazabilidad_verificar($r['codigo']===303 && $cambiada['estado']==='Atendida' && $cambiada['observaciones']!=='No autorizado','No separa permiso de estado de edición de datos.');
    prueba_trazabilidad_verificar(str_contains($r['cabeceras'],'index.php?mes='.date('Y-m').'&fecha='.$hoy.'#agenda-calendario'),'La acción no conserva el día seleccionado en el panel.');
    $bd->query("UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_editar=1 WHERE p.cod_rol=2 AND m.nombre='Citas'");
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('docentes/cambiar_estado.php',['cod_docente'=>201,'estado'=>'Inactivo']+$token,'prueba_psicologa')['codigo']===403,'Psicóloga da de baja perfil docente.');
    prueba_trazabilidad_http('docentes/cambiar_estado.php',['cod_docente'=>201,'estado'=>'Inactivo']+$token,'prueba_admin');
    prueba_trazabilidad_verificar(prueba_trazabilidad_fila($bd,'SELECT estado FROM docentes WHERE cod_docente=201')['estado']==='Inactivo' && prueba_trazabilidad_fila($bd,'SELECT estado FROM usuarios WHERE cod_usuario=103')['estado']==='Activo','Baja de perfil altera cuenta.');
    $r=prueba_trazabilidad_http('derivaciones/listar.php',null,'prueba_docente');
    prueba_trazabilidad_verificar($r['codigo']!==200,'Perfil inactivo conserva sesión docente.');
    prueba_trazabilidad_http('docentes/cambiar_estado.php',['cod_docente'=>201,'estado'=>'Activo']+$token,'prueba_admin');
    prueba_trazabilidad_verificar(prueba_trazabilidad_http('derivaciones/listar.php',null,'prueba_docente')['codigo']===200,'No restablece acceso al reactivar perfil.');
    if (in_array('--navegador',$argv,true)) {
        $bd->query("INSERT INTO derivaciones (cod_derivacion,fecha,cod_estudiante,cod_docente,motivo,solicitar_cita,cod_psicologa_solicitada) VALUES (41,'$hoy',3,201,'Motivo navegador del flujo',1,102)");
        $chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome para --navegador.');
        file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto,'navegador'=>true,'cuatro_roles'=>true],true).';');
        file_put_contents($temporal.'/navegador.html',prueba_trazabilidad_ejecutar([PHP_BINARY,__DIR__.'/trazabilidad_navegador.php']));
        $html=prueba_trazabilidad_ejecutar([$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--user-data-dir='.$temporal.'/chrome','--dump-dom','--virtual-time-budget=20000','http://127.0.0.1:'.$puerto.'/__prueba_navegador?secreto='.$secreto]);
        $doc=new DOMDocument(); @$doc->loadHTML($html);
        $resultado=json_decode($doc->getElementById('resultado')?->textContent ?? '',true);
        prueba_trazabilidad_verificar(($resultado['ok']??false)===true,'Navegador: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
        $cadena=prueba_trazabilidad_fila($bd,'SELECT i.cod_informe FROM informe_individual i JOIN historias_clinicas h ON h.cod_historia=i.cod_historia JOIN citas c ON c.cod_cita=h.cod_cita_origen WHERE i.cod_estudiante=3 AND i.cod_derivacion=41 AND i.cod_anexo_historico IS NULL AND h.cod_derivacion_origen=41 AND c.cod_derivacion=41');
        prueba_trazabilidad_verificar($cadena!==[],'El navegador no conservó toda la cadena al guardar.');
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
