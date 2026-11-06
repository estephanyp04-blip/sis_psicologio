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

    <link rel="icon" type="image/png" sizes="512x512" href="<?= login_html(login_url('asset/img/favicon.png?v=2')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= login_html(login_url('asset/img/apple-touch-icon.png?v=2')) ?>">
    <meta name="theme-color" content="#0f172a">

    <link rel="stylesheet" href="<?= login_html(login_url('asset/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= login_html(login_url('asset/vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= login_html(login_url('asset/vendor/datatables/dataTables.bootstrap5.min.css')) ?>">

    <!-- CSS propio con ruta dinámica -->
    <link rel="stylesheet" href="<?= login_html(login_url('asset/css/estilos.css?v=11')) ?>">
    <link rel="stylesheet" href="<?= login_html(login_url('asset/css/interfaz.css?v=1')) ?>">
</head>
<body data-modulo="<?= login_html(basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''))) ?>" data-cuenta="<?= (int)($_SESSION['cod_usuario'] ?? 0) ?>">
<a class="ui-skip" href="#contenido-principal">Saltar al contenido</a>
