<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $campos = array_column($bd->query('SHOW COLUMNS FROM materias')->fetch_all(MYSQLI_ASSOC), 'Field');
    $identificador = in_array('cod_materia', $campos, true) ? 'cod_materia' : 'id_materia';
    $consulta = $bd->prepare("SELECT `$identificador` FROM materias WHERE nombre=?");
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
