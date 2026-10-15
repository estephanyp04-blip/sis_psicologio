<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $plan = [];
    $grupos = [
        'conductas_riesgo' => [
            'nombre' => 'Conductas de riesgo',
            'opciones' => [
                'Conducta delictiva',
                'Pandillaje',
                'Sospecha de consumo de alcohol/drogas',
                'Problema sexual',
            ],
        ],
        'adaptacion_normas' => [
            'nombre' => 'Adaptación a normas',
            'opciones' => [
                'No obedece el reglamento del Colegio',
                'Le es difícil seguir indicaciones - protesta',
                'No cumple normas establecidas',
                'Propicia el desorden',
                'Miente',
                'Agrede de manera verbal',
                'Agrede de manera física',
                'Llama por apodos / Bullying',
                'Sus compañeros lo rechazan',
            ],
        ],
        'atencion_distraccion' => [
            'opciones' => [
                'Se equivoca por descuido',
                'No termina de hacer tareas, se atrasa',
                'Lenguaje inapropiado / conducta obscena',
            ],
        ],
        'dificultades_socioemocionales' => [
            'opciones' => [
                'Se le observa triste, deprimido, desganado',
                'Cambia de humor frecuentemente',
                'Prefiere estar solo(a)',
                'Se calla y aguanta si lo molestan',
                'Tartamudea / habla nerviosamente',
                'Se muerde / come las uñas',
                'Duda al expresar su opinión',
                'Se mantiene callado',
            ],
        ],
        'estrategias_previas' => [
            'opciones' => [
                'Conversación con él/ella',
                'Llamadas de atención',
                'Premios y sanciones',
            ],
        ],
    ];

    $buscarGrupo = $bd->prepare('SELECT cod_grupo FROM grupos_opciones_historia WHERE codigo=? LIMIT 1');
    $buscarOpcion = $bd->prepare('SELECT o.cod_opcion FROM opciones_historia o
        INNER JOIN grupos_opciones_historia g ON g.cod_grupo=o.cod_grupo
        WHERE g.codigo=? AND o.descripcion=? LIMIT 1');

    foreach ($grupos as $codigo => $datos) {
        $buscarGrupo->bind_param('s', $codigo);
        $buscarGrupo->execute();
        $existeGrupo = (bool)$buscarGrupo->get_result()->fetch_assoc();
        if (!$existeGrupo && isset($datos['nombre'])) {
            $codigoSql = $bd->real_escape_string($codigo);
            $nombreSql = $bd->real_escape_string($datos['nombre']);
            $plan[] = "INSERT IGNORE INTO grupos_opciones_historia (codigo,nombre) VALUES ('$codigoSql','$nombreSql')";
        }

        foreach ($datos['opciones'] as $descripcion) {
            $buscarOpcion->bind_param('ss', $codigo, $descripcion);
            $buscarOpcion->execute();
            if ($buscarOpcion->get_result()->fetch_assoc()) continue;

            $codigoSql = $bd->real_escape_string($codigo);
            $descripcionSql = $bd->real_escape_string($descripcion);
            $plan[] = "INSERT IGNORE INTO opciones_historia (cod_grupo,descripcion,estado) "
                . "SELECT cod_grupo,'$descripcionSql','Activo' FROM grupos_opciones_historia WHERE codigo='$codigoSql'";
        }
    }

    $buscarGrupo->close();
    $buscarOpcion->close();
    return $plan;
};
