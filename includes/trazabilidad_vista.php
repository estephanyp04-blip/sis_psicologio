<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/trazabilidad_datos.php';

/** Solo personal clínico: docentes y director no reciben datos ni enlaces del expediente. */
function flujo_panel(mysqli $bd, string $tipo, array $registro): void
{
    if (!login_puede('historias_clinicas/ver.php')) return;
    $d = (int)($registro['cod_derivacion'] ?? $registro['cod_derivacion_origen'] ?? 0);
    $h = (int)($registro['cod_historia'] ?? 0);
    $c = (int)($registro['cod_cita'] ?? $registro['cod_cita_origen'] ?? 0);
    $enlaces = [];
    $agregar = static function (string $ruta, string $titulo, int $id) use (&$enlaces): void {
        if ($id) $enlaces[$ruta . '?id=' . $id] = $titulo . ' #' . $id;
    };
    if ($tipo === 'informe') {
        $anexo = (int)($registro['cod_anexo_historico'] ?? 0);
        $origen = $anexo
            ? flujo_fila($bd,'SELECT cod_cita FROM historia_anexos WHERE cod_anexo=?',[$anexo])
            : ($h ? flujo_fila($bd,'SELECT cod_cita_origen AS cod_cita FROM historias_clinicas WHERE cod_historia=?',[$h]) : null);
        $c = (int)($origen['cod_cita'] ?? $c);
    }
    if ($tipo === 'cita') {
        foreach (flujo_historias_cita($bd, $registro) as $historia) {
            $agregar('historias_clinicas/ver.php', 'Historia', (int)$historia['cod_historia']);
        }
    }
    foreach ([['derivaciones/ver.php','Derivación',$d],['citas/editar.php','Cita',$c],['historias_clinicas/ver.php','Historia',$h]] as [$ruta,$titulo,$id]) $agregar($ruta,$titulo,$id);
    $consultas = [];
    if ($tipo === 'derivacion') {
        $consultas = [
            ['SELECT cod_cita id FROM citas WHERE cod_derivacion=? ORDER BY fecha,cod_cita',[$d],'citas/editar.php','Cita'],
            ['SELECT h.cod_historia id FROM historias_clinicas h WHERE h.cod_derivacion_origen=?
                OR EXISTS (SELECT 1 FROM historia_anexos s LEFT JOIN citas c ON c.cod_cita=s.cod_cita
                    WHERE s.cod_historia=h.cod_historia AND COALESCE(s.cod_derivacion,c.cod_derivacion)=?)
                ORDER BY h.fecha_apertura,h.cod_historia',[$d,$d],'historias_clinicas/ver.php','Historia de esta derivación'],
            ['SELECT i.cod_informe id FROM informes i JOIN informe_individual ii ON ii.cod_informe=i.cod_informe WHERE ii.cod_derivacion=? ORDER BY i.fecha_inicio,i.cod_informe',[$d],'informes/ver.php','Informe']];
    } elseif ($tipo === 'historia') {
        $consultas = [
            ['SELECT i.cod_informe id FROM informes i JOIN informe_individual ii ON ii.cod_informe=i.cod_informe WHERE ii.cod_historia=? ORDER BY i.fecha_inicio,i.cod_informe',[$h],'informes/ver.php','Informe']];
    }
    foreach ($consultas as [$sql,$valores,$ruta,$titulo]) {
        $stmt=$bd->prepare($sql); $stmt->bind_param(str_repeat('i', count($valores)),...$valores); $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) $agregar($ruta,$titulo,(int)$fila['id']);
        $stmt->close();
    }
    echo '<section class="card p-3 my-3 d-print-none"><h2 class="h5">Trazabilidad de la atención</h2><div class="d-flex flex-wrap gap-2">';
    foreach ($enlaces as $ruta=>$titulo) echo '<a class="btn btn-outline-secondary btn-sm" href="../' . login_html($ruta) . '">' . login_html($titulo) . '</a>';
    if (!$enlaces) echo '<span>Sin registros de origen vinculados.</span>';
    echo '</div></section>';
}
