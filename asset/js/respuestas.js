/* Respuestas compartidas: el éxito siempre procede del servidor. */
(() => {
    const main = document.querySelector('.main-content');
    if (!main) return;
    const tipos = {
        success: ['Acción completada', 'bi-check-circle-fill'],
        danger: ['No se pudo completar la acción', 'bi-exclamation-octagon-fill'],
        warning: ['Revisa esta información', 'bi-exclamation-triangle-fill'],
        info: ['Información del sistema', 'bi-info-circle-fill']
    };
    main.querySelectorAll('.alert').forEach(aviso => {
        const tipo = Object.keys(tipos).find(tipo => aviso.classList.contains('alert-' + tipo));
        if (!tipo || aviso.dataset.respuestaLista) return;
        aviso.dataset.respuestaLista = '1';
        aviso.classList.add('ui-respuesta');
        aviso.setAttribute('role', ['danger', 'warning'].includes(tipo) ? 'alert' : 'status');
        const titulo = document.createElement('strong');
        titulo.className = 'ui-respuesta-titulo';
        const icono = document.createElement('i');
        icono.className = 'bi ' + tipos[tipo][1];
        icono.setAttribute('aria-hidden', 'true');
        titulo.append(icono, document.createTextNode(' ' + tipos[tipo][0]));
        aviso.prepend(titulo);
        if (!aviso.querySelector('.btn-close')) {
            const cerrar = document.createElement('button');
            cerrar.type = 'button';
            cerrar.className = 'btn-close ui-respuesta-cerrar';
            cerrar.setAttribute('aria-label', 'Cerrar aviso');
            cerrar.addEventListener('click', () => aviso.remove());
            aviso.append(cerrar);
        }
    });
    const restaurar = form => {
        form.removeAttribute('aria-busy');
        form.querySelector('.ui-envio-estado')?.remove();
        form.querySelectorAll('[data-envio-boton]').forEach(boton => {
            boton.innerHTML = boton.dataset.envioBoton;
            delete boton.dataset.envioBoton;
            boton.removeAttribute('aria-disabled');
        });
    };
    // Se espera a las validaciones y confirmaciones propias de cada pantalla.
    window.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || event.defaultPrevented) return;
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            form.setAttribute('aria-busy', 'true');
            const estado = document.createElement('p');
            estado.className = 'ui-envio-estado';
            estado.setAttribute('role', 'status');
            estado.textContent = 'Procesando la solicitud. Espera un momento…';
            form.append(estado);
            const boton = event.submitter;
            // No se deshabilita: su nombre y valor pueden ser necesarios en el POST.
            if (boton?.tagName === 'BUTTON') {
                boton.dataset.envioBoton = boton.innerHTML;
                boton.textContent = 'Procesando…';
                boton.setAttribute('aria-disabled', 'true');
            }
        });
    });
    // El historial puede recuperar una página con el estado de envío anterior.
    window.addEventListener('pageshow', () => main.querySelectorAll('form[aria-busy="true"]').forEach(restaurar));
})();
