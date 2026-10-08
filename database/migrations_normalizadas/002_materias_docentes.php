<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $consulta = $bd->prepare('SELECT id_materia FROM materias WHERE nombre=?');
    foreach (['Física', 'Química', 'Tecnología', 'Religión'] as $nombre) {
        $consulta->bind_param('s', $nombre);
        $consulta->execute();
        // Conserva el identificador y estado de una materia que ya exista.
        if (!$consulta->get_result()->num_rows) {
            $valor = $bd->real_escape_string($nombre);
            $plan[] = "INSERT INTO materias (nombre,estado) VALUES ('$valor','Activo')";
        }
    }
    $consulta->close();
    return $plan;
};
