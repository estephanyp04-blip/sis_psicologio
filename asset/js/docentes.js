(() => {
    const formulario = document.getElementById('formDocente');
    if (!formulario) return;

    const cuenta = formulario.querySelector('#id_usuario');
    const materias = Array.from(formulario.querySelectorAll('.materia-check'));
    const errorMaterias = document.getElementById('errorMaterias');

    // Solo cambia los datos al elegir otra cuenta; al editar conserva los valores cargados.
    cuenta.addEventListener('change', () => {
        const opcion = cuenta.selectedOptions[0];
        for (const nombre of ['nombres', 'apellidos', 'correo', 'telefono']) {
            formulario.elements[nombre].value = cuenta.value ? (opcion?.dataset[nombre] ?? '') : '';
        }
    });

    const tieneMaterias = () => materias.some(materia => materia.checked);
    formulario.addEventListener('submit', evento => {
        const seleccionadas = tieneMaterias();
        errorMaterias.classList.toggle('d-none', seleccionadas);
        if (!seleccionadas) {
            evento.preventDefault();
            materias[0]?.focus();
        }
    });
    materias.forEach(materia => materia.addEventListener('change', () => {
        if (tieneMaterias()) errorMaterias.classList.add('d-none');
    }));
})();
