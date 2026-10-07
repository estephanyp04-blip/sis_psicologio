<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$opciones = getopt('', ['base:', 'aplicar', 'instalar', 'comprobar']);
$base = $opciones['base'] ?? '';
if (!is_string($base) || !preg_match('/^[a-zA-Z0-9_]+$/', $base)) {
    fwrite(STDERR, "Uso: php database/migrar.php --base=NOMBRE [--comprobar | --aplicar [--instalar]]\n");
    exit(1);
}
$aplicar = isset($opciones['aplicar']);
$instalar = isset($opciones['instalar']);
if (($instalar && !$aplicar) || ($aplicar && isset($opciones['comprobar']))) {
    fwrite(STDERR, "Use --instalar junto con --aplicar; --comprobar es solo lectura.\n");
    exit(1);
}
$config = require dirname(__DIR__) . '/config/login.php';
$bd = null;
try {
    $bd = new mysqli($config['host'], $config['usuario_bd'], $config['clave_bd'], '', $config['puerto']);
    $bd->set_charset('utf8mb4');
    $bd->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    if ($instalar) {
        $bd->query("CREATE DATABASE IF NOT EXISTS `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    $bd->select_db($base);
    $bloqueo = $bd->real_escape_string('psicologia_migrar_' . $base);
    if ($aplicar && (int)$bd->query("SELECT GET_LOCK('$bloqueo', 0)")->fetch_row()[0] !== 1) {
        throw new RuntimeException('Ya hay una migración en ejecución.');
    }
    if ($instalar) {
        if ($bd->query('SHOW TABLES')->num_rows) throw new RuntimeException('La instalación requiere una base vacía.');
        $archivoEsquema = __DIR__ . '/psicologia_db.sql';
        if (!is_file($archivoEsquema)) throw new RuntimeException('No se encontró database/psicologia_db.sql.');
        $bd->multi_query(file_get_contents($archivoEsquema));
        do {
            if ($resultado = $bd->store_result()) $resultado->free();
        } while ($bd->more_results() && $bd->next_result());
    }
    $tablas = array_column($bd->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
    $normalizado = false;
    if (in_array('personas', $tablas, true) && in_array('informe_individual', $tablas, true)) {
        $campos = [];
        foreach (['usuarios', 'docentes', 'historias_clinicas', 'informes'] as $tabla) {
            $campos[$tabla] = array_column($bd->query("SHOW COLUMNS FROM `$tabla`")->fetch_all(MYSQLI_ASSOC), 'Field');
        }
        $normalizado = in_array('id_persona', $campos['usuarios'], true)
            && in_array('id_persona', $campos['docentes'], true)
            && in_array('id_derivacion_origen', $campos['historias_clinicas'], true)
            && in_array('id_elaborado_por', $campos['informes'], true);
    }
    $directorioMigraciones = $normalizado ? '/migrations_normalizadas/*.php' : '/migrations/*.php';
    $planificar = static function (mysqli $bd) use ($directorioMigraciones): array {
        $plan = [];
        foreach (glob(__DIR__ . $directorioMigraciones) as $archivo) {
            $planificador = require $archivo;
            $plan = array_merge($plan, $planificador($bd));
        }
        return $plan;
    };
    $plan = $planificar($bd);
    echo "Base: $base. Operaciones pendientes: " . count($plan) . ".\n";
    if (!$aplicar) {
        foreach ($plan as $sql) echo $sql . ";\n";
        exit(0);
    }
    foreach ($plan as $sql) $bd->query($sql);
    if ($planificar($bd)) throw new RuntimeException('La comprobación posterior detectó operaciones pendientes.');
    if ($normalizado) {
        echo "Esquema normalizado verificado.\n";
        exit(0);
    }
    $bd->query('CREATE TABLE IF NOT EXISTS esquema_migraciones (version VARCHAR(80) NOT NULL PRIMARY KEY,
        aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $bd->query("INSERT IGNORE INTO esquema_migraciones (version) VALUES ('001_alinear_esquema')");
    $bd->query("INSERT IGNORE INTO esquema_migraciones (version) VALUES ('002_trazabilidad')");
    echo "Esquema 002_trazabilidad verificado.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    if ($bd instanceof mysqli) $bd->close();
}
