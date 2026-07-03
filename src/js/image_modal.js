// all images inside the image modal content class OR images with the class directly
const lightboxImages = document.querySelectorAll('.image-modal-content img, img.image-modal-content');

// dynamically selects all elements inside modal popup
const modalElement = element =>
    document.querySelector(`.image-modal-popup ${element}`);

const body = document.querySelector('body');
const modalPopup = document.querySelector('.image-modal-popup');

// Track the element that triggered the modal for focus restoration
let triggeringElement = null;

// Only set up modal functionality if the modal element exists and viewport is desktop-sized
if (modalPopup && window.innerWidth >= 768) {
    // Add ARIA attributes to modal
    modalPopup.setAttribute('role', 'dialog');
    modalPopup.setAttribute('aria-modal', 'true');
    modalPopup.setAttribute('aria-hidden', 'true');
    modalPopup.setAttribute('id', 'image-modal-popup');

    // Add ARIA attributes to close button
    const closeButton = modalPopup.querySelector('.image-modal-close');
    if (closeButton) {
        closeButton.setAttribute('role', 'button');
        closeButton.setAttribute('tabindex', '0');
        closeButton.setAttribute('aria-label', 'Close modal');
        closeButton.setAttribute('aria-controls', 'image-modal-popup');
    }

    // Function to close modal
    const closeModal = () => {
        body.style.overflow = 'auto';
        modalPopup.style.display = 'none';
        modalPopup.setAttribute('aria-hidden', 'true');

        // Restore focus to triggering element
        if (triggeringElement) {
            triggeringElement.focus();
            triggeringElement = null;
        }
    };

    // Keyboard support - close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalPopup.style.display === 'block') {
            closeModal();
        }
    });

    // closes modal on clicking anywhere and adds overflow back
    document.addEventListener('click', closeModal);

    // Track which images have been preloaded to avoid redundant fetches
    const preloadedImages = new WeakSet();

    // loops over each modal content img and adds click event functionality
    lightboxImages.forEach(img => {
    const data = img.dataset;

    // Preload full-size image on hover (only once per image)
    img.addEventListener('mouseenter', () => {
        if (!preloadedImages.has(img)) {
            const tempImg = new Image();
            tempImg.src = img.dataset.modalSrc || img.src;
            preloadedImages.add(img);
        }
    });

    img.addEventListener('click', e => {
        // Store triggering element for focus restoration
        triggeringElement = img;

        body.style.overflow = 'hidden';
        e.stopPropagation();
        modalPopup.style.display = 'block';
        modalPopup.setAttribute('aria-hidden', 'false');

        if(typeof data.title !== "undefined") {
            modalElement('h2').textContent = data.title;
        } else {
            modalElement('h2').textContent = '';
        }
        if(typeof data.description !== "undefined") {
            modalElement('p').textContent = data.description;
        } else {
            modalElement('p').textContent = '';
        }
        // Use modal image if available (from data-modal-src), otherwise use current src
        modalElement('img').src = img.dataset.modalSrc || img.src;

        // Move focus to modal for keyboard navigation
        modalPopup.focus();
    });
});
}