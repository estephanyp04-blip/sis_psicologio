<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
return static function (mysqli $bd): array {
    $plan = [];
    foreach (['usuarios','roles'] as $tabla) {
        $campos = array_column($bd->query("SHOW COLUMNS FROM `$tabla`")->fetch_all(MYSQLI_ASSOC), 'Field');
        if (!in_array('sesion_version', $campos, true)) $plan[] = "ALTER TABLE `$tabla` ADD sesion_version INT UNSIGNED NOT NULL DEFAULT 0";
    }
    $columnas = array_column($bd->query('SHOW COLUMNS FROM auditoria')->fetch_all(MYSQLI_ASSOC), 'Field');
    foreach (['actor_codigo' => 'INT UNSIGNED NULL', 'actor_usuario' => 'VARCHAR(40) NULL',
        'actor_rol' => 'INT UNSIGNED NULL', 'resultado' => 'VARCHAR(16) NULL DEFAULT NULL'] as $campo => $tipo) {
        if (!in_array($campo, $columnas, true)) $plan[] = "ALTER TABLE auditoria ADD `$campo` $tipo";
    }
    $plan[] = "CREATE TABLE IF NOT EXISTS historia_versiones (
        cod_version BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        cod_historia INT UNSIGNED NOT NULL,
        cod_editor INT UNSIGNED NOT NULL,
        editor_usuario VARCHAR(40) NULL,
        fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        contenido_anterior LONGTEXT NOT NULL CHECK (JSON_VALID(contenido_anterior)),
        KEY idx_version_historia (cod_historia,cod_version),
        CONSTRAINT fk_version_historia FOREIGN KEY (cod_historia) REFERENCES historias_clinicas(cod_historia) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $triggers = array_column($bd->query('SHOW TRIGGERS')->fetch_all(MYSQLI_ASSOC), 'Trigger');
    $definiciones = [
        'usuarios_revocar_sesiones' => "BEFORE UPDATE ON usuarios FOR EACH ROW SET NEW.sesion_version = OLD.sesion_version +
            IF(NOT (BINARY NEW.password <=> BINARY OLD.password) OR NOT (NEW.cod_rol <=> OLD.cod_rol) OR NOT (NEW.estado <=> OLD.estado),1,0)",
        'roles_revocar_sesiones' => "BEFORE UPDATE ON roles FOR EACH ROW SET NEW.sesion_version = OLD.sesion_version + IF(NOT (NEW.estado <=> OLD.estado),1,0)",
        'auditoria_identidad' => "BEFORE INSERT ON auditoria FOR EACH ROW BEGIN
            SET NEW.actor_codigo = NEW.cod_usuario;
            SET NEW.actor_usuario = (SELECT usuario FROM usuarios WHERE cod_usuario=NEW.cod_usuario);
            SET NEW.actor_rol = (SELECT cod_rol FROM usuarios WHERE cod_usuario=NEW.cod_usuario);
            SET NEW.resultado = CASE WHEN NEW.accion IN ('Denegado','Fallo') THEN 'denegado' ELSE 'exito' END;
        END",
        'version_identidad' => "BEFORE INSERT ON historia_versiones FOR EACH ROW
            SET NEW.editor_usuario = (SELECT usuario FROM usuarios WHERE cod_usuario=NEW.cod_editor)",
    ];
    foreach (['auditoria', 'historia_versiones'] as $tabla) {
        foreach (['UPDATE', 'DELETE'] as $operacion) {
            $definiciones[$tabla . '_' . strtolower($operacion) . '_prohibido'] =
                "BEFORE $operacion ON $tabla FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro protegido contra modificación'";
        }
    }
    foreach ($definiciones as $nombre => $sql) if (!in_array($nombre, $triggers, true)) $plan[] = "CREATE TRIGGER `$nombre` $sql";
    $registrada = $bd->query("SELECT version FROM esquema_migraciones_normalizadas WHERE version='016_auditoria_versiones'")->num_rows;
    if (!$registrada) $plan[] = "INSERT INTO esquema_migraciones_normalizadas(version) VALUES ('016_auditoria_versiones')";
    // CREATE TABLE IF NOT EXISTS solo se emite si falta; --comprobar queda vacío tras aplicar.
    $existe = $bd->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='historia_versiones'")->num_rows;
    if ($existe) $plan = array_values(array_filter($plan, static fn(string $sql): bool => !str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS')));
    return $plan;
};
