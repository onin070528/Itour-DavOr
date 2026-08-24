document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.getElementById('mobile-menu-button');
    const menu = document.getElementById('mobile-menu');
    const iconOpen = document.getElementById('mobile-menu-icon-open');
    const iconClose = document.getElementById('mobile-menu-icon-close');

    if (!menuButton || !menu) {
        return;
    }

    menuButton.addEventListener('click', () => {
        const isOpen = menu.classList.toggle('hidden') === false;

        menuButton.setAttribute('aria-expanded', String(isOpen));
        iconOpen?.classList.toggle('hidden', isOpen);
        iconClose?.classList.toggle('hidden', !isOpen);
    });
});
