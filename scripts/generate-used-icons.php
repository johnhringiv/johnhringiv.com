<?php

$projectRoot = "www/"; // Change if needed
$outputFile = '.build/used-icons.txt';

// Pattern 1: Direct bi_inline() calls like bi_inline('icon-name')
$patternBiInline = '/bi_inline\([\'"]([a-zA-Z0-9_-]+)[\'"]\)/';

// Pattern 2: Icon references in data arrays like 'icon' => 'icon-name'
$patternDataIcon = '/[\'"]icon[\'"]\s*=>\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/';

$icons = [];

// Scan www/ for PHP/HTML/JS files
$directory = new RecursiveDirectoryIterator($projectRoot);
$iterator = new RecursiveIteratorIterator($directory);

foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.(php|html|js)$/', $file->getFilename())) {
        echo "🔍 Scanning: " . $file->getPathname() . "\n";
        $contents = file_get_contents($file->getPathname());

        // Match bi_inline() calls
        if (preg_match_all($patternBiInline, $contents, $matches)) {
            foreach ($matches[1] as $icon) {
                $icons[$icon] = true;
            }
        }

        // Match 'icon' => 'name' in data files
        if (preg_match_all($patternDataIcon, $contents, $matches)) {
            foreach ($matches[1] as $icon) {
                $icons[$icon] = true;
            }
        }
    }
}

// Extract icons from research table link entries
$db = new SQLite3(__DIR__ . '/../www/generated/site.db');
$results = $db->query('SELECT links FROM research');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $links = json_decode($row['links'], true);
    foreach ($links as $link) {
        if (!empty($link['icon'])) {
            $icons[$link['icon']] = true;
        }
    }
}

ksort($icons); // Optional: sort alphabetically

// Ensure .build directory exists
if (!is_dir('.build')) {
    mkdir('.build', 0755, true);
}

file_put_contents($outputFile, implode(PHP_EOL, array_map(fn($name) => ".build/svg/$name.svg", array_keys($icons))));

echo "✅ Generated used-icons.txt with " . count($icons) . " icon(s).\n";