<?php
// Regresión del guardado de informes normalizados, solo con tablas TEMPORARY.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/conexion_login.php';
require_once dirname(__DIR__) . '/informes/datos.php';

$total = 0;
$fallo = false;
function comprobar(bool $condicion, string $mensaje): void
{
    global $total;
    ++$total;
    if (!$condicion) throw new RuntimeException($mensaje);
}
function foto(mysqli $bd): array
{
    $datos = [];
    foreach (['informes', 'informe_individual', 'auditoria'] as $tabla) {
        $datos[$tabla] = $bd->query("SELECT * FROM `$tabla` ORDER BY 1")->fetch_all(MYSQLI_ASSOC);
    }
    return $datos;
}

$bd = null;
try {
    $bd = login_bd();
    $bd->query("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    // Se crean todas las tablas antes de insertar. Nunca se escriben tablas permanentes.
    $bd->query("CREATE TEMPORARY TABLE informes (
        id_informe INT UNSIGNED PRIMARY KEY, numero_ficha VARCHAR(30), id_elaborado_por INT UNSIGNED,
        titulo VARCHAR(180), fecha_inicio DATE, fecha_fin DATE, descripcion TEXT, conclusiones TEXT,
        recomendaciones TEXT, estado ENUM('Borrador','Finalizado')) ENGINE=InnoDB");
    $bd->query("CREATE TEMPORARY TABLE informe_individual (
        id_informe INT UNSIGNED PRIMARY KEY, id_estudiante INT UNSIGNED, id_historia INT UNSIGNED,
        id_derivacion INT UNSIGNED, id_seguimiento INT UNSIGNED, numero_atenciones INT UNSIGNED,
        referido_por VARCHAR(150), tipo_atencion TEXT, motivo TEXT, diagnostico TEXT,
        aspecto_cognitivo TEXT, aspectos_afectivos TEXT, diagnostico_acuerdos TEXT, recibido_por VARCHAR(150),
        CONSTRAINT fallo_detalle_prueba CHECK (numero_atenciones < 100)) ENGINE=InnoDB");
    $bd->query('CREATE TEMPORARY TABLE seguimientos (id_seguimiento INT PRIMARY KEY, fecha DATE) ENGINE=InnoDB');
    $bd->query("CREATE TEMPORARY TABLE auditoria (
        id_auditoria INT PRIMARY KEY AUTO_INCREMENT, id_usuario INT, modulo VARCHAR(50),
        accion VARCHAR(50), registro_id INT, detalle TEXT,
        CONSTRAINT fallo_auditoria_prueba CHECK (id_usuario = 102)) ENGINE=InnoDB");
    $bd->query("INSERT INTO informes (id_informe,numero_ficha,id_elaborado_por,titulo,fecha_inicio,estado)
        VALUES (1,'PRUEBA-1',101,'Original','2026-01-01','Borrador'),(2,'PRUEBA-2',101,'Otro','2026-01-01','Borrador')");
    $bd->query("INSERT INTO informe_individual (id_informe,id_estudiante,id_historia,id_derivacion,id_seguimiento,numero_atenciones,motivo)
        VALUES (1,201,301,401,501,1,'Original'),(2,202,NULL,NULL,NULL,2,'Otro')");
    $bd->query("INSERT INTO seguimientos VALUES (501,'2026-01-01')");

    $datos = informe_datos([
        'id_estudiante' => 201, 'id_historia' => 301, 'id_seguimiento' => 501,
        'titulo' => 'Título editado', 'fecha' => '2026-01-02', 'numero_atenciones' => 9,
        'tipo_atencion' => ['Evaluación', 'Orientación'], 'referido_por' => 'Referente editado',
        'motivo' => 'Motivo editado', 'diagnostico' => 'Diagnóstico editado',
        'aspecto_cognitivo' => 'Cognitivo editado', 'aspectos_afectivos' => 'Afectivos editados',
        'diagnostico_acuerdos' => 'Acuerdos editados', 'recomendaciones' => 'Recomendaciones editadas',
        'recibido_por' => 'Receptor editado', 'estado' => 'Finalizado',
    ]);
    $antes = foto($bd);
    informe_actualizar($bd, 1, $datos, 102);
    $despues = foto($bd);
    $cabecera = $despues['informes'][0];
    $detalle = $despues['informe_individual'][0];
    foreach (['titulo', 'recomendaciones', 'estado'] as $campo) {
        comprobar($cabecera[$campo] === $datos[$campo], "No se guardó $campo en la cabecera.");
    }
    comprobar($cabecera['fecha_inicio'] === $datos['fecha'] && $cabecera['fecha_fin'] === $datos['fecha'], 'No se guardó la fecha.');
    comprobar($cabecera['descripcion'] === $datos['motivo'] && $cabecera['conclusiones'] === $datos['diagnostico'], 'La cabecera no coincide con el detalle.');
    comprobar((int)$detalle['numero_atenciones'] === 9, 'No se guardó el número de atenciones.');
    comprobar($detalle['tipo_atencion'] === 'Evaluación, Orientación', 'No se guardaron los tipos de atención.');
    foreach (['referido_por', 'motivo', 'diagnostico', 'aspecto_cognitivo', 'aspectos_afectivos', 'diagnostico_acuerdos', 'recibido_por'] as $campo) {
        comprobar($detalle[$campo] === $datos[$campo], "No se guardó $campo en el detalle.");
    }
    comprobar((int)$cabecera['id_elaborado_por'] === 101 && $cabecera['numero_ficha'] === 'PRUEBA-1', 'Cambió el autor o la ficha.');
    foreach (['id_estudiante' => 201, 'id_historia' => 301, 'id_derivacion' => 401, 'id_seguimiento' => 501] as $campo => $id) {
        comprobar((int)$detalle[$campo] === $id, "Cambió la relación $campo.");
    }
    comprobar($antes['informes'][1] === $despues['informes'][1] && $antes['informe_individual'][1] === $despues['informe_individual'][1], 'Se modificó otro informe.');
    comprobar(count($despues['auditoria']) === 1 && (int)$despues['auditoria'][0]['id_usuario'] === 102
        && (int)$despues['auditoria'][0]['registro_id'] === 1, 'No se registró al editor en auditoría.');

    foreach ([['numero_atenciones' => 100], ['id_editor' => 999]] as $caso) {
        $previo = foto($bd);
        $rechazado = false;
        try {
            informe_actualizar($bd, 1, array_replace($datos, ['titulo' => 'No debe persistir', 'motivo' => 'No debe persistir'], $caso), $caso['id_editor'] ?? 102);
        } catch (mysqli_sql_exception $error) {
            // 4025 corresponde a las restricciones CHECK de estas tablas temporales.
            if ($error->getCode() !== 4025) throw $error;
            $rechazado = true;
        }
        comprobar($rechazado, 'No se detectó el fallo SQL provocado.');
        comprobar(foto($bd) === $previo, 'El fallo dejó cambios parciales en cabecera, detalle o auditoría.');
    }

    foreach (['id_estudiante' => 202, 'id_seguimiento' => 502] as $campo => $id) {
        $previo = foto($bd);
        $rechazado = false;
        try { informe_actualizar($bd, 1, array_replace($datos, [$campo => $id]), 102); }
        catch (InvalidArgumentException $error) { $rechazado = true; }
        comprobar($rechazado && foto($bd) === $previo, "Se aceptó un cambio de $campo o hubo escritura parcial.");
    }

    $vacios = array_fill_keys(['referido_por', 'diagnostico', 'diagnostico_acuerdos', 'recibido_por'], '');
    informe_actualizar($bd, 1, array_replace($datos, $vacios), 102);
    $detalle = foto($bd)['informe_individual'][0];
    foreach ($vacios as $campo => $_) comprobar($detalle[$campo] === null, "No se vació el campo opcional $campo.");
    echo "OK: $total comprobaciones de actualización de informes. Solo tablas TEMPORARY; sin escrituras en datos reales.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    $fallo = true;
} finally {
    // Cerrar la conexión elimina automáticamente todas las tablas temporales.
    if ($bd instanceof mysqli) $bd->close();
}
exit($fallo ? 1 : 0);
