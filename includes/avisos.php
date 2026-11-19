<?php
/** Puente de presentación. No autoriza acciones ni sustituye validaciones. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function ui_aviso_pendiente(): void
{
    // Algunas vistas antiguas no imprimían sus mensajes (Usuarios, Roles/editar, Informes/ver).
    if (empty($_SESSION['mensaje'])) return;
    $tipo = $_SESSION['tipo_mensaje'] ?? 'info';
    $tipo = in_array($tipo, ['success', 'danger', 'warning', 'info'], true) ? $tipo : 'info';
    echo '<div data-ui-aviso class="ui-aviso-pendiente alert alert-' . $tipo . '" role="status">'
        . nl2br(login_html($_SESSION['mensaje'])) . '</div>';
    unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
}

/** Solo entradas administrativas permitidas, una vez, 15 minutos y ligadas al registro. */
function ui_recordar_entrada(string $clave, array $campos, int $id = 0): void
{
    $datos = [];
    foreach ($campos as $campo) {
        if (in_array($campo, ['csrf', 'password', 'confirmar_password', 'clave', 'token'], true)) continue;
        if (is_string($_POST[$campo] ?? null)) $datos[$campo] = substr($_POST[$campo], 0, 1000);
    }
    // Permisos son casillas; no se aceptan valores ni claves arbitrarias.
    if (in_array('permisos', $campos, true)) {
        $datos['permisos'] = [];
        $permisos = is_array($_POST['permisos'] ?? null) ? $_POST['permisos'] : [];
        foreach (array_slice($permisos, 0, 100, true) as $modulo => $acciones) {
            if (!ctype_digit((string)$modulo) || !is_array($acciones)) continue;
            foreach (['puede_ver', 'puede_crear', 'puede_editar', 'puede_eliminar', 'puede_cambiar_estado'] as $accion) {
                if (isset($acciones[$accion])) $datos['permisos'][(int)$modulo][$accion] = '1';
            }
        }
    }
    $_SESSION['ui_entrada'][$clave] = ['id' => $id, 'hasta' => time() + 900, 'datos' => $datos];
}

function ui_entrada_recuperar(string $clave, int $id = 0): ?array
{
    $entrada = $_SESSION['ui_entrada'][$clave] ?? null;
    unset($_SESSION['ui_entrada'][$clave]);
    if (!is_array($entrada) || $entrada['id'] !== $id || $entrada['hasta'] < time()) return null;
    return $entrada['datos'];
}

function ui_entrada_emitir(?array $datos, ?string $formulario = null): void
{
    if ($datos === null) return;
    echo '<script type="application/json" data-ui-entrada' . ($formulario !== null ? ' data-ui-formulario="' . login_html($formulario) . '"' : '') . '>' . json_encode($datos,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . '</script>';
}
