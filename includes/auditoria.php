<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Solo metadatos; no acepta textos clínicos, rutas ni entradas del formulario. */
function seguridad_evento(mysqli $bd, ?int $actor, string $recurso, string $accion, ?int $id = null): void
{
    $stmt = $bd->prepare('INSERT INTO auditoria (cod_usuario,modulo,accion,registro_id) VALUES (?,?,?,?)');
    $stmt->bind_param('issi', $actor, $recurso, $accion, $id);
    $stmt->execute();
    $stmt->close();
}

/** Fallo cerrado: sin traza no se entrega el contenido clínico. */
function seguridad_consulta(mysqli $bd, string $recurso, int $id): void
{
    try {
        seguridad_evento($bd, (int)$_SESSION['cod_usuario'], $recurso, 'Consultar', $id);
    } catch (Throwable $error) {
        error_log('Auditoria de consulta no disponible; tipo=' . get_class($error) . '; codigo=' . $error->getCode());
        http_response_code(503);
        exit('No se pudo registrar el acceso. Intente nuevamente más tarde.');
    }
}
