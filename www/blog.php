<?php
require_once "includes/classes.php";

// Create PageInfo for the blog listing page
$page_info = new PageInfo(
    title: "Blog | John H. Ring IV",
    description: "Technical blog posts on AI, machine learning, software engineering, and computer science.",
    og_image: "/img/blog/open_graph/blog.svg",
    og_type: 'website'
);

include "includes/top.php";
?>
    <div class="headline mb-4">
        <h1 class="mt-2">Blog</h1>
    </div>
    <div class="blog-post mt-2 pb-2">
        <p class="lead">
            Welcome to my blog, a bit about me...<br>
            By day, I'm a Principal Data Scientist at MassMutual focusing on Cybersecurity and Enterprise Technology. I lead the Cybersecurity & Fraud Data Science Team and drive enterprise-wide AI transformation initiatives.
            I also hold a PhD in Computer Science from the University of Vermont, with research in computational finance and cybersecurity.
            That said, you'll probably find me writing about completely unrelated side projects that caught my curiosity.
            Like building compilers and running a home-lab.
            I love exploring new technical challenges and sharing what I learn along the way.
        </p>

        <hr class="my-5">
        
        <?php
        $db = SiteDB::get();
        $results = $db->query('SELECT * FROM blog_posts ORDER BY sort_order');
        $index = 0;
        $total = $db->querySingle('SELECT COUNT(*) FROM blog_posts');
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $entry = PageInfo::fromRow($row);
            echo $entry->renderBlogEntry($row['slug']);

            if ($index < $total - 1) {
                echo "<hr class='my-5'>\n";
            }
            $index++;
        }
        ?>
        <!-- Add more blog posts as needed -->
    </div>
<?php
include "includes/footer.php";
?>