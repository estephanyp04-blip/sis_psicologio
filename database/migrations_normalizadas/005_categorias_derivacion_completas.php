<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $campos = array_column($bd->query('SHOW COLUMNS FROM categorias_derivacion')->fetch_all(MYSQLI_ASSOC), 'Field');
    $identificador = in_array('cod_categoria', $campos, true) ? 'cod_categoria' : 'id_categoria';
    $buscar = $bd->prepare("SELECT `$identificador` FROM categorias_derivacion WHERE nombre=? LIMIT 1");
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
