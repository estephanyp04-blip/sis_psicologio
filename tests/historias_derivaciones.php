<?php
/** Punto 3: persistencia clínica y derivaciones, exclusivamente con datos sintéticos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/La_Paz');
require_once dirname(__DIR__) . '/historias_clinicas/datos.php';
require_once dirname(__DIR__) . '/derivaciones/datos.php';
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

function prueba_historias_verificar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}
function prueba_historias_rechaza(callable $accion, string $mensaje): void
{
    try { $accion(); } catch (InvalidArgumentException | mysqli_sql_exception $error) { prueba_historias_verificar(true, $mensaje); return; }
    prueba_historias_verificar(false, $mensaje);
}
function prueba_historias_ejecutar(array $argumentos): string
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
function prueba_historias_fila(mysqli $bd, string $sql): array { return $bd->query($sql)->fetch_assoc() ?? []; }
function prueba_historias_foto(mysqli $bd): array
{
    $datos = [];
    foreach (['historias_clinicas', 'historia_opciones', 'historia_familiares', 'auditoria', 'derivaciones', 'derivacion_categorias'] as $tabla) {
        $datos[$tabla] = $bd->query("SELECT * FROM $tabla ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    }
    return $datos;
}
function prueba_historias_http(string $ruta, ?array $datos = null, string $usuario = 'prueba_psicologa'): array
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
    prueba_historias_verificar(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', (string)$cuerpo), "Error PHP en $ruta");
    return ['codigo' => (int)$m[1], 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $http_response_header)];
}
function prueba_historias_formulario(string $html, string $id): array
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
    prueba_historias_verificar($datos !== [], "No se encontró el formulario $id");
    return $datos;
}

try {
    $admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
    prueba_historias_ejecutar([PHP_BINARY, $raiz . '/database/migrar.php', '--base=' . $base, '--aplicar', '--instalar']);
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], $base, $config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    prueba_autores($bd);
    prueba_estudiantes($bd, 8);
    $bd->query("INSERT INTO derivaciones (cod_derivacion,fecha,cod_estudiante,cod_docente,cod_materia,motivo,prioridad,observaciones) VALUES
        (1,'2026-01-01',1,201,5,'Motivo inicial','Alta','Referencia visible'),
        (2,'2026-01-02',1,201,2,'Motivo reciente','Media','Otra referencia'),
        (3,'2026-01-03',2,202,2,'Motivo ajeno','Media','Privado de otro estudiante'),
        (4,'2026-01-04',3,201,2,'Motivo navegador','Alta','Referencia navegador')");
    $entrada = ['cod_estudiante' => 1, 'cod_derivacion' => 1, 'fecha_apertura' => '2026-01-05',
        'fecha_derivacion' => '2026-01-01', 'motivo_consulta' => 'Motivo clínico <conservar>', 'estado' => 'Activa',
        'lugar_nacimiento' => 'Lugar clínico', 'celular_estudiante' => '222', 'padre_madre' => 'Tutor clínico',
        'tutor_curso' => 'Tutor <curso>', 'talla_cm' => '155.50', 'peso_kg' => '48.25', 'situacion_escolar' => 'Buena', 'valoracion_familiar' => 'Regular',
        'formulario_completo' => '1', 'familiares_presentes' => '1',
        'familiares' => [['nombre' => 'Familiar <uno>', 'edad' => '0', 'relacion' => 'Hermano', 'profesion' => 'Estudio', 'ocupacion' => 'Ocupación', 'observaciones' => 'Detalle familiar']]];
    foreach (HISTORIA_TEXTOS as $campo => $_) if (!isset($entrada[$campo])) $entrada[$campo] = 'Dato clínico ' . $campo;
    foreach (HISTORIA_OPCIONES as $grupo => $opciones) {
        $entrada[$grupo] = [$opciones[0]];
        $entrada['opciones_presentes'][$grupo] = '1';
    }
    $id = historia_guardar($bd, $entrada,112);
    $guardada = prueba_historias_fila($bd, "SELECT * FROM historias_clinicas WHERE cod_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) prueba_historias_verificar($guardada[$campo] === $entrada[$campo], "El alta pierde $campo");
    prueba_historias_verificar((int)$guardada['cod_psicologa'] === 112 && (int)$guardada['cod_derivacion_origen'] === 1, 'Alta sin autor o vínculo.');
    $hijos = historia_cargar_hijos($bd, $id);
    foreach (HISTORIA_OPCIONES as $grupo => $_) prueba_historias_verificar($hijos[$grupo] === $entrada[$grupo], "Alta pierde $grupo");
    prueba_historias_verificar($hijos['familiares'][0]['edad'] === 0 && $hijos['familiares'][0]['nombre'] === 'Familiar <uno>', 'Alta pierde familiar o edad cero.');
    prueba_historias_verificar(prueba_historias_fila($bd, 'SELECT accion FROM auditoria ORDER BY cod_auditoria DESC LIMIT 1')['accion'] === 'Crear', 'No audita el alta.');

    historia_guardar($bd, ['cod_estudiante' => 1, 'formulario_completo' => '1', 'cod_usuario' => 999], 102, $id);
    $editada = prueba_historias_fila($bd, "SELECT * FROM historias_clinicas WHERE cod_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) prueba_historias_verificar($editada[$campo] === $guardada[$campo], "Edición parcial pierde $campo");
    prueba_historias_verificar((int)$editada['cod_psicologa'] === 112, 'Edición cambia autor.');
    prueba_historias_verificar(historia_cargar_hijos($bd, $id) === $hijos, 'Edición parcial borra hijos omitidos.');
    $auditoria = prueba_historias_fila($bd, 'SELECT * FROM auditoria ORDER BY cod_auditoria DESC LIMIT 1');
    prueba_historias_verificar((int)$auditoria['cod_usuario'] === 102 && $auditoria['accion'] === 'Actualizar' && $auditoria['fecha'] !== null, 'No registra editora y fecha.');
    foreach (['1000', '-1', '1.555', '48 kg', ['4']] as $valor) prueba_historias_rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['peso' => $valor]), 102, $id), 'Acepta peso inválido.');
    foreach ([['cod_estudiante' => 2], ['cod_derivacion' => 3], ['formulario_completo' => ''], ['fecha_apertura' => '2026-02-30'],
        ['motivo_consulta' => ''], ['estado' => 'Desconocido'], ['talla' => str_repeat('x', 31)], ['atencion_distraccion' => ['Opción inventada']],
        ['familiares' => [['nombre' => 'Familiar', 'edad' => 121]]]] as $cambio) {
        $antes = prueba_historias_foto($bd);
        prueba_historias_rechaza(fn() => historia_guardar($bd, array_replace($entrada, $cambio), 102, $id), 'Acepta historia inválida.');
        prueba_historias_verificar(prueba_historias_foto($bd) === $antes, 'El rechazo modifica datos.');
    }
    $antes = prueba_historias_foto($bd);
    prueba_historias_rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['observaciones' => 'Cambio que debe revertirse']), 9999, $id), 'Acepta auditor inexistente.');
    prueba_historias_verificar(prueba_historias_foto($bd) === $antes, 'Fallo de auditoría no revierte historia e hijos.');
    $segundaHistoria = historia_guardar($bd, array_replace($entrada, ['cod_derivacion' => 2, 'motivo_consulta' => 'Historia de la segunda sesión', 'situacion_escolar' => 'Muy buena']), 102);
    prueba_historias_verificar($segundaHistoria !== $id, 'No conserva una historia propia para la nueva sesión.');
    prueba_historias_verificar((int)prueba_historias_fila($bd, 'SELECT COUNT(*) n FROM historias_clinicas WHERE cod_estudiante=1')['n'] === 2, 'No agrega la sesión al expediente.');
    prueba_historias_verificar(prueba_historias_fila($bd, "SELECT motivo_consulta FROM historias_clinicas WHERE cod_historia=$id")['motivo_consulta'] === $guardada['motivo_consulta'], 'Crear otra historia modifica la anterior.');
    prueba_historias_verificar((int)prueba_historias_fila($bd, "SELECT cod_derivacion_origen FROM historias_clinicas WHERE cod_historia=$segundaHistoria")['cod_derivacion_origen'] === 2, 'La segunda historia pierde su derivación.');
    prueba_historias_rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['cod_estudiante' => 2, 'cod_derivacion' => 1]),112), 'Alta vincula derivación ajena.');
    $sinDerivacion = historia_guardar($bd, array_replace($entrada, ['cod_estudiante' => 2, 'cod_derivacion' => '']),112);
    $sin = prueba_historias_fila($bd, "SELECT * FROM historias_clinicas WHERE cod_historia=$sinDerivacion");
    prueba_historias_verificar($sin['cod_derivacion_origen'] === null, 'No admite historia sin derivación.');
    $bd->query("INSERT INTO grupos_opciones_historia (cod_grupo,codigo,nombre) VALUES (99,'grupo_legado','Grupo previo')");
    $bd->query("INSERT INTO opciones_historia (cod_opcion,cod_grupo,descripcion,estado) VALUES
        (98,1,'Opción histórica','Inactivo'),(99,99,'Conservar legado','Inactivo')");
    $bd->query("INSERT INTO historia_opciones (cod_historia,cod_opcion) VALUES ($id,98),($id,99)");
    $bd->query("UPDATE estudiantes SET estado='Retirado' WHERE cod_estudiante=8");
    prueba_historias_rechaza(fn() => historia_guardar($bd, array_replace($entrada, ['cod_estudiante' => 8, 'cod_derivacion' => 0]),112), 'Alta acepta estudiante retirado.');
    echo "Persistencia de campos, autoría, auditoría, validaciones y rollback: correctos.\n";

    $observaciones = "Categorías observadas: Conducta en Aula, Categoría histórica\n\nObservaciones adicionales: Texto previo <seguro>\n\nSolicitud de cita psicológica: Sí\n\nProfesional solicitado: Psicóloga de prueba";
    $stmt = $bd->prepare('UPDATE derivaciones SET observaciones=? WHERE cod_derivacion=1');
    $stmt->bind_param('s', $observaciones); $stmt->execute(); $stmt->close();
    $derivacion = ['fecha' => '2026-01-01', 'materia' => 'Lenguaje', 'motivo' => 'Motivo editado', 'prioridad' => 'Alta'];
    derivacion_actualizar($bd, 1, $derivacion + ['observaciones' => ''], 3, 201);
    prueba_historias_verificar(prueba_historias_fila($bd, 'SELECT observaciones FROM derivaciones WHERE cod_derivacion=1')['observaciones'] === $observaciones, 'Un POST incompleto borra observaciones.');
    $antes = prueba_historias_foto($bd);
    prueba_historias_rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion, 3, 202), 'Docente modifica derivación ajena.');
    prueba_historias_rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion, 2, 0), 'Psicóloga edita derivación sin permiso.');
    prueba_historias_rechaza(fn() => derivacion_actualizar($bd, 1, $derivacion + ['observaciones_presentes' => '1'], 1, 0), 'Acepta observaciones truncadas.');
    prueba_historias_verificar(prueba_historias_foto($bd) === $antes, 'Rechazo de derivación altera datos.');
    $bd->query("UPDATE derivaciones SET estado='Atendido' WHERE cod_derivacion=3");
    prueba_historias_rechaza(fn() => derivacion_actualizar($bd, 3, $derivacion, 1, 0), 'Edita derivación ya atendida.');

    mkdir($temporal . '/app', 0700); mkdir($temporal . '/sesiones', 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (!in_array($archivo->getExtension(), ['php','css','js','woff','woff2','svg','png'], true)) continue;
        $relativa = substr($archivo->getPathname(), strlen($raiz) + 1);
        if (str_starts_with($relativa, 'tests' . DIRECTORY_SEPARATOR)) continue;
        $destino = $temporal . '/app/' . $relativa;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0700, true);
        copy($archivo->getPathname(), $destino);
    }
    $configPrueba = $config; $configPrueba['base_datos'] = $base; $configPrueba['base_url'] = '';
    file_put_contents($temporal . '/app/config/login.php', '<?php return ' . var_export($configPrueba, true) . ';');
    $secreto = bin2hex(random_bytes(32));
    file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'secreto' => $secreto, 'cuatro_roles' => true], true) . ';');
    copy(__DIR__ . '/integracion_router.php', $temporal . '/router.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $puerto = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1); fclose($socket);
    $servidor = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-S', '127.0.0.1:' . $puerto, '-t', $temporal . '/app', $temporal . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporal . '/servidor.log', 'a'], 2 => ['file', $temporal . '/servidor.log', 'a']], $pipes, $temporal);
    fclose($pipes[0]);
    for ($i = 0; $i < 40; ++$i) { $s = @fsockopen('127.0.0.1', $puerto, $errno, $error, 0.1); if ($s) { fclose($s); break; } usleep(100000); }
    foreach (['historias_clinicas/registrar.php?cod_estudiante=1', 'historias_clinicas/registrar.php?cod_derivacion=2'] as $rutaAlta) {
        $r = prueba_historias_http($rutaAlta, null, 'prueba_psicologa');
        prueba_historias_verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'formHistoriaClinica'), 'Nueva sesión no abre una nueva historia clínica.');
    }
    $bd->query("INSERT INTO historia_anexos (cod_historia,cod_psicologa,fecha,descripcion) VALUES
        ($id,101,'2026-01-06','Sesion original del expediente'),
        ($segundaHistoria,102,'2026-01-07','Sesion de historia legada'),
        ($sinDerivacion,101,'2026-01-08','Sesion privada de otro estudiante')");
    $r = prueba_historias_http('historias_clinicas/listar.php');
    prueba_historias_verificar($r['codigo'] === 200 && substr_count($r['cuerpo'], 'data-estudiante="1"') === 1, 'El listado duplica el expediente del estudiante.');
    prueba_historias_verificar(str_contains($r['cuerpo'], '2 sesiones'), 'No suma las sesiones de las historias anteriores.');
    $r = prueba_historias_http('historias_clinicas/ver.php?id=' . $id);
    prueba_historias_verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], '2 sesiones registradas'), 'El expediente no muestra el total de sesiones.');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Sesion original del expediente') && !str_contains($r['cuerpo'], 'Sesion de historia legada'), 'Mezcla anexos pertenecientes a distintas historias.');
    $anterior = prueba_historias_http('historias_clinicas/ver.php?id=' . $segundaHistoria);
    prueba_historias_verificar(str_contains($anterior['cuerpo'], 'Sesion de historia legada'), 'Oculta el anexo de su historia original.');
    prueba_historias_verificar(!str_contains($r['cuerpo'], 'Sesion privada de otro estudiante'), 'Mezcla sesiones de otros estudiantes.');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'ver.php?id=' . $segundaHistoria), 'Oculta la historia clínica anterior.');
    $r = prueba_historias_http('estadisticas/individual.php?estudiante=1');
    prueba_historias_verificar($r['codigo'] === 200, 'No consulta el historial desde estadísticas.');
    $r = prueba_historias_http('estadisticas/individual.php?estudiante=1');
    prueba_historias_verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'Seguimiento psicológico individual'), 'No abre la comparación de historias.');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'value="h'.$id.'actual"') && str_contains($r['cuerpo'], 'value="h'.$segundaHistoria.'actual"'), 'Oculta alguna historia del estudiante.');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Muy buena') && str_contains($r['cuerpo'], 'Buena'), 'No muestra las valoraciones de cada sesión.');
    prueba_historias_verificar(!str_contains($r['cuerpo'], 'value="h'.$sinDerivacion.'actual"'), 'Incluye historias de otro estudiante en el avance.');
    $r = prueba_historias_http('informes/registrar.php?cod_estudiante=1');
    $docHistorias = new DOMDocument();
    @$docHistorias->loadHTML('<?xml encoding="UTF-8">' . $r['cuerpo']);
    $xpathHistorias = new DOMXPath($docHistorias);
    prueba_historias_verificar($xpathHistorias->query('//select[@id="cod_estudiante"]/option[@value="1"]')->length === 1, 'Duplica al estudiante en el selector de informes.');
    prueba_historias_verificar($xpathHistorias->query('//select[@id="cod_estudiante"]/option[@value="2"]')->length === 1, 'Oculta un estudiante con historia clínica.');
    prueba_historias_verificar($xpathHistorias->query('//select[@id="cod_estudiante"]/option[@value="4"]')->length === 0, 'Muestra un estudiante sin historia clínica en informes.');
    prueba_historias_verificar($xpathHistorias->query('//select[@id="cod_historia"]/option[@value!=""]')->length === 2, 'El informe no permite elegir las dos historias.');
    $r = prueba_historias_http('informes/antecedentes.php?cod_estudiante=1&fecha=' . date('Y-m-d'));
    $antecedentes = json_decode($r['cuerpo'], true, 512, JSON_THROW_ON_ERROR);
    prueba_historias_verificar($r['codigo'] === 200 && $antecedentes['requiere_historia'] && $antecedentes['cod_historia'] === 0, 'Elige una historia arbitraria para el informe.');
    foreach (['' => 400, '?cod_estudiante=0' => 400, '?cod_estudiante[]=1' => 400, '?cod_estudiante=9999' => 404, '?cod_estudiante=1' => 200, '?cod_estudiante=4' => 200] as $consulta => $codigo) {
        $r = prueba_historias_http('historias_clinicas/ajax_derivaciones.php' . $consulta);
        prueba_historias_verificar($r['codigo'] === $codigo && str_contains($r['cabeceras'], 'application/json'), 'Contrato JSON incorrecto.');
        $json = json_decode($r['cuerpo'], true, 512, JSON_THROW_ON_ERROR);
        if ($consulta === '?cod_estudiante=1') prueba_historias_verificar(array_column($json['derivaciones'], 'id') === [2, 1], 'JSON incluye derivaciones ajenas o desordenadas.');
        if ($consulta === '?cod_estudiante=4') prueba_historias_verificar($json['derivaciones'] === [], 'Sin derivaciones no devuelve lista vacía.');
    }
    $r = prueba_historias_http('historias_clinicas/ajax_derivaciones.php?cod_estudiante=1', []);
    prueba_historias_verificar($r['codigo'] === 405 && isset(json_decode($r['cuerpo'], true)['error']), 'El endpoint acepta POST/no responde JSON.');
    $r = prueba_historias_http('historias_clinicas/editar.php?id=' . $id);
    prueba_historias_verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'Texto previo &lt;seguro&gt;'), 'Edición pierde referencia de derivación.');
    $post = prueba_historias_formulario($r['cuerpo'], 'formHistoriaClinica');
    prueba_historias_verificar($post['lugar_nacimiento'] === 'Lugar de ficha' && $post['celular_estudiante'] === '111' && $post['padre_madre'] === 'Tutor de ficha', 'No muestra los datos vigentes de la ficha.');
    prueba_historias_verificar(in_array('Opción histórica', $post['atencion_distraccion'], true), 'No muestra opción histórica para conservarla.');
    prueba_historias_verificar($post['derivado_por'] === 'Docente Uno' && $post['fecha_derivacion'] === '2026-01-01', 'Edición pierde docente y fecha de origen.');
    prueba_historias_verificar($post['tutor_curso'] === 'Tutor <curso>' && $post['talla'] === '155.50' && $post['peso'] === '48.25', 'Formulario pierde tutor o medidas.');
    $r = prueba_historias_http('historias_clinicas/actualizar.php', $post, 'prueba_psicologa');
    prueba_historias_verificar(str_contains($r['cabeceras'], 'ver.php?id=' . $id), 'No guarda formulario renderizado.');
    $editada = prueba_historias_fila($bd, "SELECT * FROM historias_clinicas WHERE cod_historia=$id");
    foreach (HISTORIA_TEXTOS as $campo => $_) prueba_historias_verificar($editada[$campo] === $guardada[$campo], "Ida y vuelta del formulario pierde $campo");
    prueba_historias_verificar((int)$editada['cod_psicologa'] === 112, 'Receptor HTTP cambia autor.');
    prueba_historias_verificar((int)prueba_historias_fila($bd, "SELECT COUNT(*) n FROM historia_opciones WHERE cod_opcion=99")['n'] === 1, 'Borra grupos históricos no representados.');
    $r = prueba_historias_http('historias_clinicas/ver.php?id=' . $id);
    prueba_historias_verificar($r['codigo'] === 200 && str_contains($r['cuerpo'], 'Tutor &lt;curso&gt;') && str_contains($r['cuerpo'], 'Docente Uno') && str_contains($r['cuerpo'], 'Dato clínico impresion_diagnostica') && str_contains($r['cuerpo'], 'Familiar &lt;uno&gt;'), 'Detalle no recupera campos clínicos escapados.');
    $antes = prueba_historias_foto($bd);
    $invalido = array_replace($post, ['motivo_consulta' => 'Recuperar <edición>', 'talla' => '1000', 'peso' => '51.75', 'valoracion' => 'Recuperar valoración', 'tutor_curso' => 'Recuperar tutor', 'atencion_distraccion' => [], 'familiares' => []]);
    $r = prueba_historias_http('historias_clinicas/actualizar.php', $invalido);
    prueba_historias_verificar(str_contains($r['cabeceras'], 'editar.php?id=' . $id) && prueba_historias_foto($bd) === $antes, 'Edición inválida no revierte/redirige.');
    $r = prueba_historias_http('historias_clinicas/editar.php?id=' . $id);
    $recuperado = prueba_historias_formulario($r['cuerpo'], 'formHistoriaClinica');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Recuperar &lt;edición&gt;') && !isset($recuperado['atencion_distraccion']), 'No recupera texto o casillas desmarcadas.');
    prueba_historias_verificar($recuperado['familiares'][0]['nombre'] === '', 'Error restaura familiares que se habían quitado.');
    prueba_historias_verificar($recuperado['talla'] === '1000' && $recuperado['peso'] === '51.75' && $recuperado['valoracion'] === 'Recuperar valoración' && $recuperado['tutor_curso'] === 'Recuperar tutor', 'Error pierde talla, peso, valoración o tutor.');
    $recuperado['talla'] = '160.25';
    $r = prueba_historias_http('historias_clinicas/actualizar.php', $recuperado);
    prueba_historias_verificar(str_contains($r['cabeceras'], 'ver.php'), 'No permite corregir el error.');
    prueba_historias_verificar(historia_cargar_hijos($bd, $id)['familiares'] === [] && historia_cargar_hijos($bd, $id)['atencion_distraccion'] === [], 'No guarda borrado explícito de hijos.');
    $r = prueba_historias_http('historias_clinicas/registrar.php?cod_estudiante=4');
    $alta = prueba_historias_formulario($r['cuerpo'], 'formHistoriaClinica');
    $alta['cod_derivacion'] = ''; $alta['motivo_consulta'] = 'Alta <recuperable>'; $alta['peso'] = '1000';
    $r = prueba_historias_http('historias_clinicas/guardar.php', $alta);
    prueba_historias_verificar(str_contains($r['cabeceras'], 'registrar.php'), 'Alta inválida no vuelve al formulario.');
    $r = prueba_historias_http('historias_clinicas/registrar.php');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Alta &lt;recuperable&gt;'), 'Alta pierde datos ante error.');
    $alta = prueba_historias_formulario($r['cuerpo'], 'formHistoriaClinica'); $alta['peso'] = '45.50'; $alta['cod_usuario'] = 999;
    $r = prueba_historias_http('historias_clinicas/guardar.php', $alta);
    prueba_historias_verificar(str_contains($r['cabeceras'], 'ver.php'), 'No guarda alta HTTP sin derivación.');
    prueba_historias_verificar((int)prueba_historias_fila($bd, 'SELECT cod_psicologa FROM historias_clinicas WHERE cod_estudiante=4')['cod_psicologa'] === 102, 'Alta acepta autor de POST.');
    $bd->query("INSERT INTO categorias_derivacion (nombre,estado) VALUES ('Categoría nueva de catálogo','Activo'),('Categoría inactiva','Inactivo')");
    $r = prueba_historias_http('derivaciones/editar.php?id=1', null, 'prueba_docente');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Categoría nueva de catálogo')
        && !str_contains($r['cuerpo'], 'Categoría inactiva')
        && str_contains($r['cuerpo'], 'Social / Emocional'), 'Edición no refleja el estado de las categorías del catálogo.');
    $postDerivacion = prueba_historias_formulario($r['cuerpo'], 'formEditarDerivacion');
    $r = prueba_historias_http('derivaciones/actualizar.php', $postDerivacion, 'prueba_docente');
    prueba_historias_verificar(str_contains($r['cabeceras'], 'listar.php') && prueba_historias_fila($bd, 'SELECT observaciones FROM derivaciones WHERE cod_derivacion=1')['observaciones'] === $observaciones, 'Guardar derivación sin JS altera observaciones.');
    $postDerivacion['observaciones_adicionales'] = 'Texto cambiado <nuevo>';
    $postDerivacion['categorias'] = ['Categoría nueva de catálogo'];
    $r = prueba_historias_http('derivaciones/actualizar.php', $postDerivacion, 'prueba_docente');
    $nuevas = prueba_historias_fila($bd, 'SELECT observaciones FROM derivaciones WHERE cod_derivacion=1')['observaciones'];
    prueba_historias_verificar(str_contains($nuevas, 'Texto cambiado <nuevo>') && str_contains($nuevas, 'Categoría nueva de catálogo') && str_contains($nuevas, 'Profesional solicitado: Psicóloga de prueba') && str_contains($nuevas, 'Solicitud de cita psicológica: Sí'), 'Edición no conserva solicitud/profesional.');
    prueba_historias_verificar(prueba_historias_fila($bd, 'SELECT c.nombre FROM derivacion_categorias dc JOIN categorias_derivacion c ON c.cod_categoria=dc.cod_categoria WHERE dc.cod_derivacion=1')['nombre'] === 'Categoría nueva de catálogo', 'Texto y categoría relacional no coinciden.');
    $bd->query("UPDATE categorias_derivacion SET estado='Inactivo' WHERE nombre='Categoría nueva de catálogo'");
    $bd->query("UPDATE materias SET estado='Inactivo' WHERE cod_materia=5");
    $r = prueba_historias_http('derivaciones/editar.php?id=1', null, 'prueba_docente');
    $conservada = prueba_historias_formulario($r['cuerpo'], 'formEditarDerivacion');
    prueba_historias_verificar(in_array('Categoría nueva de catálogo', $conservada['categorias'], true) && $conservada['materia'] === 'Lenguaje', 'No permite conservar el catálogo histórico.');
    $r = prueba_historias_http('derivaciones/actualizar.php', $conservada, 'prueba_docente');
    prueba_historias_verificar(str_contains($r['cabeceras'], 'listar.php'), 'No guarda categorías y materia históricas conservadas.');
    $antes = prueba_historias_foto($bd);
    foreach (['Categoría inactiva', 'Inventada'] as $categoria) {
        prueba_historias_rechaza(fn() => derivacion_actualizar($bd, 1, array_replace($postDerivacion, ['categorias' => [$categoria]]), 1, 0), 'Acepta categoría ausente/inactiva no vinculada.');
        prueba_historias_verificar(prueba_historias_foto($bd) === $antes, 'Categoría rechazada modifica la derivación.');
    }
    $postDerivacion['prioridad'] = 'Invalida'; $postDerivacion['observaciones_adicionales'] = 'Recuperar <derivación>';
    $r = prueba_historias_http('derivaciones/actualizar.php', $postDerivacion, 'prueba_docente');
    prueba_historias_verificar(str_contains($r['cabeceras'], 'editar.php'), 'Derivación inválida no vuelve a edición.');
    $r = prueba_historias_http('derivaciones/editar.php?id=1', null, 'prueba_docente');
    prueba_historias_verificar(str_contains($r['cuerpo'], 'Recuperar &lt;derivación&gt;'), 'No recupera/escapa derivación rechazada.');
    echo "JSON, formularios, receptores HTTP, conservación y recuperación de errores: correctos.\n";
    if (in_array('--navegador', $argv, true)) {
        $chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) throw new RuntimeException('No se encontró Chrome para --navegador.');
        file_put_contents($temporal . '/fixture.php', '<?php return ' . var_export(['app' => $temporal . '/app', 'sesiones' => $temporal . '/sesiones', 'secreto' => $secreto, 'navegador' => true], true) . ';');
        file_put_contents($temporal . '/navegador.html', prueba_historias_ejecutar([PHP_BINARY, __DIR__ . '/historias_navegador.php']));
        $html = prueba_historias_ejecutar([$chrome, '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--user-data-dir=' . $temporal . '/chrome', '--dump-dom', '--virtual-time-budget=15000',
            'http://127.0.0.1:' . $puerto . '/__prueba_navegador?secreto=' . $secreto]);
        $doc = new DOMDocument(); @$doc->loadHTML($html);
        $resultado = json_decode($doc->getElementById('resultado')?->textContent ?? '', true);
        prueba_historias_verificar(($resultado['ok'] ?? false) === true, 'Prueba navegador: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE));
        echo 'Navegador Chrome: ' . $resultado['total'] . " comprobaciones correctas.\n";
        prueba_historias_verificar(prueba_historias_fila($bd, 'SELECT motivo_consulta FROM historias_clinicas WHERE cod_estudiante=5')['motivo_consulta'] === 'Alta sin derivación desde navegador', 'El alta de navegador no persistió.');
        prueba_historias_verificar((int)prueba_historias_fila($bd, 'SELECT cod_historia FROM informe_individual ORDER BY cod_informe DESC LIMIT 1')['cod_historia'] === $id, 'El informe del navegador guarda otra historia.');
        prueba_historias_verificar((int)prueba_historias_fila($bd, 'SELECT cod_psicologa FROM historias_clinicas WHERE cod_estudiante=1')['cod_psicologa'] === 112, 'La edición de navegador cambia autor.');
    }
    echo "OK: $total comprobaciones. Ninguna escritura en la base real.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    if (is_file($temporal . '/servidor.log')) fwrite(STDERR, implode('', array_slice(file($temporal . '/servidor.log'), -20)));
    $fallo = true;
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    if (!preg_match('/^psicologia_test_clinica_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Base de limpieza no permitida.');
    if ($admin instanceof mysqli) $admin->query("DROP DATABASE IF EXISTS `$base`");
    $ruta = realpath($temporal);
    if ($ruta && dirname($ruta) === realpath(sys_get_temp_dir()) && basename($ruta) === 'psicologia-clinica-' . $sufijo) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $archivo) {
            $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
        }
        rmdir($ruta);
    }
}
exit(isset($fallo) ? 1 : 0);
