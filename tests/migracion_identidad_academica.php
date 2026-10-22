<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/login.php';
$admin = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
$admin->set_charset('utf8mb4');
$base = 'psicologia_test_migracion_' . bin2hex(random_bytes(6));
$total = 0;
$bd = null;

$verificar = static function (bool $condicion, string $mensaje) use (&$total): void {
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
};

try {
    $admin->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $admin->select_db($base);
    $sql = file_get_contents($raiz . '/database/backups/psicologia_db_2026-10-20_pre_modelo_academico.sql');
    if (!is_string($sql)) throw new RuntimeException('No se pudo leer el SQL de instalación.');
    $sql = preg_replace('/^\\xEF\\xBB\\xBF/', '', $sql);
    $admin->query("SET FOREIGN_KEY_CHECKS=0");
    $admin->multi_query($sql);
    do {
        if ($resultado = $admin->store_result()) $resultado->free();
    } while ($admin->more_results() && $admin->next_result());
    $admin->query("SET FOREIGN_KEY_CHECKS=1");
    $admin->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    $admin->query("INSERT INTO personas (cod_persona,nombres,apellidos,fecha_registro) VALUES (900000,'Sin','Cuenta','2026-10-20 10:00:00')");
    $planificar = require $raiz . '/database/migrations_normalizadas/009_personas_historial_academico.php';
    $rechazada = false;
    try { $planificar($admin); } catch (RuntimeException $e) { $rechazada = str_contains($e->getMessage(), 'persona(s) sin cuenta'); }
    $verificar($rechazada, 'La migración no detuvo la eliminación de una persona sin cuenta.');
    $admin->query('DELETE FROM personas WHERE cod_persona=900000');

    $admin->query("INSERT INTO personas (cod_persona,nombres,apellidos,ci,correo,telefono,fecha_registro) VALUES (900001,'Docente','Prueba','CI-MIG-2','docente-mig@example.test','70000002','2026-10-20 10:00:00')");
    $admin->query("INSERT INTO usuarios (cod_usuario,cod_persona,usuario,password,cod_rol,estado,fecha_registro,fecha_actualizacion) VALUES (900001,900001,'docente_migracion','hash-prueba',3,'Activo','2026-10-20 10:00:00','2026-10-20 10:00:00')");
    $admin->query("INSERT INTO docentes (cod_docente,cod_persona) VALUES (900001,900001)");
    $admin->query("INSERT INTO estudiantes (cod_estudiante,codigo,nombres,apellidos) VALUES (900002,'MIGRACION500','Estudiante','Prueba')");
    $admin->query("INSERT INTO inscripciones (cod_inscripcion,cod_estudiante,cod_seccion,fecha_inscripcion,estado) VALUES (900002,900002,1,'2026-10-20','Activo')");

    foreach ($planificar($admin) as $operacion) $admin->query($operacion);
    $baseTables = array_column($admin->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    $views = array_column($admin->query("SHOW FULL TABLES WHERE Table_type='VIEW'")->fetch_all(MYSQLI_NUM), 0);
    $verificar(!in_array('personas', $baseTables, true) && !in_array('inscripciones', $baseTables, true), 'Quedó una tabla personas o inscripciones.');
    $verificar(!in_array('personas', $views, true) && !in_array('inscripciones', $views, true), 'Quedó una vista con nombre del esquema anterior.');

    $usuario = $admin->query('SELECT nombres,apellidos,ci,correo,telefono FROM usuarios WHERE cod_usuario=900001')->fetch_assoc();
    $verificar($usuario === ['nombres' => 'Docente', 'apellidos' => 'Prueba', 'ci' => 'CI-MIG-2', 'correo' => 'docente-mig@example.test', 'telefono' => '70000002'], 'No se copiaron todos los datos personales del usuario.');
    $docente = $admin->query('SELECT d.cod_usuario,u.nombres,u.apellidos,d.estado FROM docentes d JOIN usuarios u ON u.cod_usuario=d.cod_usuario WHERE d.cod_docente=900001')->fetch_assoc();
    $verificar($docente === ['cod_usuario' => '900001', 'nombres' => 'Docente', 'apellidos' => 'Prueba', 'estado' => 'Activo'], 'No se conservó el vínculo del docente con su cuenta.');
    $administrador = $admin->query("SELECT r.nombre, u.estado, COUNT(p.cod_modulo) AS permisos FROM usuarios u JOIN roles r ON r.cod_rol=u.cod_rol LEFT JOIN permisos p ON p.cod_rol=r.cod_rol WHERE u.cod_usuario=1 GROUP BY r.cod_rol,u.estado")->fetch_assoc();
    $verificar($administrador === ['nombre' => 'Administrador', 'estado' => 'Activo', 'permisos' => '11'], 'La cuenta o los permisos del administrador cambiaron durante la migración.');
    $historial = $admin->query('SELECT cod_historial_academico,cod_estudiante,cod_seccion,fecha_inicio,fecha_fin,estado FROM historial_academico WHERE cod_historial_academico=900002')->fetch_assoc();
    $verificar($historial === ['cod_historial_academico' => '900002', 'cod_estudiante' => '900002', 'cod_seccion' => '1', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => null, 'estado' => 'Activo'], 'No se conservaron las columnas de la inscripción en el historial académico.');
    $columnasUsuarios = array_column($admin->query('SHOW COLUMNS FROM usuarios')->fetch_all(MYSQLI_ASSOC), 'Field');
    $columnasDocentes = array_column($admin->query('SHOW COLUMNS FROM docentes')->fetch_all(MYSQLI_ASSOC), 'Field');
    $verificar(!in_array('cod_persona', $columnasUsuarios, true) && !in_array('cod_persona', $columnasDocentes, true), 'Quedó una columna de compatibilidad cod_persona.');
    $fks = (int)$admin->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='historial_academico' AND REFERENCED_TABLE_NAME IN ('estudiantes','secciones')")->fetch_row()[0];
    $verificar($fks === 2, 'Faltan las claves foráneas del historial académico.');
    $verificar($planificar($admin) === [], 'La migración no es idempotente.');

    // Simula una instalación migrada que todavía conserva los alias de la versión anterior.
    $admin->query('ALTER TABLE usuarios ADD COLUMN cod_persona INT(10) UNSIGNED GENERATED ALWAYS AS (cod_usuario) VIRTUAL');
    $admin->query('ALTER TABLE docentes ADD COLUMN cod_persona INT(10) UNSIGNED GENERATED ALWAYS AS (cod_usuario) VIRTUAL');
    $admin->query('CREATE VIEW personas AS SELECT cod_usuario AS cod_persona,nombres,apellidos,ci,correo,telefono,fecha_registro FROM usuarios');
    $admin->query('CREATE VIEW inscripciones AS SELECT cod_historial_academico AS cod_inscripcion,cod_estudiante,cod_seccion,fecha_inicio AS fecha_inscripcion,estado FROM historial_academico');
    $limpiarCompatibilidad = require $raiz . '/database/migrations_normalizadas/010_eliminar_aliases_identidad.php';
    foreach ($limpiarCompatibilidad($admin) as $operacion) $admin->query($operacion);
    $objetosFinales = array_column($admin->query('SHOW FULL TABLES')->fetch_all(MYSQLI_NUM), 0);
    $verificar(!in_array('personas', $objetosFinales, true) && !in_array('inscripciones', $objetosFinales, true), 'La limpieza no eliminó los objetos heredados.');
    $verificar($limpiarCompatibilidad($admin) === [], 'La limpieza de compatibilidad no es idempotente.');
    echo "Migración de identidad e historial académico: $total verificaciones correctas.\n";
} finally {
    if ($bd instanceof mysqli) $bd->close();
    $admin->query("DROP DATABASE IF EXISTS `$base`");
    $admin->close();
}
