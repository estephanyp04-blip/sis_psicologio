<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Huella por valores y orden de columnas: el renombrado de identificadores no altera el contenido. */
function retiro_huella(mysqli $bd, string $tabla, string $id): array
{
    $hash = hash_init('sha256'); $n = 0;
    $r = $bd->query("SELECT * FROM `$tabla` ORDER BY `$id`", MYSQLI_USE_RESULT);
    while ($fila = $r->fetch_row()) {
        hash_update($hash, json_encode($fila, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n"); ++$n;
    }
    $r->free();
    return ['filas'=>$n, 'sha256'=>hash_final($hash)];
}

/** Comprueba también claves compuestas, sin desactivar FOREIGN_KEY_CHECKS. */
function retiro_verificar_integridad(mysqli $bd): int
{
    $grupos=[];
    $r=$bd->query("SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE()
        ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION");
    foreach($r->fetch_all(MYSQLI_ASSOC) as $fk)$grupos[$fk['TABLE_NAME'].'/'.$fk['CONSTRAINT_NAME']][]=$fk;
    $q=static fn(string $s):string=>'`'.str_replace('`','``',$s).'`';
    foreach($grupos as $columnas){
        $primera=$columnas[0];$join=[];$noNulos=[];
        foreach($columnas as $fk){$join[]='h.'.$q($fk['COLUMN_NAME']).'=p.'.$q($fk['REFERENCED_COLUMN_NAME']);$noNulos[]='h.'.$q($fk['COLUMN_NAME']).' IS NOT NULL';}
        $sql='SELECT COUNT(*) FROM '.$q($primera['TABLE_NAME']).' h LEFT JOIN '.$q($primera['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ',$join)
            .' WHERE '.implode(' AND ',$noNulos).' AND p.'.$q($primera['REFERENCED_COLUMN_NAME']).' IS NULL';
        if((int)$bd->query($sql)->fetch_row()[0])throw new RuntimeException('Se encontraron relaciones huérfanas; no continuar la migración.');
    }
    return count($grupos);
}

function retiro_verificar_respaldo(array $opciones, string $base): void
{
    require_once dirname(__DIR__) . '/herramientas/respaldo_lib.php';
    $copia = respaldo_fuera_web($opciones['respaldo'] ?? '');
    $clave = respaldo_clave($opciones['clave'] ?? '', $copia);
    try {
        $f = fopen('php://memory', 'w+');
        try {
            respaldo_descifrar($copia.'/manifest.enc', $f, $clave); rewind($f);
            $manifest = json_decode(stream_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
        } finally { fclose($f); }
        $fecha = strtotime($manifest['fecha_utc'] ?? '');
        if (($manifest['base'] ?? '') !== $base || !$fecha || $fecha < time()-86400 || $fecha > time()+60
            || !isset($manifest['archivos']['base.enc'])
            || !hash_equals($manifest['archivos']['base.enc'], hash_file('sha256', $copia.'/base.enc'))) {
            throw new RuntimeException('El respaldo debe ser íntegro, de esta base y de las últimas 24 horas.');
        }
        $nulo = fopen(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'wb');
        try { respaldo_descifrar($copia.'/base.enc', $nulo, $clave); } finally { fclose($nulo); }
    } finally { sodium_memzero($clave); }
}
