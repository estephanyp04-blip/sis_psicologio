<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tituloPagina = $tituloPagina ?? 'Sistema Psicológico - U.E. Cañada Pailita "B"';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- DataTables con Bootstrap -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- CSS general del sistema -->
    <link rel="stylesheet" href="/proyecto_vercionII/asset/css/estilos.css?v=3">
</head>
<body>