<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/estudiantes/csv.php';
require_once dirname(__DIR__) . '/informes/datos.php';
require_once __DIR__ . '/datos_sinteticos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
$admin->set_charset('utf8mb4');
$sufijo = bin2hex(random_bytes(6));
$bases = [];
$temporal = sys_get_temp_dir() . '/psicologia-integracion-' . $sufijo;
mkdir($temporal, 0700, true);
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
    if (!is_resource($p)) throw new RuntimeException('No se pudo iniciar PHP.');
    fclose($pipes[0]);
    $limite = microtime(true) + 45;
    do {
        $estado = proc_get_status($p);
        if (!$estado['running']) break;
        usleep(100000);
    } while (microtime(true) < $limite);
    if ($estado['running']) proc_terminate($p);
    proc_close($p);
    if ($estado['running'] || $estado['exitcode'] !== 0) throw new RuntimeException(file_get_contents($temporal . '/proceso.out') . file_get_contents($temporal . '/proceso.err'));
    return file_get_contents($temporal . '/proceso.out');
}
function conectar(string $base): mysqli
{
    global $config;
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], $base, $config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $bd;
}
function consultar(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function estructura(mysqli $bd): array
{
    $resultado = [];
    foreach ($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM) as [$tabla]) {
        $crear = $bd->query("SHOW CREATE TABLE `$tabla`")->fetch_row()[1];
        $resultado[$tabla] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $crear);
    }
    ksort($resultado);
    return $resultado;
}
function importar(mysqli $bd, string $csv): array
{
    $h = fopen('php://temp', 'w+'); fwrite($h, $csv); rewind($h);
    try { return estudiantes_importar_csv($bd, $h); } finally { fclose($h); }
}
function http_prueba(string $ruta, ?array $datos = null, ?string $csv = null, string $usuario = 'prueba_admin'): array
{
    global $puerto, $secreto, $cookie;
    $cabeceras = ['X-Prueba-Secreto: ' . $secreto, 'X-Prueba-Usuario: ' . $usuario, 'Connection: close'];
    if ($cookie) $cabeceras[] = 'Cookie: ' . $cookie;
    $opciones = ['method' => $datos === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
    if ($datos !== null) {
        if ($csv === null) {
            $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            $opciones['content'] = http_build_query($datos);
        } else {
            $limite = 'PruebaCSV' . bin2hex(random_bytes(8));
            $cabeceras[] = 'Content-Type: multipart/form-data; boundary=' . $limite;
            $cuerpo = '';
            foreach ($datos as $nombre => $valor) $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$nombre\"\r\n\r\n$valor\r\n";
            $opciones['content'] = $cuerpo . "--$limite\r\nContent-Disposition: form-data; name=\"archivo\"; filename=\"prueba.csv\"\r\nContent-Type: text/csv\r\n\r\n$csv\r\n--$limite--\r\n";
        }
    }
    $opciones['header'] = implode("\r\n", $cabeceras);
    $cuerpo = file_get_contents('http://127.0.0.1:' . $puerto . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    foreach ($http_response_header as $cabecera) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecera, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
    verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP al abrir $ruta");
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

try {
    $nueva = 'psicologia_test_nueva_' . $sufijo;
    $actualizacion = 'psicologia_test_actualizar_' . $sufijo;
    $bases[] = $nueva;
    $salida = ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $nueva, '--aplicar', '--instalar']);
    verificar(str_contains($salida, 'verificado'), 'Falló la instalación vacía.');
    $bd = conectar($nueva);
    $antes = estructura($bd);
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $nueva, '--aplicar']);
    verificar($antes === estructura($bd), 'Reaplicar la migración altera el esquema.');
    verificar((int)consultar($bd, 'SELECT COUNT(*) n FROM roles')['n'] === 4, 'Faltan roles al instalar.');
    verificar((int)consultar($bd, 'SELECT COUNT(*) n FROM paralelos')['n'] === 4, 'Faltan catálogos al instalar.');

    // Ensaya la migración normalizada con datos sintéticos y conserva cada columna previa.
    $bases[] = $actualizacion;
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $actualizacion, '--aplicar', '--instalar']);
    $copia = conectar($actualizacion);
    $copia->query('ALTER TABLE historias_clinicas DROP COLUMN tutor_curso');
    prueba_autores($copia);
    prueba_estudiantes($copia, 1);
    $copia->query("INSERT INTO historias_clinicas (id_historia,id_estudiante,id_psicologa,fecha_apertura,motivo_consulta,observaciones) VALUES (1,1,101,'2026-01-01','Motivo previo','Conservar observaciones')");
    $copia->query("INSERT INTO seguimientos (id_historia,id_psicologa,fecha,descripcion,acuerdos,proxima_sesion) VALUES (1,101,'2026-01-02','Descripción previa','Acuerdo previo','2026-02-01')");
    $copia->query("INSERT INTO informes (id_informe,id_elaborado_por,titulo,numero_ficha,tipo) VALUES (1,101,'Título anterior','INF-0042','Individual')");
    $copia->query('INSERT INTO informe_individual (id_informe,id_estudiante,id_historia) VALUES (1,1,1)');
    $copia->query('UPDATE informes_secuencia SET ultimo=42 WHERE id=1');
    $filasAntes = [];
    foreach (array_keys(estructura($copia)) as $tabla) $filasAntes[$tabla] = $copia->query("SELECT * FROM `$tabla` ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    $esquemaAntes = estructura($copia);
    $simulacion = ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $actualizacion, '--comprobar']);
    verificar(str_contains($simulacion, 'ADD COLUMN tutor_curso') && estructura($copia) === $esquemaAntes, 'La simulación altera el esquema o no detecta el tutor pendiente.');
    ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $actualizacion, '--aplicar']);
    verificar(estructura($bd) === estructura($copia), 'Instalación y actualización no producen el mismo esquema.');
    foreach ($filasAntes as $tabla => $filas) {
        $despues = $copia->query("SELECT * FROM `$tabla` ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
        verificar(count($filas) === count($despues), "La migración cambia el número de filas: $tabla");
        foreach ($filas as $i => $fila) verificar($fila === array_intersect_key($despues[$i], $fila), "La migración modificó datos previos: $tabla");
    }
    verificar((int)consultar($copia, 'SELECT ultimo FROM informes_secuencia WHERE id=1')['ultimo'] === 42, 'La migración altera la secuencia.');
    $planificar = require $raiz . '/database/migrations_normalizadas/001_tutor_historia.php';
    verificar($planificar($copia) === [], 'La migración no es idempotente.');
    $copia->query('DELETE FROM materias WHERE id_materia BETWEEN 7 AND 10');
    $copia->query("INSERT INTO materias (id_materia,nombre,estado) VALUES (77,'Fisica','Inactivo')");
    $catalogoAntes = $copia->query('SELECT * FROM materias ORDER BY id_materia')->fetch_all(MYSQLI_ASSOC);
    $planMaterias = require $raiz . '/database/migrations_normalizadas/002_materias_docentes.php';
    verificar(count($planMaterias($copia)) === 3, 'Duplica Física por su acento o no detecta materias faltantes.');
    ejecutar([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$actualizacion,'--comprobar']);
    verificar($catalogoAntes === $copia->query('SELECT * FROM materias ORDER BY id_materia')->fetch_all(MYSQLI_ASSOC), 'Comprobar modifica el catálogo.');
    ejecutar([PHP_BINARY,$raiz.'/database/migrar.php','--base='.$actualizacion,'--aplicar']);
    foreach ($catalogoAntes as $materia) verificar($materia === consultar($copia,'SELECT * FROM materias WHERE id_materia='.(int)$materia['id_materia']), 'La migración cambia una materia existente.');
    verificar($planMaterias($copia) === [] && (int)consultar($copia,'SELECT COUNT(*) n FROM materias')['n'] === 10, 'Catálogo incompleto o migración no idempotente.');
    echo "Instalación, actualización, conservación de datos e idempotencia: correctas.\n";

    prueba_autores($bd);
    // IDs diferentes del grado visible: detecta el antiguo fallback numérico.
    $bd->query('UPDATE cursos SET id_curso=71 WHERE id_curso=1');
    $bd->query("UPDATE secciones s JOIN paralelos p ON p.id_paralelo=s.id_paralelo SET s.estado='Inactivo' WHERE s.id_curso=2 AND p.nombre='D'");
    $datos = estudiante_datos(['codigo'=>'UNO','ci'=>'CIUNO','nombres'=>'María','apellidos'=>'Prueba','fecha_nacimiento'=>'2010-02-28',
        'genero'=>'Femenino','curso'=>'1','paralelo'=>'D','turno'=>'Mañana','estado'=>'Activo']);
    $academico = estudiante_validar($datos, estudiante_catalogo($bd));
    $seccion = consultar($bd, 'SELECT id_curso,id_paralelo FROM secciones WHERE id_seccion=' . (int)$academico['id_seccion']);
    verificar((int)$seccion['id_curso'] === 71 && (int)$seccion['id_paralelo'] === 4, 'No resuelve IDs reales del catálogo.');
    estudiante_insertar($bd, $datos, $academico);
    $idEstudiante = (int)consultar($bd, "SELECT id_estudiante FROM estudiantes WHERE codigo='UNO'")['id_estudiante'];
    $guardado = consultar($bd, "SELECT * FROM vista_estudiantes WHERE id_estudiante=$idEstudiante");
    verificar($guardado['curso'] === '1ro de Secundaria' && (int)$guardado['id_curso'] === 71 && $guardado['sexo'] === 'F', 'No persiste datos coherentes del estudiante.');
    foreach ([['curso'=>'99'], ['curso'=>'2','paralelo'=>'D'], ['estado'=>'Baja'], ['estado'=>'En seguimiento'], ['fecha_nacimiento'=>'2010-02-30'], ['codigo'=>str_repeat('x',21)]] as $cambio) {
        rechaza(fn() => estudiante_validar(array_replace($datos,$cambio), estudiante_catalogo($bd)), 'Aceptó datos inválidos del estudiante.');
    }
    rechaza(fn() => estudiante_insertar($bd,$datos,$academico), 'Aceptó CI/código duplicado.');
    $encabezado = 'codigo;ci;nombres;apellidos;fecha_nacimiento;genero;curso;paralelo;turno;estado';
    $filaCSV = 'DOS;CIDOS;Ana;Prueba;2011-01-01;Femenino;1;A;Tarde;Activo';
    $resultado = importar($bd, "\xEF\xBB\xBF$encabezado\n$filaCSV\n$filaCSV\nTRES;CITRES;Ana;Prueba;2011-01-01;Femenino;2;D;Tarde;Activo\nCUATRO;CICUATRO;Ana;Prueba;2011-01-01;Femenino;3;D;Tarde;Retirado\nmal;columnas\n");
    verificar($resultado['importados'] === 2 && $resultado['rechazados'] === 3, 'El resumen parcial es incorrecto.');
    verificar(str_contains(implode(' ', $resultado['errores']), 'Fila 3'), 'El error de duplicado no indica su fila.');
    rechaza(fn() => importar($bd, "$encabezado;codigo\n"), 'Aceptó encabezados duplicados.');
    rechaza(fn() => importar($bd, 'codigo;ci'), 'Aceptó encabezados incompletos.');
    verificar((int)consultar($bd,"SELECT COUNT(*) n FROM inscripciones i LEFT JOIN secciones s ON s.id_seccion=i.id_seccion WHERE s.id_seccion IS NULL")['n'] === 0, 'Se importó un paralelo ajeno al curso.');
    echo "Alta manual, catálogo con IDs no consecutivos e importación parcial: correctos.\n";

    $informe = informe_datos(['id_estudiante'=>$idEstudiante,'titulo'=>'Título de prueba','fecha'=>'2026-01-15','tipo_atencion'=>['Evaluación'],
        'motivo'=>'Motivo','aspecto_cognitivo'=>'Cognitivo','aspectos_afectivos'=>'Afectivo','estado'=>'Borrador']);
    verificar(informe_crear($bd,$informe,101) === 'INF-0001', 'Primera ficha incorrecta.');
    $idInforme = (int)consultar($bd,'SELECT MAX(id_informe) id FROM informes')['id'];
    $filaInforme = consultar($bd,"SELECT i.*,d.* FROM informes i JOIN informe_individual d ON d.id_informe=i.id_informe WHERE i.id_informe=$idInforme");
    verificar((int)$filaInforme['id_elaborado_por']===101 && $filaInforme['tipo']==='Individual' && $filaInforme['titulo']==='Título de prueba', 'No conserva el modelo individual y su autor.');
    verificar($filaInforme['id_historia']===null && $filaInforme['id_derivacion']===null, 'No admite informe sin historia.');
    informe_actualizar($bd,$idInforme,array_replace($informe,['estado'=>'Finalizado','titulo'=>'Título editado']));
    informe_actualizar($bd,$idInforme,array_replace($informe,['estado'=>'Finalizado','titulo'=>'Título editado']));
    verificar(consultar($bd,"SELECT estado FROM informes WHERE id_informe=$idInforme")['estado']==='Finalizado', 'No guarda el estado canónico.');
    foreach ([['estado'=>'Emitido'],['estado'=>'Anulado'],['titulo'=>''],['titulo'=>str_repeat('x',181)],['referido_por'=>str_repeat('x',151)],['recibido_por'=>str_repeat('x',151)],['tipo_atencion'=>['Desconocida']],['numero_atenciones'=>-1],['id_historia'=>987]] as $cambio) {
        rechaza(fn() => informe_crear($bd,array_replace($informe,$cambio),101), 'Aceptó informe inválido.');
    }
    rechaza(fn()=>informe_actualizar($bd,9999,$informe),'La edición inexistente comunicó éxito.');
    rechaza(fn()=>informe_actualizar($bd,$idInforme,array_replace($informe,['id_estudiante'=>9999])),'Aceptó cambio de estudiante.');
    $secuenciaAntes=consultar($bd,'SELECT ultimo FROM informes_secuencia WHERE id=1');
    rechaza(fn()=>informe_crear($bd,$informe,9999),'Aceptó autor inexistente.');
    verificar($secuenciaAntes===consultar($bd,'SELECT ultimo FROM informes_secuencia WHERE id=1'),'No revirtió la numeración tras fallo SQL.');
    $bd->query("INSERT INTO historias_clinicas (id_estudiante,id_psicologa,fecha_apertura,motivo_consulta) VALUES ($idEstudiante,101,'2026-01-01','Motivo historia')");
    $idHistoria = $bd->insert_id;
    $bd->query("INSERT INTO seguimientos (id_historia,id_psicologa,fecha,descripcion,acuerdos,recomendaciones,proxima_sesion) VALUES ($idHistoria,101,'2026-01-02','Evolución','Acuerdos separados','Recomendación verificable','2026-02-01')");
    $informe['id_historia']=$idHistoria;
    informe_crear($bd,$informe,102);
    verificar((int)consultar($bd,'SELECT id_historia FROM informe_individual ORDER BY id_informe DESC LIMIT 1')['id_historia']===$idHistoria,'No vincula la historia correcta.');

    // Cuatro conexiones independientes compiten por la misma numeración.
    $trabajadores=[];
    $codigo = 'require ' . var_export($raiz.'/informes/datos.php',true) . '; mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT); $c=' . var_export($config,true) . '; $b=new mysqli($c["host"],$c["usuario_bd"],$c["clave_bd"],' . var_export($nueva,true) . ',$c["puerto"]); $b->set_charset("utf8mb4"); echo informe_crear($b,' . var_export($informe,true) . ',101);';
    for($i=0;$i<4;$i++) {
        $p=proc_open([PHP_BINARY,'-r',$codigo],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $trabajadores[]=[$p,$pipes];
    }
    $fichas=[];
    foreach($trabajadores as [$p,$pipes]) {
        $fichas[]=stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
        verificar(proc_close($p)===0,'Falló informe concurrente: '.$error);
    }
    verificar(count(array_unique($fichas))===4,'La numeración concurrente produjo duplicados.');
    echo "Informes: creación, edición, rollback y numeración concurrente correctos.\n";

    mkdir($temporal.'/app',0700); mkdir($temporal.'/sesiones',0700);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz,FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if(!in_array($archivo->getExtension(),['php','css','js'],true)) continue;
        $relativa=substr($archivo->getPathname(),strlen($raiz)+1);
        if(str_starts_with($relativa,'tests'.DIRECTORY_SEPARATOR)) continue;
        $destino=$temporal.'/app/'.$relativa;
        if(!is_dir(dirname($destino))) mkdir(dirname($destino),0700,true);
        copy($archivo->getPathname(),$destino);
    }
    $configPrueba=$config; $configPrueba['base_datos']=$nueva; $configPrueba['base_url']='';
    file_put_contents($temporal.'/app/config/login.php','<?php return '.var_export($configPrueba,true).';');
    $secreto=bin2hex(random_bytes(32));
    file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto],true).';');
    copy(__DIR__.'/integracion_router.php',$temporal.'/router.php');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    $puerto=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1); fclose($socket);
    $servidor=proc_open([PHP_BINARY,'-d','display_errors=1','-S','127.0.0.1:'.$puerto,'-t',$temporal.'/app',$temporal.'/router.php'],
        [0=>['pipe','r'],1=>['file',$temporal.'/servidor.log','a'],2=>['file',$temporal.'/servidor.log','a']],$pipes,$temporal);
    fclose($pipes[0]);
    for($i=0;$i<40;$i++) { $s=@fsockopen('127.0.0.1',$puerto,$errno,$error,0.1); if($s){fclose($s);break;} usleep(100000); }
    foreach(['informes/registrar.php','informes/listar.php','informes/ver.php?id='.$idInforme,'informes/editar.php?id='.$idInforme,'estudiantes/registrar.php','estudiantes/listar.php'] as $ruta) {
        $r=http_prueba($ruta); verificar($r['codigo']===200,"Formulario no disponible: $ruta");
        if($ruta==='informes/registrar.php') verificar(str_contains($r['cuerpo'],'Recomendación verificable'),'No consulta recomendaciones de seguimientos.');
    }
    $token=['csrf'=>str_repeat('c',64)];
    $r=http_prueba('informes/guardar.php',array_replace($informe,['titulo'=>'','motivo'=>'Recuperar <contenido>'])+$token);
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'registrar.php'),'Alta inválida no vuelve al formulario.');
    $r=http_prueba('informes/registrar.php');
    verificar(str_contains($r['cuerpo'],'Recuperar &lt;contenido&gt;'),'No recupera/escapa el texto del informe.');
    $r=http_prueba('informes/guardar.php',$informe+$token+['id_usuario'=>9999]);
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'listar.php'),'Falló el receptor de alta de informes.');
    verificar((int)consultar($bd,'SELECT id_elaborado_por FROM informes ORDER BY id_informe DESC LIMIT 1')['id_elaborado_por']===101,'Aceptó autor desde POST.');
    $r=http_prueba('informes/actualizar.php',array_replace($informe,['id_informe'=>$idInforme,'titulo'=>'Edición HTTP','estado'=>'Finalizado'])+$token,null,'prueba_psicologa');
    verificar($r['codigo']===302 && str_contains($r['cabeceras'],'ver.php'),'Falló receptor de edición.');
    verificar((int)consultar($bd,"SELECT id_elaborado_por FROM informes WHERE id_informe=$idInforme")['id_elaborado_por']===101,'La edición cambia el autor.');
    $r=http_prueba('informes/actualizar.php',array_replace($informe,['id_informe'=>$idInforme,'recibido_por'=>str_repeat('x',151),'motivo'=>'Recuperar edición'])+$token);
    verificar(str_contains($r['cabeceras'],'editar.php'),'Edición inválida no redirige.');
    $r=http_prueba('informes/editar.php?id='.$idInforme);
    verificar(str_contains($r['cuerpo'],'Recuperar edición'),'No recupera los datos de edición.');
    $r=http_prueba('estudiantes/guardar.php',array_replace($datos,['estado'=>'Baja','nombres'=>'Recuperar <alumno>'])+$token);
    verificar(str_contains($r['cabeceras'],'registrar.php'),'Alta de estudiante inválida no redirige.');
    $r=http_prueba('estudiantes/registrar.php');
    verificar(str_contains($r['cuerpo'],'Recuperar &lt;alumno&gt;'),'No recupera/escapa los datos del estudiante.');
    $r=http_prueba('estudiantes/guardar.php',array_replace($datos,['codigo'=>'MANUALHTTP','ci'=>'MANUALHTTP'])+$token);
    verificar(str_contains($r['cabeceras'],'listar.php'),'Falló alta manual HTTP.');
    $csvHTTP=$encabezado."\n".str_replace(['DOS','CIDOS'],['HTTP','CIHTTP'],$filaCSV)."\nmal;columnas";
    $r=http_prueba('estudiantes/importar.php',$token,$csvHTTP);
    verificar($r['codigo']===302,'Falló carga multipart de CSV.');
    $r=http_prueba('estudiantes/listar.php');
    verificar(str_contains($r['cuerpo'],'1 estudiante(s); 1 fila(s) rechazadas'),'No presenta resumen de importación parcial.');
    verificar((int)consultar($bd,"SELECT COUNT(*) n FROM estudiantes WHERE codigo='HTTP'")['n']===1,'El archivo subido no persistió.');
    $retirado = (int)consultar($bd, "SELECT id_estudiante FROM estudiantes WHERE codigo='CUATRO'")['id_estudiante'];
    $r = http_prueba('estudiantes/editar.php?id=' . $retirado);
    $edicion = formulario($r['cuerpo'], 'formEditarEstudiante');
    verificar($edicion['curso'] === '3' && $edicion['paralelo'] === 'D' && $edicion['estado'] === 'Retirado', 'Editar retirado pierde curso/paralelo.');
    $fotoEstudiantes = static fn() => [
        $bd->query('SELECT * FROM estudiantes ORDER BY id_estudiante')->fetch_all(MYSQLI_ASSOC),
        $bd->query('SELECT * FROM inscripciones ORDER BY id_inscripcion')->fetch_all(MYSQLI_ASSOC)
    ];
    $antesEdicion = $fotoEstudiantes();
    $r = http_prueba('estudiantes/actualizar.php', array_replace($edicion, ['ci' => 'CIDOS', 'nombres' => 'Recuperar <estudiante>', 'curso' => '5', 'paralelo' => 'B']));
    verificar(str_contains($r['cabeceras'], 'editar.php?id=' . $retirado) && $fotoEstudiantes() === $antesEdicion, 'CI duplicado altera estudiante o inscripción.');
    $r = http_prueba('estudiantes/editar.php?id=' . $retirado);
    $recuperada = formulario($r['cuerpo'], 'formEditarEstudiante');
    verificar(str_contains($r['cuerpo'], 'El CI ya está registrado.') && str_contains($r['cuerpo'], 'Recuperar &lt;estudiante&gt;'), 'No muestra el error y el nombre escapado.');
    verificar($recuperada['ci'] === 'CIDOS' && $recuperada['curso'] === '5' && $recuperada['paralelo'] === 'B', 'Error pierde los datos editados.');
    foreach ([['nombres' => ['Inválido']], ['nombres' => ''], ['ci' => str_repeat('a', 21)], ['estado' => 'Baja'], ['curso' => '99']] as $cambio) {
        rechaza(fn() => estudiante_actualizar($bd, $retirado, array_replace($edicion, $cambio)), 'Acepta edición de estudiante inválida.');
        verificar($fotoEstudiantes() === $antesEdicion, 'Validación modifica estudiante o inscripción.');
    }
    $bd->query("CREATE TRIGGER fallo_edicion_estudiante BEFORE UPDATE ON inscripciones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo intencional de edición'");
    try {
        rechaza(fn() => estudiante_actualizar($bd, $retirado, array_replace($edicion, ['nombres' => 'Debe revertirse', 'estado' => 'Activo'])), 'No propaga error al guardar inscripción.');
        verificar($fotoEstudiantes() === $antesEdicion, 'Fallo en inscripción deja el estudiante modificado.');
    } finally { $bd->query('DROP TRIGGER fallo_edicion_estudiante'); }
    $inscripcionAntes = consultar($bd, "SELECT s.* FROM inscripciones i JOIN secciones s ON s.id_seccion=i.id_seccion WHERE i.id_estudiante=$retirado ORDER BY i.id_inscripcion DESC LIMIT 1");
    $r = http_prueba('estudiantes/actualizar.php', array_replace($recuperada, ['ci' => '', 'estado' => 'Activo']), null, 'prueba_psicologa');
    verificar(str_contains($r['cabeceras'], 'listar.php'), 'No permite corregir el error y guardar.');
    $estudianteEditado = consultar($bd, "SELECT * FROM estudiantes WHERE id_estudiante=$retirado");
    $inscripcionEditada = consultar($bd, "SELECT s.*,i.estado AS estado_inscripcion FROM inscripciones i JOIN secciones s ON s.id_seccion=i.id_seccion WHERE i.id_estudiante=$retirado ORDER BY i.id_inscripcion DESC LIMIT 1");
    verificar($estudianteEditado['ci'] === null && $estudianteEditado['nombres'] === 'Recuperar <estudiante>' && $estudianteEditado['estado'] === 'Activo' && $inscripcionEditada['estado_inscripcion'] === 'Activo', 'Edición no guarda ambos estados y CI opcional.');
    foreach (['id_institucion', 'gestion', 'turno'] as $campo) verificar($inscripcionAntes[$campo] === $inscripcionEditada[$campo], 'Edición cambia ' . $campo);
    $r = http_prueba('estudiantes/editar.php?id=' . $retirado);
    $actual = formulario($r['cuerpo'], 'formEditarEstudiante');
    verificar($actual['curso'] === '5' && $actual['paralelo'] === 'B' && $actual['ci'] === '', 'No recupera la edición persistida.');
    $r = http_prueba('estudiantes/actualizar.php', array_replace($actual, ['estado' => 'Baja', 'nombres' => 'No mezclar fichas']));
    $r = http_prueba('estudiantes/editar.php?id=' . $idEstudiante);
    verificar(!str_contains($r['cuerpo'], 'No mezclar fichas'), 'La recuperación contamina otro estudiante.');
    verificar(http_prueba('estudiantes/editar.php?id=999999')['codigo'] === 404, 'Edición inexistente no devuelve 404.');
    verificar(http_prueba('estudiantes/editar.php?id[]=1')['codigo'] === 302, 'No rechaza identificador como arreglo.');
    echo "Receptores HTTP, carga CSV, formularios, edición de estudiantes y recuperación de errores: correctos.\n";

    $bd->query("INSERT INTO personas (id_persona,nombres,apellidos,telefono,correo) VALUES
        (1006,'Ana','Prueba','70000000','ana@example.test'),(1007,'Bruno','Prueba',NULL,NULL),
        (1008,'Inactivo','Prueba',NULL,NULL),(1010,'María','D''Ávila','71111111','maria@example.test'),(1011,'Luis','Prueba',NULL,NULL)");
    $bd->query("INSERT INTO usuarios (id_usuario,id_persona,usuario,password,id_rol,estado) VALUES
        (105,1006,'docente_alta','hash-prueba',3,'Activo'),(106,1007,'docente_otro','hash-prueba',3,'Activo'),
        (107,1008,'docente_inactivo','hash-prueba',3,'Inactivo'),(109,1010,'docente_navegador','hash-prueba',3,'Activo'),
        (110,1011,'docente_sin_contacto','hash-prueba',3,'Activo')");
    $r = http_prueba('docentes/registrar.php');
    verificar($r['codigo'] === 200 && str_contains($r['cuerpo'],'data-telefono="70000000"'), 'No ofrece el contacto de la cuenta.');
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$r['cuerpo']); $xpath = new DOMXPath($dom);
    $opciones = array_map(static fn($n)=>(int)$n->getAttribute('value'), iterator_to_array($xpath->query('//select[@id="id_usuario"]/option[@value!=""]')));
    verificar(in_array(105,$opciones,true) && !array_intersect([101,103,104,107],$opciones), 'Ofrece cuentas asignadas, inactivas o de otro rol.');
    $alta = ['id_usuario'=>105,'nombres'=>'Ana','apellidos'=>'Prueba','telefono'=>'70000000','correo'=>'ana@example.test','materias'=>['Física','Química','Tecnología','Religión']] + $token;
    foreach ([[],['Materia inventada'],[['Física']]] as $invalidas) {
        $r = http_prueba('docentes/guardar.php',array_replace($alta,['materias'=>$invalidas]));
        verificar(str_contains($r['cabeceras'],'registrar.php') && !consultar($bd,'SELECT id_docente FROM docentes WHERE id_persona=1006'), 'Guarda materias inválidas o incompletas.');
    }
    $r = http_prueba('docentes/guardar.php',array_replace($alta,['id_usuario'=>107]));
    verificar(!consultar($bd,'SELECT id_docente FROM docentes WHERE id_persona=1008'), 'Registra una cuenta inactiva.');
    $r = http_prueba('docentes/guardar.php',array_replace($alta,['materias'=>[...$alta['materias'],'Física']]));
    $idDocente = (int)(consultar($bd,'SELECT id_docente FROM docentes WHERE id_persona=1006')['id_docente'] ?? 0);
    verificar($r['codigo'] === 302 && $idDocente > 0, 'No registra el docente con varias materias.');
    $asignadas = static fn() => array_column($bd->query("SELECT m.nombre FROM docente_materias dm JOIN materias m ON m.id_materia=dm.id_materia WHERE dm.id_docente=$idDocente ORDER BY m.nombre")->fetch_all(MYSQLI_ASSOC),'nombre');
    verificar($asignadas() === ['Física','Química','Religión','Tecnología'], 'Pierde o duplica materias al guardar.');
    $r = http_prueba('docentes/editar.php?id='.$idDocente);
    $editarDocente = formulario($r['cuerpo'],'formDocente');
    verificar($editarDocente['materias'] === $asignadas(), 'Editar no recupera todas las casillas seleccionadas.');
    $r = http_prueba('docentes/actualizar.php',array_replace($editarDocente,['materias'=>['Matemática','Religión']]));
    verificar(str_contains($r['cabeceras'],'ver.php') && $asignadas() === ['Matemática','Religión'], 'No permite cambiar varias materias.');
    $r = http_prueba('docentes/listar.php?buscar='.rawurlencode('Matemática'));
    verificar(str_contains($r['cuerpo'],'Matemática, Religión'), 'Buscar por materia oculta las otras asignaciones.');
    $personaAntes = consultar($bd,'SELECT * FROM personas WHERE id_persona=1006');
    $bd->query("CREATE TRIGGER fallo_materias BEFORE INSERT ON docente_materias FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo intencional de materias'");
    try {
        http_prueba('docentes/actualizar.php',array_replace($editarDocente,['nombres'=>'Revertir','materias'=>['Física','Química']]));
        verificar($asignadas() === ['Matemática','Religión'] && $personaAntes === consultar($bd,'SELECT * FROM personas WHERE id_persona=1006'), 'Un fallo en materias deja cambios parciales.');
    } finally { $bd->query('DROP TRIGGER fallo_materias'); }
    echo "Docentes: cuentas disponibles, materias múltiples, edición, búsqueda y rollback correctos.\n";

    if (in_array('--navegador',$argv,true)) {
        $chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome.');
        file_put_contents($temporal.'/fixture.php','<?php return '.var_export(['app'=>$temporal.'/app','sesiones'=>$temporal.'/sesiones','secreto'=>$secreto,'navegador'=>true],true).';');
        file_put_contents($temporal.'/navegador.html', <<<'HTML'
<!doctype html><html lang="es"><meta charset="utf-8"><title>Prueba de docentes</title>
<body><pre id="resultado">PENDIENTE</pre><iframe id="app"></iframe><script>
(async()=>{
    const app=document.getElementById('app'); let total=0;
    const comprobar=(ok,m)=>{if(!ok)throw new Error(m);total++;};
    const abrir=url=>new Promise(resolve=>{app.onload=resolve;app.src=url;});
    const campo=id=>app.contentDocument.getElementById(id);
    const cambiar=valor=>{campo('id_usuario').value=valor;campo('id_usuario').dispatchEvent(new app.contentWindow.Event('change'));};
    const casillas=()=>Array.from(app.contentDocument.querySelectorAll('.materia-check'));
    const elegir=nombres=>casillas().forEach(c=>{c.checked=nombres.includes(c.value);c.dispatchEvent(new app.contentWindow.Event('change'));});
    const enviar=()=>new Promise(resolve=>{app.onload=resolve;campo('formDocente').requestSubmit();});
    try {
        await abrir('/docentes/registrar.php');
        cambiar('109');
        comprobar(campo('nombres').value==='María' && campo('apellidos').value==="D'Ávila",'No completa nombres con acentos y comillas.');
        comprobar(campo('telefono').value==='71111111' && campo('correo').value==='maria@example.test','No completa contactos.');
        campo('nombres').value='Nombre corregido'; cambiar('110');
        comprobar(campo('nombres').value==='Luis' && campo('telefono').value==='' && campo('correo').value==='','Cambiar cuenta arrastra datos de otra persona.');
        cambiar('');
        comprobar(['nombres','apellidos','telefono','correo'].every(id=>campo(id).value===''),'Vaciar la cuenta conserva datos ajenos.');
        cambiar('109');
        const evento=new app.contentWindow.Event('submit',{cancelable:true});
        comprobar(!campo('formDocente').dispatchEvent(evento) && !campo('errorMaterias').classList.contains('d-none'),'No exige al menos una materia.');
        elegir(['Física','Química','Tecnología','Religión']);
        comprobar(casillas().filter(c=>c.checked).length===4,'No permite marcar cuatro materias.');
        campo('nombres').value='María Elena';
        await enviar();
        comprobar(app.contentWindow.location.pathname==='/docentes/listar.php','El formulario no guarda.');
        const fila=Array.from(app.contentDocument.querySelectorAll('tbody tr')).find(f=>f.textContent.includes('docente_navegador'));
        comprobar(fila && ['Física','Química','Tecnología','Religión','María Elena'].every(t=>fila.textContent.includes(t)),'No muestra lo guardado.');
        await abrir(fila.querySelector('a[href^="editar.php"]').href);
        comprobar(campo('nombres').value==='María Elena' && casillas().filter(c=>c.checked).length===4,'Editar sobrescribe los datos o desmarca materias.');
        elegir(['Física','Religión']); await enviar();
        comprobar(app.contentWindow.location.pathname==='/docentes/ver.php' && app.contentDocument.body.textContent.includes('Física, Religión'),'No persiste cambio de materias.');
        document.getElementById('resultado').textContent=JSON.stringify({ok:true,total});
    }catch(e){document.getElementById('resultado').textContent=JSON.stringify({ok:false,total,error:e.message});}
})();
</script></body></html>
HTML);
        $html = ejecutar([$chrome,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking',
            '--user-data-dir='.$temporal.'/chrome','--dump-dom','--virtual-time-budget=20000','http://127.0.0.1:'.$puerto.'/__prueba_navegador?secreto='.$secreto]);
        $dom = new DOMDocument(); @$dom->loadHTML($html);
        $resultado = json_decode($dom->getElementById('resultado')?->textContent ?? '',true);
        verificar(($resultado['ok']??false)===true,'Navegador docentes: '.json_encode($resultado,JSON_UNESCAPED_UNICODE));
        verificar((int)consultar($bd,'SELECT COUNT(*) n FROM docente_materias dm JOIN docentes d ON d.id_docente=dm.id_docente WHERE d.id_persona=1010')['n']===2,'Navegador no persistió ambas materias.');
        echo 'Chrome: '.$resultado['total']." comprobaciones de autocompletado y materias correctas.\n";
    }
    echo "OK: $total comprobaciones. Ninguna escritura de prueba en la base real.\n";
} catch(Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");
    if(is_file($temporal.'/servidor.log')) fwrite(STDERR,file_get_contents($temporal.'/servidor.log'));
    $fallo=true;
} finally {
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    foreach($bases as $base) {
        if(!preg_match('/^psicologia_test_(nueva|actualizar)_[a-f0-9]{12}$/',$base)) throw new RuntimeException('Nombre de limpieza no permitido.');
        $admin->query("DROP DATABASE IF EXISTS `$base`");
    }
    $rutaReal=realpath($temporal);
    $raizTemporal=realpath(sys_get_temp_dir());
    if($rutaReal && str_starts_with($rutaReal,$raizTemporal.DIRECTORY_SEPARATOR) && basename($rutaReal)==='psicologia-integracion-'.$sufijo) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rutaReal,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir()?rmdir($archivo->getPathname()):unlink($archivo->getPathname());
        }
        rmdir($rutaReal);
    }
}
exit(isset($fallo)?1:0);
