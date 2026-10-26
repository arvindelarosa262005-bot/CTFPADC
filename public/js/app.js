const menuButton = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');

if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    });

    document.addEventListener('click', (event) => {
        if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) {
            sidebar.classList.remove('is-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation');
        }
    });
}

document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.copyTarget);

        if (!input) {
            return;
        }

        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand('copy');
        }

        button.textContent = 'Copied';
        window.setTimeout(() => {
            button.textContent = 'Copy code';
        }, 1600);
    });
});
