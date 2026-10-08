document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.menuToggle);

            if (target) {
                target.classList.toggle('hidden');
                button.setAttribute('aria-expanded', String(! target.classList.contains('hidden')));
            }
        });
    });

    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const container = repeater.querySelector('[data-repeater-items]');
        const template = repeater.querySelector('template');
        const addButton = repeater.querySelector('[data-repeater-add]');
        let nextIndex = container.children.length;

        addButton?.addEventListener('click', () => {
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        });

        repeater.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-repeater-remove]');
            if (removeButton && container.children.length > 1) {
                removeButton.closest('[data-repeater-row]').remove();
            }
        });
    });
});
