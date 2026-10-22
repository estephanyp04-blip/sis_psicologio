<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $tablas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('historial_academico', $tablas, true)) return [];
    if (in_array('personas', $tablas, true) || in_array('inscripciones', $tablas, true)) {
        throw new RuntimeException('Quedaron tablas del esquema anterior. Aplique y revise la migración de historial académico.');
    }

    $vistas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetch_all(MYSQLI_NUM), 0);
    $usuarios = array_column($bd->query('SHOW COLUMNS FROM usuarios')->fetch_all(MYSQLI_ASSOC), 'Field');
    $docentes = array_column($bd->query('SHOW COLUMNS FROM docentes')->fetch_all(MYSQLI_ASSOC), 'Field');
    $codPersona = in_array('cod_persona', $usuarios, true) || in_array('cod_persona', $docentes, true);
    $compatibilidad = in_array('personas', $vistas, true) || in_array('inscripciones', $vistas, true) || $codPersona;
    foreach (['vista_citas', 'vista_derivaciones', 'vista_estudiantes'] as $nombre) {
        if (!in_array($nombre, $vistas, true)) continue;
        $fila = $bd->query('SHOW CREATE VIEW `' . $nombre . '`')->fetch_assoc();
        $definicion = (string)($fila['Create View'] ?? '');
        if (preg_match('/\b(personas|inscripciones|cod_persona)\b/i', $definicion)) $compatibilidad = true;
    }
    if (!$compatibilidad) return [];

    $plan = [
        'DROP VIEW IF EXISTS vista_derivaciones',
        'DROP VIEW IF EXISTS vista_citas',
        'DROP VIEW IF EXISTS vista_estudiantes',
        'DROP VIEW IF EXISTS personas',
        'DROP VIEW IF EXISTS inscripciones',
    ];
    if (in_array('cod_persona', $usuarios, true)) $plan[] = 'ALTER TABLE usuarios DROP COLUMN cod_persona';
    if (in_array('cod_persona', $docentes, true)) $plan[] = 'ALTER TABLE docentes DROP COLUMN cod_persona';
    $plan[] = 'CREATE VIEW vista_citas AS SELECT c.cod_cita,c.cod_estudiante,c.cod_psicologa,c.cod_derivacion,c.fecha,c.hora,c.estado,c.observaciones,c.motivo,c.fecha_registro,c.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,CONCAT(u.nombres,\' \',u.apellidos) AS psicologa FROM citas c JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante JOIN usuarios u ON u.cod_usuario=c.cod_psicologa';
    $plan[] = 'CREATE VIEW vista_estudiantes AS SELECT e.cod_estudiante,e.codigo,e.ci,e.rude,e.nombres,e.apellidos,e.fecha_nacimiento,e.lugar_nacimiento,e.sexo,e.direccion,e.telefono,e.estado,s.cod_seccion,c.cod_curso,c.nombre AS curso,p.cod_paralelo,p.nombre AS paralelo,s.turno,s.gestion,i.cod_institucion,i.nombre AS institucion FROM estudiantes e LEFT JOIN historial_academico ha ON ha.cod_estudiante=e.cod_estudiante AND ha.estado=\'Activo\' LEFT JOIN secciones s ON s.cod_seccion=ha.cod_seccion LEFT JOIN cursos c ON c.cod_curso=s.cod_curso LEFT JOIN paralelos p ON p.cod_paralelo=s.cod_paralelo LEFT JOIN instituciones i ON i.cod_institucion=s.cod_institucion';
    $plan[] = 'CREATE VIEW vista_derivaciones AS SELECT d.cod_derivacion,d.fecha,d.cod_estudiante,d.cod_docente,d.cod_materia,m.nombre AS materia,d.motivo,d.observaciones,d.prioridad,d.estado,d.solicitar_cita,d.cod_psicologa_solicitada,d.fecha_registro,d.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,ve.curso,ve.paralelo,ve.turno,CONCAT(u.apellidos,\' \',u.nombres) AS docente FROM derivaciones d JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=doc.cod_usuario LEFT JOIN materias m ON m.cod_materia=d.cod_materia LEFT JOIN vista_estudiantes ve ON ve.cod_estudiante=e.cod_estudiante';
    return $plan;
};
