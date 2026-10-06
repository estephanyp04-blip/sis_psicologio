<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/mis_derivaciones.php');

// Compatibilidad con enlaces anteriores: usa el listado con filtro por docente.
require __DIR__ . '/listar.php';
