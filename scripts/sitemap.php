<?php

$baseUrl = 'https://johnhringiv.com/';

$priority_map = [
    'index' => '1.00',
    'research' => '0.80',
    'press' => '0.64',
    'blog' => '0.72'
];

// Load blog post metadata for authoritative modified_time from database
$db = new SQLite3(__DIR__ . '/../www/generated/site.db');
$newest_blog = $db->querySingle('SELECT MAX(modified_time) FROM blog_posts') ?: '1970-01-01';
$blog_modified = [];
$results = $db->query('SELECT slug, modified_time FROM blog_posts');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $blog_modified[$row['slug']] = $row['modified_time'];
}

// Research/press: use git history on data source files
$research_lastmod = trim(shell_exec("git log -1 --format=%cI -- data/research.php"));
$press_lastmod = trim(shell_exec("git log -1 --format=%cI -- data/press.php"));

$data_modified = [
    'index' => $newest_blog,
    'blog' => $newest_blog,
    'feed' => $newest_blog,
    'research' => $research_lastmod,
    'press' => $press_lastmod,
];

$allUrls = [];

// Scan for PHP files in root
foreach (glob("www/*.php") as $file) {
    $slug = basename($file, '.php');
    if ($slug === 'index') {
        $url = $baseUrl;
    } else {
        $url = $baseUrl . $slug;
    }

    // Use data-driven dates where available, git history for the rest
    if (isset($blog_modified[$slug])) {
        $lastmod = $blog_modified[$slug];
    } elseif (!empty($data_modified[$slug])) {
        $lastmod = $data_modified[$slug];
    } else {
        $lastmod = trim(shell_exec("git log -1 --format=%cI -- $file")) ?: date('Y-m-d');
    }

    $allUrls[] = [
        'loc' => $url,
        'lastmod' => $lastmod,
        'priority' => $priority_map[$slug] ?? '0.80'
    ];
}

// Start XML
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($allUrls as $page) {
    $lastmod = substr($page['lastmod'] ?? date('Y-m-d'), 0, 10);
    $xml .= "  <url>\n";
    $xml .= "    <loc>{$page['loc']}</loc>\n";
    $xml .= "    <lastmod>$lastmod</lastmod>\n";
    $xml .= "    <priority>{$page['priority']}</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= "</urlset>";

// Write XML to file
file_put_contents('www/sitemap.xml', $xml);