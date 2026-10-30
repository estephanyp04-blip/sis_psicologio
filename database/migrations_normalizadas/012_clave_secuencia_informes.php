<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $tablas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('informes_secuencia', $tablas, true)) return [];

    $columnas = array_column($bd->query('SHOW COLUMNS FROM informes_secuencia')->fetch_all(MYSQLI_ASSOC), 'Field');
    if (in_array('cod_secuencia', $columnas, true)) return [];
    if (!in_array('id', $columnas, true)) {
        throw new RuntimeException('No se puede normalizar informes_secuencia: falta su clave primaria.');
    }

    return ['ALTER TABLE informes_secuencia CHANGE COLUMN id cod_secuencia TINYINT UNSIGNED NOT NULL'];
};
