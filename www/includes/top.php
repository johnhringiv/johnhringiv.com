<?php
require_once __DIR__ . '/classes.php';

$phpSelf = htmlentities($_SERVER['PHP_SELF'], ENT_QUOTES, "UTF-8");
$fname = pathinfo($phpSelf)['filename'];

// Generate CSP nonce and set Content-Security-Policy header
$csp_nonce = base64_encode(random_bytes(16));
$csp_script_src = "'self' https://static.cloudflareinsights.com";
if (!empty($csp_nonce)) {
    $csp_script_src .= " 'nonce-{$csp_nonce}'";
}
header("Content-Security-Policy: default-src 'none'; script-src {$csp_script_src}; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");

function versioned_url($relative_path): string
{
    $abs_path = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    $version = file_exists($abs_path) ? md5_file($abs_path) : 'missing';
    return htmlspecialchars($relative_path . '?v=' . $version, ENT_QUOTES, 'UTF-8');
}

function versioned_asset($relative_path): void
{
    $escaped_path = versioned_url($relative_path);
    $extension = pathinfo($relative_path, PATHINFO_EXTENSION);

    if ($extension === 'css') {
        echo '<link href="' . $escaped_path . '" rel="stylesheet">' . "\n";
    } elseif ($extension === 'js') {
        echo '<script src="' . $escaped_path . '" defer></script>' . "\n";
    } else {
        echo "<!-- ERROR: Unsupported asset type: {$relative_path} -->\n";
    }
}


function bi_inline($name): string
{
    $spritePath = $_SERVER['DOCUMENT_ROOT'] . "/generated/sprite.svg";
    $version = file_exists($spritePath) ? md5_file($spritePath) : 'missing';
    $href = "/generated/sprite.svg?v={$version}#{$name}";

    return <<<HTML
<svg class="bi" aria-hidden="true" focusable="false">
  <use xlink:href="{$href}"></use>
</svg>
HTML;
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <?php 
    // Render meta tags using PageInfo if available
    if (isset($page_info) && $page_info instanceof PageInfo) {
        $page_info->renderMetaTags();
    }
    ?>
    <meta name="author" content="John H. Ring IV">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#1c9b5d">

    <!-- Standard favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo versioned_url('/img/favicon_io/favicon.ico'); ?>">

    <!-- Modern vector favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo versioned_url('/img/favicon_io/favicon.svg'); ?>">

    <!-- PNG favicons for Android / PWA (referenced by the manifest) -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo versioned_url('/img/favicon_io/android-chrome-192x192.png'); ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?php echo versioned_url('/img/favicon_io/android-chrome-512x512.png'); ?>">

    <!-- Apple touch icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo versioned_url('/img/favicon_io/apple-touch-icon.png'); ?>">

    <!-- Web app manifest -->
    <link rel="manifest" href="<?php echo versioned_url('/img/favicon_io/site.webmanifest'); ?>">

    <!-- Atom feed -->
    <link rel="alternate" type="application/atom+xml" title="John H. Ring IV - Feed" href="/feed">

    <?php versioned_asset("/generated/bundle.css"); ?>
    <?php if (isset($page_info) && !empty($page_info->extra_css)) foreach ($page_info->extra_css as $css) versioned_asset($css); ?>
</head>
<body id="<?php echo $fname; ?>" class="">
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="navbar">
    <div class="container-lg navbar-container">
        <a class="navbar-brand" href="/">
            <img class="navbar-logo" src="<?php echo versioned_url('/img/logo.svg'); ?>" alt="" width="34" height="34">
            <span>John H. Ring IV</span>
        </a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Collection of nav links, forms, and other content for toggling -->
        <div id="navbarCollapse" class="collapse navbar-collapse">
            <ul class="navbar-nav">
                <?php
                $pages = array("index", "research", "press", "blog");
                foreach($pages as &$page) {
                    $name = $page == "index" ? "Home" : ucfirst($page);
                    $active = $fname == $page ? " active" : "";
                    $href = $page == "index" ? "" : $page;
                    echo "<li><a class='nav-link{$active}' href='/{$href}'>{$name}</a></li>";
                }
                ?>
            </ul>
            <ul class="navbar-nav navbar-right social-network flex-row">
                <li>
                    <a href="https://www.linkedin.com/in/johnhringiv" class="nav-link icoLinkedin" aria-label="LinkedIn Profile">
                        <?php echo bi_inline("linkedin-icon");?>
                    </a>
                </li>
                <li>
                    <a href="https://scholar.google.com/citations?user=ubl_nT8AAAAJ&hl=en" class="nav-link icoScholar" aria-label="Google Scholar Profile">
                        <?php echo bi_inline("google-scholar");?>
                    </a>
                </li>
                <li>
                    <a href="https://github.com/johnhringiv?tab=repositories" class="nav-link" aria-label="GitHub Repositories">
                        <?php echo bi_inline("github-icon");?>
                    </a>
                </li>
                <li>
                    <a href="https://gitlab.com/users/jhring/projects" class="nav-link" aria-label="GitLab Projects">
                        <?php echo bi_inline("gitlab-icon");?>
                    </a>
                </li>
                <li>
                    <a href="https://twitter.com/johnhringiv" class="nav-link" aria-label="Twitter Profile">
                        <?php echo bi_inline("twitter");?>
                    </a>
                </li>
                <li>
                    <a href="https://www.instagram.com/johnhringiv/" class="nav-link" aria-label="Instagram Profile">
                        <?php echo bi_inline("instagram-icon");?>
                    </a>
                </li>
                <li>
                    <a href="https://www.reddit.com/user/GoldPanther/" class="nav-link" aria-label="Reddit Profile">
                        <?php echo bi_inline("reddit-icon");?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container-lg bg-parchment rounded mt-lg-4 pb-3">
<main id="main-content">