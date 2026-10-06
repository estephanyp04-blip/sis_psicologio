<?php
// Se ejecuta únicamente en el servidor efímero creado por seguridad.php.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$fixture = require __DIR__ . '/fixture.php';
if (!hash_equals($fixture['secreto'], $_SERVER['HTTP_X_PRUEBA_SECRETO'] ?? '')) {
    http_response_code(403);
    exit;
}
$caso = json_decode(base64_decode($_SERVER['HTTP_X_PRUEBA_CASO'] ?? ''), true) ?: [];
$ruta = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$archivo = realpath($fixture['app'] . '/' . $ruta);
if (!$archivo || !str_starts_with($archivo, realpath($fixture['app']) . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}
$_SERVER['SCRIPT_FILENAME'] = $archivo;
if (str_starts_with($ruta, 'config/') || str_starts_with($ruta, 'includes/') || $ruta === 'historias_clinicas/formulario.php') {
    require $archivo;
    exit;
}

ini_set('session.save_path', $fixture['sesiones']);
require $fixture['app'] . '/includes/autenticacion.php';
$db = login_bd();
// Estas tablas SOLO existen en esta conexión; ocultan a las permanentes.
// No ejecutar DROP/TRUNCATE ni DDL contra las tablas reales.
$db->query("CREATE TEMPORARY TABLE roles (id_rol INT PRIMARY KEY, nombre VARCHAR(40), estado VARCHAR(20))");
$db->query("INSERT INTO roles VALUES (1,'Administrador','Activo'),(2,'Psicologa','Activo'),(3,'Docente','Activo'),(4,'Director','Activo')");
$db->query("CREATE TEMPORARY TABLE usuarios (id_usuario INT PRIMARY KEY, usuario VARCHAR(40), nombre VARCHAR(60), apellido VARCHAR(60), password VARCHAR(255), estado VARCHAR(20), id_rol INT, correo VARCHAR(120))");
$db->query("INSERT INTO usuarios VALUES (101,'prueba_admin','Admin','Prueba','hash-prueba','Activo',1,''),(102,'prueba_psicologa','Psicologa','Prueba','hash-prueba','Activo',2,''),(103,'prueba_docente','Docente','Prueba','hash-prueba','Activo',3,''),(104,'prueba_director','Director','Prueba','hash-prueba','Activo',4,''),(105,'otro_docente','Otro','Docente','hash-prueba','Activo',3,'')");
$db->query("CREATE TEMPORARY TABLE docentes (id_docente INT PRIMARY KEY, id_usuario INT, nombres VARCHAR(60), apellidos VARCHAR(60))");
$db->query("INSERT INTO docentes VALUES (303,103,'Docente','Prueba'),(305,105,'Otro','Docente')");

login_iniciar_sesion();
// Cada petición construye su propio caso, sin heredar estado de otro ensayo.
$_SESSION = [];
$rol = (int) ($caso['rol'] ?? 0);
if ($rol > 0) {
    $usuarios = [1 => 'prueba_admin', 2 => 'prueba_psicologa', 3 => 'prueba_docente', 4 => 'prueba_director'];
    $usuario = login_buscar_usuario($usuarios[$rol]);
    if ($rol === 3) $usuario['id_docente'] = login_docente(103);
    login_establecer($usuario);
}
$_SESSION['login_csrf'] = str_repeat('c', 64);
switch ($caso['sesion'] ?? '') {
    case 'vencida': $_SESSION['login_actividad'] = time() - 1900; break;
    case 'duracion': $_SESSION['login_inicio'] = time() - 30000; break;
    case 'inactivo': $db->query("UPDATE usuarios SET estado='Inactivo' WHERE id_usuario=" . (100 + $rol)); break;
    case 'rol_inactivo': $db->query('UPDATE roles SET estado=\'Inactivo\' WHERE id_rol=' . $rol); break;
    case 'clave_cambiada': $db->query("UPDATE usuarios SET password='nuevo-hash' WHERE id_usuario=" . (100 + $rol)); break;
    case 'rol_cambiado': $db->query('UPDATE usuarios SET id_rol=4 WHERE id_usuario=' . (100 + $rol)); break;
    case 'sin_docente': $db->query('DELETE FROM docentes WHERE id_usuario=103'); break;
    case 'docente_duplicado': $db->query("INSERT INTO docentes VALUES (306,103,'Duplicado','Prueba')"); break;
}

$GLOBALS['prueba_negocio'] = !empty($caso['negocio']);
if ($GLOBALS['prueba_negocio']) {
    $db->query("CREATE TEMPORARY TABLE estudiantes (id_estudiante INT PRIMARY KEY, nombres VARCHAR(60), apellidos VARCHAR(60), ci VARCHAR(20), curso VARCHAR(10), paralelo VARCHAR(10), estado VARCHAR(20))");
    $db->query("INSERT INTO estudiantes VALUES (1,'Estudiante','Prueba','PRUEBA','1','A','Activo')");
    $db->query("CREATE TEMPORARY TABLE derivaciones (id_derivacion INT PRIMARY KEY, fecha DATE, id_estudiante INT, id_docente INT, materia VARCHAR(150), motivo TEXT, observaciones TEXT, prioridad VARCHAR(20), estado VARCHAR(30), fecha_registro DATETIME)");
    $db->query("INSERT INTO derivaciones VALUES (1,CURDATE(),1,303,'Lenguaje y Comunicación','PROPIA_PRIVADA','Observacion propia','Media','Pendiente',NOW()),(2,CURDATE(),1,305,'Lenguaje y Comunicación','AJENA_PRIVADA','Observacion ajena','Media','Pendiente',NOW())");
    $db->query("CREATE TEMPORARY TABLE citas (id_cita INT PRIMARY KEY, id_estudiante INT, fecha DATE, hora TIME, estado VARCHAR(20), observaciones TEXT, id_derivacion INT NULL)");
    $db->query("INSERT INTO citas VALUES (1,1,CURDATE(),'09:00','Pendiente','Cita de prueba',NULL)");
    $db->query("CREATE TEMPORARY TABLE historias_clinicas (id_historia INT PRIMARY KEY,id_estudiante INT,id_derivacion INT,id_cita INT)");
    $db->query("CREATE TEMPORARY TABLE seguimientos (id_seguimiento INT PRIMARY KEY,id_cita INT)");
    $db->query("CREATE TEMPORARY TABLE informes (id_informe INT PRIMARY KEY,id_derivacion INT)");
    $db->query("CREATE TEMPORARY TABLE auditoria (id_usuario INT,modulo VARCHAR(50),accion VARCHAR(50),registro_id INT,detalle TEXT)");
    register_shutdown_function(static function () use ($db): void {
        $estado = [
            'derivaciones' => $db->query('SELECT id_derivacion,motivo,observaciones FROM derivaciones ORDER BY id_derivacion')->fetch_all(MYSQLI_ASSOC),
            'cita' => $db->query('SELECT estado FROM citas WHERE id_cita=1')->fetch_assoc()['estado'],
            'autenticado' => $_SESSION['autenticado'] ?? false,
        ];
        echo "\n__ESTADO_PRUEBA__", json_encode($estado);
    });
}
chdir(dirname($archivo));
require $archivo;
