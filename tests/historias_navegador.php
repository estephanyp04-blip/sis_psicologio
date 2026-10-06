<?php
// Generador CLI: nunca ejecutar las acciones del navegador desde la aplicación real.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="es"><meta charset="utf-8"><title>Prueba clínica aislada</title>
<body><pre id="resultado">PENDIENTE</pre><iframe id="app"></iframe>
<script>
(async () => {
    const app = document.getElementById('app');
    let total = 0;
    const comprobar = (condicion, mensaje) => { if (!condicion) throw new Error(mensaje); total++; };
    const abrir = url => new Promise(resolve => { app.onload = resolve; app.src = url; });
    const pausa = () => new Promise(resolve => setTimeout(resolve, 50));
    const esperar = async condicion => {
        for (let i = 0; i < 100; i++) { if (condicion()) return; await pausa(); }
        throw new Error('La interfaz no terminó de cargar las derivaciones.');
    };
    const campo = id => app.contentDocument.getElementById(id);
    const cargar = () => esperar(() => !campo('selector_derivacion').disabled);
    const cambiar = (id, valor) => { campo(id).value = valor; campo(id).dispatchEvent(new app.contentWindow.Event('change')); };
    const enviar = () => new Promise(resolve => { app.onload = resolve; campo('formHistoriaClinica').requestSubmit(); });
    try {
        await abrir('/historias_clinicas/editar.php?id=1');
        comprobar(campo('lugar_nacimiento').value === 'Lugar clínico', 'JS sobrescribe lugar clínico.');
        comprobar(campo('celular_estudiante').value === '222' && campo('padre_madre').value === 'Tutor clínico', 'JS sobrescribe contacto clínico.');
        comprobar(campo('input_id_derivacion').value === '1', 'JS cambia vínculo al editar.');
        campo('motivo_consulta').value = 'Edición real de navegador';
        await enviar();
        comprobar(app.contentWindow.location.pathname.endsWith('/ver.php'), 'No guarda la edición del navegador.');
        comprobar(app.contentDocument.body.textContent.includes('Edición real de navegador'), 'No se recupera edición del navegador.');

        await abrir('/historias_clinicas/registrar.php?id_derivacion=4');
        await cargar();
        comprobar(campo('selector_derivacion').value === '4' && campo('motivo_consulta').value === 'Motivo navegador', 'No conserva preselección por URL.');
        cambiar('id_estudiante', '5'); await cargar();
        comprobar(campo('input_id_derivacion').value === '' && campo('motivo_consulta').value === '', 'Cambiar estudiante arrastra motivo de otra derivación.');
        cambiar('id_estudiante', '3'); await cargar();
        cambiar('selector_derivacion', '4');
        comprobar(campo('motivo_consulta').value === 'Motivo navegador' && campo('materia_derivacion').value === 'Matemática', 'No autocompleta derivación seleccionada.');
        campo('motivo_consulta').value = 'Motivo propio';
        // Simula la marca que elimina el manejador ante una entrada del usuario.
        delete campo('motivo_consulta').dataset.autofill;
        cambiar('selector_derivacion', '');
        comprobar(campo('motivo_consulta').value === 'Motivo propio', 'Borra motivo escrito por usuario.');
        cambiar('selector_derivacion', '4');
        comprobar(campo('motivo_consulta').value === 'Motivo propio', 'Autocompletado pisa motivo propio.');
        cambiar('selector_derivacion', '');
        campo('motivo_consulta').value = '';
        cambiar('id_estudiante', '5'); await cargar();
        campo('motivo_consulta').value = 'Alta sin derivación desde navegador';
        await enviar();
        comprobar(app.contentWindow.location.pathname.endsWith('/ver.php') && app.contentDocument.body.textContent.includes('Alta sin derivación desde navegador'), 'No guarda sin derivación desde navegador.');

        await abrir('/historias_clinicas/registrar.php');
        const win = app.contentWindow;
        const solicitudes = [];
        win.fetch = () => new Promise(resolve => solicitudes.push(resolve));
        cambiar('id_estudiante', '6');
        cambiar('id_estudiante', '7');
        solicitudes[1]({ok: true, json: async () => ({derivaciones: []})});
        await cargar();
        solicitudes[0]({ok: true, json: async () => ({derivaciones: [{id: 999, fecha: '2026-01-01', docente: 'Respuesta obsoleta'}]})});
        await pausa();
        comprobar(!campo('selector_derivacion').textContent.includes('Respuesta obsoleta') && campo('input_id_derivacion').value === '', 'Respuesta atrasada vincula derivación ajena.');
        win.fetch = async () => ({ok: false, status: 500});
        win.alert = () => {};
        cambiar('id_estudiante', '6');
        await esperar(() => campo('infoDerivacion').textContent.includes('No se pudieron'));
        const evento = new win.Event('submit', {cancelable: true});
        comprobar(campo('formHistoriaClinica').dispatchEvent(evento) === false, 'Permite guardar tras error al cargar JSON.');
        comprobar(campo('selector_derivacion').disabled, 'No señala el error en selector.');
        document.getElementById('resultado').textContent = JSON.stringify({ok: true, total});
    } catch (error) {
        document.getElementById('resultado').textContent = JSON.stringify({ok: false, total, error: error.message});
    }
})();
</script></body></html>
