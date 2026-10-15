(() => {
    const sidebar = document.querySelector('#sidebar-navigation');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-close]');

    if (!sidebar || !toggle || !backdrop) return;

    const compactLayout = window.matchMedia('(max-width: 1199.98px)');
    const menuLinks = () => Array.from(sidebar.querySelectorAll('a[href]'));

    function setMenuOpen(open, returnFocus = false, focusFirstLink = false) {
        const isOpen = Boolean(open && compactLayout.matches);

        sidebar.classList.toggle('active', isOpen);
        sidebar.inert = compactLayout.matches && !isOpen;
        sidebar.setAttribute('aria-hidden', String(compactLayout.matches && !isOpen));
        backdrop.hidden = !isOpen;
        document.body.classList.toggle('sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');

        if (isOpen && focusFirstLink) {
            (menuLinks()[0] || sidebar).focus();
        } else if (!isOpen && returnFocus) {
            toggle.focus();
        }
    }

    toggle.addEventListener('click', () => {
        setMenuOpen(!sidebar.classList.contains('active'), false, true);
    });

    backdrop.addEventListener('click', () => setMenuOpen(false, true));

    sidebar.addEventListener('click', (event) => {
        if (compactLayout.matches && event.target.closest('a[href]')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!compactLayout.matches || !sidebar.classList.contains('active')) return;

        if (event.key === 'Escape') {
            setMenuOpen(false, true);
            return;
        }

        if (event.key !== 'Tab') return;
        const links = menuLinks();
        if (!links.length) return;

        const first = links[0];
        const last = links[links.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('resize', () => setMenuOpen(false));

    setMenuOpen(false);
})();
