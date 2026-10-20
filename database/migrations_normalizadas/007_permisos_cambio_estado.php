<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $columnas = array_column($bd->query('SHOW COLUMNS FROM permisos')->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('puede_cambiar_estado', $columnas, true)) {
        $plan[] = 'ALTER TABLE permisos ADD COLUMN puede_cambiar_estado TINYINT(1) NOT NULL DEFAULT 0 AFTER puede_eliminar';
    }

    $tablas = array_column($bd->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('esquema_migraciones_normalizadas', $tablas, true)) {
        $plan[] = 'CREATE TABLE IF NOT EXISTS esquema_migraciones_normalizadas (version VARCHAR(80) NOT NULL PRIMARY KEY, aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $aplicada = false;
    } else {
        $stmt = $bd->prepare('SELECT version FROM esquema_migraciones_normalizadas WHERE version=? LIMIT 1');
        $version = '007_permisos_cambio_estado';
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $aplicada = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$aplicada) {
        $plan[] = "UPDATE permisos p INNER JOIN modulos m ON m.cod_modulo=p.cod_modulo
            SET p.puede_ver=0,p.puede_crear=0,p.puede_editar=0,p.puede_cambiar_estado=0,p.puede_eliminar=0
            WHERE (p.cod_rol=3 AND m.nombre IN ('Dashboard','Estudiantes'))
               OR (p.cod_rol=4 AND m.nombre='Dashboard')";
        $plan[] = "UPDATE permisos p INNER JOIN modulos m ON m.cod_modulo=p.cod_modulo
            SET p.puede_editar=0 WHERE p.cod_rol=2 AND m.nombre='Derivaciones'";
        $plan[] = "UPDATE permisos p INNER JOIN modulos m ON m.cod_modulo=p.cod_modulo
            SET p.puede_cambiar_estado=CASE
                WHEN p.cod_rol=1 AND m.nombre IN ('Usuarios','Estudiantes','Derivaciones','Citas') THEN 1
                WHEN p.cod_rol=2 AND m.nombre IN ('Estudiantes','Derivaciones','Citas') THEN 1
                ELSE 0 END
            WHERE p.cod_rol IN (1,2,3,4)";
        $versionSql = $bd->real_escape_string('007_permisos_cambio_estado');
        $plan[] = "INSERT IGNORE INTO esquema_migraciones_normalizadas (version) VALUES ('$versionSql')";
    }
    return $plan;
};
