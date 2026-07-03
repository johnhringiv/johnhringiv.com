<?php
require_once "includes/classes.php";

$page_info = PageInfo::fromDB('secure-scalable-home-web-hosting');

include_once "includes/top.php";

function generate_config_list($tuples): string
{
    $output = '<ul class="">';
    foreach ($tuples as $tuple) {
        if (is_array($tuple[1])) {
            $output .= '<li><b>' . htmlspecialchars($tuple[0]) . ':</b> ' . generate_config_list($tuple[1]) . '</li>';
        } else {
            $value = $tuple[1] === 'Checked' ? '✓' : htmlspecialchars($tuple[1]);
            $output .= '<li><b>' . htmlspecialchars($tuple[0]) . '</b> → <kbd>' . htmlspecialchars($value) . '</kbd></li>';
        }
    }
    return $output . '</ul>';
}
?>
<div class="container blog-post pb-2">
    <article>
        <?php $page_info->renderFullHeader(); ?>
        <div class="container-lg col-lg-10 mb-4">
            <div class="affiliated-icons">
                <div class="icon-container me-2">
                    <img class="affiliated-icon" src="img/brands/haproxy.svg" alt="HAProxy Logo" aria-hidden="true">
                </div>
                <div class="icon-container me-2">
                    <img class="affiliated-icon" src="img/brands/pfsense-blue.svg" alt="Pfsense Logo" aria-hidden="true">
                </div>
                <div class="icon-container me-2">
                    <img class="affiliated-icon" src="img/brands/letsencrypt.svg" alt="Lets encrypt Logo" aria-hidden="true">
                </div>
                <div class="icon-container">
                    <img class="affiliated-icon" src="img/brands/cloudflare.svg" alt="Cloudflare" aria-hidden="true">
                </div>
            </div>
        </div>
        <!-- Post content-->
        <div class="mb-5">
            <?php echo $page_info->html_description ?>
        </div>
        <section class="">
            <h2 id="lets-encrypt" class="fw-bolder mb-4 mt-5"><a href="#lets-encrypt" class="text-reset text-decoration-none">Let's Encrypt!</a></h2>
            <p>
                It's 2025. I don't care if you're serving a static site of cat pics, it needs to be encrypted.
                Fortunately, Let's Encrypt is free and easy.
                We'll be using the ACME protocol to automatically request and renew certificates.
            </p>

            <h2 id="whats-haproxy-and-why-are-we-complicating-things" class="fw-bolder mb-4 mt-5">
                <a href="#whats-haproxy-and-why-are-we-complicating-things" class="text-reset text-decoration-none">What's HAProxy and why are we complicating things?</a>
            </h2>
            <p>
                HAProxy (High Availability Proxy) is a reverse proxy, also known as a load balancer.
                A reverse proxy sits between backend server(s) and clients forwarding requests to the appropriate server.
                There are a lot of reasons one would want to use a reverse proxy but if you're reading this, you're likely a beginner or homelabber.
                I'll focus on what's important to me (and likely you) which differs from an enterprise environment.
            </p>
            <ol>
                <li class="mb-4 fs-5">Content-Based Routing
                    <p class="mb-2 mt-2 fs-6">
                        This is the most crucial feature in my opinion for both the home and enterprise user.
                        Here are a few general use cases enabled by HAProxy:
                    </p>
                    <ul class="mb-2 fs-6">
                        <li>Multiple domains single public <code>IP:Port</code></li>
                        <li>Multiple apps on a shared backend server</li>
                        <li>Routing based on subdomain</li>
                    </ul>
                    <p class="fs-6">
                        You likely only have one public IP address, and want to use standard ports (80 and 443) for your websites.
                        HAProxy can listen to these ports and route traffic based on the domain name in the HTTP header to the corresponding backend server and port.
                        <br><br>
                        While not necessarily a security product, a reverse proxy can improve your security posture by reducing the number of “holes” in your firewall (port forwards) and masking the IP address of backend servers.
                    </p>
                </li>
                <li class="mb-4 fs-5">SSL Termination
                    <p class="mb-4 mt-2 fs-6">
                        HAProxy can handle SSL termination, meaning it can decrypt incoming HTTPS requests and forward them to the backend server.
                        Setting up and renewing certs across multiple backend services can be time-consuming, error-prone, and fragile.
                        Separating SSL handling from the backend server reduces complexity, especially when tinkering or supporting multiple backends.
                        I find certificate management for containerized applications (Docker) particularly difficult, this removes that headache.
                        <br><br>
                        Performing SSL termination on HAProxy may have performance benefits as well.
                        For example, my PFSense appliance has specialized hardware, Intel QAT, for SSL acceleration, which my backend servers lack.
                        This is unlikely to be of practical concern, unless you're running a high traffic site on a Raspberry Pi 😉.
                        <br><br>
                        Finally, SSL termination is required to fully utilize intrusion detection and prevention systems like Suricata as core features like deep packet inspection are not possible on encrypted traffic.
                        <br><br>
                        We'll be using SSL termination in the guide below, but advanced topics like Suricata will be left for another day.
                    </p>
                </li>
                <li class="mb-4 fs-5">Load Balancing
                <p class="fs-6 mt-2">
                    Unsurprisingly, a tool called a load balancer can balance the load across multiple backend servers.
                    HAProxy can also be provisioned to handle failover, health checks, and sticky sessions.
                    This is similar to the content-based routing feature, but instead of routing based on the domain, it routes based on the load or health of the backend server.
                </p>
                </li>
            </ol>
            <h2 id="why-cloudflare" class="fw-bolder mb-4 mt-5">
                <a href="#why-cloudflare" class="text-reset text-decoration-none">Why Cloudflare</a>
            </h2>
            <p class="mb-4">
                I decided to move my domain over to Cloudflare when I started this project.
                My previous provider was not supported by ACME, didn't have DDNS functionality, and I wanted to take advantage of Cloudflare's features.
                Cloudflare can be used without transferring, but it seemed worthwhile to consolidate.
                The transfer was painless, and I highly recommend it as Cloudflare offers a variety of benefits, even in its free tier.
                Some features that are important to me:
            </p>
            <ol>
                <li class="fs-5">DNS Services
                    <p class="fs-6 mt-2">Cloudflares DNS Service supports ACME, DDNS, and is highly optimized.</p>
                </li>
                <li class="fs-5">Content Delivery Network (CDN)
                    <p class="fs-6 mt-2">Cloudflare caches your content on servers around the globe, reducing the load on your server and improving performance for clients.</p>
                </li>
                <li class="fs-5">Security
                    <p class="fs-6 mt-2">Cloudflare offers protection against Distributed Denial of Service (DDoS) attacks and provides a basic Web Application Firewall (WAF) in the free tier.</p>
                </li>
            </ol>
            <p class="mb-4">
                If you read the section above, you likely have a good idea of how Cloudflare works; that's right, it's a reverse proxy performing SSL termination.
                If you don't have any interest in Cloudflare that's fine, most of the guide will still apply.
            </p>

            <h2 id="getting-to-work" class="fw-bolder mb-4 mt-5">
                <a href="#getting-to-work" class="text-reset text-decoration-none">Getting to work</a>
            </h2>
            <p class="mb-4">
            With the <i>what</i> and <i>why</i> out of the way lets get into the <i>how</i>.
            </p>
            <h3 id="installing-packages" class="fw-bolder mb-4 mt-5">
                <a href="#installing-packages" class="text-reset text-decoration-none">Installing Packages</a>
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        We'll start by installing the ACME and HAProxy packages on pfSense.
                        Navigate to <code>System/ Package Manager/ Available Packages</code>, search for ACME and HAProxy, and click install.
                        When you're done, you'll see them in the installed packages tab and the services menu.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/installed_packages.png', 'standard', 'pfSense installed packages', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <h3 id="change-admin-port" class="fw-bolder mb-4 mt-5">
                <a href="#change-admin-port" class="text-reset text-decoration-none">Change Admin Port</a>
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        Do not skip this step! We'll be using ports 80 and 443 for HAProxy, so we need to change the pfSense admin port.
                        Failure to do so will result in a conflict <mark>locking you out of the web interface</mark>.
                        Go to <code>System/ Advanced/ Admin Access</code>, change <b>TCP port</b> and click save.
                        I personally use <kbd>10443</kbd>, but you can use any open port.
                        Note that you'll need to include the new port in the URL when accessing the pfSense web interface ex. <var>https://192.168.1.1:10443</var>
                        <br><br>
                    </p>
                    <blockquote class="blockquote">
                        <p class="mb-2">You may have an understanding spouse now, but that's unlikely to last if bring down the internet for a few hours when tinkering a few beers deep.</p>
                        <footer class="blockquote-footer">John <cite title="Source Title">Definitely not speaking from experience</cite></footer>
                    </blockquote>
                    <p>I recommend you get familiar with the <a href="https://docs.netgate.com/pfsense/en/latest/solutions/netgate-6100/connect-to-console.html">recovery instructions</a> and have the necessary drivers installed <i>before</i> an outage.</p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/pfsense_port.png', 'standard', 'pfsense port config', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <h2 id="configuring-acme-with-cloudflare" class="fw-bolder mb-4 mt-5">
                <a href="#configuring-acme-with-cloudflare" class="text-reset text-decoration-none">Configuring ACME with Cloudflare</a>
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <h3 id="create-an-acme-account" class="fw-bolder mb-4">
                        <a href="#create-an-acme-account" class="text-reset text-decoration-none">Create an ACME Account</a>
                    </h3>
                    <p class="mb-4">
                        First, we need to create an account key.
                        Navigate to <code>Services/ ACME/ Account Keys</code> and click <code>Add</code>.
                        Complete the form being sure to set <b>ACME Server</b> to <i>Let's Encrypt Production ACME v2</i>.
                        Click <code>Create a new account key</code> then <code>Register ACME key</code> and finally <code>Save</code>.
                    </p>
                    <p>
                        Ultimately, we want to create a new certificate with ACME using the <i>DNS-Cloudflare</i> method.
                        This requires an API token from Cloudflare along with some other information we'll get in the next section before jumping back into ACME.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/acme_account.png', 'standard', 'ACME account creation', 'img-fluid mb-4 image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <h3 id="dns-configuration-cloudflare" class="fw-bolder mb-4">
                        <a href="#dns-configuration-cloudflare" class="text-reset text-decoration-none">DNS Configuration (Cloudflare)</a>
                    </h3>
                    <p class="mb-4">
                        I'm assuming you already have a domain with DNS through Cloudflare.
                        If not, you can buy a new domain from Cloudflare at cost, transfer an existing one, or just change the existing one's DNS servers.
                        Alternatively, you can skip this section and proceed with the guide using a different DNS provider supported by ACME.
                    </p>
                    <p class="mb-4">
                        Log into Cloudflare, select your domain, then click <var>DNS</var> on the left.
                        We'll need to add <var>A</var> records <kbd>@, *, www</kbd> which Cloudflare will expand to the correct text for your domain.
                        Technically, <kbd>*</kbd> covers <kbd>www</kbd>, but I like to be explicit to avoid confusion.
                        We'll need to temporarily disable the <b>Proxy status</b> (orange cloud) for these records to allow ACME to validate the domain ownership.
                        If you're not planning to set up email for your domain I recommend clicking the prompt <var>set up restrictive SPF, DKIM, and DMARC records</var> which will prevent email spoofing.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/dns_records.png', 'standard', 'DNS Records', 'img-fluid mb-4 image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="creating-a-cloudflare-token" class="fw-bolder mb-4">
                        <a href="#creating-a-cloudflare-token" class="text-reset text-decoration-none">Creating A Cloudflare Token</a>
                    </h3>
                    <p class="mb-4">
                        From your domain's overview page, grab the <b>Zone ID</b> and <b>Account ID</b> from the bottom right.
                        Once you've done that, click the <i>Get your API token</i> link right below.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/cloudflare_overview.png', 'standard', 'Cloudflare Overview', 'img-fluid mb-4 image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="text-center mb-4">
                <div class="col-md-12">
                    <p class="mb-4">
                        On the following page, click <var>Create Token</var> then <var>Use Template</var> on the <b>Edit zone DNS</b> row.
                    </p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <?php echo responsiveImage('/img/blog/pfsense/user_tokens.png', 'standard', 'Cloudflare user tokens', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo responsiveImage('/img/blog/pfsense/user_tokens_template.png', 'standard', 'Cloudflare token templates', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                    </div>
                </div>
                <div class="col-md-12"><p class="mb-4">
                        Pick a name, select the zone you want to manage, then click <i>Continue to summary</i> and <i>Create Token</i>.
                        Be sure to copy the token from the following page as it will not be shown again.
                    </p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <?php echo responsiveImage('/img/blog/pfsense/user_tokens_create.png', 'standard', 'Cloudflare create token', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo responsiveImage('/img/blog/pfsense/user_tokens_create_confirm.png', 'standard', 'Cloudflare confirm', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="creating-a-certificate-acme" class="fw-bolder mb-4">
                        <a href="#creating-a-certificate-acme" class="text-reset text-decoration-none">Creating a Certificate (ACME)</a>
                    </h3>
                    <p>
                        With our token created, we can now generate a certificate with ACME.
                        In pfSense, navigate to <code>Services/ ACME/ Certificates</code> and click <i>Add</i>.
                        Enter a name, description, and select your Acme account.
                        On the same page in the <var>Domain SAN list</var> add your domain name, ex. <kbd>johnhringiv.com</kbd> and select <var>DNS-Cloudflare</var> as the <i>Method</i>.
                        In the popup, enter the information you gathered from Cloudflare.
                        The form doesn't make this clear, but you do <b>not</b> need to enter a global key or email when using the token method.
                        Click add again and <mark>enter the same Cloudflare information for the wildcard domain</mark>, ex. <i>*.johnhringiv.com</i>.
                        If you skip this step your certificated will not be valid for subdomains.
                    </p>
                    <ul>
                        <li>Token → Tutorial Token</li>
                        <li>Token Zone ID → Zone ID</li>
                        <li>Token Account ID → Account ID</li>
                    </ul>
                    <p>
                        Finally in <b>Actions list</b> add <code>/usr/local/etc/rc.d/haproxy.sh restart</code> to automatically restart HAProxy when the certificate is renewed and click save.
                        After completing these steps your end result should look similar to mine.
                    </p>
                    <p>
                        With all the configuration complete click <var>Issue/Renew</var> to create the certificate.
                        <mark>If this step fails make sure you don't have any firewall rules blocking ACME (Ex. pfBlockerNG)</mark>.
                        I don't recall what the defaults are under general settings, but I have both <b>Cron Entry</b> and <b>Write Certificates</b> checked.
                    </p>
                    <h3 id="re-enable-cloudflare-proxy" class="fs-4 fw-bolder">
                        <a href="#re-enable-cloudflare-proxy" class="text-reset text-decoration-none">Re-enable Cloudflare Proxy</a>
                    </h3>
                    <p>
                        At this stage, you should re-enable <var>Proxy status</var> (Orange Cloud) for your DNS records in Cloudflare.
                        Other important Cloudflare settings are in <code>DNS/Settings</code> <var>Enable DNSSEC</var> and under <code>SSL/TLS</code> clicking configure and selecting <var>Full(Strict)</var>.
                        The <var>Full (strict)</var> mode ensures that the connection between Cloudflare and your server is encrypted using a valid certificate; like the one we just created.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/acme_cert.png', 'standard', 'Acme cert', 'img-fluid mb-2 image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="dynamic-dns-ddns" class="fw-bolder mb-4">
                        <a href="#dynamic-dns-ddns" class="text-reset text-decoration-none">Dynamic DNS (DDNS)</a>
                    </h3>
                    <p>
                        Most residential customers don't have a static IP.
                        If your IP changes, you'll need to update your DNS records in Cloudflare.
                        Fortunately, Cloudflare and pfSense support dynamic DNS updates.
                        We'll use our token from the previous step.
                        In pfSense go to <code>Services/Dynamic DNS</code> and click <var>Add</var>.
                        Set the following:
                    </p>
                    <?php
                    $tuples = [
                        ['Service Type', 'Cloudflare'],
                        ['Interface to monitor', 'WAN'],
                        ['Hostname', '@, johnhringiv.com'],
                        ['Cloudflare Proxy', 'Checked'],
                        ['Password', 'Token'],
                        ['TTL', '600'],
                        ['Description', '@ johnhringiv.com']
                    ];
                    echo generate_config_list($tuples);
                    ?>
                    <p>and <var>save</var>.
                        Repeat those steps for <kbd>*</kbd> and <kbd>www</kbd></p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/pfsense_ddns.png', 'standard', 'Dynamic DNS', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <h2 id="pfsense-firewall-rules" class="fw-bolder mb-4 mt-5">
                <a href="#pfsense-firewall-rules" class="text-reset text-decoration-none">pfSense Firewall Rules</a>
            </h2>
            <div class="row mb-2">
                <div class="col-md-6">
                    <p>
                        Like most firewalls, pfSense blocks all incoming traffic by default.
                        We need to create a firewall rule to allow traffic on ports <kbd>80</kbd> and <kbd>443</kbd> on the WAN.
                        Note that this is not a port forward as we are not forwarding traffic to a specific server.
                    </p>
                    <p>
                        Since we're proxying though Cloudflare, we can restrict this rule to only allow traffic from <a href="https://www.cloudflare.com/ips/">Cloudflare's IP addresses</a>.
                        In the pfSense interface head to <code>Firewall/Aliases/IP</code>, click <var>Import</var>, set <var>Alias Name</var> to <kbd>cloudflair_ipv4</kbd>, paste the IP ranges from the link above into the <var>IP Address</var> field then click <var>save</var>.
                    </p>
                    <p class="">
                        Now go to <code>Firewall/Rules/WAN</code>, click <var>Add</var> and set the following:
                    </p>
                    <?php
                    $tuples = [
                        ['Action', 'Pass'],
                        ['Interface', 'WAN'],
                        ['Address Family', 'IPv4'],
                        ['Protocol', 'TCP'],
                        ['Source', 'Address or Alias, cloudflare_ipv4'],
                        ['Destination', 'WAN address'],
                        ['Destination Port Range', 'HTTPS (443)']
                    ];
                    echo generate_config_list($tuples);
                    ?>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/ip_import.png', 'standard', 'Cloudflare ipv4', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <p>
                click <var>Save</var>.
                Then repeat the process for <var>Destination Port Range</var> HTTP (80) and click <var>Apply Changes</var>.
                The end result should look something like this.
            </p>
            <?php echo responsiveImage('/img/blog/pfsense/firewall_rules.png', 'standard', 'Cloudflare ipv4', 'img-fluid image-modal-content', ['100vw']); ?>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="nat-reflection" class="fs-4 fw-bolder mb-4">
                        <a href="#nat-reflection" class="text-reset text-decoration-none">NAT Reflection</a>
                    </h3>
                    <p>
                        Those rules cover traffic coming into our network, but what about reaching our site from the lan?
                        As it stands, it won't work, but fortunately there's an easy solution, NAT reflection.
                        Navigate to <code>System/ Advanced/ Firewall & NAT</code>.
                        In the <var>Network Address Translation</var> section set:
                    </p>
                    <?php
                    $tuples = [
                        ['NAT Reflection mode for port forwards', 'Pure NAT'],
                        ['Enable NAT Reflection for 1:1 NAT', 'Checked']
                    ];

                    echo generate_config_list($tuples);
                    ?>
                    <p>and click <var>save</var>.</p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/nat_settings.png', 'standard', 'NAT reflection', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <h2 id="configuring-haproxy" class="fw-bolder mb-4 mt-5">
                <a href="#configuring-haproxy" class="text-reset text-decoration-none">Configuring HAProxy</a>
            </h2>
            <p>
                We now have everything in place to put our site/app on the internet.
                In this part of the guide, I'll be showing you how to use HAProxy to route requests to your backend server(s).
                As a prerequisite, you should have a backend server running a web service (ex. nginx, apache, etc.) that is accessible on your local network.
                My backends are using Nginx, and there will be some optional Nginx specific examples.
                A major benefit of using HAProxy is simplifying backend server configurations and allowing you to focus on the application itself rather than the networking.
                I'll be covering a few configurations, don't hesitate to deviate from the guide to meet your needs.
            </p>
            <p>
                In the pfSense web interface navigate to <code>Services/HAProxy/Settings</code> and set the following:
            </p>
            <?php
            $tuples = [
                ['Enable HAProxy', 'Checked'],
                ['Maximum connections', '500'],
                ['Reload Behavior', 'Check Force immediate stop of old process on reload. (closes existing connections)'],
                ['Remote syslog host', '/var/run/log'],
                ['Internal stats port', '2200'],
                ['SSL/TLS Compatibility Mode', 'Modern'],
                ['Max SSL Diffie-Hellman size', '2048'],
                ['Custom options', 'tune.comp.maxlevel 6']
            ];

            echo generate_config_list($tuples);
            ?>
            <p>
                These are a bit opinionated, but I find them to be a good starting point.
                Having the stats and logging enabled is useful for debugging and monitoring.
                I'll expand on the custom options late in the guide, but for now, just know that <kbd>tune.comp.maxlevel 6</kbd> is a performance tweak for compression.
                <br><br>
                <mark>Pro-Tip: There's a show button at the bottom of this page which displays the complete generated config.</mark>
                This is useful for debugging as most documentation isn't showing screenshots of pfSense.
            </p>
            <h3 id="reasoning-about-frontends-and-backends" class="fs-4 fw-bolder mt-5 mb-4">
                <a href="#reasoning-about-frontends-and-backends" class="text-reset text-decoration-none">Reasoning About Frontends and Backends</a>
            </h3>
            <p>
                The following sections should make things clear by example, but it can be helpful to quickly think big picture before we jump in.
                First, we need to know how many <b>distinct</b> services (websites, applications, etc.) we want to run.
                Each service will need its own backend entry in HAProxy.
                Multiple servers providing the same service for load balancing or high availability can be added to the same backend entry.

                I think of frontends in terms of domains with one frontend entry per domain.
                Distinct services on subdomains can be added to the same frontend entry.

                Put together, this creates a many-to-one relationship between backends and frontends.
                This mental model does not cover all use cases, but it will likely cover yours and creates a simple structure to build on.
            </p>
            <h3 id="creating-a-backend" class="fw-bolder mt-5 mb-4">
                <a href="#creating-a-backend" class="text-reset text-decoration-none">Creating a Backend</a>
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        Next, we need to define a backend server.
                        Navigate to <code>Services/HAProxy/Backend</code> and click <var>Add</var>.
                        Then add an entry to the <var>Server list</var>.
                    </p>
                    <?php
                    $tuples = [
                        ['mode', 'active'],
                        ['name', 'web_server (use whatever you want here)'],
                        ['Forward to', 'Address+Port'],
                        ['Address', 'LAN IP of backend server'],
                        ['Port', 'Port your app is listening on']
                    ];
                    echo generate_config_list($tuples);
                    ?>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_backend_server.png', 'standard', 'HAProxy backend', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <p>
                Note that we're not using SSL here as it partially defeats the purpose of SSL termination and isn't necessary on the LAN in a home environment.
                Feel free to use SSL if you want to just make sure to disable <var>SSL checks</var> if using an unsigned cert.
            </p>
            <h3 id="health-checking-optional" class="fs-4 fw-bolder mb-4 mt-5">
                <a href="#health-checking-optional" class="text-reset text-decoration-none">Health Checking (Optional)</a>
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        <mark>I recommend setting <var>Health check method</var> to <kbd>none</kbd> until you have everything working as it has to be properly configured on your service.</mark>
                        Health checking allows HAProxy to check the health of your backend server and only route traffic to healthy servers.
                        This is useful for load balancing and failover.
                    </p>
                    <?php
                    $tuples = [
                        ['Health check method', 'HTTP'],
                        ['Http check method', 'OPTIONS'],
                        ['Url used by http check requests', '/nginx-health']
                    ];

                    echo generate_config_list($tuples);
                    ?>
                    <p class="mb-0">
                        This is a custom endpoint I created on my applications using nginx.
                    </p>
                    <?php include "generated/highlighted-shiki/secure-scalable-home-web-hosting/nginx-health.html"; ?>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_backend_healthchecking.png', 'standard', 'Health check config', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <p>Again, if this isn't configured on your backend server, just set health check to none.
                That's it for the backend server, give it a name and click <var>Save</var>.
                If you have additional backends, for serving different applications, repeat the process.
                I'll be creating two additional entries for my Plex server* and a second web server.
                <br>
                <small>*Plex requires additional configuration to comply with Cloudflare's TOS which is not covered in this post but may be in a follow-up if there's interest.</small>
            </p>
            <h4 id="load-balancing" class="fs-5 fw-bolder mt-4 mb-2">
                <a href="#load-balancing" class="text-reset text-decoration-none">Load Balancing</a>
            </h4>
            <p>
                While we're here, let's take a moment to talk about some of the more advanced features of HAProxy.
                To use the load balancing feature all need to do is add multiple servers to the backend server list and select an algorithm (ex round robin) from Load balancing options.
                To avoid downtime during a redeployment, you can update your servers one at a time.
                Alternatively, you can designate a server as <var>backup</var> which will only be used if the primary server is down.
                For both these options, you will need to previously mentioned health checks for this to work.
            </p>
            <h3 id="creating-the-frontend" class="fw-bolder mt-5 mb-4">
                <a href="#creating-the-frontend" class="text-reset text-decoration-none">Creating the Frontend</a>
            </h3>
            <p>
                We're nearing the end!
                Let's create a frontend to listen for incoming requests and route them to the appropriate backend server.
                I recommend creating a frontend for each domain you want to route.
                We'll start with a single frontend for our main domain then show how to easily add a second.
            </p>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        Navigate to <code>Services/HAProxy/Frontend</code> and click <var>Add</var>.
                        We need to add an entry to <var>External Address</var> to listen to incoming connections.
                    </p>
                    <?php
                    $tuples = [
                        ['Name', 'johnhringiv'],
                        ['Status', 'Active'],
                        ['External Address', [
                            ['Listen Address', 'WAN address (IPv4)'],
                            ['Port', '443'],
                            ['SSL Offloading', 'Checked']
                        ]],
                        ['Type', 'http/https (offloading)']
                    ];

                    echo generate_config_list($tuples);
                    ?>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_front_edit_front.png', 'standard', 'HAProxy Frontend', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        Next we'll be completing the <var>Default backend, access control lists and actions</var>.
                        We'll use the <var>Access Control Lists</var> (ACLs) to route traffic based on the domain in the HTTP header.
                        For my use case I'll add the following ACL entries:
                    </p>
                    <?php
                    $tuples = [
                        ['Name', 'johnhringiv'],
                        ['Expression', 'Host Matches'],
                        ['Value', 'johnhringiv.com']
                    ];
                    echo generate_config_list($tuples);


                    $tuples = [
                        ['Name', 'plex'],
                        ['Expression', 'Host Matches'],
                        ['Value', 'plex.johnhringiv.com']
                    ];
                    echo generate_config_list($tuples);
                    ?>
                    <p>Then add corresponding actions:</p>
                    <?php
                    $tuples = [
                        ['Action', 'Use Backend'],
                        ['backend', 'web_server'],
                        ['Condition acl names', 'johnhringiv']
                    ];

                    echo generate_config_list($tuples);

                    $tuples = [
                        ['Action', 'Use Backend'],
                        ['Backend', 'plex'],
                        ['Condition acl names', 'plex']
                    ];

                    echo generate_config_list($tuples);
                    ?>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_front_acl.png', 'standard', 'HAProxy Front ACL', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p>
                        The last mandatory setting for this frontend is selecting the matching certificate (in my case johnhringiv) in the <var>SSL Offloading</var> section.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_front_ssl.png', 'standard', 'HAProxy Front SSL', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="optional-configuration" class="fs-4 fw-bolder mb-4">
                        <a href="#optional-configuration" class="text-reset text-decoration-none">Optional Configuration</a>
                    </h3>
                    <p>
                        I've enabled some additional settings helpful for debugging and monitoring.
                    </p>
                    <?php
                    $tuples = [
                        ['Stats options/ Separate sockets', 'Checked'],
                        ['Logging options/ Detailed logging', 'Checked'],
                        ['Advanced settings/ Use "forwardfor" option', 'Checked'],
                    ];

                    echo generate_config_list($tuples);
                    ?>
                    <p>Finally, in <var>Advanced settings/Advanced pass thru</var> I have:</p>
                    <?php include "generated/highlighted-shiki/secure-scalable-home-web-hosting/passthru_config.html"; ?>
                    <p>
                        This offloads compression from our backend server(s) to HAProxy.
                        I do this for a few reasons:
                    </p>
                    <ol>
                        <li>Intel QAT (hardware acceleration) present on my pfSense box accelerates gzip in addition to cryptography</li>
                        <li>We move a CPU intensive task from our backend servers</li>
                        <li>Backend configuration is simplified.</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_front_stats.png', 'standard', 'HAProxy Front Stats', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_front_advanced.png', 'standard', 'HAProxy Front Advanced', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <p>
                The long list of mime types is copied from Cloudflare's recommendations.
                Note that this is not a requirement, and you can skip this step if you prefer to handle compression on your backend server.
                Finally, click <var>Save</var> and <var>Apply Changes</var>.
            </p>
            <p>
                Congratulations! You should now be able to access your website!
                Before we pop the champagne, there's still a couple of minor things to take care of.
            </p>
            <h3 id="http-redirects" class="fw-bolder mb-4 mt-5">
                <a href="#http-redirects" class="text-reset text-decoration-none">HTTP Redirects</a>
            </h3>
            <p>
                Currently, we don't have anything listening to HTTP traffic (port 80).
                We're also ignoring the <var>www</var> subdomain which some users may enter out of habit.
                We'll add a frontend entry for each to use and redirect traffic.
            </p>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="redirect-http-to-https" class="fs-4 fw-bolder mb-4">
                        <a href="#redirect-http-to-https" class="text-reset text-decoration-none">Redirect HTTP to HTTPS</a>
                    </h3>
                    <p>
                        Our goal is to listen for HTTP traffic on port 80 and redirect it to HTTPS.
                    </p>
                    <?php
                    $tuples = [
                        ['Name', 'http_redirect'],
                        ['Status', 'Active'],
                        ['External Address',
                            [
                                ['Listen address', 'WAN address (IPv4)'],
                                ['Port', '80'],
                                ['SSL Offloading', 'Unchecked']
                            ],
                        ],
                        ['Type', 'http/https (offloading)'],
                        ['Actions',
                            [
                                ['Action', 'http-request redirect'],
                                ['Rule', 'scheme https code 301 if !{ ssl_fc }']
                            ]
                        ],
                    ];

                    echo generate_config_list($tuples);
                    ?>
                    <p>
                        Click <var>Save</var> and <var>Apply Changes</var>.
                        This rule redirects all HTTP traffic, if you want to be more specific, you can add an <var>ACL</var>.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_http_redirect.png', 'standard', 'HAProxy HTTP Redirect', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h3 id="redirect-www-to-root" class="fs-4 fw-bolder mb-4">
                        <a href="#redirect-www-to-root" class="text-reset text-decoration-none">Redirect www to Root</a>
                    </h3>
                    <p>
                        I want to redirect <var>www.johnhringiv.com</var> to <var>johnhringiv.com</var>.
                        It's best practice to redirect all traffic to a single domain, and I prefer the shorter one.
                        Since we already have a frontend listening on WAN 443, we can use a <var>Shared Frontend</var>.
                    </p>
                    <?php
                    $tuples = [
                        ['Name', 'www_redirect'],
                        ['Status', 'Active'],
                        ['Shared Frontend', 'Checked'],
                        ['Primary Frontend', 'johnhringiv - http'],
                        ['Action Control lists',
                            [
                                ['Name', 'www'],
                                ['Expression', 'Host starts with'],
                                ['Value', 'www']
                            ],
                        ],
                        ['Actions',
                            [
                                ['Action', 'http-request redirect'],
                                ['Rule', 'prefix http://%[hdr(host),regsub(^www\.,,i)] code 301'],
                                ['Condition acl names', 'www']
                            ]
                        ],
                    ];

                    echo generate_config_list($tuples);
                    ?>
                    <p>
                        This will match all domains starting with <var>www</var> you can be more restrictive if your use case requires.
                    </p>
                    <p>
                        Once again do <b>not</b> select any offloading for the redirect.
                        Click <var>Save</var> and <var>Apply Changes</var>.
                    </p>
                    <p>
                    Our redirect rules will work in combination.
                    For example, if a user enters <var>http://www.johnhringiv.com</var> they will be redirected to <var>https://www.johnhringiv.com</var> then to <var>https://johnhringiv.com</var> which is sent to a backend.
                    </p>
                </div>
                <div class="col-md-6">
                    <?php echo responsiveImage('/img/blog/pfsense/haproxy_www_redirect.png', 'standard', 'HAProxy WWW Redirect', 'img-fluid image-modal-content', ['(min-width: 768px) 50vw', '100vw']); ?>
                </div>
            </div>
            <p>

            <h3 id="additional-domains" class="fw-bolder mt-5 mb-4">
                <a href="#additional-domains" class="text-reset text-decoration-none">Additional Domains</a>
            </h3>
            <p>
                Thanks to the work we've done thus far, adding additional domains is trivial.
                I host an additional site (<a href="https://gotflashes.com">gotflashes.com</a>): configured DNS, created a cert, and added a backend as described above.
                The pattern is the same as the first domain except we use the <var>Shared Frontend</var> option and need to select an additional cert.
            </p>
            <?php
            $tuples = [
                ['Name', 'gotflashes'],
                ['Status', 'Active'],
                ['Shared Frontend', 'Checked'],
                ['Primary Frontend', 'johnhringiv - http'],
                ['Action Control lists',
                    [
                        ['Name', 'domain'],
                        ['Expression', 'Host matches'],
                        ['Value', 'gotflashes.com']
                    ],
                ],
                ['Actions',
                    [
                        ['Action', 'Use Backend'],
                        ['backend', 'gotflashes'],
                        ['Condition acl names', 'domain'],
                        ['Condition acl names', 'www']
                    ]
                ],
                ['Use Offloading - Specify additional certificates for this shared-frontend', 'Checked'],
                ['SSL Certificate', 'gotflashes']
            ];

            echo generate_config_list($tuples);
            ?>
            <p>Click <var>Save</var> and <var>Apply Changes</var>.</p>
            <h3 id="ipv6-hsts-http2" class="fw-bolder mt-5 mb-4">
                <a href="#ipv6-hsts-http2" class="text-reset text-decoration-none">IPV6, HSTS, & HTTP/2</a>
            </h3>
            <p>
                Since we're proxying through Cloudflare I didn't see any benefit to adding IPV6 as it would only apply to Cloudflare's connection to our origin.
                Users have full IPV6 connectivity to Cloudflare.
                If needed, adding IPV6 should be straightforward given the IPV4 example.
                <br><br>
                HSTS is an important security feature that forces clients to use HTTPS.
                This can be enabled in Cloudflare and I recommend it.
                Since we're using <var>Full (strict)</var> mode, we don't need to worry about it on our end as Cloudflare will only connect to our server using HTTPS.
                <br><br>
                HAProxy and Cloudflare both support HTTP/2 out of the box so no additional configuration is needed.
                The connection between Cloudflare and our origin will use HTTP/2, Cloudflare additionally supports HTTP/3 for connections between the client and Cloudflare.
                The connection between HAProxy and our backend will use HTTP/1.1 as we're performing SSL termination on HAProxy (HTTP/2 wouldn't give us any benefit here anyway).
            </p>
            <h2 id="conclusion" class="fw-bolder mb-4 mt-5">
                <a href="#conclusion" class="text-reset text-decoration-none">Conclusion</a>
            </h2>
                <p>
                    With this setup, you've built a modern and secure self-hosted infrastructure.
                    By using Cloudflare for DNS and proxying, you gain performance and protection at the edge.
                    With ACME and HAProxy, you're automatically managing TLS certificates and intelligently routing traffic to your internal services—all without relying on third-party hosting or manual certificate renewal.
                    <br><br>
                    This architecture gives you flexibility, scalability, and full control over your traffic.
                    Whether you're hosting personal projects or production workloads, it's a rock-solid foundation for any self-hosted setup.
                </p>
        </section>
        <?php include("includes/say_hello.html") ?>
    </article>
</div>
<?php
include "includes/image_modal.php";
include "includes/footer.php";
?>