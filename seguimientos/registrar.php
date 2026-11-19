<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/registrar.php');
require_once __DIR__ . '/../includes/trazabilidad_datos.php';
try { $id = flujo_id($_GET['cod_historia'] ?? 0, true); }
catch (InvalidArgumentException $e) { http_response_code(400); exit(login_html($e->getMessage())); }
login_ir($id ? 'historias_clinicas/editar.php?id=' . $id : 'historias_clinicas/registrar.php');
