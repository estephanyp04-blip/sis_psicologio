const boton = document.getElementById('ver-password');
const campo = document.getElementById('password');
if (boton && campo) {
    boton.hidden = false;
    boton.addEventListener('click', () => {
        const visible = campo.type === 'password';
        campo.type = visible ? 'text' : 'password';
        boton.textContent = visible ? 'Ocultar' : 'Mostrar';
        boton.setAttribute('aria-pressed', String(visible));
    });
}
