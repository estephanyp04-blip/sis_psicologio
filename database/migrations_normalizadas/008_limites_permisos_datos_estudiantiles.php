<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $tablas = array_column($bd->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('esquema_migraciones_normalizadas', $tablas, true)) {
        $plan[] = 'CREATE TABLE IF NOT EXISTS esquema_migraciones_normalizadas (version VARCHAR(80) NOT NULL PRIMARY KEY, aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $aplicada = false;
    } else {
        $stmt = $bd->prepare('SELECT version FROM esquema_migraciones_normalizadas WHERE version=? LIMIT 1');
        $version = '008_limites_permisos_datos_estudiantiles';
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $aplicada = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$aplicada) {
        $plan[] = "UPDATE permisos p INNER JOIN modulos m ON m.cod_modulo=p.cod_modulo
            SET p.puede_ver=0,p.puede_crear=0,p.puede_editar=0,p.puede_cambiar_estado=0,p.puede_eliminar=0
            WHERE p.cod_rol IN (3,4) AND m.nombre IN ('Dashboard','Estadísticas','Estudiantes')";
        $versionSql = $bd->real_escape_string('008_limites_permisos_datos_estudiantiles');
        $plan[] = "INSERT IGNORE INTO esquema_migraciones_normalizadas (version) VALUES ('$versionSql')";
    }
    return $plan;
};
