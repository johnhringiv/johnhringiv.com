<?php
require_once "includes/classes.php";

// Create PageInfo for the research page
$page_info = new PageInfo(
    title: "Research & Innovation | John H. Ring IV",
    description: "Research publications, preprints, and patents in cybersecurity, computational finance, and network security.",
    og_image: '/img/abstracts/complexity_2022.jpg',
    og_type: 'website'
);

include "includes/top.php";

// Load research data from DB
$db = SiteDB::get();
$results = $db->query('SELECT * FROM research ORDER BY sort_order');
$peerReviewed = [];
$preprints = [];
$patents = [];
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $row['authors'] = json_decode($row['authors'], true);
    $row['links'] = json_decode($row['links'], true);
    match ($row['category']) {
        'peer-reviewed' => $peerReviewed[] = $row,
        'preprint' => $preprints[] = $row,
        'patent' => $patents[] = $row,
    };
}
?>
<div class="headline mb-4">
    <h1 class="mt-2">Research & Innovation</h1>
</div>

<div class="headline mb-3">
    <h2 class="mt-2">Peer-Reviewed Publications</h2>
</div>

<?php
$isFirst = true;
foreach ($peerReviewed as $entry) {
    renderResearchEntry($entry, $isFirst);
    $isFirst = false;
}
?>

<div class="headline mb-3">
    <h2>Preprints</h2>
</div>
<?php
$isFirst = true;
foreach ($preprints as $entry) {
    renderResearchEntry($entry, $isFirst);
    $isFirst = false;
}
?>

<?php if (!empty($patents)): ?>
<div class="headline mb-3">
    <h2>Patents</h2>
</div>
<?php
$isFirst = true;
foreach ($patents as $entry) {
    renderResearchEntry($entry, $isFirst);
    $isFirst = false;
}
?>
<?php endif; ?>

<?php
include "includes/image_modal.php";
include "includes/footer.php";
?>
