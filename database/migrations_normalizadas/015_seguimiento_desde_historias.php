<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
return static function (mysqli $bd): array {
    if ($bd->query("SELECT version FROM esquema_migraciones_normalizadas WHERE version='015_seguimiento_desde_historias'")->num_rows) return [];
    return [
        // Marca histórica. Desde 19/11/2026 el seguimiento es consulta de historias
        // y versiones en Estadísticas; los registros antiguos se conservan para lectura.
        "INSERT INTO esquema_migraciones_normalizadas(version) VALUES ('015_seguimiento_desde_historias')",
    ];
};
