<?php
require_once "includes/classes.php";

// Create PageInfo for the press page
$page_info = new PageInfo(
    title: "Press | John H. Ring IV",
    description: "Media coverage and press releases featuring John H. Ring IV's research in computational finance and cybersecurity.",
    og_image: '/img/press/2025_Marblehead.jpg',
    og_type: 'website'
);

include "includes/top.php";

// Load press data from DB
$db = SiteDB::get();
$press_results = $db->query('SELECT * FROM press ORDER BY sort_order');
?>
<div class="headline mb-4">
    <h1 class="mt-2">Press</h1>
</div>
<p class="lead">
    Some press featuring yours truly.
</p>

<?php
$isFirst = true;
while ($entry = $press_results->fetchArray(SQLITE3_ASSOC)) {
    renderPressEntry($entry, $isFirst);
    $isFirst = false;
}
?>

<?php
include "includes/image_modal.php";
include "includes/footer.php";
?>
