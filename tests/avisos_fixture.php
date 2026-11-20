<?php
if (!defined('PRUEBA_AVISOS')) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="es">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/asset/css/estilos.css">
<link rel="stylesheet" href="/asset/css/avisos.css">
<script src="/asset/js/avisos.js" defer></script>
<body data-ui-login="/login.php">
    <main class="main-content" style="margin:16px">
<h1>Prueba sintética de avisos</h1>
<a id="salir" href="/otra">Salir</a>
<p data-ui-sesion-expirada role="status">
    Tu sesión ha finalizado. Inicia sesión nuevamente.</p>
<form method="post" id="otro-formulario"></form>
<form id="form" action="/guardar" method="post" target="resultado-post">
<input type="hidden" name="csrf" value="token-sintetico">
<label>Nombre <input name="nombre" required value="Inicial"></label>
<input name="password" type="password" value="">
<input name="permisos[3][puede_ver]" type="checkbox" value="1">
<button name="accion" value="guardar" type="submit">Guardar</button>
</form>
<script type="application/json" data-ui-entrada data-ui-formulario="form">{"nombre":"Recuperado <script>","permisos":{"3":{"puede_ver":"1"}},"password":"no-restaurar","csrf":"no-restaurar"}</script>
<iframe name="resultado-post" id="post" title="Resultado de prueba" hidden></iframe><pre id="resultado">Pendiente</pre></main>
<script>
window.addEventListener('error', e => { document.getElementById('resultado').textContent = JSON.stringify({ok:false,error:e.message}); });
document.addEventListener('DOMContentLoaded', async () => {
    const resultado = document.getElementById('resultado');
    const pausa = (ms = 210) => new Promise(r => setTimeout(r, ms));
    const esperar = async fn => { for (let i=0;i<100;i++) { if (fn()) return; await pausa(30); } throw new Error('Tiempo de espera agotado'); };
    let total = 0;
    const verificar = (c, m) => { total++; if (!c) throw new Error(m); };
    const dialogo = () => document.querySelector('.ui-dialogo[open]');
    const cerrar = async (aceptar = true) => { dialogo().querySelector(aceptar ? '.ui-aviso-primario' : '.ui-aviso-boton:not(.ui-aviso-primario)').click(); await pausa(); };
    const form = document.getElementById('form');
    const campo = form.elements.nombre;
    const boton = form.querySelector('button');
    try {
        await pausa();
        // Un iframe da un viewport real incluso en Chrome headless con ancho mínimo.
        verificar(typeof Avisos.confirmar === 'function', 'API cargada sin dependencias');
        verificar(!dialogo(), 'Aviso de sesión en el acceso sin ventana duplicada');
        verificar(campo.value === 'Recuperado <script>', 'Recuperación literal de campos');
        verificar(form.elements.password.value === '' && form.elements.csrf.value === 'token-sintetico', 'No restaura contraseña ni CSRF');
        verificar(form.querySelector('[type=checkbox]').checked, 'Recupera permisos');
        let salidaRecuperada = new Event('beforeunload',{cancelable:true}); window.dispatchEvent(salidaRecuperada);
        verificar(salidaRecuperada.defaultPrevented, 'Entradas recuperadas siguen pendientes de guardar');
        Avisos.marcarGuardado(form);
        boton.focus();
        const respuesta = Avisos.confirmar({mensaje:'¿Continuar con la prueba?', aceptar:'Continuar'});
        verificar(document.activeElement.textContent === 'Cancelar', 'Foco inicial en cancelar');
        verificar(dialogo().getAttribute('aria-modal') !== 'false' && dialogo().getAttribute('aria-describedby'), 'Diálogo etiquetado');
        const rect = dialogo().getBoundingClientRect();
        verificar(rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight, 'Modal cabe en viewport');
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',shiftKey:true,bubbles:true,cancelable:true}));
        verificar(document.activeElement.textContent === 'Continuar', 'Ciclo inverso de teclado');
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
        verificar(document.activeElement.textContent === 'Cancelar', 'Ciclo de teclado');
        dialogo().dispatchEvent(new Event('cancel',{cancelable:true}));
        verificar(await respuesta === false, 'Escape cancela');
        verificar(document.activeElement === boton, 'Restaura foco al cerrar');

        const primera = Avisos.modal({mensaje:'<img src=x onerror=alert(1)>'});
        const segunda = Avisos.modal({tipo:'warning',mensaje:'Segundo aviso'});
        verificar(!dialogo().querySelector('img'), 'Mensaje interpretado como texto, sin HTML');
        verificar(document.querySelectorAll('.ui-dialogo').length === 1, 'Cola sin modales superpuestos');
        await cerrar(); await primera;
        verificar(dialogo().textContent.includes('Segundo aviso'), 'Orden de la cola');
        await cerrar(); await segunda;

        await Avisos.informacion('<img src=x onerror=alert(1)>');
        const mensaje = document.querySelector('[data-ui-mensaje]');
        verificar(!dialogo() && !mensaje.querySelector('img') && mensaje.textContent.includes('<img'), 'Información literal dentro de la página');
        await Avisos.advertencia('Selecciona un estudiante.').then(() => campo.focus());
        verificar(!dialogo() && document.activeElement === campo && mensaje.getAttribute('role') === 'alert', 'Validación sin ventana y foco en el campo');
        await Avisos.error('No se pudo completar la solicitud.');
        verificar(!dialogo() && mensaje.textContent.includes('No se pudo'), 'Error visible sin modal');
        await Avisos.exito('La operación se completó correctamente.');
        verificar(!dialogo() && !document.querySelector('.ui-toast') && mensaje.getAttribute('role') === 'status', 'Éxito dentro de la página');

        const flash = document.createElement('div');
        flash.className = 'alert alert-success'; flash.dataset.uiAviso = ''; flash.textContent = 'Nombre clínico sintético confidencial';
        document.querySelector('main').append(flash);
        await pausa();
        verificar(!flash.hidden && !dialogo() && !document.querySelector('.ui-toast'), 'Éxito PHP visible sin popup');
        verificar(flash.textContent === 'Nombre clínico sintético confidencial', 'Detalle PHP conservado en su contenedor');
        Avisos.integrar();
        verificar(!dialogo() && !document.querySelector('.ui-toast') && flash.dataset.uiIntegrado === 'true', 'Reintegrar no duplica avisos');
        const error = document.createElement('div');
        error.className='alert alert-danger'; error.dataset.uiAviso=''; error.textContent='Revisa el campo sintético'; document.querySelector('main').append(error);
        await pausa(); verificar(!dialogo() && !error.hidden && error.getAttribute('role') === 'alert', 'Error PHP visible sin modal');
        for (const tipo of ['warning', 'info']) {
            const aviso = document.createElement('div');
            aviso.className = 'alert alert-' + tipo; aviso.dataset.uiAviso = ''; aviso.textContent = 'Detalle sintético'; document.querySelector('main').append(aviso);
            await pausa(); verificar(!aviso.hidden && !dialogo() && !document.querySelector('.ui-toast'), 'Aviso PHP ' + tipo + ' sin popup');
        }

        // Los precargados asíncronos no cuentan como edición del usuario.
        campo.value = 'Precargado';
        campo.dispatchEvent(new CustomEvent('input',{detail:{uiPrecarga:true}}));
        let salida = new Event('beforeunload',{cancelable:true}); window.dispatchEvent(salida);
        verificar(!salida.defaultPrevented, 'Precarga sin falso cambio pendiente');
        campo.value = 'Edición conservada'; campo.dispatchEvent(new Event('input',{bubbles:true}));
        document.getElementById('salir').click(); await pausa();
        verificar(dialogo().textContent.includes('sin guardar'), 'Navegación con cambios pide autorización');
        await cerrar(false); verificar(campo.value === 'Edición conservada', 'Cancelar salida conserva valores');
        salida = new Event('beforeunload',{cancelable:true}); window.dispatchEvent(salida);
        verificar(salida.defaultPrevented, 'Protección al cerrar/recargar');
        const consulta=document.createElement('form'); consulta.method='get'; consulta.action='/otra'; document.querySelector('main').append(consulta);
        consulta.requestSubmit(); await pausa();
        verificar(dialogo().textContent.includes('sin guardar'), 'Filtro GET protege cambios de otro formulario');
        await cerrar(false); consulta.remove();
        Avisos.marcarGuardado(form);

        let validado = 0;
        const validar = event => { validado++; event.preventDefault(); };
        form.addEventListener('submit', validar);
        form.requestSubmit(boton); await pausa();
        verificar(validado === 1 && !form.hasAttribute('aria-busy') && !dialogo(), 'Validador local conserva control');
        form.removeEventListener('submit', validar);
        const anterior = campo.value; campo.value=''; form.requestSubmit(boton); await pausa();
        verificar(!form.hasAttribute('aria-busy'), 'Validación required antes del bloqueo'); campo.value=anterior;

        form.dataset.uiConfirmar = '¿Guardar la prueba?';
        form.requestSubmit(boton); form.requestSubmit(boton); await pausa();
        verificar(document.querySelectorAll('.ui-dialogo').length === 1, 'Doble envío esperando confirmación abre una ventana');
        await cerrar(false);
        verificar(!form.hasAttribute('aria-busy') && campo.value === anterior, 'Cancelar operación no bloquea ni borra');
        let recibido = false;
        document.getElementById('post').addEventListener('load',()=> { recibido = true; });
        form.requestSubmit(boton); await pausa();
        dialogo().querySelector('.ui-aviso-primario').click();
        await esperar(() => form.getAttribute('aria-busy') === 'true');
        form.requestSubmit(boton);
        await esperar(() => recibido);
        const post = JSON.parse(document.getElementById('post').contentDocument.body.textContent);
        verificar(post.contador === 1, 'Una sola escritura ante doble envío');
        verificar(post.datos.csrf === 'token-sintetico' && post.datos.accion === 'guardar' && post.datos.nombre === anterior, 'POST conserva CSRF, submitter y campos');
        verificar(!dialogo() && form.querySelector('.ui-envio-estado')?.getAttribute('role') === 'status', 'Procesamiento dentro del formulario sin modal');
        window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})); await pausa();
        verificar(!form.hasAttribute('aria-busy') && !boton.hasAttribute('aria-disabled'), 'Retorno del historial restaura formulario');
        verificar(!dialogo() && !form.querySelector('.ui-envio-estado'), 'Indicador retirado al restaurar');
        delete form.dataset.uiConfirmar;

        const carga = Avisos.procesando();
        verificar(!dialogo() && document.querySelector('.ui-envio-estado'), 'Carga AJAX sin ventana emergente');
        carga.cerrar(); await pausa();
        verificar(!document.querySelector('.ui-envio-estado'), 'Carga AJAX retirada al terminar');

        Avisos.comprobarRespuesta(new Response('{}',{status:401})); await pausa();
        verificar(dialogo().textContent.includes('sesión ha finalizado'), '401 abre aviso de sesión');
        verificar(campo.value === anterior, 'Sesión expirada mantiene campos en la página');
        verificar(!Avisos.comprobarRespuesta(new Response('{}',{status:401})) && document.querySelectorAll('.ui-dialogo').length === 1, '401 repetido no duplica ventanas');
        form.requestSubmit(boton); verificar(!form.hasAttribute('aria-busy'), 'Sesión finalizada bloquea nuevos envíos');
        verificar(document.documentElement.scrollWidth <= innerWidth, 'Sin desbordamiento horizontal');
        resultado.textContent=JSON.stringify({ok:true,total,ancho:innerWidth});
    } catch(error) { resultado.textContent=JSON.stringify({ok:false,total,error:error.message}); }
});
</script></body></html>
