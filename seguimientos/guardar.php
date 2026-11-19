<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('seguimientos/guardar.php');
http_response_code(410);
header('Content-Type: text/html; charset=UTF-8');
echo 'El registro independiente de seguimiento fue retirado. Registre la atención en una historia clínica. ';
echo '<a href="' . login_html(login_url('historias_clinicas/listar.php')) . '">Abrir historias clínicas</a>';
