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
if (str_starts_with($ruta, 'config/') || str_starts_with($ruta, 'includes/') || in_array($ruta, $fixture['internos_modulo'], true)) {
    require $archivo;
    exit;
}

ini_set('session.save_path', $fixture['sesiones']);
require $fixture['app'] . '/includes/autenticacion.php';
$db = login_bd();
// Estas tablas SOLO existen en esta conexión; ocultan a las permanentes.
// No ejecutar DROP/TRUNCATE ni DDL contra las tablas reales.
$db->query("CREATE TEMPORARY TABLE roles (cod_rol INT PRIMARY KEY, nombre VARCHAR(40), estado VARCHAR(20))");
$db->query("INSERT INTO roles VALUES (1,'Administrador','Activo'),(2,'Psicologa','Activo'),(3,'Docente','Activo'),(4,'Director','Activo')");
$db->query("CREATE TEMPORARY TABLE personas (cod_persona INT PRIMARY KEY, nombres VARCHAR(60), apellidos VARCHAR(60), correo VARCHAR(120))");
$db->query("INSERT INTO personas VALUES (201,'Admin','Prueba',''),(202,'Psicologa','Prueba',''),(203,'Docente','Prueba',''),(204,'Director','Prueba',''),(205,'Otro','Docente','')");
$db->query("CREATE TEMPORARY TABLE usuarios (cod_usuario INT PRIMARY KEY, cod_persona INT, usuario VARCHAR(40), password VARCHAR(255), estado VARCHAR(20), cod_rol INT)");
$db->query("INSERT INTO usuarios VALUES (101,201,'prueba_admin','hash-prueba','Activo',1),(102,202,'prueba_psicologa','hash-prueba','Activo',2),(103,203,'prueba_docente','hash-prueba','Activo',3),(104,204,'prueba_director','hash-prueba','Activo',4),(105,205,'otro_docente','hash-prueba','Activo',3)");
$db->query("CREATE TEMPORARY TABLE docentes (cod_docente INT PRIMARY KEY, cod_persona INT)");
$db->query("INSERT INTO docentes VALUES (303,203),(305,205)");

login_iniciar_sesion();
// Cada petición construye su propio caso, sin heredar estado de otro ensayo.
$_SESSION = [];
$rol = (int) ($caso['rol'] ?? 0);
if ($rol > 0) {
    $usuarios = [1 => 'prueba_admin', 2 => 'prueba_psicologa', 3 => 'prueba_docente', 4 => 'prueba_director'];
    $usuario = login_buscar_usuario($usuarios[$rol]);
    if ($rol === 3) $usuario['cod_docente'] = login_docente(103);
    login_establecer($usuario);
}
$_SESSION['login_csrf'] = str_repeat('c', 64);
switch ($caso['sesion'] ?? '') {
    case 'vencida': $_SESSION['login_actividad'] = time() - 1900; break;
    case 'duracion': $_SESSION['login_inicio'] = time() - 30000; break;
    case 'inactivo': $db->query("UPDATE usuarios SET estado='Inactivo' WHERE cod_usuario=" . (100 + $rol)); break;
    case 'rol_inactivo': $db->query('UPDATE roles SET estado=\'Inactivo\' WHERE cod_rol=' . $rol); break;
    case 'clave_cambiada': $db->query("UPDATE usuarios SET password='nuevo-hash' WHERE cod_usuario=" . (100 + $rol)); break;
    case 'rol_cambiado': $db->query('UPDATE usuarios SET cod_rol=4 WHERE cod_usuario=' . (100 + $rol)); break;
    case 'sin_docente': $db->query('DELETE FROM docentes WHERE cod_persona=203'); break;
    case 'docente_duplicado': $db->query('INSERT INTO docentes VALUES (306,203)'); break;
}

$GLOBALS['prueba_negocio'] = !empty($caso['negocio']);
if ($GLOBALS['prueba_negocio']) {
    $db->query("CREATE TEMPORARY TABLE estudiantes (cod_estudiante INT PRIMARY KEY, codigo VARCHAR(20), nombres VARCHAR(60), apellidos VARCHAR(60), ci VARCHAR(20), estado VARCHAR(20)) ENGINE=InnoDB");
    $db->query("INSERT INTO estudiantes VALUES (1,'PRUEBA1','Estudiante','Prueba','PRUEBA','Activo'),(2,'PRUEBA2','OTRO_ESTUDIANTE','Prueba','OTRO','Activo')");
    $db->query("CREATE TEMPORARY TABLE inscripciones (cod_inscripcion INT PRIMARY KEY, cod_estudiante INT, estado VARCHAR(20)) ENGINE=InnoDB");
    $db->query("INSERT INTO inscripciones VALUES (9,1,'Finalizado'),(10,1,'Retirado'),(11,1,'Activo'),(12,2,'Activo')");
    if (!empty($caso['estudiante_retirado'])) {
        $db->query("UPDATE estudiantes SET estado='Retirado' WHERE cod_estudiante=1");
        $db->query("UPDATE inscripciones SET estado='Retirado' WHERE cod_inscripcion=11");
    }
    if (!empty($caso['fallo_inscripcion'])) {
        $db->query("ALTER TABLE inscripciones ADD CONSTRAINT fallo_prueba CHECK (cod_inscripcion<>11 OR estado='Activo')");
    }
    // Oculta también la vista permanente: ningún formulario consulta personas reales.
    $db->query("CREATE TEMPORARY TABLE vista_estudiantes (cod_estudiante INT PRIMARY KEY, codigo VARCHAR(20), nombres VARCHAR(60), apellidos VARCHAR(60), ci VARCHAR(20), estado VARCHAR(20), curso VARCHAR(30), paralelo VARCHAR(10), turno VARCHAR(20))");
    $db->query("INSERT INTO vista_estudiantes SELECT e.*,'1ro de Secundaria','A','Mañana' FROM estudiantes e");
    $db->query("CREATE TEMPORARY TABLE materias (cod_materia INT PRIMARY KEY, nombre VARCHAR(150), estado VARCHAR(20))");
    $db->query("INSERT INTO materias VALUES (1,'Lenguaje y Comunicación','Activo')");
    $db->query("CREATE TEMPORARY TABLE categorias_derivacion (cod_categoria INT PRIMARY KEY, nombre VARCHAR(100), estado VARCHAR(20))");
    $db->query("INSERT INTO categorias_derivacion VALUES (1,'Rendimiento Académico','Activo')");
    $db->query('CREATE TEMPORARY TABLE derivacion_categorias (cod_derivacion INT, cod_categoria INT)');
    $db->query("CREATE TEMPORARY TABLE derivaciones (cod_derivacion INT PRIMARY KEY, fecha DATE, cod_estudiante INT, cod_docente INT, cod_materia INT, motivo TEXT, observaciones TEXT, prioridad VARCHAR(20), estado VARCHAR(30), fecha_registro DATETIME)");
    $db->query("INSERT INTO derivaciones VALUES (1,CURDATE(),1,303,1,'PROPIA_PRIVADA','Observacion propia','Media','Pendiente',NOW()),(2,CURDATE(),2,305,1,'AJENA_PRIVADA','Observacion ajena','Media','Pendiente',NOW())");
    $db->query("CREATE TEMPORARY TABLE citas (cod_cita INT PRIMARY KEY, cod_estudiante INT, fecha DATE, hora TIME, estado VARCHAR(20), observaciones TEXT, cod_derivacion INT NULL)");
    $db->query("INSERT INTO citas VALUES (1,1,CURDATE(),'09:00','Pendiente','Cita de prueba',NULL)");
    $db->query("CREATE TEMPORARY TABLE historias_clinicas (cod_historia INT PRIMARY KEY,cod_estudiante INT,cod_derivacion_origen INT,cod_cita_origen INT)");
    $db->query("CREATE TEMPORARY TABLE seguimientos (cod_seguimiento INT PRIMARY KEY,cod_cita INT)");
    $db->query("CREATE TEMPORARY TABLE informe_individual (cod_informe INT PRIMARY KEY,cod_derivacion INT)");
    $db->query("CREATE TEMPORARY TABLE auditoria (cod_usuario INT,modulo VARCHAR(50),accion VARCHAR(50),registro_id INT,detalle TEXT)");
    register_shutdown_function(static function () use ($db): void {
        $estado = [
            'derivaciones' => $db->query('SELECT cod_derivacion,motivo,observaciones FROM derivaciones ORDER BY cod_derivacion')->fetch_all(MYSQLI_ASSOC),
            'cita' => $db->query('SELECT estado FROM citas WHERE cod_cita=1')->fetch_assoc()['estado'],
            'estudiantes' => $db->query('SELECT cod_estudiante,estado FROM estudiantes ORDER BY cod_estudiante')->fetch_all(MYSQLI_ASSOC),
            'inscripciones' => $db->query('SELECT cod_inscripcion,cod_estudiante,estado FROM inscripciones ORDER BY cod_inscripcion')->fetch_all(MYSQLI_ASSOC),
            'autenticado' => $_SESSION['autenticado'] ?? false,
        ];
        echo "\n__ESTADO_PRUEBA__", json_encode($estado);
    });
}
chdir(dirname($archivo));
require $archivo;
