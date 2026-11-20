<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Retiro por transformación del archivo: RENAME conserva íntegramente filas,
 * índices, columnas adicionales y relaciones. No copia ni sintetiza historias.
 * El ejecutor exige respaldo cifrado y comprueba huellas antes/después.
 */
return static function (mysqli $bd): array {
    $tablas = array_column($bd->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
    $anterior = in_array('seguimientos', $tablas, true);
    $archivo = in_array('historia_anexos', $tablas, true);
    if ($anterior && $archivo) throw new RuntimeException('Hay dos archivos clínicos. Conciliarlos antes de migrar; no se sobrescribe ninguno.');
    if (!$anterior && !$archivo) throw new RuntimeException('Falta el archivo clínico esperado.');
    $plan = [];
    // Objetos no pertenecientes al esquema conocido exigen resolución explícita.
    foreach ([
        "SELECT VIEW_DEFINITION definicion FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()",
        "SELECT ROUTINE_DEFINITION definicion FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()",
        "SELECT ACTION_STATEMENT definicion FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()",
        "SELECT EVENT_DEFINITION definicion FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()",
    ] as $sql) foreach ($bd->query($sql)->fetch_all(MYSQLI_ASSOC) as $objeto) {
        if ($objeto['definicion'] === null) throw new RuntimeException('No se pudo inspeccionar la definición de un objeto SQL.');
        if (preg_match('/\bseguimientos\b/i', $objeto['definicion'])) throw new RuntimeException('Objeto SQL dependiente de la tabla retirada. Resolverlo antes de migrar.');
    }
    $referencias = $bd->query("SELECT TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('seguimientos','historia_anexos')")->fetch_all(MYSQLI_ASSOC);
    $base = $bd->query('SELECT DATABASE()')->fetch_row()[0];
    foreach ($referencias as $fk) {
        if ($fk['TABLE_SCHEMA'] !== $base || $fk['TABLE_NAME'] !== 'informe_individual'
            || !in_array($fk['COLUMN_NAME'], ['cod_seguimiento','cod_anexo_historico'], true)) {
            throw new RuntimeException('Clave foránea adicional dependiente del archivo clínico. Resolverla antes de migrar.');
        }
    }
    $camposArchivo = array_column($bd->query('SHOW COLUMNS FROM `' . ($anterior ? 'seguimientos' : 'historia_anexos') . '`')->fetch_all(MYSQLI_ASSOC), 'Field');
    $camposInforme = array_column($bd->query('SHOW COLUMNS FROM informe_individual')->fetch_all(MYSQLI_ASSOC), 'Field');
    $renombrarId = in_array('cod_seguimiento', $camposArchivo, true);
    $renombrarInforme = in_array('cod_seguimiento', $camposInforme, true);
    if ($renombrarId || $renombrarInforme) foreach ($referencias as $fk) {
        $nombre = str_replace('`', '``', $fk['CONSTRAINT_NAME']);
        $plan[] = "ALTER TABLE informe_individual DROP FOREIGN KEY `$nombre`";
    }
    if ($anterior) $plan[] = 'RENAME TABLE seguimientos TO historia_anexos';
    if ($renombrarId) $plan[] = 'ALTER TABLE historia_anexos CHANGE cod_seguimiento cod_anexo INT UNSIGNED NOT NULL AUTO_INCREMENT';
    if ($renombrarInforme) $plan[] = 'ALTER TABLE informe_individual CHANGE cod_seguimiento cod_anexo_historico INT UNSIGNED NULL';
    if (!$referencias || $renombrarId || $renombrarInforme) {
        $plan[] = 'ALTER TABLE informe_individual ADD CONSTRAINT fk_informe_anexo FOREIGN KEY(cod_anexo_historico) REFERENCES historia_anexos(cod_anexo) ON DELETE SET NULL ON UPDATE CASCADE';
    }
    if ($bd->query("SELECT 1 FROM modulos WHERE nombre='Seguimientos' OR ruta LIKE '%/seguimientos/%'")->num_rows) {
        if ($bd->query("SELECT 1 FROM permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo WHERE m.nombre='Seguimientos' OR m.ruta LIKE '%/seguimientos/%'")->num_rows) {
            $plan[] = "DELETE p FROM permisos p JOIN modulos m ON m.cod_modulo=p.cod_modulo WHERE m.nombre='Seguimientos' OR m.ruta LIKE '%/seguimientos/%'";
        }
        $plan[] = "DELETE FROM modulos WHERE nombre='Seguimientos' OR ruta LIKE '%/seguimientos/%'";
    }
    // Solo actualizar la descripción original conocida, preservando personalizaciones.
    if ($bd->query("SELECT 1 FROM roles WHERE descripcion='Gestiona derivaciones, citas, historias clínicas, seguimientos e informes'")->num_rows) {
        $plan[] = "UPDATE roles SET descripcion='Gestiona derivaciones, citas, historias clínicas, estadísticas e informes' WHERE descripcion='Gestiona derivaciones, citas, historias clínicas, seguimientos e informes'";
    }
    if (!$bd->query("SELECT 1 FROM esquema_migraciones_normalizadas WHERE version='017_retirar_seguimientos'")->num_rows) {
        $plan[] = "INSERT INTO esquema_migraciones_normalizadas(version) VALUES ('017_retirar_seguimientos')";
    }
    return $plan;
};
