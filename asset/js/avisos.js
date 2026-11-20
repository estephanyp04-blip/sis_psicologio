/* API única de avisos. Sin HTML dinámico, sin dependencias ni datos en almacenamiento. */
(() => {
    'use strict';
    if (window.Avisos) return;
    const titulos = { success: 'Acción completada', error: 'No se pudo completar', warning: 'Revisa esta información', info: 'Información del sistema' };
    const trazos = {
        success: '<path d="m7 12 3 3 7-7"/><circle cx="12" cy="12" r="9"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        warning: '<path d="m10.3 4-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>',
        loading: '<path d="M21 12a9 9 0 1 1-9-9"/>'
    };
    const formularios = new WeakMap();
    const confirmados = new WeakSet();
    const pendientes = new WeakSet();
    const cola = [];
    let actual = null;
    let region;
    let omitirSalida = false;
    let sesionVencida = false;
    let promesaSesion;

    const elemento = (tag, clase, texto) => {
        const nodo = document.createElement(tag);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const icono = tipo => {
        const nodo = elemento('span', 'ui-aviso-icono' + (tipo === 'loading' ? ' ui-aviso-giro' : ''));
        nodo.setAttribute('aria-hidden', 'true');
        // Solo constantes del componente; los mensajes siempre usan textContent.
        nodo.innerHTML = '<svg viewBox="0 0 24 24">' + (trazos[tipo] || trazos.info) + '</svg>';
        return nodo;
    };
    const demoraSalida = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 140;

    function siguiente() {
        if (actual || !cola.length) return;
        const peticion = cola.shift();
        const { opciones, resolver } = peticion;
        const tipo = opciones.tipo || 'info';
        const previo = document.activeElement;
        const dialogo = elemento('dialog', 'ui-dialogo');
        dialogo.dataset.tipo = tipo;
        dialogo.setAttribute('aria-labelledby', 'ui-aviso-titulo');
        dialogo.setAttribute('aria-describedby', 'ui-aviso-texto');
        const titulo = elemento('h2', '', opciones.titulo || titulos[tipo] || 'Procesando');
        titulo.id = 'ui-aviso-titulo';
        const texto = elemento('p', '', opciones.mensaje || '');
        texto.id = 'ui-aviso-texto';
        const acciones = elemento('div', 'ui-aviso-acciones');
        const cancelar = elemento('button', 'ui-aviso-boton', opciones.cancelar || 'Cancelar');
        const aceptar = elemento('button', 'ui-aviso-boton ui-aviso-primario' + (opciones.destructivo ? ' ui-aviso-destructivo' : ''), opciones.aceptar || 'Entendido');
        cancelar.type = aceptar.type = 'button';
        cancelar.hidden = !opciones.confirmacion;
        if (opciones.procesando) {
            dialogo.setAttribute('aria-busy', 'true');
            acciones.hidden = true;
        }
        acciones.append(cancelar, aceptar);
        dialogo.append(icono(opciones.procesando ? 'loading' : tipo), titulo, texto, acciones);
        document.body.append(dialogo);
        let cerrando = false;
        const cerrar = resultado => {
            if (cerrando) return;
            cerrando = true;
            dialogo.classList.add('ui-aviso-saliendo');
            setTimeout(() => {
                dialogo.close();
                dialogo.remove();
                document.body.classList.remove('ui-aviso-abierto');
                actual = null;
                if (previo?.isConnected) previo.focus({ preventScroll: true });
                resolver(Boolean(resultado));
                siguiente();
            }, demoraSalida());
        };
        actual = { dialogo, cerrar, peticion };
        cancelar.addEventListener('click', () => cerrar(false));
        aceptar.addEventListener('click', () => cerrar(true));
        dialogo.addEventListener('cancel', event => {
            event.preventDefault();
            if (!opciones.procesando && !opciones.obligatorio) cerrar(false);
        });
        // El fondo no confirma ni descarta; Escape y los botones tienen significado explícito.
        dialogo.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const botones = [...dialogo.querySelectorAll('button')].filter(b => !b.hidden && !b.closest('[hidden]'));
            if (!botones.length) { event.preventDefault(); return; }
            const primero = botones[0], ultimo = botones[botones.length - 1];
            if (event.shiftKey && document.activeElement === primero) { event.preventDefault(); ultimo.focus(); }
            else if (!event.shiftKey && document.activeElement === ultimo) { event.preventDefault(); primero.focus(); }
        });
        document.body.classList.add('ui-aviso-abierto');
        dialogo.showModal();
        if (opciones.procesando) { dialogo.tabIndex = -1; dialogo.focus(); }
        else (opciones.confirmacion ? cancelar : aceptar).focus();
    }

    function modal(opciones) {
        return new Promise(resolver => { cola.push({ opciones, resolver }); siguiente(); });
    }

    function toast(mensaje, opciones = {}) {
        if (!region) {
            region = elemento('div', 'ui-toast-region');
            region.setAttribute('aria-label', 'Notificaciones del sistema');
            document.body.append(region);
        }
        // Evita acumulaciones. El detalle permanente sigue disponible en la página.
        while (region.children.length >= 3) region.firstElementChild.remove();
        const tipo = opciones.tipo || 'success';
        const aviso = elemento('div', 'ui-toast');
        aviso.dataset.tipo = tipo;
        aviso.setAttribute('role', 'status');
        aviso.setAttribute('aria-atomic', 'true');
        const contenido = elemento('div');
        contenido.append(elemento('strong', '', opciones.titulo || titulos[tipo]), elemento('p', '', mensaje));
        const boton = elemento('button', 'ui-aviso-cerrar', '×');
        boton.type = 'button';
        boton.setAttribute('aria-label', 'Cerrar notificación');
        aviso.append(icono(tipo), contenido, boton);
        let temporizador;
        const cerrar = () => {
            clearTimeout(temporizador);
            aviso.classList.add('ui-aviso-saliendo');
            setTimeout(() => aviso.remove(), demoraSalida());
        };
        const iniciar = () => {
            clearTimeout(temporizador);
            if (aviso.matches(':hover') || aviso.contains(document.activeElement)) return;
            // Sin caducidad al reducir movimiento; se puede cerrar con teclado.
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && opciones.duracion !== 0)
                temporizador = setTimeout(cerrar, opciones.duracion || 6500);
        };
        boton.addEventListener('click', cerrar);
        aviso.addEventListener('mouseenter', () => clearTimeout(temporizador));
        aviso.addEventListener('focusin', () => clearTimeout(temporizador));
        aviso.addEventListener('mouseleave', iniciar);
        aviso.addEventListener('focusout', () => queueMicrotask(iniciar));
        region.append(aviso);
        iniciar();
        return { cerrar };
    }

    function avisoEnPagina(mensaje, tipo = 'info') {
        const contenedor = document.querySelector('.main-content, .login-formulario') || document.body;
        let aviso = contenedor.querySelector('[data-ui-mensaje]');
        if (!aviso) {
            aviso = elemento('div');
            aviso.dataset.uiMensaje = 'true';
            contenedor.prepend(aviso);
        }
        aviso.className = 'alert alert-' + (tipo === 'error' ? 'danger' : tipo) + ' ui-respuesta ui-mensaje';
        aviso.setAttribute('role', tipo === 'error' || tipo === 'warning' ? 'alert' : 'status');
        aviso.textContent = mensaje;
        // Conserva la API asíncrona de los validadores sin exigir cerrar una ventana.
        return Promise.resolve(true);
    }

    function procesando(mensaje = 'Estamos procesando la solicitud. Espera un momento…') {
        const aviso = elemento('p', 'ui-envio-estado', mensaje);
        aviso.setAttribute('role', 'status');
        (document.querySelector('.main-content, .login-formulario') || document.body).prepend(aviso);
        return { cerrar() { aviso.remove(); } };
    }

    function huella(form) {
        return JSON.stringify([...form.elements].filter(c => c.name && !c.disabled && !['hidden', 'submit', 'button', 'reset'].includes(c.type)).map(c =>
            [c.name, c.type === 'file' ? [...c.files].map(f => [f.name, f.size, f.lastModified]) :
                ['checkbox', 'radio'].includes(c.type) ? c.checked : c.multiple ? [...c.selectedOptions].map(o => o.value) : c.value]));
    }
    function vigilar(form) {
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || formularios.has(form)) return;
        formularios.set(form, { inicial: huella(form), enviando: false, tocado: false });
    }
    const cambios = form => {
        const estado = formularios.get(form);
        return estado && estado.tocado && !estado.enviando && form.dataset.uiSinCambios !== 'true' && estado.inicial !== huella(form);
    };
    const hayCambios = excepto => [...document.forms].some(form => form !== excepto && cambios(form));
    const sinGuardar = () => modal({ tipo: 'warning', titulo: 'Tienes cambios sin guardar', mensaje: 'Si sales ahora, perderás los cambios de este formulario.', confirmacion: true, aceptar: 'Salir sin guardar', cancelar: 'Seguir editando', destructivo: true });

    function confirmacionDe(form) {
        if (form.dataset.uiConfirmar) return { mensaje: form.dataset.uiConfirmar, aceptar: form.dataset.uiAceptar || 'Confirmar', destructivo: form.dataset.uiDestructivo === 'true' };
        const ruta = new URL(form.action).pathname;
        if (form.matches('.formulario-retirar')) return { mensaje: '¿Retirar este estudiante? Se conservarán sus registros y su historial.', aceptar: 'Retirar estudiante', destructivo: true };
        if (form.matches('.formulario-reactivar')) return null;
        if (ruta.endsWith('/eliminar.php')) return { mensaje: '¿Eliminar este registro? Esta acción no se puede deshacer.', aceptar: 'Eliminar', destructivo: true };
        if (ruta.endsWith('/cambiar_estado.php')) return { mensaje: '¿Cambiar el estado de este registro? Esta acción puede afectar su disponibilidad en el sistema.', aceptar: 'Cambiar estado' };
        if (ruta.endsWith('/citas/cancelar.php') && (!form.elements.estado || form.elements.estado.value === 'Cancelada')) return { mensaje: '¿Cancelar esta cita? Se conservará el registro.', aceptar: 'Cancelar cita', destructivo: true };
        if (ruta.endsWith('/roles/actualizar.php')) return { mensaje: '¿Guardar los permisos y el estado de este rol? Los cambios pueden modificar el acceso de sus usuarios.', aceptar: 'Guardar permisos' };
        return null;
    }

    async function confirmarFormulario(form, opciones, boton) {
        if (pendientes.has(form)) return;
        pendientes.add(form);
        try {
            const aprobado = await modal({ tipo: 'warning', titulo: 'Confirmar acción', confirmacion: true, ...opciones });
            if (aprobado && form.isConnected && (!boton || boton.isConnected)) {
                confirmados.add(form);
                // requestSubmit conserva validaciones, eventos y nombre/valor del botón.
                try { form.requestSubmit(boton || undefined); }
                finally { confirmados.delete(form); }
            }
        } finally { pendientes.delete(form); }
    }

    function restaurar(form) {
        const estado = formularios.get(form);
        if (!estado) return;
        estado.enviando = false;
        omitirSalida = false;
        form.removeAttribute('aria-busy');
        form.querySelector('.ui-envio-estado')?.remove();
        if (estado.boton) estado.boton.removeAttribute('aria-disabled');
    }

    function sesionExpirada() {
        if (promesaSesion) return promesaSesion;
        sesionVencida = true;
        // Un 401 tiene prioridad sobre cargas o confirmaciones pendientes.
        cola.splice(0).forEach(p => p.resolver(false));
        actual?.cerrar(false);
        document.querySelectorAll('form[aria-busy="true"]').forEach(restaurar);
        promesaSesion = modal({ tipo: 'warning', titulo: 'Tu sesión ha finalizado', mensaje: 'Vuelve a iniciar sesión para continuar. Los datos del formulario permanecen en esta página hasta que salgas.', aceptar: 'Ir a iniciar sesión', obligatorio: true }).then(() => {
            omitirSalida = true;
            window.location.assign(document.body.dataset.uiLogin || 'login.php');
        });
        return promesaSesion;
    }

    function comprobarRespuesta(respuesta) {
        if (respuesta.status === 401 || (respuesta.redirected && new URL(respuesta.url).pathname === new URL(document.body.dataset.uiLogin, location.href).pathname)) {
            sesionExpirada();
            return false;
        }
        return true;
    }

    function integrar(root = document) {
        root.querySelectorAll('form').forEach(vigilar);
        root.querySelectorAll('[data-ui-aviso]:not([data-ui-integrado])').forEach(aviso => {
            aviso.dataset.uiIntegrado = 'true';
            const tipo = aviso.classList.contains('alert-success') ? 'success' : aviso.classList.contains('alert-danger') ? 'error' : aviso.classList.contains('alert-warning') ? 'warning' : 'info';
            // El mensaje del servidor ya está en la página; no se duplica en un popup.
            aviso.hidden = false;
            aviso.setAttribute('role', tipo === 'error' || tipo === 'warning' ? 'alert' : 'status');
        });
        root.querySelectorAll('.alert:not([data-ui-inline])').forEach(aviso => {
            aviso.dataset.uiInline = 'true';
            aviso.classList.add('ui-respuesta');
            if (!aviso.hasAttribute('role')) aviso.setAttribute('role', aviso.classList.contains('alert-danger') ? 'alert' : 'status');
        });
    }

    function iniciar() {
        if (window.jQuery?.fn?.dataTable) {
            window.jQuery.fn.dataTable.ext.errMode = () => avisoEnPagina('No se pudo cargar la tabla. Revisa la conexión e inténtalo nuevamente.', 'error');
        }
        document.querySelectorAll('[data-ui-entrada]').forEach(nodo => {
            try {
                const datos = JSON.parse(nodo.textContent);
                const form = nodo.dataset.uiFormulario ? document.getElementById(nodo.dataset.uiFormulario) : [...document.forms].find(f => f.method.toLowerCase() === 'post' && f.closest('.main-content'));
                if (!(form instanceof HTMLFormElement)) return;
                vigilar(form);
                for (const campo of form.elements) {
                    if (!campo.name || ['password', 'hidden', 'file', 'submit', 'button'].includes(campo.type)) continue;
                    const permiso = campo.name.match(/^permisos\[(\d+)\]\[(\w+)\]$/);
                    if (permiso && Object.hasOwn(datos, 'permisos')) campo.checked = Boolean(datos.permisos[permiso[1]]?.[permiso[2]]);
                    else if (Object.hasOwn(datos, campo.name) && typeof datos[campo.name] === 'string') campo.value = datos[campo.name];
                }
                if (form.closest('details')) form.closest('details').open = true;
                const estado = formularios.get(form);
                if (estado) estado.tocado = true;
            } catch (_) { /* El formulario sigue siendo utilizable. */ }
            nodo.remove();
        });
        integrar();
        new MutationObserver(registros => {
            if (registros.some(r => [...r.addedNodes].some(n => n.nodeType === 1 && (n.matches('form, [data-ui-aviso]') || n.querySelector('form, [data-ui-aviso]'))))) integrar();
        }).observe(document.body, { childList: true, subtree: true });
    }

    // Última fase del evento: las validaciones propias del módulo se ejecutan primero.
    window.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (form.method.toLowerCase() !== 'post') {
            if (hayCambios() && !confirmados.has(form)) {
                event.preventDefault();
                confirmarFormulario(form, { titulo: 'Tienes cambios sin guardar', mensaje: 'La consulta abandonará los cambios del formulario actual. ¿Deseas continuar?', aceptar: 'Continuar sin guardar', destructivo: true }, event.submitter);
            } else if (confirmados.has(form)) omitirSalida = true;
            return;
        }
        vigilar(form);
        const estado = formularios.get(form);
        if (sesionVencida) { event.preventDefault(); sesionExpirada(); return; }
        if (estado.enviando) { event.preventDefault(); return; }
        if (hayCambios(form) && !confirmados.has(form)) {
            event.preventDefault();
            confirmarFormulario(form, { titulo: 'Tienes cambios sin guardar', mensaje: 'Esta acción abandonará los cambios de otro formulario en esta página. ¿Deseas continuar?', aceptar: 'Continuar sin guardar', destructivo: true }, event.submitter);
            return;
        }
        const confirmacion = confirmacionDe(form);
        if (confirmacion && !confirmados.has(form)) {
            event.preventDefault();
            confirmarFormulario(form, confirmacion, event.submitter);
            return;
        }
        // Bloqueo sin deshabilitar campos ni modificar el valor enviado por el botón.
        estado.enviando = true;
        estado.boton = event.submitter;
        estado.boton?.setAttribute('aria-disabled', 'true');
        form.setAttribute('aria-busy', 'true');
        const aviso = elemento('p', 'ui-envio-estado', 'Procesando la solicitud…');
        aviso.setAttribute('role', 'status');
        form.append(aviso);
        queueMicrotask(() => {
            if (event.defaultPrevented) restaurar(form);
            else omitirSalida = true;
        });
    });

    document.addEventListener('click', async event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const enlace = event.target.closest('a[href]');
        if (!enlace || enlace.hasAttribute('download') || (enlace.target && enlace.target !== '_self') || enlace.hasAttribute('data-bs-toggle')) return;
        const destino = new URL(enlace.href, location.href);
        if (!['http:', 'https:'].includes(destino.protocol) || (destino.pathname === location.pathname && destino.search === location.search) || !hayCambios()) return;
        event.preventDefault();
        if (await sinGuardar()) { omitirSalida = true; location.assign(destino.href); }
    });
    // El navegador exige su propio aviso para cerrar/recargar/retroceder fuera de la página.
    window.addEventListener('beforeunload', event => {
        if (!omitirSalida && hayCambios()) { event.preventDefault(); event.returnValue = ''; }
    });
    window.addEventListener('pageshow', () => { omitirSalida = false; document.querySelectorAll('form[aria-busy="true"]').forEach(restaurar); });
    ['input', 'change'].forEach(tipo => document.addEventListener(tipo, event => {
        if (event.detail?.uiPrecarga) return;
        const form = event.target.form;
        if (!form) return;
        vigilar(form);
        const estado = formularios.get(form);
        if (estado) estado.tocado = true;
    }, true));
    // También bloquea dobles clics antes de que actúen validadores particulares.
    document.addEventListener('submit', event => {
        if (formularios.get(event.target)?.enviando) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);

    window.Avisos = Object.freeze({
        toast, modal, procesando, confirmarFormulario, sesionExpirada, comprobarRespuesta, integrar,
        exito: mensaje => avisoEnPagina(mensaje, 'success'),
        error: mensaje => avisoEnPagina(mensaje, 'error'),
        advertencia: mensaje => avisoEnPagina(mensaje, 'warning'),
        informacion: mensaje => avisoEnPagina(mensaje),
        confirmar: opciones => modal({ tipo: 'warning', titulo: 'Confirmar acción', confirmacion: true, ...opciones }),
        cambiosSinGuardar: sinGuardar,
        restablecer: restaurar,
        marcarGuardado(form) { const estado = formularios.get(form); if (estado) { estado.inicial = huella(form); estado.tocado = false; } }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
