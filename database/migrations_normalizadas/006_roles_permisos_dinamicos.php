<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $modulos = [
        ['Dashboard', '/index.php', 'bi-speedometer2', 1],
        ['Estudiantes', '/estudiantes/listar.php', 'bi-people-fill', 2],
        ['Derivaciones', '/derivaciones/listar.php', 'bi-send-fill', 3],
        ['Citas', '/citas/listar.php', 'bi-calendar2-week-fill', 4],
        ['Historias clinicas', '/historias_clinicas/listar.php', 'bi-file-earmark-medical-fill', 5],
        ['Informes', '/informes/listar.php', 'bi-file-earmark-bar-graph-fill', 6],
        ['Usuarios', '/usuarios/listar.php', 'bi-person-gear', 7],
        ['Roles y permisos', '/roles/listar.php', 'bi-shield-lock', 8],
        ['Docentes', '/docentes/listar.php', 'bi-person-badge-fill', 9],
        ['Seguimientos', '/seguimientos/ver.php', 'bi-journal-medical', 10],
        ['Estadísticas', '/estadisticas/index.php', 'bi-bar-chart-line-fill', 11],
    ];

    $existentes = [];
    $maximo = 0;
    $resultadoModulos = $bd->query('SELECT cod_modulo,nombre,ruta FROM modulos ORDER BY cod_modulo');
    while ($fila = $resultadoModulos->fetch_assoc()) {
        $existentes[$fila['nombre']] = $fila;
        $maximo = max($maximo, (int) $fila['cod_modulo']);
    }
    $idsModulo = [];
    foreach ($modulos as [$nombre, $ruta, $icono, $orden]) {
        $actual = $existentes[$nombre] ?? null;
        if (!$actual) {
            $idModulo = ++$maximo;
            $nombreSql = $bd->real_escape_string($nombre);
            $rutaSql = $bd->real_escape_string($ruta);
            $iconoSql = $bd->real_escape_string($icono);
            $plan[] = "INSERT INTO modulos (cod_modulo,nombre,ruta,icono,orden,estado) SELECT $idModulo,'$nombreSql','$rutaSql','$iconoSql',$orden,'Activo' WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre='$nombreSql')";
        } elseif ((string) $actual['ruta'] !== $ruta) {
            $rutaSql = $bd->real_escape_string($ruta);
            $id = (int) $actual['cod_modulo'];
            $plan[] = "UPDATE modulos SET ruta='$rutaSql' WHERE cod_modulo=$id";
        }
        $idsModulo[$nombre] = $actual ? (int) $actual['cod_modulo'] : $idModulo;
    }
    $permisosIniciales = [
        'Docentes' => [1 => [1, 1, 1, 1]],
        'Seguimientos' => [1 => [1, 1, 1, 1], 2 => [1, 1, 1, 0]],
        'Estadísticas' => [1 => [1, 0, 0, 0], 2 => [1, 0, 0, 0]],
    ];
    $buscarPermiso = $bd->prepare('SELECT cod_permiso FROM permisos WHERE cod_rol=? AND cod_modulo=? LIMIT 1');
    foreach ($permisosIniciales as $modulo => $roles) {
        if (!isset($idsModulo[$modulo])) continue;
        $idModulo = $idsModulo[$modulo];
        foreach ($roles as $idRol => [$ver, $crear, $editar, $eliminar]) {
            $buscarPermiso->bind_param('ii', $idRol, $idModulo);
            $buscarPermiso->execute();
            if (!$buscarPermiso->get_result()->fetch_assoc()) {
                $plan[] = "INSERT IGNORE INTO permisos (cod_rol,cod_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar) VALUES ($idRol,$idModulo,$ver,$crear,$editar,$eliminar)";
            }
        }
    }
    $buscarPermiso->close();

    $tablas = array_column($bd->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
    if (!in_array('esquema_migraciones_normalizadas', $tablas, true)) {
        $plan[] = 'CREATE TABLE IF NOT EXISTS esquema_migraciones_normalizadas (version VARCHAR(80) NOT NULL PRIMARY KEY, aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $aplicada = false;
    } else {
        $stmtVersion = $bd->prepare('SELECT version FROM esquema_migraciones_normalizadas WHERE version=? LIMIT 1');
        $version = '006_roles_permisos_dinamicos';
        $stmtVersion->bind_param('s', $version);
        $stmtVersion->execute();
        $aplicada = (bool) $stmtVersion->get_result()->fetch_assoc();
        $stmtVersion->close();
    }
    if (!$aplicada) {
        $stmtModulo = $bd->prepare('SELECT cod_modulo FROM modulos WHERE nombre=? ORDER BY cod_modulo LIMIT 1');
        $nombreDerivaciones = 'Derivaciones';
        $stmtModulo->bind_param('s', $nombreDerivaciones);
        $stmtModulo->execute();
        $idDerivaciones = (int) ($stmtModulo->get_result()->fetch_assoc()['cod_modulo'] ?? 0);
        $stmtModulo->close();
        if ($idDerivaciones > 0) {
            // La aplicación ya permitía eliminar derivaciones propias a docentes; refleja esa regla en la tabla.
            $plan[] = "UPDATE permisos SET puede_eliminar=1 WHERE cod_rol=3 AND cod_modulo=$idDerivaciones";
        }
        $versionSql = $bd->real_escape_string('006_roles_permisos_dinamicos');
        $plan[] = "INSERT IGNORE INTO esquema_migraciones_normalizadas (version) VALUES ('$versionSql')";
    }
    return $plan;
};
