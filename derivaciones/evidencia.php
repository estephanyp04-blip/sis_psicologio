<?php
require_once __DIR__ . '/../includes/autenticacion.php';
requerir_acceso('derivaciones/evidencia.php');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/datos.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(404); exit; }

$docente = (int)($_SESSION['cod_docente'] ?? 0);
$stmt = $conexion->prepare('SELECT e.ruta,e.tipo_mime,e.nombre_original
    FROM derivacion_evidencias e
    INNER JOIN derivaciones d ON d.cod_derivacion=e.cod_derivacion
    WHERE e.cod_evidencia=? AND (? <= 0 OR (d.cod_docente=? AND (d.cod_usuario_autor IS NULL OR d.cod_usuario_autor=(SELECT cod_usuario FROM docentes WHERE cod_docente=d.cod_docente)))) LIMIT 1');
$stmt->bind_param('iii', $id, $docente, $docente);
$stmt->execute();
$evidencia = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$evidencia) { http_response_code(404); exit; }

$mime = (string)$evidencia['tipo_mime'];
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(404); exit; }
$archivo = derivacion_imagen_local((string)$evidencia['ruta']);
if ($archivo === null) { http_response_code(404); exit; }

$nombre = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '', (string)$evidencia['nombre_original']) ?: 'imagen';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($archivo));
header('Content-Disposition: inline; filename="imagen"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($archivo);
