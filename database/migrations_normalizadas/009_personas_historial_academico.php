<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $tablasBase = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    $vistas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetch_all(MYSQLI_NUM), 0);
    if (in_array('historial_academico', $tablasBase, true)) {
        if (in_array('personas', $tablasBase, true) || in_array('inscripciones', $tablasBase, true)) {
            throw new RuntimeException('Esquema parcial: historial_academico ya existe pero personas o inscripciones siguen siendo tablas. Revise la migración antes de continuar.');
        }
        $usuarios = array_column($bd->query('SHOW COLUMNS FROM usuarios')->fetch_all(MYSQLI_ASSOC), 'Field');
        $docentes = array_column($bd->query('SHOW COLUMNS FROM docentes')->fetch_all(MYSQLI_ASSOC), 'Field');
        foreach (['nombres', 'apellidos', 'ci', 'correo', 'telefono'] as $campo) {
            if (!in_array($campo, $usuarios, true)) throw new RuntimeException("Esquema parcial: falta usuarios.$campo.");
        }
        if (!in_array('cod_usuario', $docentes, true)) throw new RuntimeException('Esquema parcial: falta docentes.cod_usuario.');
        return [];
    }
    if (!in_array('personas', $tablasBase, true) || !in_array('inscripciones', $tablasBase, true)) {
        throw new RuntimeException('No se puede migrar: faltan las tablas personas o inscripciones.');
    }

    foreach (['usuarios', 'docentes', 'estudiantes', 'secciones'] as $requerida) {
        if (!in_array($requerida, $tablasBase, true)) {
            throw new RuntimeException("No se puede migrar: falta la tabla $requerida.");
        }
    }
    if (in_array('historial_academico', $vistas, true)) {
        throw new RuntimeException('No se puede migrar: ya existe una vista llamada historial_academico.');
    }

    $personasSinUsuario = (int) $bd->query('SELECT COUNT(*) FROM personas p
        LEFT JOIN usuarios u ON u.cod_persona=p.cod_persona WHERE u.cod_usuario IS NULL')->fetch_row()[0];
    if ($personasSinUsuario > 0) {
        throw new RuntimeException("No se puede migrar: hay $personasSinUsuario persona(s) sin cuenta de usuario; sus datos no se descartarían silenciosamente.");
    }
    $fechasDistintas = (int) $bd->query('SELECT COUNT(*) FROM usuarios u
        INNER JOIN personas p ON p.cod_persona=u.cod_persona
        WHERE u.fecha_registro <> p.fecha_registro')->fetch_row()[0];
    if ($fechasDistintas > 0) {
        throw new RuntimeException("No se puede migrar: hay $fechasDistintas cuenta(s) cuya fecha_registro difiere de la persona vinculada. Revise esos datos antes de quitar personas.");
    }
    $sinUsuario = (int) $bd->query('SELECT COUNT(*) FROM docentes d
        LEFT JOIN usuarios u ON u.cod_persona=d.cod_persona WHERE u.cod_usuario IS NULL')->fetch_row()[0];
    if ($sinUsuario > 0) {
        throw new RuntimeException("No se puede migrar: hay $sinUsuario docente(s) sin cuenta de usuario vinculada.");
    }
    $activosDuplicados = (int) $bd->query("SELECT COUNT(*) FROM (
        SELECT cod_estudiante FROM inscripciones WHERE estado='Activo'
        GROUP BY cod_estudiante HAVING COUNT(*) > 1
    ) activos")->fetch_row()[0];
    if ($activosDuplicados > 0) {
        throw new RuntimeException("No se puede migrar: hay $activosDuplicados estudiante(s) con más de una inscripción activa.");
    }
    $inscripcionesInvalidas = (int) $bd->query('SELECT COUNT(*) FROM inscripciones i
        LEFT JOIN estudiantes e ON e.cod_estudiante=i.cod_estudiante
        LEFT JOIN secciones s ON s.cod_seccion=i.cod_seccion
        WHERE e.cod_estudiante IS NULL OR s.cod_seccion IS NULL')->fetch_row()[0];
    if ($inscripcionesInvalidas > 0) {
        throw new RuntimeException("No se puede migrar: hay $inscripcionesInvalidas inscripción(es) sin estudiante o sección válida.");
    }

    return [
        'DROP VIEW IF EXISTS vista_derivaciones',
        'DROP VIEW IF EXISTS vista_citas',
        'DROP VIEW IF EXISTS vista_estudiantes',
        'ALTER TABLE usuarios ADD COLUMN nombres VARCHAR(80) NOT NULL DEFAULT \'\' AFTER cod_persona, ADD COLUMN apellidos VARCHAR(80) NOT NULL DEFAULT \'\' AFTER nombres, ADD COLUMN ci VARCHAR(20) NULL AFTER apellidos, ADD COLUMN correo VARCHAR(120) NULL AFTER ci, ADD COLUMN telefono VARCHAR(25) NULL AFTER correo',
        'UPDATE usuarios u INNER JOIN personas p ON p.cod_persona=u.cod_persona SET u.nombres=p.nombres,u.apellidos=p.apellidos,u.ci=p.ci,u.correo=p.correo,u.telefono=p.telefono',
        'ALTER TABLE docentes ADD COLUMN cod_usuario INT(10) UNSIGNED NULL AFTER cod_docente',
        'UPDATE docentes d INNER JOIN usuarios u ON u.cod_persona=d.cod_persona SET d.cod_usuario=u.cod_usuario',
        'ALTER TABLE docentes MODIFY cod_usuario INT(10) UNSIGNED NOT NULL',
        'ALTER TABLE docentes DROP FOREIGN KEY fk_docentes_personas',
        'ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_personas',
        'ALTER TABLE docentes DROP INDEX uk_docentes_persona, DROP COLUMN cod_persona, ADD UNIQUE KEY uk_docentes_usuario (cod_usuario), ADD CONSTRAINT fk_docentes_usuarios FOREIGN KEY (cod_usuario) REFERENCES usuarios (cod_usuario) ON UPDATE CASCADE',
        'ALTER TABLE usuarios DROP INDEX uk_usuarios_persona, DROP COLUMN cod_persona, ADD UNIQUE KEY uk_usuarios_ci (ci), ADD UNIQUE KEY uk_usuarios_correo (correo), ADD KEY idx_usuarios_nombre (apellidos,nombres)',
        'CREATE TABLE historial_academico (cod_historial_academico INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,cod_estudiante INT(10) UNSIGNED NOT NULL,cod_seccion INT(10) UNSIGNED NOT NULL,fecha_inicio DATE NOT NULL,fecha_fin DATE NULL,estado ENUM(\'Activo\',\'Retirado\',\'Finalizado\') NOT NULL DEFAULT \'Activo\',activo TINYINT GENERATED ALWAYS AS (CASE WHEN estado=\'Activo\' THEN 1 ELSE NULL END) STORED,PRIMARY KEY (cod_historial_academico),UNIQUE KEY uk_historial_estudiante_activo (cod_estudiante,activo),KEY idx_historial_estudiante_fecha (cod_estudiante,fecha_inicio,cod_historial_academico),KEY idx_historial_seccion_estado (cod_seccion,estado),CONSTRAINT fk_historial_estudiante FOREIGN KEY (cod_estudiante) REFERENCES estudiantes (cod_estudiante) ON UPDATE CASCADE,CONSTRAINT fk_historial_seccion FOREIGN KEY (cod_seccion) REFERENCES secciones (cod_seccion) ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'INSERT INTO historial_academico (cod_historial_academico,cod_estudiante,cod_seccion,fecha_inicio,fecha_fin,estado) SELECT cod_inscripcion,cod_estudiante,cod_seccion,fecha_inscripcion,NULL,estado FROM inscripciones',
        'ALTER TABLE inscripciones DROP FOREIGN KEY fk_inscripciones_estudiantes',
        'ALTER TABLE inscripciones DROP FOREIGN KEY fk_inscripciones_secciones',
        'DROP TABLE inscripciones',
        'DROP TABLE personas',
        'CREATE VIEW vista_citas AS SELECT c.cod_cita,c.cod_estudiante,c.cod_psicologa,c.cod_derivacion,c.fecha,c.hora,c.estado,c.observaciones,c.motivo,c.fecha_registro,c.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,CONCAT(u.nombres,\' \',u.apellidos) AS psicologa FROM citas c JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante JOIN usuarios u ON u.cod_usuario=c.cod_psicologa',
        'CREATE VIEW vista_estudiantes AS SELECT e.cod_estudiante,e.codigo,e.ci,e.rude,e.nombres,e.apellidos,e.fecha_nacimiento,e.lugar_nacimiento,e.sexo,e.direccion,e.telefono,e.estado,s.cod_seccion,c.cod_curso,c.nombre AS curso,p.cod_paralelo,p.nombre AS paralelo,s.turno,s.gestion FROM estudiantes e LEFT JOIN historial_academico ha ON ha.cod_estudiante=e.cod_estudiante AND ha.estado=\'Activo\' LEFT JOIN secciones s ON s.cod_seccion=ha.cod_seccion LEFT JOIN cursos c ON c.cod_curso=s.cod_curso LEFT JOIN paralelos p ON p.cod_paralelo=s.cod_paralelo',
        'CREATE VIEW vista_derivaciones AS SELECT d.cod_derivacion,d.fecha,d.cod_estudiante,d.cod_docente,d.cod_materia,m.nombre AS materia,d.motivo,d.observaciones,d.prioridad,d.estado,d.solicitar_cita,d.cod_psicologa_solicitada,d.fecha_registro,d.fecha_actualizacion,CONCAT(e.apellidos,\' \',e.nombres) AS estudiante,ve.curso,ve.paralelo,ve.turno,CONCAT(u.nombres,\' \',u.apellidos) AS docente FROM derivaciones d JOIN estudiantes e ON e.cod_estudiante=d.cod_estudiante JOIN docentes doc ON doc.cod_docente=d.cod_docente JOIN usuarios u ON u.cod_usuario=doc.cod_usuario LEFT JOIN materias m ON m.cod_materia=d.cod_materia LEFT JOIN vista_estudiantes ve ON ve.cod_estudiante=e.cod_estudiante',
    ];
};
