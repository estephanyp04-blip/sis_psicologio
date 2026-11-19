<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Límite de confidencialidad independiente de concesiones editables en BD. */
function politica_modulo_permitido(string $modulo, int $rol): bool
{
    if ($modulo === 'Seguimientos') return false; // Catálogo histórico; consulta integrada en Estadísticas.
    if (in_array($modulo, ['Dashboard', 'Estadísticas', 'Citas', 'Historias clinicas', 'Informes'], true)) {
        return $rol === 2;
    }
    if ($modulo === 'Derivaciones') return in_array($rol, [2, 3], true);
    if ($modulo === 'Estudiantes' && in_array($rol, [3, 4], true)) return false;
    return true;
}
