<?php
/**
 * Simplified runtime image functions that use pre-generated images
 * All images are pre-generated during Docker build - no VIPS needed at runtime
 */

// Standard Open Graph dimensions
const OG_WIDTH = 1200;
const OG_HEIGHT = 630;

// Cache directory for pre-generated images
const IMAGE_CACHE_DIR = '/generated/';

// Standard responsive widths for different use cases
const RESPONSIVE_WIDTHS = [
    'standard' => [400, 600, 800, 1200, 1600, 2400],
    'hero' => [576, 768, 1200, 1920, 2880, 3840],
    'column' => [400, 600, 800, 1200],
    'thumbnail' => [200, 400, 600]
];

/**
 * Get the URL for a pre-generated OG image (1200x630)
 * Always returns path to /generated/og_images/ - no fallback
 * Tests verify these files exist with correct dimensions
 */
function ogImage(
    string $source_path,
): string {
    $path_info = pathinfo($source_path);

    // All OG images go to /generated/og_images/ regardless of source location
    $cache_name = sprintf(
        '%s_%dx%d.%s',
        $path_info['filename'],
        OG_WIDTH,
        OG_HEIGHT,
        $path_info['extension'] ?? 'jpg'
    );

    $cache_url = IMAGE_CACHE_DIR . 'og_images/' . $cache_name;

    return $cache_url;
}

/**
 * Generate a responsive image tag using pre-generated sizes
 */
function responsiveImage(
    string $source_path,
    $widths = 'standard',
    string $alt = '',
    string $class = 'img-fluid',
    array $sizes_attr = [],
    array $data_attrs = [],
    string $style = ''
): string {
    // Get widths array from preset
    if (is_string($widths)) {
        $widths = RESPONSIVE_WIDTHS[$widths] ?? RESPONSIVE_WIDTHS['standard'];
    }
    
    $path_info = pathinfo($source_path);
    $source_dir = ltrim(dirname($source_path), '/');
    $cache_subdir = $source_dir ? $source_dir . '/' : '';
    
    $srcset_parts = [];
    
    // Get original image dimensions once
    $abs_source = $_SERVER['DOCUMENT_ROOT'] . $source_path;
    // Cache-bust derived URLs by the SOURCE file's hash. The pre-generated AVIFs
    // are served immutable/long-lived under stable filenames, and their bytes vary
    // by encoder (local ImageMagick vs Docker VIPS), so hashing the output would
    // churn every build. The source hash is stable across encoders and only
    // changes when the base image actually changes — exactly when caches should bust.
    $version = file_exists($abs_source) ? md5_file($abs_source) : 'missing';
    $orig_width = 0;
    if (file_exists($abs_source)) {
        // getimagesize() returns false for vector/unsupported sources (e.g. SVG);
        // guard it so destructuring a bool doesn't warn. Vector sources just skip
        // the original-width fallback and rely on the pre-generated AVIF sizes.
        $dimensions = getimagesize($abs_source);
        if (is_array($dimensions)) {
            $orig_width = $dimensions[0];
        }
    }
    
    // Check for each pre-generated AVIF size
    $last_width = 0;
    foreach ($widths as $width) {
        // Look for AVIF version first
        $avif_cache_name = sprintf(
            '%s_%dw.avif',
            $path_info['filename'],
            $width
        );
        
        $avif_cache_url = IMAGE_CACHE_DIR . $cache_subdir . $avif_cache_name;
        $avif_cache_path = $_SERVER['DOCUMENT_ROOT'] . $avif_cache_url;
        
        if (file_exists($avif_cache_path)) {
            $srcset_parts[] = sprintf('%s?v=%s %dw', $avif_cache_url, $version, $width);
            $last_width = $width;
        } else if ($orig_width > 0) {
            // If original is between last processed width and current requested width,
            // add it to srcset at its actual width
            if ($orig_width > $last_width && $orig_width < $width) {
                $srcset_parts[] = sprintf('%s?v=%s %dw', $source_path, $version, $orig_width);
                $last_width = $orig_width;
            }
        }
    }
    
    // For the src attribute (fallback), always use the original image
    // This ensures browsers that don't support AVIF get a working image
    $fallback_src = $source_path . '?v=' . $version;
    
    // Build sizes attribute
    if (empty($sizes_attr)) {
        $sizes_attr = ['100vw'];
    }
    $sizes_string = implode(', ', $sizes_attr);
    
    // Build the img tag
    $srcset_attr = !empty($srcset_parts) ? sprintf('srcset="%s"', htmlspecialchars(implode(', ', $srcset_parts))) : '';
    $sizes_html = !empty($srcset_parts) ? sprintf('sizes="%s"', htmlspecialchars($sizes_string)) : '';

    // Build data attributes
    $data_attrs_html = '';
    foreach ($data_attrs as $key => $value) {
        $data_attrs_html .= sprintf(' data-%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }

    $style_html = $style !== '' ? sprintf(' style="%s"', htmlspecialchars($style)) : '';

    return sprintf(
        '<img src="%s" %s alt="%s" class="%s" %s%s%s loading="lazy">',
        htmlspecialchars($fallback_src),
        $srcset_attr,
        htmlspecialchars($alt),
        htmlspecialchars($class),
        $sizes_html,
        $data_attrs_html,
        $style_html
    );
}