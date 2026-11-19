<?php
/** Entrada compatible: la definición vigente se valida en el flujo clínico completo. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/seguimiento_clinico.php';
