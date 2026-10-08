const boton = document.getElementById('ver-password');
const campo = document.getElementById('password');
if (boton && campo) {
    boton.hidden = false;
    boton.addEventListener('click', () => {
        const visible = campo.type === 'password';
        campo.type = visible ? 'text' : 'password';
        boton.setAttribute('aria-pressed', String(visible));
        boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}
