<?php
/**
 * Build-time image generation script with full VIPS functionality
 * This runs during Docker build to pre-generate all image sizes
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set document root - works both locally and in Docker
$_SERVER['DOCUMENT_ROOT'] = realpath('www');

if (!$_SERVER['DOCUMENT_ROOT']) {
    echo "ERROR: Could not determine document root\n";
    exit(1);
}

echo "Document root set to: " . $_SERVER['DOCUMENT_ROOT'] . "\n";

// Import image configuration constants from the runtime file
$include_file = $_SERVER['DOCUMENT_ROOT'] . '/includes/image-resizer.php';
if (!file_exists($include_file)) {
    echo "ERROR: Could not find image-resizer.php at: $include_file\n";
    exit(1);
}
require_once $include_file;
echo "Successfully loaded image-resizer.php\n";

/**
 * Resize an image to fit within specified dimensions using VIPS
 * Build-time version with VIPS support
 */
function buildResizeImage(
    string $source_path,
    int $width = OG_WIDTH,
    int $height = OG_HEIGHT
): string {
    if (!extension_loaded('vips')) {
        echo "ERROR: VIPS extension not loaded\n";
        return $source_path;
    }

    $abs_source = $_SERVER['DOCUMENT_ROOT'] . $source_path;

    if (!file_exists($abs_source)) {
        echo "ERROR: Source file not found: $source_path\n";
        return $source_path;
    }

    // Generate cache filename
    $path_info = pathinfo($source_path);

    // All OG images go to /generated/og_images/ regardless of source location
    $cache_name = sprintf(
        '%s_%dx%d.%s',
        $path_info['filename'],
        $width,
        $height,
        $path_info['extension'] ?? 'jpg'
    );

    // Create dedicated og_images directory
    $abs_cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/generated/og_images/';
    if (!is_dir($abs_cache_dir)) {
        mkdir($abs_cache_dir, 0755, true);
    }

    $cache_path = $abs_cache_dir . $cache_name;
    $cache_url = IMAGE_CACHE_DIR . 'og_images/' . $cache_name;

    // Skip if cached version exists and is at least as new as the source
    if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($abs_source)) {
        return $cache_url;
    }

    try {
        // Load image with vips
        $result = vips_image_new_from_file($abs_source);
        if (!$result || !isset($result['out'])) {
            throw new Exception("Failed to load image from $abs_source");
        }
        $image = $result['out'];

        // Get image dimensions
        $orig_width = vips_image_get($image, 'width')['out'];
        $orig_height = vips_image_get($image, 'height')['out'];

        // Calculate scaling to fit within bounds while preserving aspect ratio
        $x_scale = $width / $orig_width;
        $y_scale = $height / $orig_height;
        $scale = min($x_scale, $y_scale);

        // Don't upscale - if image is smaller than target, return original
        if ($scale > 1) {
            return $source_path;
        }

        // Resize image maintaining aspect ratio
        $result = vips_call('resize', $image, $scale);
        $resized = $result['out'];

        // Save OG images in original format (JPEG/PNG) for social media compatibility
        if (in_array(strtolower($path_info['extension'] ?? ''), ['png', 'webp'])) {
            vips_image_write_to_file($resized, $cache_path, [
                'strip' => true  // Strip metadata
            ]);
        } else {
            // Default to JPEG for photos with optimization
            vips_image_write_to_file($resized, $cache_path, [
                'Q' => 85,  // JPEG quality for OG images
                'strip' => true,  // Strip metadata
                'optimize_coding' => true,  // Optimize Huffman coding tables
                'interlace' => true  // Progressive JPEG
            ]);
        }

        return $cache_url;

    } catch (\Exception $e) {
        echo "ERROR resizing image: " . $e->getMessage() . "\n";
        return $source_path;
    }
}

/**
 * Resize an image to a specific width, maintaining aspect ratio
 */
function buildResizeImageByWidth(string $source_path, int $width): ?string {
    if (!extension_loaded('vips')) {
        return null;
    }
    
    $abs_source = $_SERVER['DOCUMENT_ROOT'] . $source_path;
    
    if (!file_exists($abs_source)) {
        return null;
    }
    
    // Generate cache filename
    $path_info = pathinfo($source_path);
    $source_dir = ltrim(dirname($source_path), '/');
    $cache_subdir = $source_dir ? $source_dir . '/' : '';
    
    $cache_name = sprintf(
        '%s_%dw.avif',
        $path_info['filename'],
        $width
    );
    
    // IMAGE_CACHE_DIR starts with / so we need to handle it properly
    $abs_cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/generated/' . $cache_subdir;
    if (!is_dir($abs_cache_dir)) {
        mkdir($abs_cache_dir, 0755, true);
    }
    
    $cache_path = $abs_cache_dir . $cache_name;
    $cache_url = IMAGE_CACHE_DIR . $cache_subdir . $cache_name;
    
    // Return cached version if it exists and is at least as new as the source
    if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($abs_source)) {
        return $cache_url;
    }
    
    try {
        // Vector sources (SVG title cards) carry crisp text — render them at 2x and
        // downscale so antialiasing is supersampled rather than soft.
        $is_vector = strtolower($path_info['extension'] ?? '') === 'svg';

        // Load image with vips
        $result = vips_image_new_from_file($abs_source, $is_vector ? ['scale' => 2] : []);
        if (!$result || !isset($result['out'])) {
            throw new Exception("Failed to load image from $abs_source");
        }
        $image = $result['out'];

        // Get image dimensions
        $orig_width = vips_image_get($image, 'width')['out'];

        // Calculate scale
        $scale = $width / $orig_width;

        // Don't upscale or recompress at same size - use original
        if ($scale >= 1) {
            return $source_path;
        }

        // Resize image
        $result = vips_call('resize', $image, $scale);
        $resized = $result['out'];

        // Determine quality based on size - higher for AVIF to maintain visual quality
        // Increased quality for better text clarity in screenshots
        $quality = 92;  // Base quality for AVIF
        if ($width <= 400) {
            $quality = 94; // Higher quality for small images
        } elseif ($width >= 1600) {
            $quality = 90; // Slightly lower for very large images
        }

        // AVIF save options. For vector sources, push quality and disable chroma
        // subsampling (4:4:4) so colored text edges stay sharp instead of soft.
        $save_opts = [
            'Q' => $quality,  // AVIF quality (85-90 range for good quality)
            'effort' => 4,  // Encoding effort (0-9, 4 is a good balance)
            'strip' => true  // Strip metadata
        ];
        if ($is_vector) {
            $save_opts['Q'] = 95;
            $save_opts['subsample_mode'] = 'off';
            $save_opts['effort'] = 6;
        }
        vips_image_write_to_file($resized, $cache_path, $save_opts);

        return $cache_url;
        
    } catch (\Exception $e) {
        echo "ERROR resizing by width: " . $e->getMessage() . "\n";
        return null;
    }
}

/** 
 * Generate all responsive sizes for an image
 */
function buildPregenerateResponsiveSizes(string $source_path, $widths = 'standard'): array {
    if (is_string($widths)) {
        $widths = RESPONSIVE_WIDTHS[$widths] ?? RESPONSIVE_WIDTHS['standard'];
    }
    
    $generated = [];
    
    foreach ($widths as $width) {
        $url = buildResizeImageByWidth($source_path, $width);
        if ($url) {
            $generated[$width] = $url;
        }
    }
    
    return $generated;
}

// ============= MAIN SCRIPT =============

echo "=== Image Generation Build Script ===\n\n";

// Check VIPS is available
if (!extension_loaded('vips')) {
    echo "ERROR: VIPS extension is required for build-time image generation\n";
    echo "Available extensions: " . implode(', ', get_loaded_extensions()) . "\n";
    exit(1);
}

echo "✓ VIPS extension loaded\n";
echo "VIPS version: " . phpversion('vips') . "\n";
echo "✓ Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "✓ Cache directory: " . IMAGE_CACHE_DIR . "\n\n";

// Find all PHP files and scan for image processing functions
echo "=== Scanning PHP files for image usage ===\n\n";

$php_files = glob('www/*.php');
echo "Found " . count($php_files) . " PHP files in www/\n";
$include_files = glob('www/includes/*.php');
echo "Found " . count($include_files) . " PHP files in www/includes/\n";
$php_files = array_merge($php_files, $include_files);
echo "Total PHP files to scan: " . count($php_files) . "\n\n";

$responsive_images = [];
$og_images = [];

// Load images from database (paths are already normalized by build_db.php)
echo "=== Loading images from database ===\n\n";

$db = new SQLite3(__DIR__ . '/../www/generated/site.db');

// Research and press images
foreach (['research', 'press'] as $table) {
    echo "Loading: $table table\n";
    $results = $db->query("SELECT image FROM $table WHERE image IS NOT NULL");

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $image_path = $row['image'];

        // Skip 'in_a_flash' images (special handling)
        if (strpos($image_path, 'in_a_flash') !== false) {
            echo "  Skipping $image_path (has special handling)\n";
            continue;
        }

        $preset = 'column';
        $key = $image_path . ':' . $preset;
        if (!isset($responsive_images[$key])) {
            $responsive_images[$key] = [
                'path' => $image_path,
                'preset' => $preset,
                'used_in' => []
            ];
        }
        $responsive_images[$key]['used_in'][] = $table;
        echo "  Found image: $image_path (preset: $preset)\n";
    }
}

// Blog post OG images and blog_images
echo "Loading: blog_posts table\n";
$results = $db->query('SELECT slug, og_image, blog_image FROM blog_posts');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $image_path = $row['og_image'];
    if (!isset($og_images[$image_path])) {
        $og_images[$image_path] = ['path' => $image_path, 'used_in' => []];
    }
    $og_images[$image_path]['used_in'][] = $row['slug'];
    echo "  Found OG image: $image_path\n";

    if (!empty($row['blog_image'])) {
        $blog_img = $row['blog_image'];
        $preset = 'column';
        $key = $blog_img . ':' . $preset;
        if (!isset($responsive_images[$key])) {
            $responsive_images[$key] = [
                'path' => $blog_img,
                'preset' => $preset,
                'used_in' => []
            ];
        }
        $responsive_images[$key]['used_in'][] = $row['slug'];
        echo "  Found blog image: $blog_img (preset: $preset)\n";
    }
}

echo "\n";

foreach ($php_files as $file) {
    echo "Scanning: $file\n";
    $content = file_get_contents($file);
    $relative_file = str_replace('www/', '', $file);
    
    // Find responsiveImage() calls
    if (preg_match_all('/responsiveImage\s*\(\s*[\'"]([^\'",]+)[\'"],\s*[\'"]?(\w+)[\'"]?/i', $content, $matches, PREG_SET_ORDER)) {
        echo "  Found " . count($matches) . " responsiveImage() calls\n";
        foreach ($matches as $match) {
            $image_path = $match[1];
            $preset = $match[2] ?? 'standard';
            
            // Normalize path
            if (!str_starts_with($image_path, '/')) {
                $image_path = '/' . $image_path;
            }
            
            $key = $image_path . ':' . $preset;
            if (!isset($responsive_images[$key])) {
                $responsive_images[$key] = [
                    'path' => $image_path,
                    'preset' => $preset,
                    'used_in' => []
                ];
            }
            $responsive_images[$key]['used_in'][] = $relative_file;
        }
    }
    
    // Find PageInfo og_image parameters (OG images)
    if (preg_match_all('/og_image:\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
        echo "  Found " . count($matches[1]) . " PageInfo og_image parameters\n";
        foreach ($matches[1] as $image_path) {
            // Normalize path
            if (!str_starts_with($image_path, '/')) {
                $image_path = '/' . $image_path;
            }
            
            if (!isset($og_images[$image_path])) {
                $og_images[$image_path] = [
                    'path' => $image_path,
                    'used_in' => []
                ];
            }
            $og_images[$image_path]['used_in'][] = $relative_file;
        }
    }
}

echo "Found " . count($responsive_images) . " responsive images\n";
echo "Found " . count($og_images) . " OG images\n";

$total_generated = 0;
$errors = 0;

// Process responsive images
echo "\n=== Generating Responsive Image Sizes ===\n\n";

foreach ($responsive_images as $image_info) {
    $source_path = $image_info['path'];
    $preset = $image_info['preset'];
    $used_in = implode(', ', array_unique($image_info['used_in']));
    
    echo "Processing: $source_path (preset: $preset)\n";
    echo "  Used in: $used_in\n";
    
    // Check if source exists
    $abs_source_check = $_SERVER['DOCUMENT_ROOT'] . $source_path;
    if (!file_exists($abs_source_check)) {
        echo "  ⚠ WARNING: Source file not found at: $abs_source_check\n";
        $errors++;
        continue;
    }
    echo "  ✓ Source file exists: $abs_source_check\n";
    
    // Generate all sizes for this preset
    $generated = buildPregenerateResponsiveSizes($source_path, $preset);
    
    if (!empty($generated)) {
        foreach ($generated as $width => $url) {
            if ($url === $source_path) {
                echo "  ℹ Using original for {$width}w (no recompression needed)\n";
            } else {
                echo "  ✓ Generated: {$width}w -> $url\n";
                $total_generated++;
            }
        }
    } else {
        echo "  ⚠ No sizes generated (image may be too small)\n";
    }
}

// Process OG images
echo "\n=== Generating Open Graph Images (1200x630) ===\n\n";

foreach ($og_images as $image_info) {
    $source_path = $image_info['path'];
    $used_in = implode(', ', array_unique($image_info['used_in']));

    echo "Processing OG: $source_path\n";
    echo "  Used in: $used_in\n";

    // Check if source is SVG - export directly to final location, skip VIPS
    if (str_ends_with(strtolower($source_path), '.svg')) {
        $svg_abs_path = $_SERVER['DOCUMENT_ROOT'] . $source_path;

        if (!file_exists($svg_abs_path)) {
            echo "  ⚠ WARNING: SVG source file not found, skipping\n";
            $errors++;
            continue;
        }

        // Calculate final OG image path in /generated/og_images/
        $path_info = pathinfo($source_path);
        $final_filename = sprintf('%s_%dx%d.png', $path_info['filename'], OG_WIDTH, OG_HEIGHT);
        $final_path = '/generated/og_images/' . $final_filename;
        $final_abs_path = $_SERVER['DOCUMENT_ROOT'] . $final_path;

        // Ensure output directory exists
        $output_dir = dirname($final_abs_path);
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        // Check if PNG needs regeneration (doesn't exist or SVG is newer)
        $needs_generation = !file_exists($final_abs_path);
        if (!$needs_generation && filemtime($svg_abs_path) > filemtime($final_abs_path)) {
            $needs_generation = true;
        }

        if ($needs_generation) {
            echo "  → Converting SVG to PNG using Inkscape...\n";

            // Export directly to final location at 1200px width
            $cmd = sprintf(
                'inkscape %s --export-filename=%s --export-width=1200 2>&1',
                escapeshellarg($svg_abs_path),
                escapeshellarg($final_abs_path)
            );

            exec($cmd, $output, $return_code);

            if ($return_code !== 0) {
                echo "  ✗ ERROR: Inkscape conversion failed\n";
                echo "  Command: $cmd\n";
                echo "  Output: " . implode("\n", $output) . "\n";
                $errors++;
                continue;
            }

            if (!file_exists($final_abs_path)) {
                echo "  ✗ ERROR: PNG was not generated\n";
                $errors++;
                continue;
            }

            echo "  ✓ Generated OG image: $final_path\n";
            $total_generated++;
        } else {
            echo "  ℹ OG image already exists and is up-to-date: $final_path\n";
        }

        // Skip VIPS processing for SVG sources
        continue;
    }

    // Process raster images with VIPS (creates optimized version in /generated/)
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $source_path)) {
        echo "  ⚠ WARNING: Source file not found, skipping\n";
        $errors++;
        continue;
    }

    $og_url = buildResizeImage($source_path);
    if ($og_url !== $source_path) {
        echo "  ✓ Optimized: $og_url\n";
        $total_generated++;
    } else {
        echo "  ℹ Using original (image smaller than 1200x630)\n";
    }
}

echo "\n=== Summary ===\n";
echo "✓ Total images generated: $total_generated\n";
echo "✓ Responsive images processed: " . count($responsive_images) . "\n";
echo "✓ OG images processed: " . count($og_images) . "\n";

if ($errors > 0) {
    echo "⚠ Warnings/Errors: $errors\n";
}

// Verify cache directory contents
$cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/generated/';
if (is_dir($cache_dir)) {
    $generated_files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $generated_files[] = $file->getPathname();
        }
    }
    echo "✓ Total files in cache directory: " . count($generated_files) . "\n";
}

// Exit with error code if there were problems
exit($errors > 0 ? 1 : 0);