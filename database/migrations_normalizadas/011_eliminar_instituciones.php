<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $tablas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    $vistas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetch_all(MYSQLI_NUM), 0);
    $tieneInstituciones = in_array('instituciones', $tablas, true);
    $tieneSecciones = in_array('secciones', $tablas, true);
    $columnasSeccion = $tieneSecciones
        ? array_column($bd->query('SHOW COLUMNS FROM secciones')->fetch_all(MYSQLI_ASSOC), 'Field')
        : [];
    $tieneRelacion = in_array('cod_institucion', $columnasSeccion, true);
    if (!$tieneInstituciones && !$tieneRelacion) return [];
    if ($tieneRelacion && !$tieneInstituciones) {
        throw new RuntimeException('No se puede eliminar cod_institucion: falta la tabla instituciones para validar sus datos.');
    }
    if ($tieneInstituciones && $tieneSecciones && !$tieneRelacion) {
        $dependencias = (int)$bd->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='instituciones'")->fetch_row()[0];
        if ($dependencias > 0) throw new RuntimeException('Hay claves foráneas que dependen de instituciones; deben migrarse explícitamente.');
    }

    if ($tieneInstituciones) {
        $total = (int)$bd->query('SELECT COUNT(*) FROM instituciones')->fetch_row()[0];
        if ($total > 1) throw new RuntimeException('La migración conserva la escuela única; instituciones tiene más de un registro. Revise y archive esas instituciones antes de continuar.');
    }
    if ($tieneRelacion) {
        $invalidas = (int)$bd->query('SELECT COUNT(*) FROM secciones s LEFT JOIN instituciones i ON i.cod_institucion=s.cod_institucion WHERE i.cod_institucion IS NULL')->fetch_row()[0];
        $institucionesReferidas = (int)$bd->query('SELECT COUNT(DISTINCT cod_institucion) FROM secciones')->fetch_row()[0];
        if ($invalidas > 0) throw new RuntimeException("No se puede migrar: hay $invalidas sección(es) sin institución válida.");
        if ($institucionesReferidas > 1) throw new RuntimeException('No se puede migrar: las secciones pertenecen a más de una institución.');
    }

    $plan = [];
    foreach (['vista_derivaciones', 'vista_citas', 'vista_estudiantes'] as $vista) {
        if (in_array($vista, $vistas, true)) $plan[] = "DROP VIEW `$vista`";
    }
    if ($tieneRelacion) {
        $llavesForaneas = $bd->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='secciones' AND COLUMN_NAME='cod_institucion'
              AND REFERENCED_TABLE_NAME='instituciones'")->fetch_all(MYSQLI_ASSOC);
        foreach ($llavesForaneas as $llave) {
            $nombre = str_replace('`', '``', $llave['CONSTRAINT_NAME']);
            $plan[] = "ALTER TABLE secciones DROP FOREIGN KEY `$nombre`";
        }
        $indices = $bd->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='secciones' AND COLUMN_NAME='cod_institucion' AND NON_UNIQUE=0")->fetch_all(MYSQLI_ASSOC);
        if (count($indices) !== 1 || ($indices[0]['INDEX_NAME'] ?? '') !== 'uk_seccion') {
            throw new RuntimeException('No se reconoce el índice único de secciones que incluye cod_institucion. Revíselo antes de migrar.');
        }
        $plan[] = 'ALTER TABLE secciones DROP INDEX uk_seccion, DROP COLUMN cod_institucion, ADD UNIQUE KEY uk_seccion (cod_curso,cod_paralelo,turno,gestion)';
    }
    if ($tieneInstituciones) $plan[] = 'DROP TABLE instituciones';

    if (in_array('vista_citas', $vistas, true)) {
        $plan[] = 'CREATE VIEW vista_citas AS SELECT c.cod_cita,c.cod_estudiante,c.cod_psicologa,c.cod_derivacion,c.fecha,c.hora,c.estado,c.observaciones,c.motivo,c.fecha_registro,c.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,CONCAT(u.nombres,\' \',u.apellidos) AS psicologa FROM citas c JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante JOIN usuarios u ON u.cod_usuario=c.cod_psicologa';
    }
    if (in_array('vista_estudiantes', $vistas, true)) {
        $plan[] = 'CREATE VIEW vista_estudiantes AS SELECT e.cod_estudiante,e.codigo,e.ci,e.rude,e.nombres,e.apellidos,e.fecha_nacimiento,e.lugar_nacimiento,e.sexo,e.direccion,e.telefono,e.estado,s.cod_seccion,c.cod_curso,c.nombre AS curso,p.cod_paralelo,p.nombre AS paralelo,s.turno,s.gestion FROM estudiantes e LEFT JOIN historial_academico ha ON ha.cod_estudiante=e.cod_estudiante AND ha.estado=\'Activo\' LEFT JOIN secciones s ON s.cod_seccion=ha.cod_seccion LEFT JOIN cursos c ON c.cod_curso=s.cod_curso LEFT JOIN paralelos p ON p.cod_paralelo=s.cod_paralelo';
    }
    if (in_array('vista_derivaciones', $vistas, true)) {
        $plan[] = 'CREATE VIEW vista_derivaciones AS SELECT d.cod_derivacion,d.fecha,d.cod_estudiante,d.cod_docente,d.cod_materia,m.nombre AS materia,d.motivo,d.observaciones,d.prioridad,d.estado,d.solicitar_cita,d.cod_psicologa_solicitada,d.fecha_registro,d.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,ve.curso,ve.paralelo,ve.turno,CONCAT(u.apellidos,\' \',u.nombres) AS docente FROM derivaciones d JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=doc.cod_usuario LEFT JOIN materias m ON m.cod_materia=d.cod_materia LEFT JOIN vista_estudiantes ve ON ve.cod_estudiante=e.cod_estudiante';
    }
    return $plan;
};
