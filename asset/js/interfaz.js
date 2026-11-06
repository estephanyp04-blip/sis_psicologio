/* Mejoras de presentación: no cambian rutas, datos ni validaciones del servidor. */
(() => {
    const main = document.querySelector('.main-content');
    if (!main) return;
    main.id ||= 'contenido-principal';
    main.tabIndex = -1;
    document.querySelector('.ui-skip')?.setAttribute('href', '#' + main.id);

    document.querySelectorAll('.table-responsive').forEach(region => {
        region.tabIndex = 0;
        region.setAttribute('role', 'region');
        region.setAttribute('aria-label', 'Listado de registros; desplazar para consultar todas las columnas');
    });
    document.querySelectorAll('#tablaEstudiantes, #tablaCitas, .estadisticas-tabla, .panel-tabla, body[data-modulo="docentes"] .table').forEach(table => {
        table.classList.add('ui-tarjetas');
        const headers = Array.from(table.querySelectorAll('thead th'), th => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => {
            if (row.cells.length !== headers.length) return;
            Array.from(row.cells).forEach((cell, i) => { cell.dataset.label = headers[i]; });
        });
    });
    main.querySelectorAll('a.btn, button.btn, .btn-accion').forEach(button => {
        if (!button.textContent.trim() && button.title) button.setAttribute('aria-label', button.title);
    });
    main.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(field => {
        if (!field.id || field.labels?.length || ['checkbox','radio','file','submit'].includes(field.type)) return;
        const names = { buscador:'Buscar registros', buscar:'Buscar registros', filtroCurso:'Curso', filtroEstado:'Estado' };
        const text = names[field.id] || field.getAttribute('placeholder');
        if (!text) return;
        const label = document.createElement('label');
        label.className = 'form-label';label.htmlFor = field.id;label.textContent = text.replace(/\.+$/, '');
        field.parentNode.insertBefore(label, field);
    });
    const citaTable = document.getElementById('tablaCitas');
    if (citaTable) {
        const summary = document.createElement('p');
        summary.className = 'ui-filtro-resumen';summary.setAttribute('role', 'status');
        citaTable.closest('.card').insertAdjacentElement('beforebegin', summary);
        const update = () => {
            const rows = Array.from(citaTable.tBodies[0].rows).filter(row => row.cells.length === 8);
            const count = rows.filter(row => row.style.display !== 'none').length;
            const active = ['buscador','filtroEstado','filtroFecha'].map(id => document.getElementById(id)?.value).filter(Boolean);
            summary.textContent = (count ? count + ' citas encontradas.' : 'No hay citas para esta búsqueda.') + (active.length ? ' Filtros: ' + active.join(' · ') : ' Sin filtros activos.');
        };
        ['buscador','filtroEstado','filtroFecha'].forEach(id => {
            const field = document.getElementById(id);
            field?.addEventListener('input', update);field?.addEventListener('change', update);
        });
        update();
    }
    main.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
        const label = field.labels?.[0];
        if (label && !label.textContent.includes('obligatorio') && !label.textContent.includes('*')) {
            const hint = document.createElement('span');
            hint.className = 'campo-obligatorio';
            hint.textContent = ' (obligatorio)';
            label.append(hint);
        }
    });
    // Conserva la validación nativa y añade su explicación junto al campo.
    main.addEventListener('invalid', event => {
        const field = event.target;
        if (!field.id || field.type === 'hidden') return;
        let error = document.getElementById(field.id + '-ayuda-error');
        if (!error) {
            error = document.createElement('span');
            error.id = field.id + '-ayuda-error';
            error.className = 'campo-error';
            field.insertAdjacentElement('afterend', error);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        }
        error.textContent = field.validationMessage;
        field.setAttribute('aria-invalid', 'true');
    }, true);
    main.addEventListener('input', event => {
        const field = event.target;
        if (field.validity?.valid && field.getAttribute('aria-invalid') === 'true') {
            field.removeAttribute('aria-invalid');
            const error = document.getElementById(field.id + '-ayuda-error');
            if (error) error.textContent = '';
        }
    });
    main.querySelectorAll('.calendario-acciones form').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.elements.estado?.value === 'Cancelada' && !window.confirm('¿Cancelar esta cita? Se conservará el registro.')) event.preventDefault();
        });
    });
    // Solo se recuerda el identificador del aviso, en esta pestaña y por cuenta.
    const user = document.body.dataset.cuenta;
    document.querySelectorAll('.navbar-notificacion').forEach(link => {
        const key = 'aviso-visto:' + user + ':' + new URL(link.href).pathname + new URL(link.href).search;
        const state = document.createElement('small');
        state.className = 'ui-notificacion-estado';
        let read = false;
        try { read = sessionStorage.getItem(key) === '1'; } catch (_) { /* Almacenamiento opcional. */ }
        link.dataset.leida = String(read);
        state.textContent = read ? 'Consultada en esta pestaña' : 'Sin consultar en esta pestaña';
        link.querySelector('span')?.append(state);
        link.addEventListener('click', () => { try { sessionStorage.setItem(key, '1'); } catch (_) { /* Opcional. */ } });
    });
})();
