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
$db->query("CREATE TEMPORARY TABLE roles (cod_rol INT PRIMARY KEY, nombre VARCHAR(40), descripcion VARCHAR(255), estado VARCHAR(20))");
$db->query("INSERT INTO roles (cod_rol,nombre,estado) VALUES (1,'Administrador','Activo'),(2,'Psicologa','Activo'),(3,'Docente','Activo'),(4,'Director','Activo')");
$politicas = require $fixture['app'] . '/config/permisos.php';
$idsModulo = [];
$db->query("CREATE TEMPORARY TABLE modulos (cod_modulo INT PRIMARY KEY,nombre VARCHAR(100),ruta VARCHAR(190),orden INT,estado VARCHAR(20))");
$insertModulo = $db->prepare('INSERT INTO modulos (cod_modulo,nombre,ruta,orden,estado) VALUES (?,?,?,?,?)');
foreach ($politicas as $ruta => $politica) {
    $nombre = $politica['modulo'];
    if (isset($idsModulo[$nombre])) continue;
    $idModulo = count($idsModulo) + 1;
    $idsModulo[$nombre] = $idModulo;
    $estadoModulo = 'Activo';
    $insertModulo->bind_param('issis', $idModulo, $nombre, $ruta, $idModulo, $estadoModulo);
    $insertModulo->execute();
}
$insertModulo->close();
$db->query("CREATE TEMPORARY TABLE permisos (cod_permiso INT AUTO_INCREMENT PRIMARY KEY,cod_rol INT NOT NULL,cod_modulo INT NOT NULL,puede_ver TINYINT(1) NOT NULL DEFAULT 0,puede_crear TINYINT(1) NOT NULL DEFAULT 0,puede_editar TINYINT(1) NOT NULL DEFAULT 0,puede_cambiar_estado TINYINT(1) NOT NULL DEFAULT 0,puede_eliminar TINYINT(1) NOT NULL DEFAULT 0,UNIQUE KEY uq_permiso_rol_modulo (cod_rol,cod_modulo))");
$acciones = ['puede_ver', 'puede_crear', 'puede_editar', 'puede_cambiar_estado', 'puede_eliminar'];
$permisosPrueba = [];
foreach ($politicas as $politica) {
    foreach ($politica['roles'] as $idRol) {
        $clave = $idRol . ':' . $idsModulo[$politica['modulo']];
        $permisosPrueba[$clave] ??= array_fill_keys($acciones, 0);
        $permisosPrueba[$clave][$politica['accion']] = 1;
    }
}
// Simula filas heredadas o concedidas por error: la autorización del servidor
// debe seguir bloqueando Docente y Director en áreas con datos estudiantiles.
foreach ([3, 4] as $idRol) {
    foreach (['Dashboard', 'Estadísticas', 'Estudiantes'] as $nombreModulo) {
        $clave = $idRol . ':' . $idsModulo[$nombreModulo];
        $permisosPrueba[$clave] = array_fill_keys($acciones, 1);
    }
}
// Docente puede editar derivaciones propias, pero esa facultad no habilita estados.
$claveDocenteDerivaciones = '3:' . $idsModulo['Derivaciones'];
$permisosPrueba[$claveDocenteDerivaciones] ??= array_fill_keys($acciones, 0);
$permisosPrueba[$claveDocenteDerivaciones]['puede_editar'] = 1;
$permisosPrueba[$claveDocenteDerivaciones]['puede_cambiar_estado'] = 0;
$insertPermiso = $db->prepare('INSERT INTO permisos (cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar,puede_cambiar_estado,puede_eliminar) VALUES (?,?,?,?,?,?,?)');
foreach ($permisosPrueba as $clave => $valores) {
    [$idRol, $idModulo] = array_map('intval', explode(':', $clave));
    $insertPermiso->bind_param('iiiiiii', $idRol, $idModulo, $valores['puede_ver'], $valores['puede_crear'], $valores['puede_editar'], $valores['puede_cambiar_estado'], $valores['puede_eliminar']);
    $insertPermiso->execute();
}
$insertPermiso->close();
$db->query("CREATE TEMPORARY TABLE usuarios (cod_usuario INT PRIMARY KEY, nombres VARCHAR(60), apellidos VARCHAR(60), correo VARCHAR(120), usuario VARCHAR(40), password VARCHAR(255), estado VARCHAR(20), cod_rol INT)");
$db->query("INSERT INTO usuarios VALUES (101,'Admin','Prueba','admin@example.test','prueba_admin','hash-prueba','Activo',1),(102,'Psicologa','Prueba','psicologa@example.test','prueba_psicologa','hash-prueba','Activo',2),(103,'Docente','Prueba','docente@example.test','prueba_docente','hash-prueba','Activo',3),(104,'Director','Prueba','director@example.test','prueba_director','hash-prueba','Activo',4),(105,'Otro','Docente','otro@example.test','otro_docente','hash-prueba','Activo',3)");
$db->query("CREATE TEMPORARY TABLE docentes (cod_docente INT PRIMARY KEY, cod_usuario INT)");
$db->query("INSERT INTO docentes VALUES (303,103),(305,105)");

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
    case 'sin_docente': $db->query('DELETE FROM docentes WHERE cod_usuario=103'); break;
    case 'docente_duplicado': $db->query('INSERT INTO docentes VALUES (306,103)'); break;
}

$GLOBALS['prueba_negocio'] = !empty($caso['negocio']);
if ($GLOBALS['prueba_negocio']) {
    $db->query("CREATE TEMPORARY TABLE estudiantes (cod_estudiante INT PRIMARY KEY, codigo VARCHAR(20), nombres VARCHAR(60), apellidos VARCHAR(60), ci VARCHAR(20), estado VARCHAR(20)) ENGINE=InnoDB");
    $db->query("INSERT INTO estudiantes VALUES (1,'PRUEBA1','Estudiante','Prueba','PRUEBA','Activo'),(2,'PRUEBA2','OTRO_ESTUDIANTE','Prueba','OTRO','Activo')");
    $db->query("CREATE TEMPORARY TABLE historial_academico (cod_historial_academico INT PRIMARY KEY, cod_estudiante INT, estado VARCHAR(20)) ENGINE=InnoDB");
    $db->query("INSERT INTO historial_academico VALUES (9,1,'Finalizado'),(10,1,'Retirado'),(11,1,'Activo'),(12,2,'Activo')");
    if (!empty($caso['estudiante_retirado'])) {
        $db->query("UPDATE estudiantes SET estado='Retirado' WHERE cod_estudiante=1");
        $db->query("UPDATE historial_academico SET estado='Retirado' WHERE cod_historial_academico=11");
    }
    if (!empty($caso['fallo_inscripcion'])) {
        $db->query("ALTER TABLE historial_academico ADD CONSTRAINT fallo_prueba CHECK (cod_historial_academico<>11 OR estado='Activo')");
    }
    // El router trabaja solo con las tablas temporales del esquema actual.
    $db->query("CREATE TEMPORARY TABLE vista_estudiantes (cod_estudiante INT PRIMARY KEY, codigo VARCHAR(20), nombres VARCHAR(60), apellidos VARCHAR(60), ci VARCHAR(20), estado VARCHAR(20), curso VARCHAR(30), paralelo VARCHAR(10), turno VARCHAR(20))");
    $db->query("INSERT INTO vista_estudiantes SELECT e.*,'1ro de Secundaria','A','Mañana' FROM estudiantes e");
    $db->query("CREATE TEMPORARY TABLE materias (cod_materia INT PRIMARY KEY, nombre VARCHAR(150), estado VARCHAR(20))");
    $db->query("INSERT INTO materias VALUES (1,'Lenguaje y Comunicación','Activo')");
    $db->query("CREATE TEMPORARY TABLE categorias_derivacion (cod_categoria INT PRIMARY KEY, nombre VARCHAR(100), estado VARCHAR(20))");
    $db->query("INSERT INTO categorias_derivacion VALUES (1,'Rendimiento Académico','Activo')");
    $db->query('CREATE TEMPORARY TABLE derivacion_categorias (cod_derivacion INT, cod_categoria INT)');
    $db->query("CREATE TEMPORARY TABLE derivaciones (cod_derivacion INT PRIMARY KEY, fecha DATE, cod_estudiante INT, cod_docente INT, cod_materia INT, motivo TEXT, observaciones TEXT, prioridad VARCHAR(20), estado VARCHAR(30), solicitar_cita TINYINT(1) NOT NULL DEFAULT 0, cod_psicologa_solicitada INT NULL, fecha_registro DATETIME)");
    $db->query("INSERT INTO derivaciones (cod_derivacion,fecha,cod_estudiante,cod_docente,cod_materia,motivo,observaciones,prioridad,estado,solicitar_cita,cod_psicologa_solicitada,fecha_registro) VALUES (1,CURDATE(),1,303,1,'PROPIA_PRIVADA','Observacion propia','Media','Pendiente',0,NULL,NOW()),(2,CURDATE(),2,305,1,'AJENA_PRIVADA','Observacion ajena','Media','Pendiente',0,NULL,NOW())");
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
            'historial_academico' => $db->query('SELECT cod_historial_academico,cod_estudiante,estado FROM historial_academico ORDER BY cod_historial_academico')->fetch_all(MYSQLI_ASSOC),
            'permisos_protegidos' => $db->query("SELECT p.cod_rol,m.nombre,p.puede_ver,p.puede_crear,p.puede_editar,p.puede_cambiar_estado,p.puede_eliminar FROM permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo WHERE p.cod_rol IN (3,4) AND m.nombre IN ('Dashboard','Estadísticas','Estudiantes') ORDER BY p.cod_rol,m.nombre")->fetch_all(MYSQLI_ASSOC),
            'autenticado' => $_SESSION['autenticado'] ?? false,
        ];
        echo "\n__ESTADO_PRUEBA__", json_encode($estado);
    });
}
chdir(dirname($archivo));
require $archivo;
