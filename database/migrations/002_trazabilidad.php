<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
return static function (mysqli $bd): array {
    $sql = [];
    // Agenda única, como en los formularios existentes; canceladas liberan el horario.
    if ($bd->query("SELECT 1 FROM citas WHERE estado <> 'Cancelada' GROUP BY fecha,hora HAVING COUNT(*)>1 LIMIT 1")->num_rows) {
        throw new RuntimeException('Hay citas vigentes con horarios duplicados. Resuélvalas antes de aplicar 002; no se modificaron esas citas.');
    }
    $columnas = array_column($bd->query('SHOW COLUMNS FROM citas')->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('turno_reservado', $columnas, true)) $sql[] = "ALTER TABLE citas ADD turno_reservado TINYINT GENERATED ALWAYS AS (CASE WHEN estado='Cancelada' THEN NULL ELSE 1 END) STORED";
    $indices = array_column($bd->query('SHOW INDEX FROM citas')->fetch_all(MYSQLI_ASSOC), 'Key_name');
    if (!in_array('uk_citas_horario_vigente', $indices, true)) $sql[] = 'ALTER TABLE citas ADD UNIQUE KEY uk_citas_horario_vigente (fecha,hora,turno_reservado)';
    if (in_array('uk_cita_profesional_fecha_hora', $indices, true)) {
        if (!in_array('idx_citas_usuario', $indices, true)) $sql[] = 'ALTER TABLE citas ADD INDEX idx_citas_usuario (id_usuario)';
        $sql[] = 'ALTER TABLE citas DROP INDEX uk_cita_profesional_fecha_hora';
    }
    foreach (['historias_clinicas' => ['id_cita','citas','id_cita'], 'informes' => ['id_seguimiento','seguimientos','id_seguimiento']] as $tabla => [$columna,$destino,$clave]) {
        $campos = array_column($bd->query("SHOW COLUMNS FROM $tabla")->fetch_all(MYSQLI_ASSOC), 'Field');
        if (!in_array($columna,$campos,true)) $sql[] = "ALTER TABLE $tabla ADD $columna INT UNSIGNED NULL";
        $nombre = "fk_{$tabla}_{$columna}_origen";
        $existe = $bd->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='$tabla' AND CONSTRAINT_NAME='$nombre'")->num_rows;
        if (!$existe) $sql[] = "ALTER TABLE $tabla ADD CONSTRAINT $nombre FOREIGN KEY ($columna) REFERENCES $destino ($clave) ON UPDATE CASCADE";
    }
    return $sql;
};
