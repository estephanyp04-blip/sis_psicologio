<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $indices = [];
    foreach ($bd->query('SHOW INDEX FROM historias_clinicas')->fetch_all(MYSQLI_ASSOC) as $indice) {
        $indices[$indice['Key_name']]['unico'] = (int)$indice['Non_unique'] === 0;
        $indices[$indice['Key_name']]['columnas'][(int)$indice['Seq_in_index']] = $indice['Column_name'];
    }
    $plan = [];
    // Mantener el índice que necesita la clave foránea, sin limitar las historias del estudiante.
    if (!isset($indices['idx_historias_estudiante'])) {
        $plan[] = 'ALTER TABLE historias_clinicas ADD INDEX idx_historias_estudiante (cod_estudiante, fecha_apertura, cod_historia)';
    }
    foreach ($indices as $nombre => $indice) {
        if ($indice['unico'] && array_values($indice['columnas']) === ['cod_estudiante']) {
            $nombre = str_replace('`', '``', $nombre);
            $plan[] = "ALTER TABLE historias_clinicas DROP INDEX `$nombre`";
        }
    }
    if (!$bd->query("SELECT version FROM esquema_migraciones_normalizadas WHERE version='014_historias_por_estudiante'")->num_rows) {
        $plan[] = "INSERT INTO esquema_migraciones_normalizadas(version) VALUES ('014_historias_por_estudiante')";
    }
    return $plan;
};
