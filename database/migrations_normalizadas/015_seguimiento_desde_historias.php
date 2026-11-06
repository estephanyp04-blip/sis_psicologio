<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
return static function (mysqli $bd): array {
    if ($bd->query("SELECT version FROM esquema_migraciones_normalizadas WHERE version='015_seguimiento_desde_historias'")->num_rows) return [];
    return [
        // Las sesiones siguen siendo la evolución clínica. La comparación entre
        // historias se consulta desde Estadísticas y no sustituye estos registros.
        "INSERT INTO esquema_migraciones_normalizadas(version) VALUES ('015_seguimiento_desde_historias')",
    ];
};
