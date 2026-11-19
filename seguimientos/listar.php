<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/listar.php');
require_once __DIR__ . '/../estadisticas/clinica_datos.php';
try { $f = clinica_filtros($_GET); }
catch (InvalidArgumentException $e) { http_response_code(400); exit(login_html($e->getMessage())); }
login_ir('estadisticas/individual.php?' . http_build_query($f));
