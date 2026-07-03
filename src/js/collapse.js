// Collapse toggler with smooth animation
// Uses Bootstrap's CSS classes: collapse, collapsing, show
// Uses data attributes: data-bs-toggle="collapse", data-bs-target="#id"

function toggleCollapse(target, button, show) {
    // Don't interrupt ongoing animation
    if (target.classList.contains('collapsing')) return;

    const isOpen = target.classList.contains('show');
    if (show === isOpen) return; // Already in desired state

    if (isOpen) {
        // Closing: set explicit height, then animate to 0
        target.style.height = target.scrollHeight + 'px';
        target.classList.remove('collapse', 'show');
        target.classList.add('collapsing');

        // Force reflow, then set height to 0
        target.offsetHeight;
        target.style.height = '';

        target.addEventListener('transitionend', function handler() {
            target.removeEventListener('transitionend', handler);
            target.classList.remove('collapsing');
            target.classList.add('collapse');
            button?.setAttribute('aria-expanded', 'false');
        });
    } else {
        // Opening: animate from 0 to scrollHeight
        target.classList.remove('collapse');
        target.classList.add('collapsing');
        target.style.height = target.scrollHeight + 'px';

        target.addEventListener('transitionend', function handler() {
            target.removeEventListener('transitionend', handler);
            target.classList.remove('collapsing');
            target.classList.add('collapse', 'show');
            target.style.height = '';
            button?.setAttribute('aria-expanded', 'true');
        });
    }
}

document.addEventListener("click", function(event) {
    // Check if clicked element (or parent) is a collapse toggle
    const button = event.target.closest('[data-bs-toggle="collapse"]');
    if (button) {
        const target = document.querySelector(button.dataset.bsTarget);
        if (target) {
            const isOpen = target.classList.contains('show');
            toggleCollapse(target, button, !isOpen);
        }
        return;
    }

    // Close navbar when clicking nav links (mobile UX)
    if (event.target.classList.contains("nav-link")) {
        const navbarCollapse = document.querySelector('.navbar-collapse.show');
        if (navbarCollapse) {
            const button = document.querySelector('[data-bs-target="#' + navbarCollapse.id + '"]');
            toggleCollapse(navbarCollapse, button, false);
        }
    }
});