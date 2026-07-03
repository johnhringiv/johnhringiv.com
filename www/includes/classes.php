<?php
require_once __DIR__ . '/image-resizer.php';

class SiteDB {
    private static ?SQLite3 $db = null;

    public static function get(): SQLite3 {
        if (self::$db === null) {
            $path = __DIR__ . '/../generated/site.db';
            self::$db = new SQLite3($path, SQLITE3_OPEN_READONLY);
            self::$db->enableExceptions(true);
        }
        return self::$db;
    }
}

/**
 * PageInfo - Manages metadata for blog posts and web pages with Open Graph support
 * 
 * This class handles structured data for both articles (blog posts) and website pages,
 * generating proper Open Graph and meta tags for optimal social media sharing.
 * 
 * BEST PRACTICES & SPECIFICATIONS:
 * 
 * Image Requirements (og_image):
 * - Required dimensions: 1200 x 630 pixels (1.91:1 aspect ratio)
 * - Optimized for LinkedIn and Reddit sharing
 * - LinkedIn requires 1200px width for large preview cards
 * - Reddit displays in link post previews
 * - Maximum file size: 8MB
 * - Formats: JPG for photos, PNG for graphics/text
 *
 * NOTE: All OG images MUST be exactly 1200x630. The code reports actual
 * dimensions via getimagesize() but does NOT validate. Create images at
 * correct size before adding to site. Use SVG → PNG workflow with Inkscape:
 *   inkscape input.svg --export-filename=output.png --export-width=1200
 * 
 * Text Length Guidelines:
 * Text Length Guidelines:
 *
 * Title:
 * - Optimal: 40-60 characters  
 * - Maximum useful: 60-70 characters
 * - Truncated with "..." if longer on most platforms
 * 
 * Description:
 * - Optimal: 55-60 characters for full visibility
 * - Maximum useful: 150-160 characters
 * - Platform display limits:
 *   • LinkedIn: ~150 characters visible
 *   • Reddit: ~200+ characters visible (varies by client)
 * 
 * Article-Specific Fields:
 * 
 * Section:
 * - Use single, broad category only
 * - Examples: Technology, Business, Health, Sports, Science
 * - Avoid subcategories or multiple sections
 * 
 * Tags:
 * - Recommended: 3-6 tags per article
 * - Maximum 2-3 words per tag
 * - Each tag generates its own meta tag
 * - Use lowercase by convention (though not required)
 * - Examples: "machine learning", "web development", "python"
 * 
 * Usage Examples:
 * 
 * Article (blog post):
 * ```php
 * $post = new PageInfo(
 *     title: "Understanding Neural Networks",
 *     description: "A deep dive into how neural networks learn patterns from data",
 *     og_type: 'article',
 *     published_time: new DateTime("2025-01-15"),
 *     modified_time: new DateTime("2025-01-20"),
 *     subtitle: "From Perceptrons to Deep Learning",
 *     tags: ['machine learning', 'ai', 'python', 'deep learning'],
 *     section: 'Technology'
 * );
 * ```
 * 
 * Website (landing page):
 * ```php
 * $post = new PageInfo(
 *     title: "John's Portfolio",
 *     description: "Data Scientist specializing in cybersecurity and fraud detection",
 *     og_type: 'website',
 * );
 * ```
 */
class PageInfo {
    public string $title;
    public ?string $subtitle;
    public string $description;
    public ?string $html_description;
    public ?DateTime $published_time;
    public ?DateTime $modified_time;
    public array $tags;
    public string $og_image;  // Required for proper social media sharing
    public ?string $section;
    public string $og_type; // "article" or "website"
    public string $canonical_url;
    public ?string $blog_image;  // Optional image for blog listing page (falls back to og_image)
    public array $extra_css = [];
    public array $extra_js = [];

    public static function fromDB(string $slug): self {
        $db = SiteDB::get();
        $stmt = $db->prepare('SELECT * FROM blog_posts WHERE slug = :slug');
        $stmt->bindValue(':slug', $slug);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            throw new RuntimeException("Blog post not found: $slug");
        }
        return self::fromRow($row);
    }

    public static function fromRow(array $row): self {
        $info = new self(
            title: $row['title'],
            description: $row['description'],
            og_image: $row['og_image'],
            og_type: 'article',
            published_time: new DateTime($row['published_time']),
            modified_time: new DateTime($row['modified_time']),
            subtitle: $row['subtitle'],
            tags: json_decode($row['tags'], true),
            section: $row['section'],
            html_description: $row['html_description'],
            blog_image: $row['blog_image']
        );
        $info->extra_css = $row['extra_css'] ? json_decode($row['extra_css'], true) : [];
        $info->extra_js = $row['extra_js'] ? json_decode($row['extra_js'], true) : [];
        return $info;
    }

    public function __construct(
        string $title,
        string $description,
        string $og_image,
        string $og_type = 'article',
        ?DateTime $published_time = null,
        ?DateTime $modified_time = null,
        ?string $subtitle = null,
        array $tags = [],
        ?string $section = null,
        ?string $canonical_url = null,
        ?string $html_description = null,
        ?string $blog_image = null
    ) {
        global $fname;
        
        $this->title = $title;
        $this->description = $description;
        $this->html_description = $html_description;
        $this->og_type = $og_type;
        $this->subtitle = $subtitle;
        $this->tags = $tags;

        // If blog_image not set and og_image is SVG, preserve SVG for blog listing
        // (browsers can render SVGs fine, looks sharper than PNG)
        if ($blog_image === null && str_ends_with($og_image, '.svg')) {
            $this->blog_image = $og_image;
        } else {
            $this->blog_image = $blog_image;
        }

        // Automatically rewrite .svg to .png for og_image
        // SVGs are source files; PNGs are what actually get served for OG tags
        if (str_ends_with($og_image, '.svg')) {
            $png_path = substr($og_image, 0, -4) . '.png';
            $this->og_image = $png_path;
        } else {
            $this->og_image = $og_image;
        }

        // Generate canonical URL if not provided
        if ($canonical_url === null) {
            $host = "johnhringiv.com";
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            
            // For index page, always use root path (handles both / and /index)
            // Check both the global $fname and also check the URI directly
            if ((isset($fname) && $fname === 'index') || $uri === '/index') {
                $canonical_path = '/';
            } else {
                $canonical_path = preg_replace('/\.php($|\?)/', '$1', $uri);
            }
            $this->canonical_url = "https://" . $host . $canonical_path;
        } else {
            $this->canonical_url = $canonical_url;
        }
        
        // Validate article-specific fields
        if ($og_type === 'article') {
            if ($published_time === null) {
                throw new InvalidArgumentException('published_time is required for article type');
            }
            if ($modified_time === null) {
                throw new InvalidArgumentException('modified_time is required for article type');
            }
            $this->published_time = $published_time;
            $this->modified_time = $modified_time;
            $this->section = $section ?? 'Technology';
        } else {
            // For non-article types, these fields are optional
            $this->published_time = $published_time;
            $this->modified_time = $modified_time;
            $this->section = $section;
        }
    }
    
    public function wasModified(): bool {
        if (!$this->published_time || !$this->modified_time) {
            return false;
        }
        return $this->published_time->format('Y-m-d') !== $this->modified_time->format('Y-m-d');
    }
    
    public function getFormattedDate(): string {
        return $this->published_time->format('F j, Y');
    }
    
    public function getFormattedModifiedDate(): string {
        return $this->modified_time ? $this->modified_time->format('F j, Y') : '';
    }
    
    public function renderFullHeader(string $author = 'John'): void {
        ?>
        <header class="mb-4">
            <!-- Post title-->
            <div class="mb-3">
                <h1 class="fw-bolder bg-parchment mt-2"><?= htmlspecialchars($this->title) ?><?php if ($this->subtitle): ?>: <small class="text-muted"><?= htmlspecialchars($this->subtitle) ?></small><?php endif; ?></h1>
            </div>
            <!-- Post meta content-->
            <div class="text-muted fst-italic mb-2">Posted on <time datetime="<?= $this->published_time->format('Y-m-d') ?>"><?= $this->getFormattedDate() ?></time> by <?= htmlspecialchars($author) ?><?php if ($this->wasModified()): ?> • Last modified <time datetime="<?= $this->modified_time->format('Y-m-d') ?>"><?= $this->getFormattedModifiedDate() ?></time><?php endif; ?></div>
            <!-- Post categories-->
            <?php foreach ($this->tags as $tag): ?>
            <?= BlogTags::renderBadge($tag) ?>
            <?php endforeach; ?>
        </header>
        <?php
    }
    
    public function renderBlogEntry(string $url, string $author = 'John'): string {
        $html = '<article class="mb-4">' . "\n";
        
        // Title, date, and tags - full width (h2 for semantics, fs-4 for h4 visual size)
        $html .= '    <h2 class="fw-bolder mb-1 fs-4"><a href="' . htmlspecialchars($url) . '" class="unlink"><i>' . htmlspecialchars($this->title);

        if ($this->subtitle) {
            $html .= ' <small class="text-muted">' . htmlspecialchars($this->subtitle) . '</small>';
        }

        $html .= '</i></a></h2>' . "\n";
        $html .= '    <div class="text-muted fst-italic mb-2">Posted on <time datetime="' . $this->published_time->format('Y-m-d') . '">' . $this->getFormattedDate() . '</time> by ' . htmlspecialchars($author);

        if ($this->wasModified()) {
            $html .= ' • Last modified <time datetime="' . $this->modified_time->format('Y-m-d') . '">' . $this->getFormattedModifiedDate() . '</time>';
        }

        $html .= '</div>' . "\n";
        
        // Render tags
        $html .= '    <div class="mb-3">' . "\n";
        foreach ($this->tags as $tag) {
            $html .= '        ' . BlogTags::renderBadge($tag) . "\n";
        }
        $html .= '    </div>' . "\n";

        // Description and image row
        $html .= '    <div class="row">' . "\n";

        // Use blog_image if set, otherwise fall back to og_image
        $display_image = $this->blog_image ?? $this->og_image;

        // Content column (left side or full width if no image)
        if (!empty($display_image)) {
            $html .= '        <div class="col-md-8">' . "\n";
        } else {
            $html .= '        <div class="col-12">' . "\n";
        }

        if ($this->html_description) {
            $html .= '            ' . $this->html_description . "\n";
        } else {
            $html .= '            <p class="lead">' . "\n";
            $html .= '                ' . htmlspecialchars($this->description) . "\n";
            $html .= '            </p>' . "\n";
        }
        
        $html .= '        </div>' . "\n";

        // Add responsive image if display_image exists (right side)
        if (!empty($display_image)) {
            $html .= '        <div class="col-md-4">' . "\n";
            $html .= '            <a href="' . htmlspecialchars($url) . '">' . "\n";
            $html .= '                ' . responsiveImage(
                $display_image,
                'column',  // Uses [400, 600, 800, 1200] widths
                htmlspecialchars($this->title),
                'img-fluid rounded',
                ['(min-width: 768px) 25vw', '100vw']  // 25% of viewport on desktop, 100% on mobile
            ) . "\n";
            $html .= '            </a>' . "\n";
            $html .= '        </div>' . "\n";
        }
        
        $html .= '    </div>' . "\n";
        $html .= '</article>' . "\n";

        return $html;
    }

    public function renderMetaTags(string $author = 'John H. Ring IV'): void {
        // Page title for browser tab
        $page_title = $this->title;
        if ($this->subtitle) {
            $page_title .= ': ' . $this->subtitle;
        }
        // Title element content doesn't need HTML encoding (it's text content, not an attribute)
        echo "<title>" . $page_title . "</title>\n";
        
        // Meta description
        echo "\t<meta name=\"description\" content=\"" . htmlspecialchars($this->description, ENT_COMPAT, 'UTF-8') . "\">\n";
        
        // Canonical URL
        echo "\t<link rel=\"canonical\" href=\"" . htmlspecialchars($this->canonical_url, ENT_COMPAT, 'UTF-8') . "\">\n";
        
        
        // Now render Open Graph tags
        $this->renderOpenGraphTags($author);
    }
    
    private function renderOpenGraphTags(string $author = 'John H. Ring IV'): void {
        
        // Combine title and subtitle for OG title
        $og_title = $this->title;
        if ($this->subtitle) {
            $og_title .= ': ' . $this->subtitle;
        }
        
        // Basic Open Graph tags (common to all types)
        echo "\t<meta property=\"og:type\" content=\"" . htmlspecialchars($this->og_type, ENT_COMPAT, 'UTF-8') . "\">\n";
        echo "\t<meta property=\"og:title\" content=\"" . htmlspecialchars($og_title, ENT_COMPAT, 'UTF-8') . "\">\n";
        echo "\t<meta property=\"og:description\" content=\"" . htmlspecialchars($this->description, ENT_COMPAT, 'UTF-8') . "\">\n";
        echo "\t<meta property=\"og:url\" content=\"" . htmlspecialchars($this->canonical_url, ENT_COMPAT, 'UTF-8') . "\">\n";
        echo "\t<meta property=\"og:site_name\" content=\"John H. Ring IV\">\n";
        echo "\t<meta property=\"og:locale\" content=\"en_US\">\n";

        // Twitter Card tags
        echo "\t<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "\t<meta name=\"twitter:site\" content=\"@johnhringiv\">\n";
        echo "\t<meta name=\"twitter:creator\" content=\"@johnhringiv\">\n";
        echo "\t<meta name=\"twitter:title\" content=\"" . htmlspecialchars($og_title, ENT_COMPAT, 'UTF-8') . "\">\n";
        echo "\t<meta name=\"twitter:description\" content=\"" . htmlspecialchars($this->description, ENT_COMPAT, 'UTF-8') . "\">\n";

        if ($this->og_image) {
            // Use ogImage() function to get the optimized version
            $optimized_image_url = ogImage($this->og_image);
            $image_path = $_SERVER['DOCUMENT_ROOT'] . $optimized_image_url;

            if (file_exists($image_path)) {
                // Add cache busting with MD5 hash
                $version = md5_file($image_path);
                $image_url = "https://johnhringiv.com" . $optimized_image_url . '?v=' . $version;

                echo "\t<meta property=\"og:image\" content=\"" . htmlspecialchars($image_url, ENT_COMPAT, 'UTF-8') . "\">\n";
                echo "\t<meta name=\"twitter:image\" content=\"" . htmlspecialchars($image_url, ENT_COMPAT, 'UTF-8') . "\">\n";

                // Get actual dimensions of the optimized image
                $size = getimagesize($image_path);
                if ($size) {
                    echo "\t<meta property=\"og:image:width\" content=\"" . $size[0] . "\">\n";
                    echo "\t<meta property=\"og:image:height\" content=\"" . $size[1] . "\">\n";
                }
            } else {
                // Log error in development/debug mode
                error_log("WARNING: og:image not found: " . $optimized_image_url);

                // Output HTML comment in development to make it visible
                if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
                    echo "\t<!-- WARNING: og:image file not found: " . htmlspecialchars($optimized_image_url, ENT_COMPAT, 'UTF-8') . " -->\n";
                }
            }
        }
        
        // Article-specific tags (only for article type)
        if ($this->og_type === 'article') {
            echo "\t<meta property=\"article:published_time\" content=\"" . $this->published_time->format('Y-m-d') . "\">\n";
            if ($this->wasModified()) {
                echo "\t<meta property=\"article:modified_time\" content=\"" . $this->modified_time->format('Y-m-d') . "\">\n";
            }
            echo "\t<meta property=\"article:author\" content=\"" . htmlspecialchars($author, ENT_COMPAT, 'UTF-8') . "\">\n";
            echo "\t<meta property=\"article:section\" content=\"" . htmlspecialchars($this->section, ENT_COMPAT, 'UTF-8') . "\">\n";
            
            // Tags
            foreach ($this->tags as $tag) {
                echo "\t<meta property=\"article:tag\" content=\"" . htmlspecialchars($tag, ENT_COMPAT, 'UTF-8') . "\">\n";
            }
        }
    }
}


/**
 * Renders a research publication entry
 *
 * @param array $entry Research entry row from site.db
 * @param bool $isFirst Whether this is the first entry in its section (affects container class)
 */
function renderResearchEntry(array $entry, bool $isFirst = false): void {
    ?>
<article class="<?= $isFirst ? 'mb-4 ' : 'mt-2 mb-4 ' ?>abstract">
    <div class="mb-3">
        <h3 class="fs-4"><i><?= htmlspecialchars($entry['title']) ?></i></h3>
    </div>
    <p><small>
            <?= htmlspecialchars(implode(', ', $entry['authors'])) ?>
            <br>
            <?= htmlspecialchars($entry['venue']) ?> (<?= htmlspecialchars($entry['date_display']) ?>)
        </small></p>
    <?php if (!empty($entry['image'])): ?>
    <div class="row">
        <figure class="col-md-4 image-modal-content">
            <?= responsiveImage(
                $entry['image'],
                'column',
                $entry['image_alt'],
                'img-fluid',
                ['(min-width: 768px) 33vw', '100vw'],
                !empty($entry['caption']) ? ['description' => strip_tags($entry['caption'])] : []
            ) ?>
            <?php if (!empty($entry['caption'])): ?>
            <figcaption class="figure-caption">
                <?= $entry['caption'] ?>
            </figcaption>
            <?php endif; ?>
        </figure>
        <div class="col-md-8 float-left">
            <p><?= $entry['description'] ?></p>
            <ul class="list-inline ms-4">
                <?php foreach ($entry['links'] as $link): ?>
                <li class="list-inline-item">
                    <?php if (isset($link['image'])): ?>
                    <a class="unlink" href="<?= htmlspecialchars($link['url']) ?>">
                        <img class="icon-size d-inline" src="<?= htmlspecialchars($link['image']) ?>" alt="<?= htmlspecialchars($link['label']) ?> Logo" aria-hidden="true"> <?= htmlspecialchars($link['label']) ?>
                    </a>
                    <?php else: ?>
                    <a class="unlink" href="<?= htmlspecialchars($link['url']) ?>">
                        <?= bi_inline($link['icon']) ?> <?= htmlspecialchars($link['label']) ?>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php else: ?>
    <div>
        <p><?= $entry['description'] ?></p>
        <ul class="list-inline ms-4">
            <?php foreach ($entry['links'] as $link): ?>
            <li class="list-inline-item">
                <?php if (isset($link['image'])): ?>
                <a class="unlink" href="<?= htmlspecialchars($link['url']) ?>">
                    <img class="icon-size d-inline" src="<?= htmlspecialchars($link['image']) ?>" alt="<?= htmlspecialchars($link['label']) ?> Logo" aria-hidden="true"> <?= htmlspecialchars($link['label']) ?>
                </a>
                <?php else: ?>
                <a class="unlink" href="<?= htmlspecialchars($link['url']) ?>">
                    <?= bi_inline($link['icon']) ?> <?= htmlspecialchars($link['label']) ?>
                </a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</article>
    <?php
}

/**
 * Renders a press coverage entry
 *
 * @param array $entry Press entry row from site.db
 * @param bool $isFirst Whether this is the first entry (affects container class)
 */
function renderPressEntry(array $entry, bool $isFirst = false): void {
    ?>
    <article class="<?= $isFirst ? 'mb-4' : 'mt-2 mb-4' ?>">
        <div class="headline mb-3">
            <h2 class="mt-2 fs-4"><a class="unlink" href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a></h2>
        </div>
        <p><small><?= htmlspecialchars($entry['date_display']) ?>, by <?= htmlspecialchars($entry['publication']) ?></small></p>
        <div class="row">
            <figure class="col-md-5 image-modal-content">
                <?php if (strpos($entry['image'], '/img/press/in_a_flash') !== false): ?>
                <img src="<?= htmlspecialchars(ltrim($entry['image'], '/')) ?>"
                     alt="<?= htmlspecialchars($entry['image_alt']) ?>"
                     class="img-fluid img-max-h-525"
                     <?php if (!empty($entry['caption'])): ?>data-description="<?= htmlspecialchars(strip_tags($entry['caption'])) ?>"<?php endif; ?>>
                <?php else: ?>
                <?= responsiveImage(
                    $entry['image'],
                    'column',
                    $entry['image_alt'],
                    'img-fluid',
                    ['(min-width: 768px) 42vw', '100vw'],
                    !empty($entry['caption']) ? ['description' => strip_tags($entry['caption'])] : []
                ) ?>
                <?php endif; ?>
                <?php if (!empty($entry['caption'])): ?>
                <figcaption class="figure-caption">
                    <?= $entry['caption'] ?>
                </figcaption>
                <?php endif; ?>
            </figure>
            <div class="col-md-7">
                <?php if (isset($entry['description_html'])): ?>
                <p class="lead mt-2"><?= $entry['description_html'] ?></p>
                <?php else: ?>
                <p class="lead mt-2"><?= htmlspecialchars($entry['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

class BlogTags {
    public static function renderBadge($tag) {
        $content = match($tag) {
            'C' => bi_inline('c'),
            'Rust' => bi_inline('rust') . 'ust',
            'Python' => bi_inline('python') . ' Python',
            'Claude' => bi_inline('claude-icon') . ' Claude',
            'Qwen' => bi_inline('qwen-icon') . ' Qwen',
            'Ollama' => bi_inline('ollama-icon') . ' Ollama',
            'LM Studio' => bi_inline('lm-studio-icon') . ' LM Studio',
            default => $tag
        };

        return '<span class="badge bg-primary text-decoration-none">' . $content . '</span>';
    }
}