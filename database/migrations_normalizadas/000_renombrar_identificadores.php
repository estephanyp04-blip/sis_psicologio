<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    $citar = static fn(string $nombre): string => '`' . str_replace('`', '``', $nombre) . '`';
    $tablas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    $campos = [];
    $renombres = [];

    foreach ($tablas as $tabla) {
        $columnas = $bd->query('SHOW FULL COLUMNS FROM ' . $citar($tabla))->fetch_all(MYSQLI_ASSOC);
        $existentes = array_column($columnas, 'Field');
        foreach ($columnas as $columna) {
            $anterior = (string)$columna['Field'];
            if (!str_starts_with($anterior, 'id_')) continue;
            $nuevo = 'cod_' . substr($anterior, 3);
            if (in_array($nuevo, $existentes, true)) {
                throw new RuntimeException("No se puede renombrar $tabla.$anterior: ya existe $tabla.$nuevo.");
            }
            $campos[$tabla][$anterior] = $columna;
            $renombres[$tabla][$anterior] = $nuevo;
        }
    }

    if (!$renombres) return [];

    $filasFk = $bd->query("SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, k.ORDINAL_POSITION,
            r.UPDATE_RULE, r.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE k
        INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
            AND r.TABLE_NAME = k.TABLE_NAME
            AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
        WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION")->fetch_all(MYSQLI_ASSOC);
    $clavesFk = [];
    foreach ($filasFk as $fila) {
        $clave = $fila['TABLE_NAME'] . "\0" . $fila['CONSTRAINT_NAME'];
        if (!isset($clavesFk[$clave])) {
            $clavesFk[$clave] = ['tabla' => $fila['TABLE_NAME'], 'nombre' => $fila['CONSTRAINT_NAME'], 'filas' => [], 'renombrar' => false];
        }
        $clavesFk[$clave]['filas'][] = $fila;
        if (isset($renombres[$fila['TABLE_NAME']][$fila['COLUMN_NAME']])
            || isset($renombres[$fila['REFERENCED_TABLE_NAME']][$fila['REFERENCED_COLUMN_NAME']])) {
            $clavesFk[$clave]['renombrar'] = true;
        }
    }

    $sql = [];
    foreach ($clavesFk as $fk) {
        if ($fk['renombrar']) {
            $sql[] = 'ALTER TABLE ' . $citar($fk['tabla']) . ' DROP FOREIGN KEY ' . $citar($fk['nombre']);
        }
    }

    foreach ($renombres as $tabla => $columnas) {
        foreach ($columnas as $anterior => $nuevo) {
            $columna = $campos[$tabla][$anterior];
            $definicion = $columna['Type'] . ($columna['Null'] === 'YES' ? ' NULL' : ' NOT NULL');
            if ($columna['Default'] === null) {
                if ($columna['Null'] === 'YES') $definicion .= ' DEFAULT NULL';
            } else {
                $tipo = strtolower((string)$columna['Type']);
                $valor = (string)$columna['Default'];
                $esNumerico = preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|bit)/', $tipo) === 1;
                $default = $esNumerico && is_numeric($valor) ? $valor : "'" . $bd->real_escape_string($valor) . "'";
                $definicion .= ' DEFAULT ' . $default;
            }
            if ($columna['Extra'] !== '') $definicion .= ' ' . $columna['Extra'];
            if ($columna['Comment'] !== '') $definicion .= " COMMENT '" . $bd->real_escape_string($columna['Comment']) . "'";
            $sql[] = 'ALTER TABLE ' . $citar($tabla) . ' CHANGE COLUMN ' . $citar($anterior) . ' '
                . $citar($nuevo) . ' ' . $definicion;
        }
    }

    foreach ($clavesFk as $fk) {
        if (!$fk['renombrar']) continue;
        $filas = $fk['filas'];
        $primera = $filas[0];
        $columnasHijas = [];
        $columnasPadre = [];
        foreach ($filas as $fila) {
            $columnaHija = $renombres[$fila['TABLE_NAME']][$fila['COLUMN_NAME']] ?? $fila['COLUMN_NAME'];
            $columnaPadre = $renombres[$fila['REFERENCED_TABLE_NAME']][$fila['REFERENCED_COLUMN_NAME']] ?? $fila['REFERENCED_COLUMN_NAME'];
            $columnasHijas[] = $citar($columnaHija);
            $columnasPadre[] = $citar($columnaPadre);
        }
        $reglasValidas = ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'];
        $onUpdate = in_array($primera['UPDATE_RULE'], $reglasValidas, true) ? $primera['UPDATE_RULE'] : 'RESTRICT';
        $onDelete = in_array($primera['DELETE_RULE'], $reglasValidas, true) ? $primera['DELETE_RULE'] : 'RESTRICT';
        $sql[] = 'ALTER TABLE ' . $citar($fk['tabla']) . ' ADD CONSTRAINT ' . $citar($fk['nombre'])
            . ' FOREIGN KEY (' . implode(',', $columnasHijas) . ') REFERENCES '
            . $citar($primera['REFERENCED_TABLE_NAME']) . ' (' . implode(',', $columnasPadre) . ')'
            . ' ON UPDATE ' . $onUpdate . ' ON DELETE ' . $onDelete;
    }

    $vistas = array_column($bd->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetch_all(MYSQLI_NUM), 0);
    foreach ($vistas as $vista) {
        $resultado = $bd->query('SHOW CREATE VIEW ' . $citar($vista))->fetch_assoc();
        $crear = $resultado['Create View'] ?? null;
        if (!is_string($crear) || !preg_match('/\bid_[A-Za-z0-9_]*\b/', $crear)) continue;
        $crear = preg_replace_callback('/\bid_([A-Za-z0-9_]*)\b/', static fn($m) => 'cod_' . $m[1], $crear);
        $crear = preg_replace('/^CREATE\s+/i', 'CREATE OR REPLACE ', $crear, 1);
        $sql[] = rtrim($crear, "; \t\r\n");
    }

    return $sql;
};
