<?php
require_once "includes/classes.php";

// Create PageInfo for the home page
$page_info = new PageInfo(
    title: "John H. Ring IV - Home",
    description: "Principal Data Scientist for Cybersecurity & Enterprise Technology at MassMutual, leading the enterprise's move to agentic AI. PhD in Computer Science, University of Vermont.",
    og_image: "/img/open_graph/home.svg",
    og_type: 'website',
);

include "includes/top.php";
?>
<div class="row">
    <figure class="figure text-center col col-md-6 mt-4 d-flex flex-column">
        <?php echo responsiveImage(
            '/img/self_pic.jpg',
            'column',  // Uses [400, 600, 800, 1200] widths
            'pictured John H. Ring IV',
            'img-fluid mw-65 rounded',
            ['(min-width: 768px) 25vw', '50vw']  // 25% of viewport on desktop (50% col * 50% mw-50), 50% on mobile
        ); ?>
        <figcaption class="figure-caption pt-2">
            <div class="text-center">
                Principal Data Scientist - Cybersecurity & Enterprise Technology
            </div>
            <div class="text-center">
                <i>Cybersecurity • Agentic AI • Technical Leadership • Data Science • Software Engineering</i>
            </div>
            <div class="text-center">
                <a href="mailto:johnhringiv@gmail.com" class="unlink"><?php echo bi_inline("envelope");?> johnhringiv@gmail.com</a> <?php echo bi_inline("geo-alt");?> Vermont, USA
            </div>
        </figcaption>
    </figure>
    <div class="col-md-6 mt-4 position-relative">
        <h1>A Bit About Me</h1>
        <p>
            John is a Principal Data Scientist for Cybersecurity and Enterprise Technology at MassMutual, where he built and leads the company's cybersecurity data science program. He created its first in-house cybersecurity machine learning models and now leads the shift to agentic AI, defending the enterprise at machine speed.
        </p>
        <p>
            His work includes CATCH, MassMutual's primary user and entity behavior analytics tool, which factors into roughly 40% of confirmed detections while outperforming commercial tooling. He now leads SPARTA, a program for autonomous zero-day discovery and patching, and is modernizing security operations and the software development lifecycle with agentic workflows.
        </p>
        <p>
            Before MassMutual, John earned his PhD in Computer Science from the University of Vermont and co-founded the UVM&ndash;MITRE Computational Finance Laboratory. His research on U.S. equity market microstructure was featured in the Wall Street Journal and led to a new DARPA program and an SEC contract at MITRE.
        </p>
        <p>
            Working remotely from Vermont, John races in the International Lightning Class sailing circuit and spends his off hours on woodworking and photography, all with the backdrop of the Green Mountains.
        </p>

        <div class="bg-tan-dark rounded p-3 shadow-sm mb-3 mt-4">
            <p class="mb-3">Here to do some tinkering? Check out my <a href="/blog">Blog</a> for technical deep dives.</p>

            <h2 class="mb-2 fs-5">Recent Posts</h2>
            <ul class="list-unstyled ps-3 mb-0">
                <?php
                $db = SiteDB::get();
                $results = $db->query('SELECT slug, title, subtitle FROM blog_posts ORDER BY sort_order LIMIT 3');
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $subtitle = $row['subtitle'] ? ' <small class="text-muted">- ' . htmlspecialchars($row['subtitle']) . '</small>' : '';
                    echo '                <li class="mb-2"><a href="/' . htmlspecialchars($row['slug']) . '">' . htmlspecialchars($row['title']) . '</a>' . $subtitle . '</li>' . "\n";
                }
                ?>
            </ul>
        </div>

        <div class="sign-off">
            <p><b>Consulting:</b> Serious inquiries welcome. Please reach out via email. John's <a href="PDF/John_Ring_CV.pdf" target="_blank"><?php echo bi_inline("file-earmark-pdf");?>CV</a> provides additional details about his professional background.</p>
        </div>
    </div>
</div>

<?php
include "includes/footer.php";
?>