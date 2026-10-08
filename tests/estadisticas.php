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
$base = 'psicologia_test_estadisticas_' . $sufijo;
$temporal = sys_get_temp_dir() . '/psicologia-estadisticas-' . $sufijo;
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

try {
    $admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $base, '--aplicar', '--instalar']);
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], $base, $config['puerto']);
    $bd->set_charset('utf8mb4');
    prueba_autores($bd);
    prueba_estudiantes($bd, 25);
    $anio = (int)date('Y') - 1;
    $anterior = $anio - 1;
    for ($n = 1; $n <= 24; ++$n) {
        $idHistoria = 100 + $n;
        $fecha = $n === 1 ? "$anterior-12-01" : ($n === 24 ? "$anio-04-01" : "$anio-01-01");
        $estado = $n === 1 ? 'En seguimiento' : ($n === 2 ? 'Cerrada' : 'Activa');
        $bd->query("INSERT INTO historias_clinicas (id_historia,id_estudiante,id_psicologa,fecha_apertura,motivo_consulta,estado)
            VALUES ($idHistoria,$n,102,'$fecha','NOTA_CLINICA_NO_DEBE_EXHIBIRSE','$estado')");
    }
    $bd->query("UPDATE estudiantes SET nombres='Ana <prueba>',apellidos='Alumno' WHERE id_estudiante=1");
    $bd->query("UPDATE estudiantes SET estado='Retirado' WHERE id_estudiante=3");
    $bd->query("UPDATE inscripciones SET estado='Retirado' WHERE id_estudiante=3");
    $bd->query('DELETE FROM inscripciones WHERE id_estudiante=2');
    $bd->query("INSERT INTO inscripciones (id_estudiante,id_seccion,fecha_inscripcion,estado)
        SELECT 1,se.id_seccion,CURRENT_DATE(),'Activo' FROM secciones se JOIN cursos c ON c.id_curso=se.id_curso
        JOIN paralelos p ON p.id_paralelo=se.id_paralelo WHERE c.orden=2 AND p.nombre='B' AND se.turno='Mañana' LIMIT 1");
    $bd->query("INSERT INTO secciones (id_seccion,id_institucion,id_curso,id_paralelo,turno,gestion,estado)
        VALUES (101,1,3,1,'Mañana',$anterior,'Activo')");
    $bd->query("INSERT INTO inscripciones (id_estudiante,id_seccion,fecha_inscripcion,estado) VALUES (1,101,'$anterior-02-01','Finalizado')");
    $bd->query("INSERT INTO seguimientos (id_seguimiento,id_historia,id_psicologa,fecha,descripcion,proxima_sesion) VALUES
        (1,101,102,'$anio-01-01','DETALLE_PRIVADO',NULL),
        (2,101,102,'$anio-02-10','DETALLE_PRIVADO','$anio-03-15'),
        (3,101,102,'$anio-02-10','DETALLE_PRIVADO',NULL),
        (4,102,102,'$anio-03-31','DETALLE_PRIVADO','$anio-04-15'),
        (5,103,102,'$anio-02-20','DETALLE_PRIVADO',NULL),
        (6,101,102,'$anterior-12-31','DETALLE_PRIVADO',NULL),
        (7,101,102,'$anio-04-02','DETALLE_PRIVADO','$anio-04-16')");
    $bd->query("INSERT INTO citas (id_estudiante,id_psicologa,fecha,hora,estado) VALUES
        (1,102,'$anio-03-10','10:00','Atendida'),(1,102,'$anio-03-11','10:00','Cancelada')");
    $filtros = estadisticas_filtros(['desde'=>"$anio-01-01",'hasta'=>"$anio-03-31"]);
    $datos = estadisticas_datos($bd, $filtros);
    verificar($datos['resumen'] === ['historias'=>23,'sesiones'=>5,'con_sesiones'=>3,'sin_sesiones'=>20], 'Conteos incorrectos o duplicados por inscripciones/citas.');
    verificar($datos['meses'] === ["$anio-01"=>1,"$anio-02"=>3,"$anio-03"=>1], 'Serie mensual incorrecta.');
    verificar($datos['estados'] === ['Activa'=>21,'En seguimiento'=>1,'Cerrada'=>1], 'Estados no reconcilian.');
    $porEstudiante = array_column($datos['historias'], null, 'id_estudiante');
    verificar(count($porEstudiante) === 23 && !isset($porEstudiante[24]) && !isset($porEstudiante[25]), 'Incluye historia posterior al corte o estudiante sin historia.');
    verificar($porEstudiante[1]['total_sesiones'] === 4 && $porEstudiante[1]['sesiones_periodo'] === 3, 'Total acumulado se confunde con período.');
    verificar((int)$porEstudiante[1]['id_seguimiento'] === 3 && $porEstudiante[1]['proxima_sesion'] === null, 'No elige el último seguimiento o restaura una fecha antigua.');
    verificar($porEstudiante[1]['curso'] === '2do de Secundaria' && $porEstudiante[1]['paralelo'] === 'B', 'No resuelve la inscripción vigente de mayor gestión.');
    verificar($porEstudiante[2]['curso'] === null && $porEstudiante[3]['estado_estudiante'] === 'Retirado', 'Excluye fichas retiradas o sin inscripción.');
    verificar($porEstudiante[4]['total_sesiones'] === 0 && $porEstudiante[4]['ultima_sesion'] === null, 'Inventa sesiones o fechas para una historia vacía.');
    $curso2 = (int)fila($bd, 'SELECT id_curso FROM cursos WHERE orden=2')['id_curso'];
    $porCurso = estadisticas_datos($bd, array_replace($filtros,['curso'=>$curso2]));
    verificar($porCurso['resumen'] === ['historias'=>1,'sesiones'=>3,'con_sesiones'=>1,'sin_sesiones'=>0], 'Filtro de curso duplica o pierde historia.');
    $cerradas = estadisticas_datos($bd, array_replace($filtros,['estado'=>'Cerrada']));
    verificar($cerradas['resumen']['historias'] === 1 && $cerradas['resumen']['sesiones'] === 1, 'Filtro de estado no afecta a los conteos.');
    $febrero = estadisticas_datos($bd, array_replace($filtros,['desde'=>"$anio-02-01",'hasta'=>"$anio-02-28"]));
    verificar($febrero['resumen']['sesiones'] === 3 && $febrero['meses'] === ["$anio-02"=>3], 'Filtro temporal no modifica todos los componentes.');
    $vacio = estadisticas_datos($bd, array_replace($filtros,['curso'=>$curso2,'estado'=>'Cerrada']));
    verificar($vacio['resumen']['historias'] === 0 && array_sum($vacio['meses']) === 0, 'Selección vacía inventa resultados.');
    foreach ([['desde'=>[]],['desde'=>"$anio-02-30"],['desde'=>"$anio-04-01"],['hasta'=>'2999-01-01'],['desde'=>"$anterior-01-01"],['estado'=>[]],['estado'=>'Inventado'],['curso'=>[]],['curso'=>-1]] as $cambio) {
        rechaza(fn()=>estadisticas_filtros(array_replace($filtros,$cambio)), 'Acepta filtros inválidos.');
    }
    rechaza(fn()=>estadisticas_datos($bd,array_replace($filtros,['curso'=>999999])), 'Acepta curso inexistente.');
    $baseVacia = estadisticas_datos($bd, ['desde'=>'1900-01-01','hasta'=>'1900-01-31']);
    verificar($baseVacia['resumen'] === ['historias'=>0,'sesiones'=>0,'con_sesiones'=>0,'sin_sesiones'=>0], 'No maneja un período sin historias.');
    echo "Conteos clínicos, inscripciones múltiples, límites de fechas y filtros: correctos.\n";
    $sinDatos = estadisticas_panel($bd,'1900-01-01');
    verificar(array_sum($sinDatos['resumen']) === 0 && !$sinDatos['prioritarios'] && !$sinDatos['agenda'], 'Panel sin datos inventa actividad.');
    $hoy = new DateTimeImmutable('today');
    $fecha = static fn(string $cambio): string => $hoy->modify($cambio)->format('Y-m-d');
    $bd->query("UPDATE historias_clinicas SET estado='En seguimiento' WHERE id_historia=104");
    $bd->query("UPDATE historias_clinicas SET estado='En seguimiento',fecha_apertura='".$fecha('+1 day')."' WHERE id_historia=124");
    $bd->query("INSERT INTO seguimientos (id_historia,id_psicologa,fecha,descripcion) VALUES
        (101,102,'".$fecha('-29 days')."','DETALLE_PRIVADO'),(101,102,'".$fecha('0 days')."','DETALLE_PRIVADO'),
        (101,102,'".$fecha('+1 day')."','DETALLE_PRIVADO'),(101,102,'".$fecha('-30 days')."','DETALLE_PRIVADO'),
        (104,102,'".$fecha('-4 days')."','DETALLE_PRIVADO')");
    foreach ([[901,1,'Alta','Pendiente','-10 days'],[902,1,'Alta','En seguimiento','-5 days'],
        [903,2,'Media','Pendiente','-7 days'],[904,3,'Alta','Pendiente','-8 days'],[905,4,'Baja','En seguimiento','-7 days'],
        [906,5,'Alta','Atendido','-10 days'],[907,6,'Alta','Pendiente','+1 day'],[908,7,'Alta','Pendiente','-6 days'],
        [909,25,'Media','Pendiente','-2 days'],[910,8,'Baja','Pendiente','-3 days']] as [$id,$estudiante,$prioridad,$estado,$cuando]) {
        $bd->query("INSERT INTO derivaciones (id_derivacion,id_estudiante,id_docente,fecha,motivo,prioridad,estado)
            VALUES ($id,$estudiante,201,'".$fecha($cuando)."','DETALLE_PRIVADO','$prioridad','$estado')");
    }
    foreach ([[901,1,'09:00','Pendiente','0 days'],[902,2,'10:00','Reprogramada','0 days'],
        [903,4,'11:00','Atendida','0 days'],[904,7,'12:00','Cancelada','0 days'],[905,2,'09:00','Pendiente','+6 days'],
        [906,2,'09:00','Pendiente','+7 days'],[907,2,'09:00','Pendiente','-1 day'],[908,2,'09:00','Atendida','+1 day']] as [$id,$estudiante,$hora,$estado,$cuando]) {
        $bd->query("INSERT INTO citas (id_cita,id_estudiante,id_psicologa,fecha,hora,estado)
            VALUES ($id,$estudiante,102,'".$fecha($cuando)."','$hora','$estado')");
    }
    $panel = estadisticas_panel($bd);
    verificar($panel['resumen'] === ['en_seguimiento'=>2,'prioridad_alta'=>2,'citas_pendientes'=>3,'sesiones'=>11,'sesiones_recientes'=>3,'por_atender'=>6], 'Panel: totales, estados o límites de 7/30 días incorrectos.');
    verificar(array_map('intval',array_column($panel['prioritarios'],'id_estudiante')) === [7,1,25,2,8], 'Panel: repite estudiantes, omite sin historia o no respeta prioridad/antigüedad.');
    $prioritario = $panel['prioritarios'][1];
    verificar((int)$prioritario['id_derivacion'] === 901 && $prioritario['curso'] === '2do de Secundaria' && $prioritario['paralelo'] === 'B', 'Panel no elige derivación abierta más antigua o última inscripción.');
    verificar($prioritario['ultima_sesion'] === $fecha('0 days') && $panel['prioritarios'][2]['id_historia'] === null, 'Panel incluye sesión futura o inventa historia.');
    verificar(array_map('intval',array_column($panel['agenda'],'id_cita')) === [901,902,903], 'Agenda no respeta horario, día o cancelaciones.');
    echo "Panel: tarjetas, prioridad por estudiante, agenda y límites de fechas correctos.\n";
    mkdir($temporal . '/app', 0700); mkdir($temporal . '/sesiones', 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (!in_array($archivo->getExtension(), ['php','css','js'], true)) continue;
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


    $url = 'estadisticas/index.php?' . http_build_query($filtros);
    foreach (['prueba_admin','prueba_psicologa'] as $usuario) {
        $r = http($url, null, $usuario);
        verificar($r['codigo'] === 200 && str_contains($r['cuerpo'],'Ana &lt;prueba&gt;'), 'No permite consultar la ficha escapada.');
        verificar(str_contains($r['cuerpo'],'data-valor="5"') && !str_contains($r['cuerpo'],'NOTA_CLINICA_NO_DEBE_EXHIBIRSE') && !str_contains($r['cuerpo'],'DETALLE_PRIVADO'), 'No muestra total o expone notas innecesarias.');
        verificar(str_contains($r['cuerpo'],'historias_clinicas/ver.php?id=101') && str_contains($r['cuerpo'],'seguimientos/ver.php?id=3'), 'No permite abrir las fuentes de la ficha.');
    }
    foreach (['prueba_docente','prueba_director'] as $usuario) {
        verificar(http($url, null, $usuario)['codigo'] === 403, 'Rol restringido accede a estadísticas clínicas.');
        $ruta = $usuario === 'prueba_docente' ? 'derivaciones/listar.php' : 'informes/listar.php';
        verificar(!str_contains(http($ruta,null,$usuario)['cuerpo'],'estadisticas/index.php'), 'El menú ofrece estadísticas a un rol restringido.');
    }
    verificar(http('estadisticas/datos.php')['codigo'] === 404, 'Datos internos accesibles directamente.');
    verificar(http($url, ['csrf'=>str_repeat('c',64)])['codigo'] === 405, 'Estadísticas acepta escrituras.');
    $r = http('index.php');
    verificar(str_contains($r['cuerpo'],'href="/estadisticas/index.php"'), 'El panel conserva el enlace deshabilitado.');
    verificar($r['codigo'] === 200 && str_contains($r['cuerpo'],'id="panel-citas_pendientes" data-valor="3"') && str_contains($r['cuerpo'],'Mostrando 5 de 6'), 'El panel no muestra los conteos y límite reales.');
    verificar(str_contains($r['cuerpo'],'Ana &lt;prueba&gt;') && !str_contains($r['cuerpo'],'DETALLE_PRIVADO') && !str_contains($r['cuerpo'],'NOTA_CLINICA_NO_DEBE_EXHIBIRSE'), 'El panel no escapa nombres o muestra notas clínicas.');
    $r = http('index.php',null,'prueba_psicologa');
    verificar($r['codigo'] === 200 && !str_contains($r['cuerpo'],'docentes/listar.php'), 'El panel ofrece accesos sin permiso a psicóloga.');
    foreach (['prueba_docente','prueba_director'] as $usuario) verificar(http('index.php',null,$usuario)['codigo'] === 403, 'Rol restringido accede al panel clínico.');
    $r = http($url . '&pagina=2');
    verificar(str_contains($r['cuerpo'],'21–23 de 23 historias') && str_contains($r['cuerpo'],'data-valor="5"'), 'Paginación cambia los totales.');
    verificar(http($url . '&pagina[]=1')['codigo'] === 400, 'No rechaza página inválida.');
    $r = http('estadisticas/index.php?' . http_build_query(array_replace($filtros,['curso'=>$curso2,'estado'=>'Cerrada'])));
    verificar(str_contains($r['cuerpo'],'No hay historias clínicas para los filtros seleccionados.'), 'Selección vacía no explica el resultado.');
    $r = http('estadisticas/index.php?desde[]=1');
    verificar($r['codigo'] === 400 && !str_contains($r['cuerpo'],'id="total-historias"'), 'Error de filtros se presenta como ceros.');
    $bd->query('RENAME TABLE seguimientos TO seguimientos_prueba');
    try {
        $r = http($url);
        verificar($r['codigo'] === 503 && str_contains($r['cuerpo'],'No se pudieron consultar') && !str_contains($r['cuerpo'],'id="total-historias"'), 'Error SQL se presenta como ausencia de atenciones.');
        $r = http('index.php');
        verificar($r['codigo'] === 503 && str_contains($r['cuerpo'],'No se pudo cargar el resumen') && !str_contains($r['cuerpo'],'id="panel-sesiones"'), 'Panel presenta errores de consulta como ceros.');
    } finally { $bd->query('RENAME TABLE seguimientos_prueba TO seguimientos'); }
    echo "Página, enlaces, paginación, errores y privacidad por rol: correctos.\n";
    if (in_array('--navegador', $argv, true)) {
        $chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome.');
        file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto,'cuatro_roles'=>true,'navegador'=>true],true) . ';');
        $plantilla = <<<'HTML'
<!doctype html><html lang="es"><meta charset="utf-8"><title>Prueba de estadísticas</title>
<style>body{margin:0}iframe{display:block;border:0;width:100%;height:100vh}#resultado{position:fixed;bottom:0;left:0;background:#fff;z-index:1000;font-size:10px;margin:0}</style>
<body><pre id="resultado">PENDIENTE</pre><iframe id="app" title="Estadísticas de prueba"></iframe>
<script>
(async()=>{
    const ruta=__RUTA__, curso=__CURSO__, app=document.getElementById('app');
    let total=0;
    const comprobar=(ok,m)=>{if(!ok) throw new Error(m);total++;};
    const abrir=url=>new Promise(resolve=>{app.onload=resolve;app.src=url;});
    const campo=id=>app.contentDocument.getElementById(id);
    const valor=id=>Number(campo('total-'+id).dataset.valor);
    const enviar=()=>new Promise(resolve=>{app.onload=resolve;campo('filtros-estadisticas').requestSubmit();});
    const ancho=new URL(location.href).searchParams.get('ancho');
    if(ancho) app.style.width=ancho+'px';
    try {
        await abrir(ruta);
        comprobar(valor('historias')===23 && valor('sesiones')===5,'Resumen inicial incorrecto.');
        comprobar(app.contentDocument.querySelector('.estadisticas-pista > span').getBoundingClientRect().width>0,'Las barras no son visibles.');
        comprobar(app.contentDocument.documentElement.scrollWidth<=app.contentWindow.innerWidth+1,'La página desborda el ancho disponible.');
        campo('curso').value=curso; await enviar();
        comprobar(valor('historias')===1 && valor('sesiones')===3,'El filtro de curso no actualiza el resumen.');
        comprobar(app.contentDocument.querySelectorAll('tr[data-historia]').length===1,'El filtro no actualiza estudiantes.');
        campo('estado').value='Cerrada'; await enviar();
        comprobar(valor('historias')===0 && app.contentDocument.body.textContent.includes('No hay historias clínicas'),'No muestra selección vacía.');
        campo('estado').value=''; campo('curso').value='0'; await enviar();
        comprobar(valor('historias')===23 && valor('sesiones')===5,'Todos los cursos no recupera los datos.');
        campo('desde').value=campo('desde').value.slice(0,4)+'-02-01'; campo('hasta').value=campo('hasta').value.slice(0,4)+'-02-28'; await enviar();
        comprobar(valor('sesiones')===3 && app.contentDocument.querySelectorAll('[data-mes]').length===1,'El período no actualiza el gráfico.');
        await abrir(ruta);
        const siguiente=Array.from(app.contentDocument.querySelectorAll('.estadisticas-paginacion a')).find(a=>a.textContent==='Siguiente');
        await abrir(siguiente.href);
        comprobar(app.contentDocument.querySelectorAll('tr[data-historia]').length===3 && valor('sesiones')===5,'Paginación incorrecta.');
        await abrir(ruta);
        const historia=app.contentDocument.querySelector('tr[data-historia="101"] a');
        await abrir(historia.href);
        comprobar(app.contentWindow.location.pathname.endsWith('/historias_clinicas/ver.php') && app.contentDocument.body.textContent.includes('Ana <prueba>'),'No abre el detalle clínico.');
        await abrir(ruta);
        await abrir(campo('restablecer-estadisticas').href);
        comprobar(campo('curso').value==='0' && campo('estado').value==='' && campo('hasta').value===__HOY__,'No restablece los filtros.');
        await abrir(ruta);
        comprobar(app.contentDocument.querySelector('.estadisticas-tabla-scroll').getBoundingClientRect().right<=app.contentWindow.innerWidth+1,'La tabla desborda su contenedor.');
        await abrir('/index.php');
        comprobar(campo('panel-citas_pendientes').dataset.valor==='3' && campo('panel-sesiones').dataset.valor==='11','Tarjetas del panel incorrectas.');
        comprobar(app.contentDocument.querySelectorAll('.panel-tabla tr[data-estudiante]').length===5 && app.contentDocument.querySelectorAll('.panel-agenda li').length===3,'Panel sin tabla o agenda.');
        comprobar(app.contentDocument.documentElement.scrollWidth<=app.contentWindow.innerWidth+1,'El panel desborda el ancho disponible.');
        const tarjetas=Array.from(app.contentDocument.querySelectorAll('.panel-tarjeta'));
        comprobar(tarjetas.every(t=>t.getBoundingClientRect().width>0) && (ancho>1250 ? tarjetas[0].offsetTop===tarjetas[3].offsetTop : tarjetas[0].offsetTop<tarjetas[3].offsetTop),'Tarjetas no se adaptan a pantalla.');
        const derivacion=app.contentDocument.querySelector('.panel-abrir').href;
        await abrir(derivacion);
        comprobar(app.contentWindow.location.pathname==='/derivaciones/ver.php' && app.contentDocument.body.textContent.includes('Prueba 7'),'No abre la derivación del panel.');
        await abrir('/index.php');
        await abrir(app.contentDocument.querySelector('.panel-cita > a').href);
        comprobar(app.contentWindow.location.pathname==='/citas/editar.php' && campo('form-cita')!==null,'No abre la cita de la agenda.');
        await abrir('/index.php');
        await abrir(campo('panel-sesiones').closest('a').href);
        comprobar(campo('filtros-estadisticas')!==null,'Tarjeta no abre estadísticas.');
        await abrir('/index.php');
        app.contentWindow.scrollTo(0,0);
        window.scrollTo(0,0);
        await new Promise(resolve=>setTimeout(resolve,500));
        document.getElementById('resultado').textContent=JSON.stringify({ok:true,total,ancho:app.contentWindow.innerWidth});
    } catch(e) {document.getElementById('resultado').textContent=JSON.stringify({ok:false,total,error:e.message});}
})();
</script></body></html>
HTML;
        $plantilla = str_replace(['__RUTA__','__CURSO__','__HOY__'], [json_encode('/'.$url),json_encode((string)$curso2),json_encode(date('Y-m-d'))], $plantilla);
        file_put_contents($temporal . '/navegador.html', $plantilla);
        foreach ([1440,390] as $ancho) {
            $argumentos = [$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking',
                '--user-data-dir='.$temporal.'/chrome-'.$ancho,'--window-size='.max(500,$ancho).',1800','--dump-dom','--virtual-time-budget=20000',
                'http://127.0.0.1:'.$puerto.'/__prueba_navegador?secreto='.$secreto.'&ancho='.$ancho];
            $capturas = getenv('CAPTURAS_ESTADISTICAS');
            if ($capturas && is_dir($capturas)) array_splice($argumentos, -1, 0, ['--screenshot='.$capturas.'/panel-'.$ancho.'.png']);
            $html = ejecutar($argumentos);
            $dom = new DOMDocument(); @$dom->loadHTML($html);
            $resultado = json_decode($dom->getElementById('resultado')?->textContent ?? '',true);
            verificar(($resultado['ok'] ?? false) === true, 'Navegador: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
            echo 'Chrome '.$ancho.' px: '.$resultado['total']." comprobaciones correctas.\n";
        }
    }
    echo "OK: $total comprobaciones de estadísticas. Sin escrituras de prueba en la base real.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    if (is_file($temporal . '/servidor.log')) fwrite(STDERR, implode('', array_slice(file($temporal . '/servidor.log'), -20)));
    $fallo = true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    if (!preg_match('/^psicologia_test_estadisticas_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Base de limpieza no permitida.');
    if ($admin instanceof mysqli) $admin->query("DROP DATABASE IF EXISTS `$base`");
    $ruta = realpath($temporal);
    if ($ruta && dirname($ruta) === realpath(sys_get_temp_dir()) && basename($ruta) === 'psicologia-estadisticas-' . $sufijo) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
        }
        rmdir($ruta);
    }
}
exit(isset($fallo) ? 1 : 0);
