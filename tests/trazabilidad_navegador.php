<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
?>
<!doctype html><html lang="es"><meta charset="utf-8"><title>Prueba del flujo aislado</title>
<body><pre id="resultado">PENDIENTE</pre><iframe id="app"></iframe><script>
(async()=>{
    const app=document.getElementById('app'); let total=0;
    const comprobar=(condicion,mensaje)=>{if(!condicion) throw new Error(mensaje); total++;};
    const abrir=url=>new Promise(resolve=>{app.onload=resolve; app.src=url;});
    const campo=id=>app.contentDocument.getElementById(id);
    const enviar=id=>new Promise(resolve=>{app.onload=resolve; campo(id).requestSubmit();});
    const esperar=async condicion=>{for(let n=0;n<100;n++){if(condicion()) return; await new Promise(resolve=>setTimeout(resolve,50));}throw new Error('Tiempo de espera de interfaz agotado');};
    const idActual=()=>new URL(app.contentWindow.location.href).searchParams.get('id');
    try {
        await abrir('/citas/registrar.php?id_derivacion=4');
        comprobar(campo('estudiante').value==='3' && campo('derivacion').value==='4','Cita sin origen preseleccionado');
        campo('hora').value='15:00'; await enviar('form-cita');
        comprobar(app.contentWindow.location.pathname==='/citas/editar.php','No guarda cita en navegador');
        const cita=idActual();
        await abrir('/historias_clinicas/registrar.php?id_cita='+cita);
        await esperar(()=>!campo('selector_derivacion').disabled);
        comprobar(campo('id_estudiante').value==='3' && campo('input_id_derivacion').value==='4','Historia pierde origen en JavaScript');
        comprobar(campo('motivo_consulta').value==='Motivo navegador del flujo','Historia no precarga motivo');
        await enviar('formHistoriaClinica');
        comprobar(app.contentWindow.location.pathname==='/historias_clinicas/ver.php','No guarda historia en navegador');
        const historia=idActual();
        await abrir('/seguimientos/registrar.php?id_historia='+historia+'&id_cita='+cita);
        comprobar(campo('id_cita').value===cita,'Seguimiento sin cita preseleccionada');
        campo('descripcion').value='Seguimiento desde navegador'; campo('recomendaciones').value='Recomendación navegador';
        await enviar('form-seguimiento');
        comprobar(app.contentWindow.location.pathname==='/seguimientos/ver.php','No guarda seguimiento en navegador');
        const seguimiento=idActual();
        comprobar(app.contentDocument.body.textContent.includes('Seguimiento desde navegador'),'No recupera seguimiento en detalle');
        await abrir('/informes/registrar.php?id_estudiante=3&id_seguimiento='+seguimiento);
        comprobar(campo('id_historia').value===historia && campo('id_seguimiento').value===seguimiento && campo('id_derivacion').value==='4','Informe pierde relaciones en JavaScript');
        comprobar(campo('motivo').value==='Motivo navegador del flujo' && campo('recomendaciones').value==='Recomendación navegador','Informe precarga otro episodio');
        campo('aspecto_cognitivo').value='Cognitivo navegador'; campo('aspectos_afectivos').value='Afectivo navegador';
        app.contentDocument.querySelector('input[name="tipo_atencion[]"]').checked=true;
        await enviar('formInforme');
        comprobar(app.contentWindow.location.pathname==='/informes/listar.php' && app.contentDocument.body.textContent.includes('guardado correctamente'),'No guarda informe en navegador');
        document.getElementById('resultado').textContent=JSON.stringify({ok:true,total});
    } catch(error) {document.getElementById('resultado').textContent=JSON.stringify({ok:false,total,error:error.message});}
})();
</script></body></html>
