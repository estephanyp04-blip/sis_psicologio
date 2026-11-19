<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/clinica_datos.php';
function estadisticas_filtros(array $entrada, ?string $hoy = null): array { return clinica_filtros($entrada); }
function estadisticas_consultar(mysqli $bd, string $sql, array $valores): array { return clinica_consultar($bd,$sql,$valores); }
function estadisticas_datos(mysqli $bd, array $filtros): array { return clinica_generales($bd,clinica_filtros($filtros)); }
function estadisticas_panel(mysqli $bd, ?string $hoy = null): array
{
    $fecha = new DateTimeImmutable($hoy ?? date('Y-m-d'));
    $hoy = $fecha->format('Y-m-d');
    $finAgenda = $fecha->modify('+6 days')->format('Y-m-d');
    $inicioSesiones = $fecha->modify('-29 days')->format('Y-m-d');
    $bd->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $resumen = estadisticas_consultar($bd, "SELECT
            (SELECT COUNT(DISTINCT h.cod_estudiante) FROM historias_clinicas h JOIN estudiantes e ON e.cod_estudiante=h.cod_estudiante
                WHERE e.estado='Activo' AND h.estado='En seguimiento' AND h.fecha_apertura<=?) AS en_seguimiento,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.cod_estudiante=e.cod_estudiante AND d.prioridad='Alta'
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS prioridad_alta,
            (SELECT COUNT(*) FROM citas WHERE estado IN ('Pendiente','Reprogramada') AND fecha BETWEEN ? AND ?) AS citas_pendientes,
            (SELECT COUNT(*) FROM historias_clinicas WHERE fecha_apertura<=?) AS sesiones,
            (SELECT COUNT(*) FROM historias_clinicas WHERE fecha_apertura BETWEEN ? AND ?) AS sesiones_recientes,
            (SELECT COUNT(*) FROM estudiantes e WHERE e.estado='Activo' AND EXISTS (
                SELECT 1 FROM derivaciones d WHERE d.cod_estudiante=e.cod_estudiante
                AND d.estado IN ('Pendiente','En seguimiento') AND d.fecha<=?)) AS por_atender",
            [$hoy,$hoy,$hoy,$finAgenda,$hoy,$inicioSesiones,$hoy,$hoy])[0];
        // Una fila por estudiante, con su derivación abierta de mayor prioridad.
        $prioritarios = estadisticas_consultar($bd, "SELECT e.cod_estudiante,e.nombres,e.apellidos,e.codigo,
            c.nombre AS curso,p.nombre AS paralelo,d.cod_derivacion,d.prioridad,h.cod_historia,
            (SELECT MAX(hs.fecha_apertura) FROM historias_clinicas hs WHERE hs.cod_estudiante=e.cod_estudiante AND hs.fecha_apertura<=?) AS ultima_sesion
            FROM estudiantes e
            INNER JOIN derivaciones d ON d.cod_derivacion=(
                SELECT d2.cod_derivacion FROM derivaciones d2 WHERE d2.cod_estudiante=e.cod_estudiante
                AND d2.estado IN ('Pendiente','En seguimiento') AND d2.fecha<=?
                ORDER BY FIELD(d2.prioridad,'Alta','Media','Baja'),d2.fecha,d2.cod_derivacion LIMIT 1)
            LEFT JOIN historias_clinicas h ON h.cod_historia=(
                SELECT h2.cod_historia FROM historias_clinicas h2 WHERE h2.cod_estudiante=e.cod_estudiante
                AND h2.fecha_apertura<=?
                ORDER BY (h2.cod_derivacion_origen=d.cod_derivacion) DESC,h2.fecha_apertura DESC,h2.cod_historia DESC LIMIT 1)
            LEFT JOIN historial_academico i ON i.cod_historial_academico=(
                SELECT i2.cod_historial_academico FROM historial_academico i2 JOIN secciones se2 ON se2.cod_seccion=i2.cod_seccion
                WHERE i2.cod_estudiante=e.cod_estudiante
                ORDER BY se2.gestion DESC,i2.fecha_inicio DESC,i2.cod_historial_academico DESC LIMIT 1)
            LEFT JOIN secciones se ON se.cod_seccion=i.cod_seccion
            LEFT JOIN cursos c ON c.cod_curso=se.cod_curso
            LEFT JOIN paralelos p ON p.cod_paralelo=se.cod_paralelo
            WHERE e.estado='Activo'
            ORDER BY FIELD(d.prioridad,'Alta','Media','Baja'),ultima_sesion,d.fecha,e.cod_estudiante LIMIT 5",
            [$hoy,$hoy,$hoy]);
        $agenda = estadisticas_consultar($bd, "SELECT c.cod_cita,c.hora,c.estado,e.nombres,e.apellidos,
            CONCAT_WS(' ',u.nombres,u.apellidos) AS profesional
            FROM citas c JOIN estudiantes e ON e.cod_estudiante=c.cod_estudiante
            JOIN usuarios u ON u.cod_usuario=c.cod_psicologa
            WHERE c.fecha=? AND c.estado<>'Cancelada' ORDER BY c.hora,c.cod_cita", [$hoy]);
        $bd->commit();
    } catch (Throwable $error) {
        $bd->rollback();
        throw $error;
    }
    return ['resumen'=>array_map('intval',$resumen),'prioritarios'=>$prioritarios,'agenda'=>$agenda,
        'hoy'=>$hoy,'fin_agenda'=>$finAgenda,'inicio_sesiones'=>$inicioSesiones];
}
