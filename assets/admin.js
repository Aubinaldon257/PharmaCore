(() => {
    const menuButton = document.getElementById('mobileMenuButton');
    const collapseButton = document.getElementById('desktopCollapseButton');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');
    const appShell = document.querySelector('.app-shell');
    if (collapseButton && appShell) {
        collapseButton.addEventListener('click', () => {
            const collapsed = appShell.classList.toggle('is-sidebar-collapsed');
            collapseButton.setAttribute('aria-expanded', String(!collapsed));
            collapseButton.setAttribute('aria-label', collapsed ? 'Développer la barre latérale' : 'Réduire la barre latérale');
        });
    }

    if (!menuButton || !sidebar || !scrim) {
        return;
    }

    const closeMenu = () => {
        sidebar.classList.remove('is-open');
        scrim.classList.remove('is-visible');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
    };

    menuButton.addEventListener('click', () => {
        const open = sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-visible', open);
        menuButton.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('menu-open', open);
    });

    scrim.addEventListener('click', closeMenu);
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
})();
