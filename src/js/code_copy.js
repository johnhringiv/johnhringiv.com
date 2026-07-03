// Code copy and reveal answer functionality using event delegation
// Uses data attributes: data-action="copy" data-code="...", data-action="reveal"

// Detect horizontal scrollbars on code blocks and add class for button positioning
function updateScrollbarClasses() {
    document.querySelectorAll('.shiki-container').forEach(container => {
        const pre = container.querySelector('.shiki-pre');
        if (pre) {
            container.classList.toggle('has-scrollbar', pre.scrollWidth > pre.clientWidth);
        }
    });
}

// DOM is ready with defer
updateScrollbarClasses();
window.addEventListener('resize', updateScrollbarClasses);

document.addEventListener('click', function(event) {
    const button = event.target.closest('[data-action]');
    if (!button) return;

    const action = button.dataset.action;

    if (action === 'copy') {
        const encodedCode = button.dataset.code;
        const originalCode = atob(encodedCode);

        navigator.clipboard.writeText(originalCode).then(() => {
            const originalText = button.innerText;
            button.innerText = 'Copied!';
            button.classList.add('copy-success');

            setTimeout(() => {
                button.innerText = originalText;
                button.classList.remove('copy-success');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy code:', err);
            button.innerText = 'Failed!';
            setTimeout(() => {
                button.innerText = 'Copy';
            }, 2000);
        });
    }

    if (action === 'reveal') {
        const answer = button.nextElementSibling;
        if (answer && answer.classList.contains('hidden-answer')) {
            answer.style.display = 'block';
            button.style.display = 'none';
        }
    }
});