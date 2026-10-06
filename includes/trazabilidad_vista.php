<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/trazabilidad_datos.php';

/** Solo personal clínico: docentes y director no reciben datos ni enlaces del expediente. */
function flujo_panel(mysqli $bd, string $tipo, array $registro): void
{
    if (!login_puede('historias_clinicas/ver.php')) return;
    $d = (int)($registro['id_derivacion'] ?? $registro['id_derivacion_origen'] ?? 0);
    $h = (int)($registro['id_historia'] ?? 0);
    $c = (int)($registro['id_cita'] ?? $registro['id_cita_origen'] ?? 0);
    $s = (int)($registro['id_seguimiento'] ?? 0);
    $enlaces = [];
    $agregar = static function (string $ruta, string $titulo, int $id) use (&$enlaces): void {
        if ($id) $enlaces[$ruta . '?id=' . $id] = $titulo . ' #' . $id;
    };
    if ($tipo === 'informe' && $s) {
        $seguimiento = flujo_fila($bd,'SELECT id_cita FROM seguimientos WHERE id_seguimiento=?',[$s]);
        $c = (int)($seguimiento['id_cita'] ?? 0);
    }
    if ($tipo === 'cita') {
        $historia = flujo_fila($bd,'SELECT id_historia FROM historias_clinicas WHERE id_estudiante=?',[$registro['id_estudiante']]);
        $h = (int)($historia['id_historia'] ?? 0);
    }
    foreach ([['derivaciones/ver.php','Derivación',$d],['citas/editar.php','Cita',$c],['historias_clinicas/ver.php','Historia',$h],['seguimientos/ver.php','Seguimiento',$s]] as [$ruta,$titulo,$id]) $agregar($ruta,$titulo,$id);
    $consultas = [];
    if ($tipo === 'derivacion') {
        $consultas = [
            ['SELECT id_cita id FROM citas WHERE id_derivacion=? ORDER BY fecha,id_cita',[$d],'citas/editar.php','Cita'],
            ['SELECT id_historia id FROM historias_clinicas WHERE id_estudiante=?',[$registro['id_estudiante']],'historias_clinicas/ver.php','Historia del estudiante'],
            ['SELECT s.id_seguimiento id FROM seguimientos s JOIN historias_clinicas h ON h.id_historia=s.id_historia LEFT JOIN citas c ON c.id_cita=s.id_cita WHERE COALESCE(c.id_derivacion,h.id_derivacion_origen)=? ORDER BY s.fecha,s.id_seguimiento',[$d],'seguimientos/ver.php','Seguimiento'],
            ['SELECT i.id_informe id FROM informes i JOIN informe_individual ii ON ii.id_informe=i.id_informe WHERE ii.id_derivacion=? ORDER BY i.fecha_inicio,i.id_informe',[$d],'informes/ver.php','Informe']];
    } elseif ($tipo === 'historia') {
        $consultas = [
            ['SELECT id_seguimiento id FROM seguimientos WHERE id_historia=? ORDER BY fecha,id_seguimiento',[$h],'seguimientos/ver.php','Seguimiento'],
            ['SELECT i.id_informe id FROM informes i JOIN informe_individual ii ON ii.id_informe=i.id_informe WHERE ii.id_historia=? ORDER BY i.fecha_inicio,i.id_informe',[$h],'informes/ver.php','Informe']];
    } elseif ($tipo === 'cita') {
        $consultas = [['SELECT id_seguimiento id FROM seguimientos WHERE id_cita=? ORDER BY fecha,id_seguimiento',[$c],'seguimientos/ver.php','Seguimiento']];
    } elseif ($tipo === 'seguimiento') {
        $consultas = [['SELECT i.id_informe id FROM informes i JOIN informe_individual ii ON ii.id_informe=i.id_informe WHERE ii.id_seguimiento=? ORDER BY i.fecha_inicio,i.id_informe',[$s],'informes/ver.php','Informe']];
    }
    foreach ($consultas as [$sql,$valores,$ruta,$titulo]) {
        $stmt=$bd->prepare($sql); $stmt->bind_param('i',...$valores); $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) $agregar($ruta,$titulo,(int)$fila['id']);
        $stmt->close();
    }
    echo '<section class="card p-3 my-3 d-print-none"><h2 class="h5">Trazabilidad de la atención</h2><div class="d-flex flex-wrap gap-2">';
    foreach ($enlaces as $ruta=>$titulo) echo '<a class="btn btn-outline-secondary btn-sm" href="../' . login_html($ruta) . '">' . login_html($titulo) . '</a>';
    if (!$enlaces) echo '<span>Sin registros de origen vinculados.</span>';
    echo '</div></section>';
}
