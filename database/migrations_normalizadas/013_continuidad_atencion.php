<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
return static function (mysqli $bd): array {
    $plan = [];
    $aplicada = $bd->query("SELECT version FROM esquema_migraciones_normalizadas WHERE version='013_continuidad_atencion'")->num_rows > 0;
    $campos = static fn(string $t): array => array_column($bd->query("SHOW COLUMNS FROM `$t`")->fetch_all(MYSQLI_ASSOC), 'Field');
    $d = $campos('derivaciones');
    if (!in_array('cod_usuario_autor', $d, true)) {
        $plan[] = 'ALTER TABLE derivaciones ADD cod_usuario_autor INT UNSIGNED NULL, ADD autor_nombres VARCHAR(80) NULL, ADD autor_apellidos VARCHAR(80) NULL, ADD CONSTRAINT fk_derivacion_autor FOREIGN KEY (cod_usuario_autor) REFERENCES usuarios(cod_usuario)';
    }
    if (!$aplicada) $plan[] = 'UPDATE derivaciones d JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=doc.cod_usuario SET d.cod_usuario_autor=u.cod_usuario,d.autor_nombres=u.nombres,d.autor_apellidos=u.apellidos,d.fecha_actualizacion=d.fecha_actualizacion WHERE d.cod_usuario_autor IS NULL';
    if (!in_array('cod_derivacion', $campos('seguimientos'), true)) {
        $plan[] = 'ALTER TABLE seguimientos ADD cod_derivacion INT UNSIGNED NULL, ADD cod_proxima_cita INT UNSIGNED NULL, ADD CONSTRAINT fk_seguimiento_episodio FOREIGN KEY(cod_derivacion) REFERENCES derivaciones(cod_derivacion), ADD CONSTRAINT fk_seguimiento_proxima FOREIGN KEY(cod_proxima_cita) REFERENCES citas(cod_cita)';
    }
    if (!$aplicada) $plan[] = 'UPDATE seguimientos s JOIN historias_clinicas h ON h.cod_historia=s.cod_historia LEFT JOIN citas c ON c.cod_cita=s.cod_cita SET s.cod_derivacion=COALESCE(c.cod_derivacion,h.cod_derivacion_origen) WHERE s.cod_derivacion IS NULL';
    if (!in_array('motivo_cierre', $campos('historias_clinicas'), true)) {
        $plan[] = 'ALTER TABLE historias_clinicas ADD motivo_cierre TEXT NULL';
    }
    if (!in_array('total_sesiones', $campos('informe_resumen'), true)) {
        $plan[] = 'ALTER TABLE informe_resumen ADD total_sesiones INT UNSIGNED NULL, ADD filtros_json TEXT NULL';
    }
    $vista=$bd->query("SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vista_derivaciones'")->fetch_assoc();
    if ($vista && !str_contains($vista['VIEW_DEFINITION']??'','autor_apellidos')) {
        $plan[] = "CREATE OR REPLACE VIEW vista_derivaciones AS SELECT d.cod_derivacion,d.fecha,d.cod_estudiante,d.cod_docente,d.cod_materia,m.nombre AS materia,d.motivo,d.observaciones,d.prioridad,d.estado,d.solicitar_cita,d.cod_psicologa_solicitada,d.fecha_registro,d.fecha_actualizacion,CONCAT(e.apellidos,' ',e.nombres) AS estudiante,ve.curso,ve.paralelo,ve.turno,CONCAT(COALESCE(d.autor_apellidos,u.apellidos),' ',COALESCE(d.autor_nombres,u.nombres)) AS docente FROM derivaciones d JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=COALESCE(d.cod_usuario_autor,doc.cod_usuario) LEFT JOIN materias m ON m.cod_materia=d.cod_materia LEFT JOIN vista_estudiantes ve ON ve.cod_estudiante=e.cod_estudiante";
    }
    if (!$aplicada) {
        $plan[] = "UPDATE permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo SET p.puede_cambiar_estado=1 WHERE m.nombre='Docentes' AND p.cod_rol=1";
        $plan[] = "INSERT IGNORE INTO esquema_migraciones_normalizadas(version) VALUES ('013_continuidad_atencion')";
    }
    return $plan;
};
