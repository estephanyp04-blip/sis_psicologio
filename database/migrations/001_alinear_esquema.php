<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Plan de actualización desde la base activa revisada el 02-10-2026. Solo consulta. */
return static function (mysqli $bd): array {
    $columnas = [];
    foreach (['roles', 'usuarios', 'cursos', 'paralelos', 'estudiantes', 'historias_clinicas', 'seguimientos', 'informes'] as $tabla) {
        foreach ($bd->query("SHOW COLUMNS FROM `$tabla`")->fetch_all(MYSQLI_ASSOC) as $columna) {
            $columnas[$tabla][$columna['Field']] = $columna;
        }
    }
    foreach (['roles' => ['estado'], 'estudiantes' => ['id_curso', 'id_paralelo', 'curso', 'paralelo'],
        'historias_clinicas' => ['id_derivacion', 'observaciones'], 'seguimientos' => ['descripcion', 'proxima_sesion'],
        'informes' => ['id_usuario', 'titulo', 'elaborado_por', 'tipo']] as $tabla => $campos) {
        foreach ($campos as $campo) {
            if (!isset($columnas[$tabla][$campo])) throw new RuntimeException("Esquema de origen no compatible: falta $tabla.$campo. Consulte database/README.md.");
        }
    }
    foreach (['usuarios' => 'id_usuario', 'estudiantes' => 'id_estudiante', 'historias_clinicas' => 'id_historia',
        'informes' => 'id_informe'] as $tabla => $campo) {
        if (!str_contains($columnas[$tabla][$campo]['Type'], 'unsigned')) {
            throw new RuntimeException("El origen no usa los IDs unsigned esperados: $tabla.$campo.");
        }
    }
    if ($columnas['informes']['estado']['Type'] !== "enum('Borrador','Finalizado')"
        || $columnas['estudiantes']['estado']['Type'] !== "enum('Activo','Retirado')") {
        throw new RuntimeException('Los estados del origen no coinciden con el contrato documentado. No se convertirán automáticamente.');
    }
    $conflicto = $bd->query("SELECT numero_ficha FROM informes GROUP BY numero_ficha HAVING COUNT(*) > 1 OR numero_ficha = '' LIMIT 1")->fetch_assoc();
    if ($conflicto) throw new RuntimeException('Hay números de ficha vacíos o duplicados; resuélvalos antes de migrar.');
    foreach (['id_estudiante' => ['estudiantes', 'id_estudiante'], 'elaborado_por' => ['usuarios', 'id_usuario'],
        'id_historia' => ['historias_clinicas', 'id_historia'], 'id_derivacion' => ['derivaciones', 'id_derivacion']] as $campo => [$tabla, $clave]) {
        if ($bd->query("SELECT 1 FROM informes i LEFT JOIN `$tabla` r ON i.`$campo` = r.`$clave`
            WHERE i.`$campo` IS NOT NULL AND r.`$clave` IS NULL LIMIT 1")->num_rows) {
            throw new RuntimeException("Hay referencias inválidas en informes.$campo. No se modificarán los datos existentes.");
        }
    }
    $sql = [];
    if (!isset($columnas['seguimientos']['recomendaciones'])) {
        $sql[] = 'ALTER TABLE seguimientos ADD COLUMN recomendaciones TEXT NULL';
    }
    if (!isset($columnas['historias_clinicas']['evolucion_caso'])) {
        $sql[] = 'ALTER TABLE historias_clinicas ADD COLUMN evolucion_caso TINYINT UNSIGNED NULL';
    }
    $indices = $bd->query('SHOW INDEX FROM informes')->fetch_all(MYSQLI_ASSOC);
    $unico = false;
    foreach ($indices as $indice) {
        if ($indice['Key_name'] === 'uk_informes_ficha' && (int)$indice['Non_unique'] === 0) $unico = true;
    }
    if (!$unico) $sql[] = 'ALTER TABLE informes ADD UNIQUE KEY uk_informes_ficha (numero_ficha)';
    $restricciones = array_column($bd->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'informes'")->fetch_all(MYSQLI_ASSOC), 'CONSTRAINT_NAME');
    foreach (['id_estudiante' => ['estudiantes', 'id_estudiante'], 'elaborado_por' => ['usuarios', 'id_usuario'],
        'id_historia' => ['historias_clinicas', 'id_historia'], 'id_derivacion' => ['derivaciones', 'id_derivacion']] as $campo => [$tabla, $clave]) {
        $nombre = 'fk_informes_' . $campo;
        if (!in_array($nombre, $restricciones, true)) {
            $sql[] = "ALTER TABLE informes ADD CONSTRAINT `$nombre` FOREIGN KEY (`$campo`) REFERENCES `$tabla` (`$clave`) ON UPDATE CASCADE";
        }
    }
    $tablas = array_column($bd->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('informes_secuencia', $tablas, true)) {
        $sql[] = 'CREATE TABLE informes_secuencia (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, ultimo BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $sql[] = "INSERT INTO informes_secuencia (id, ultimo) SELECT 1, COALESCE(MAX(CAST(SUBSTRING(numero_ficha, 5) AS UNSIGNED)), 0) FROM informes WHERE numero_ficha REGEXP '^INF-[0-9]+$'";
    } elseif (!$bd->query('SELECT 1 FROM informes_secuencia WHERE id = 1')->num_rows) {
        $sql[] = "INSERT INTO informes_secuencia (id, ultimo) SELECT 1, COALESCE(MAX(CAST(SUBSTRING(numero_ficha, 5) AS UNSIGNED)), 0) FROM informes WHERE numero_ficha REGEXP '^INF-[0-9]+$'";
    }
    return $sql;
};
