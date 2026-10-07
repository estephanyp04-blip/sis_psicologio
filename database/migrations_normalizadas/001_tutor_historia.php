<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return static function (mysqli $bd): array {
    if ($bd->query("SHOW COLUMNS FROM historias_clinicas LIKE 'tutor_curso'")->num_rows) return [];
    return ['ALTER TABLE historias_clinicas ADD COLUMN tutor_curso VARCHAR(150) NULL AFTER contexto_familiar'];
};
