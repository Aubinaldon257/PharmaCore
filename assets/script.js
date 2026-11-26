(() => {
    const toggle = document.getElementById('publicMenuToggle');
    const navigation = document.getElementById('publicNav');

    if (!toggle || !navigation) {
        return;
    }

    const fermerMenu = () => {
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
    };

    toggle.addEventListener('click', () => {
        const ouvert = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(ouvert));
        navigation.classList.toggle('is-open', ouvert);
    });

    navigation.querySelectorAll('a').forEach((lien) => {
        lien.addEventListener('click', fermerMenu);
    });

    document.addEventListener('keydown', (evenement) => {
        if (evenement.key === 'Escape') {
            fermerMenu();
            toggle.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) {
            fermerMenu();
        }
    });
})();
