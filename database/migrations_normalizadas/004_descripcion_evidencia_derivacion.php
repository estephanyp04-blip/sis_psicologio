<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $columnas = $bd->query("SHOW COLUMNS FROM derivacion_evidencias LIKE 'descripcion'");
    return $columnas->num_rows
        ? []
        : ['ALTER TABLE derivacion_evidencias ADD COLUMN descripcion TEXT NULL AFTER nombre_original'];
};
