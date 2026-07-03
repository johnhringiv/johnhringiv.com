<?php
/**
 * Atom Feed Generator
 *
 * Aggregates content from blog, research, and press sections
 * and outputs a valid Atom 1.0 feed with Media RSS thumbnails.
 */

require_once "includes/classes.php";

// Set content type for Atom feed
header('Content-Type: application/atom+xml; charset=utf-8');

// Base URL for absolute image paths
$baseUrl = 'https://johnhringiv.com';

// Collect all entries
$entries = [];

$db = SiteDB::get();

// =============================================================================
// Blog Entries
// =============================================================================
$results = $db->query('SELECT * FROM blog_posts ORDER BY sort_order');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $full_title = $row['title'];
    if ($row['subtitle']) {
        $full_title .= ': ' . $row['subtitle'];
    }

    // Replicate the og_image SVG → PNG rewrite from PageInfo constructor
    $og_image = $row['og_image'];
    if (str_ends_with($og_image, '.svg')) {
        $og_image = substr($og_image, 0, -4) . '.png';
    }
    $thumbnail = $baseUrl . $og_image;

    $entries[] = [
        'title' => $full_title,
        'url' => $baseUrl . '/' . $row['slug'],
        'id' => 'tag:johnhringiv.com,2024:blog/' . $row['slug'],
        'published' => new DateTime($row['published_time'] . ' 12:00:00', new DateTimeZone('UTC')),
        'updated' => new DateTime($row['modified_time'] . ' 12:00:00', new DateTimeZone('UTC')),
        'summary' => $row['description'],
        'section' => 'blog',
        'thumbnail' => $thumbnail
    ];
}

// =============================================================================
// Research Entries
// =============================================================================
$results = $db->query('SELECT * FROM research ORDER BY sort_order');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $links = json_decode($row['links'], true);
    $url = !empty($links) ? $links[0]['url'] : $baseUrl . '/research';
    if ($url && !preg_match('#^https?://#', $url)) {
        $url = $baseUrl . '/' . ltrim($url, '/');
    }

    $thumbnail = !empty($row['image']) ? $baseUrl . $row['image'] : null;

    $entries[] = [
        'title' => $row['title'],
        'url' => $url,
        'id' => 'tag:johnhringiv.com,2024:research/' . $row['slug'],
        'published' => new DateTime($row['date']),
        'updated' => new DateTime($row['date']),
        'summary' => substr(strip_tags($row['description']), 0, 300) . '...',
        'section' => 'research',
        'thumbnail' => $thumbnail
    ];
}

// =============================================================================
// Press Entries
// =============================================================================
$results = $db->query('SELECT * FROM press ORDER BY sort_order');
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $thumbnail = !empty($row['image']) ? $baseUrl . $row['image'] : null;

    $entries[] = [
        'title' => $row['title'],
        'url' => $row['url'],
        'id' => 'tag:johnhringiv.com,2024:press/' . $row['slug'],
        'published' => new DateTime($row['date']),
        'updated' => new DateTime($row['date']),
        'summary' => $row['description'],
        'section' => 'press',
        'thumbnail' => $thumbnail
    ];
}

// =============================================================================
// Sort by date descending
// =============================================================================
usort($entries, fn($a, $b) => $b['published'] <=> $a['published']);

// Determine feed updated time (most recent entry)
$feedUpdated = $entries[0]['updated'] ?? new DateTime();

// Output Atom feed
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">
  <title>John H. Ring IV</title>
  <subtitle>Blog, research publications, and press coverage</subtitle>
  <link href="https://johnhringiv.com/feed" rel="self" type="application/atom+xml"/>
  <link href="https://johnhringiv.com/" rel="alternate" type="text/html"/>
  <id>tag:johnhringiv.com,2024:feed</id>
  <updated><?= $feedUpdated->format('c') ?></updated>
  <author>
    <name>John H. Ring IV</name>
    <uri>https://johnhringiv.com/</uri>
  </author>
  <icon>https://johnhringiv.com/img/favicon_io/favicon-32x32.png</icon>
  <logo>https://johnhringiv.com/img/favicon_io/android-chrome-192x192.png</logo>
  <rights>Copyright (c) <?= date('Y') ?> John H. Ring IV</rights>

<?php foreach ($entries as $e): ?>
  <entry>
    <title><?= htmlspecialchars($e['title'], ENT_XML1, 'UTF-8') ?></title>
    <link href="<?= htmlspecialchars($e['url'], ENT_XML1, 'UTF-8') ?>" rel="alternate" type="text/html"/>
    <id><?= htmlspecialchars($e['id'], ENT_XML1, 'UTF-8') ?></id>
    <published><?= $e['published']->format('c') ?></published>
    <updated><?= $e['updated']->format('c') ?></updated>
    <summary type="text"><?= htmlspecialchars($e['summary'], ENT_XML1, 'UTF-8') ?></summary>
    <category term="<?= htmlspecialchars($e['section'], ENT_XML1, 'UTF-8') ?>"/>
<?php if (!empty($e['thumbnail'])): ?>
    <media:thumbnail url="<?= htmlspecialchars($e['thumbnail'], ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
  </entry>
<?php endforeach; ?>
</feed>
