<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   Calcula la URL base del proyecto automáticamente
   ========================================================= */
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);   // /proyecto_vercionII/historias/listar.php
$basePath   = preg_replace('#/(historias|derivaciones|citas|estudiantes|docentes|usuarios|informes|estadisticas|includes)/.*$#i', '', $scriptPath);
$basePath   = rtrim($basePath, '/');                              // /proyecto_vercionII

$tituloPagina = $tituloPagina ?? 'Sistema Psicológico - U.E. Cañada Pailita "B"';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- CSS propio con ruta dinámica -->
    <link rel="stylesheet" href="<?= login_html(login_url('asset/css/estilos.css?v=7')) ?>">
</head>
<body>
