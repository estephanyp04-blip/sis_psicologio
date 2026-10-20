<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function login_clave_valida(mixed $clave): bool
{
    if (!is_string($clave)) {
        return false;
    }
    return mb_check_encoding($clave, 'UTF-8')
        && mb_strlen($clave, 'UTF-8') >= 8
        && strlen($clave) <= 72
        && strpos($clave, "\0") === false;
}
