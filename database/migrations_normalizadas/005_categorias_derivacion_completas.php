<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $buscar = $bd->prepare('SELECT cod_categoria FROM categorias_derivacion WHERE nombre=? LIMIT 1');
    foreach (['Dinámica Familiar', 'Social / Emocional', 'Otro'] as $nombre) {
        $buscar->bind_param('s', $nombre);
        $buscar->execute();
        if ($buscar->get_result()->fetch_assoc()) continue;
        $nombreSql = $bd->real_escape_string($nombre);
        $plan[] = "INSERT IGNORE INTO categorias_derivacion (nombre,estado) VALUES ('$nombreSql','Activo')";
    }
    $buscar->close();
    return $plan;
};
