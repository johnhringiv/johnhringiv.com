/**
 * Zoom Detection for High-Resolution Image Swapping
 * 
 * Detects when users pinch to zoom on mobile/desktop and swaps responsive images 
 * to their original high-resolution versions for better quality during zoom.
 */

class ZoomDetector {
    constructor() {
        this.isZoomed = false;
        this.zoomThreshold = 1.1; // Trigger at 110% zoom
        this.debounceDelay = 100; // ms
        this.debounceTimer = null;
        
        // Track original image sources and zoom state
        this.imageBackups = new Map();
        
        this.init();
    }

    init() {
        // Listen for keyboard zoom events (Ctrl+Plus, Ctrl+Minus)
        window.addEventListener('keydown', this.handleKeyZoom.bind(this));
        
        // Also listen for Ctrl+wheel zoom events (works on all browsers)
        window.addEventListener('wheel', this.handleWheelZoom.bind(this), { passive: false });
        
        // Initialize image tracking
        this.trackImages();
        
        // Re-track images when DOM changes (e.g., modal opens)
        const observer = new MutationObserver(() => {
            this.trackImages();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Add Visual Viewport API support if available (for mobile pinch zoom)
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', this.handleViewportChange.bind(this));
        }
    }

    handleViewportChange() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            const scale = window.visualViewport.scale || 1;
            this.handleZoomChange(scale);
        }, this.debounceDelay);
    }

    handleKeyZoom(event) {
        // Detect Ctrl+Plus (zoom in) - covers various plus key codes
        if (event.ctrlKey && (event.key === '+' || event.key === '=' || event.code === 'Equal' || event.code === 'NumpadAdd')) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                if (!this.isZoomed) {
                    this.isZoomed = true;
                    this.swapToHighRes();
                }
            }, this.debounceDelay);
        }
    }

    handleWheelZoom(event) {
        // Detect Ctrl+wheel zoom on desktop
        if (event.ctrlKey) {
            // For desktop zoom, we can't rely on devicePixelRatio or visual viewport scale
            // Instead, we assume any Ctrl+wheel means the user is zooming and trigger high-res
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                if (!this.isZoomed) {
                    this.isZoomed = true;
                    this.swapToHighRes();
                }
            }, this.debounceDelay);
        }
    }

    handleZoomChange(scale) {
        const wasZoomed = this.isZoomed;
        this.isZoomed = scale >= this.zoomThreshold;

        // Only swap to high-res when zoom starts - never swap back
        if (!wasZoomed && this.isZoomed) {
            this.swapToHighRes();
        }
    }

    trackImages() {
        // Track all responsive images that have srcset (not just modal images)
        const responsiveImages = document.querySelectorAll('img[srcset]');
        
        responsiveImages.forEach(img => {
            if (!this.imageBackups.has(img)) {
                // Store original srcset, src, and sizes for restoration
                this.imageBackups.set(img, {
                    originalSrcset: img.srcset,
                    originalSrc: img.src,
                    originalSizes: img.sizes || '',
                    currentSrc: img.currentSrc || img.src
                });
            }
        });
    }

    swapToHighRes() {
        this.imageBackups.forEach((backup, img) => {
            // Check if image is currently visible in viewport
            if (this.isImageInViewport(img)) {
                // Remove srcset to force use of src (which is always the original)
                img.removeAttribute('srcset');
                img.removeAttribute('sizes');
                
                // Ensure we're using the highest quality source
                // The src attribute should already be the original from responsiveImage()
                if (backup.originalSrc) {
                    img.src = backup.originalSrc;
                }
                
                // Add a class for potential CSS styling
                img.classList.add('zoomed-high-res');
            }
        });
    }


    isImageInViewport(img) {
        const rect = img.getBoundingClientRect();
        const viewport = window.visualViewport || window;
        
        return (
            rect.bottom >= 0 &&
            rect.right >= 0 &&
            rect.top <= (viewport.height || window.innerHeight) &&
            rect.left <= (viewport.width || window.innerWidth)
        );
    }
}

// Initialize zoom detection (DOM is ready with defer)
new ZoomDetector();