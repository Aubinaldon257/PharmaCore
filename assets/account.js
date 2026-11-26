(() => {
    const accountMenu = document.querySelector('[data-account-menu]');

    if (accountMenu) {
        const trigger = accountMenu.querySelector('.account-menu-trigger');
        const panel = accountMenu.querySelector('.account-menu-panel');

        if (trigger && panel) {
            const closeAccountMenu = () => {
                panel.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            };

            trigger.addEventListener('click', () => {
                const isOpen = !panel.hidden;
                panel.hidden = isOpen;
                trigger.setAttribute('aria-expanded', String(!isOpen));
            });
            panel.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', closeAccountMenu);
            });

            document.addEventListener('click', (event) => {
                if (!accountMenu.contains(event.target)) {
                    closeAccountMenu();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeAccountMenu();
                    trigger.focus();
                }
            });
        }
    }

    const avatarElements = document.querySelectorAll('[data-account-avatar][data-account-id]');
    const avatarChoices = ['leaf', 'plus', 'star', 'sun', 'heart', 'shield'];
    const readAvatar = (accountId) => {
        try {
            return window.localStorage.getItem(`pharmacore.avatar.${accountId}`);
        } catch (error) {
            return null;
        }
    };
    const writeAvatar = (accountId, value) => {
        try {
            window.localStorage.setItem(`pharmacore.avatar.${accountId}`, value);
            return true;
        } catch (error) {
            return false;
        }
    };

    avatarElements.forEach((avatar) => {
        const accountId = avatar.dataset.accountId;
        if (!accountId) {
            return;
        }
        const selectedAvatar = readAvatar(accountId);
        if (selectedAvatar && avatarChoices.includes(selectedAvatar)) {
            avatar.dataset.avatar = selectedAvatar;
        }
    });

    const avatarPicker = document.querySelector('[data-avatar-picker]');
    if (avatarPicker) {
        const accountId = avatarPicker.dataset.accountId;
        const status = document.getElementById('avatarStatus');
        const savedAvatar = accountId ? readAvatar(accountId) : null;
        const selectedInput = savedAvatar
            ? avatarPicker.querySelector(`input[value="${savedAvatar}"]`)
            : null;
        if (selectedInput instanceof HTMLInputElement) {
            selectedInput.checked = true;
        } else if (status && accountId && savedAvatar === null) {
            status.textContent = 'Le stockage local est indisponible ou aucun avatar personnalisé n’a été enregistré. Les initiales sont affichées.';
        }

        avatarPicker.addEventListener('change', (event) => {
            const selected = event.target;
            if (!(selected instanceof HTMLInputElement) || !selected.checked || !accountId) {
                return;
            }
            if (!avatarChoices.includes(selected.value) || !writeAvatar(accountId, selected.value)) {
                if (status) {
                    status.textContent = 'Le navigateur n’a pas pu enregistrer ce choix. Les initiales restent affichées.';
                }
                return;
            }

            avatarElements.forEach((avatar) => {
                if (avatar.dataset.accountId === accountId) {
                    avatar.dataset.avatar = selected.value;
                }
            });
            if (status) {
                status.textContent = 'Votre avatar a été mis à jour sur cet appareil.';
            }
        });
    }
})();
