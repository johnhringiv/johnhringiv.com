# Image Strategy Documentation

## Overview
This project implements a build-time image optimization strategy that pre-generates all responsive image sizes during Docker build, eliminating the need for runtime image processing and the VIPS dependency in production.

## Key Benefits
- **Reduced container size**: ~40MB saved by removing VIPS from runtime container
- **Better performance**: No runtime image processing overhead
- **Modern formats**: AVIF for responsive images (70-80% smaller than JPEG)
- **Progressive enhancement**: Original images as fallback for older browsers

## Image Processing Pipeline

### 1. Build Time (Docker)
During `docker build`, the script `scripts/generate_images_build.php`:
- Scans all PHP files for `responsiveImage()` and `ogImage()` calls
- Generates AVIF versions at predefined widths (400w, 600w, 800w, 1200w, 1600w, 2400w)
- Creates Open Graph images at 1200x630 (keeping original format for social media compatibility)
- Stores all generated images in `/app/www/generated/`

### 2. Runtime
The runtime uses `www/includes/image-resizer.php` which:
- Serves pre-generated AVIF images via srcset
- Falls back to original images for browsers without AVIF support
- Includes original images in srcset when they fall between generated sizes

## Image Formats

### Responsive Images (AVIF)
- **Format**: AVIF
- **Quality settings**:
  - 400w and below: 94% quality
  - 400w-1600w: 92% quality  
  - 1600w+: 90% quality
- **Compression**: 70-80% smaller than JPEG at comparable visual quality
- **Usage**: All responsive images in srcset

### Open Graph Images  
- **Format**: Original (JPEG/PNG)
- **Dimensions**: Fit within 1200x630 while maintaining aspect ratio
- **Quality**: 85% for JPEG
- **Reason**: Social platforms don't support AVIF

### Original Images (Fallback)
- **Purpose**: Fallback for browsers without AVIF support
- **Usage**: 
  - `src` attribute (fallback)
  - Added to srcset when size falls between generated widths
  - Example: 1143px original included at "1143w" between 800w and 1200w AVIF

## Implementation Details

### PHP Functions

```php
// Generate responsive image with srcset
responsiveImage(
    '/img/photo.jpg',      // source path
    'standard',            // width preset or array
    'Description',         // alt text
    'img-fluid',          // CSS classes
    ['(min-width: 768px) 50vw', '100vw']  // sizes attribute
)

// Generate Open Graph image
ogImage('/img/og-preview.jpg')
```

### Sizes Attribute for Bootstrap Grid
The sizes attribute should match Bootstrap column widths for optimal image selection:

```php
// Bootstrap column width mappings (768px = md breakpoint)
'col-md-3' => ['(min-width: 768px) 25vw', '100vw']  // 25% width
'col-md-4' => ['(min-width: 768px) 33vw', '100vw']  // 33% width  
'col-md-5' => ['(min-width: 768px) 42vw', '100vw']  // 42% width
'col-md-6' => ['(min-width: 768px) 50vw', '100vw']  // 50% width
'col-md-8' => ['(min-width: 768px) 67vw', '100vw']  // 67% width

// Examples:
responsiveImage($path, 'column', 'Alt text', 'img-fluid', 
    ['(min-width: 768px) 42vw', '100vw']); // for col-md-5

responsiveImage($path, 'standard', 'Alt text', 'img-fluid', 
    ['(min-width: 768px) 50vw', '100vw']); // for col-md-6
```

**Key Points:**
- `768px` corresponds to Bootstrap's `md` breakpoint
- Below 768px: images are full-width (`100vw`) as columns stack
- Above 768px: images match their column percentage of viewport width
- Helps browser select appropriately sized images from srcset

**Known Limitation:**
- Current sizes attributes assume columns take full viewport width percentages
- Doesn't account for Bootstrap container margins that center content
- On wide screens, actual image width is smaller than calculated viewport percentage
- This results in slightly oversized images being selected, which is acceptable
- Alternative would be complex breakpoint-specific calculations accounting for container max-widths

### Interactive Features

#### Image Modal System
Images with the `image-modal-content` class enable click-to-zoom functionality:
- Click image to open full-size version in modal overlay
- Modal displays `img.src` (already full-resolution) by default
- Optional `data-modal-src` attribute can specify different modal image
- Preloading on hover improves performance
- Implemented via lightweight JavaScript without dependencies
- No additional classes needed since `img.src` serves the original image

#### Zoom-Aware Image Swapping
Automatic high-resolution image swapping when users zoom:
- **Mobile**: Detects pinch zoom via Visual Viewport API (scale ≥ 1.1x)
- **Desktop**: Detects Ctrl+wheel and Ctrl+Plus zoom gestures
- **Behavior**: One-way upgrade from responsive to original high-res images
- **Optimization**: Only swaps images currently visible in viewport
- **Performance**: Never downgrades back to responsive versions
- **Implementation**: `zoom_detection.js` loaded globally via footer
- **Trigger**: Removes `srcset` and forces original `src` on zoom detection
- **Visual feedback**: Adds `zoomed-high-res` class for potential CSS styling

This ensures users get crisp, full-resolution images during zoom operations without manually implementing fallbacks for each responsive image.

### Width Presets
- `standard`: [400, 600, 800, 1200, 1600, 2400]
- `hero`: [576, 768, 1200, 1920, 2880, 3840]
- `column`: [400, 600, 800, 1200]
- `thumbnail`: [200, 400, 600]

### Directory Structure
```
www/
├── img/                    # Original images
│   ├── blog/
│   └── abstracts/
└── generated/             # Build-time generated images
    └── img/
        ├── blog/
        │   ├── image_400w.avif
        │   ├── image_600w.avif
        │   └── image_1200x630.jpg  # OG image
        └── abstracts/
```

## Docker Build Process

### Dependencies (Build Stage)
```dockerfile
RUN apk --no-cache add php83 php83-pecl-vips git bash \
    libheif libheif-dev aom-libs  # AVIF support
```

### Image Generation
```dockerfile
# Pre-generate all responsive image sizes
RUN php scripts/generate_images_build.php
```

### Runtime Container
- No VIPS dependency
- Only serves pre-generated images
- Lightweight Alpine base (~40MB total)

## Browser Support

### Modern Browsers (AVIF Support)
- Chrome 85+
- Firefox 93+
- Safari 16+ (macOS 13+)
- Receive highly optimized AVIF images

### Legacy Browsers
- Automatically receive original JPEG/PNG via `src` fallback
- Full functionality maintained

## Performance Metrics

### File Size Comparisons
- **Original JPEG**: 1.18 MB (self_pic.jpg)
- **400w AVIF**: 28 KB (97.6% reduction)
- **800w AVIF**: 97 KB (91.8% reduction)
- **1200w AVIF**: 266 KB (77.4% reduction)

### Size Scaling
- 1200w → 1600w: ~25-45% size increase
- Getting 78% more pixels for 25-45% more file size

## Best Practices

### Adding New Images
1. Place original in appropriate `www/img/` subdirectory
2. Use `responsiveImage()` or `ogImage()` in PHP
3. Rebuild Docker image to generate sizes
4. Verify srcset in browser DevTools

### Image Guidelines
- **Photos**: Use JPEG originals
- **Screenshots**: Use PNG for text clarity
- **Graphics**: Use PNG for transparency
- **OG Images**: Design at 1200x630 or larger

### Quality Considerations
- Text-heavy screenshots benefit from higher quality settings
- Current settings (92-94%) optimized for text clarity
- Natural photos can use lower quality (85-90%)

## Troubleshooting

### Images Not Generating
1. Check script output during Docker build
2. Verify VIPS extension loaded: `php -m | grep vips`
3. Ensure source images exist in `www/img/`

### Srcset Not Working
1. Check browser AVIF support
2. Verify generated files exist in container
3. Check console for 404 errors

### Soft/Blurry Images
- Increase quality settings in `generate_images_build.php`
- Current: 92% base, 94% small, 90% large
- Consider using PNG for text-heavy images

## Future Considerations

### Potential Optimizations
- WebP fallback for better legacy browser support
- Dynamic quality based on image content analysis
- Lazy generation for rarely accessed images
- CDN integration for generated images

### Format Migration
- Monitor JPEG XL adoption
- Consider WebP as intermediate format
- Evaluate AVIF encoder improvements