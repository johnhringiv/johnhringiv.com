</main>
</div><!--/container-->
<footer>
<div class="container-lg copyright mt-4">
    <?php
    if (in_array($fname, array("index", "research"))) {
echo <<<HTML
    <div class="container">
        <div class="headline mb-3">
            <h2>Employer and Research Partners</h2>
        </div>
    </div>
    <div class="container">
        <div class="affiliated-icons">
            <div class="icon-container">
                <a href="https://www.massmutual.com/" aria-label="MassMutual">
                    <img class="affiliated-icon" src="/img/affilated/MM_FullMark.svg" alt="MassMutual Logo" aria-hidden="true">
                </a>
            </div>
            <div class="icon-container">
                <a href="https://vermontcomplexsystems.org/" aria-label="Vermont Complex Systems Center">
                    <img class="affiliated-icon" src="/img/affilated/roboctopus.png" alt="Vermont Complex Systems Center Logo" aria-hidden="true">
                </a>
            </div>
            <div class="icon-container">
                <a href="https://www.mitre.org/" aria-label="MITRE Corporation">
                    <img class="affiliated-icon" src="/img/affilated/Mitre_Corporation_logo.svg" alt="MITRE Logo" aria-hidden="true">
                </a>
            </div>
            <div class="icon-container">
                <a href="https://www.uvm.edu/" aria-label="University of Vermont">
                    <img class="affiliated-icon" src="/img/affilated/uvm_logo.svg" alt="UVM Logo" aria-hidden="true">
                </a>
            </div>
        </div>
    </div>
    HTML;
    }
    ?>
    <div class="container">
        <p class="text-center"><small>Copyright ©2020-<?php echo date("Y"); ?> John H. Ring IV. All rights reserved.
            <br>
        The content on this website is my own and does not necessarily reflect the views of my employer or any other party.</small></p>
    </div>
</div><!--/copyright-->
</footer>

<?php if (isset($page_info) && !empty($page_info->extra_js)) foreach ($page_info->extra_js as $js) versioned_asset($js); ?>
<?php versioned_asset("/generated/bundle.js");?>
</body>
</html>
