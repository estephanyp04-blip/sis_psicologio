<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('citas/editar.php');
require_once __DIR__ . '/../config/conexion.php';
$editarCita = true;
require __DIR__ . '/../includes/cita_formulario.php';