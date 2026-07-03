<?php

return [
    [
        'slug' => 'claude-vs-local',
        'title' => 'The Gap Between Claude and Local',
        'subtitle' => 'Can a Self-Hosted Coding Agent Compete?',
        'description' => 'Is a self-hosted coding agent viable yet? Open-weight models versus a Claude subscription, planning and implementation on a production codebase, with agentic-coding tips I rely on professionally.',
        'html_description' => '<p class="lead mt-2">
    I set out to find how big the gap between a Claude subscription and a self-hosted setup actually is, and
    whether a local coding agent is viable for real work. The experiment ran in two phases. First, five arms
    (four local open-weight variants chosen to span the capability/context/speed tradeoff, against Claude Opus 4.7 as
    the cloud baseline) each designed a complete Playwright E2E test suite for a real Laravel + Livewire app
    from scratch. Then the best plan
    was handed back out to be <em>built</em>: Claude Code against the strongest local arm, head-to-head on the
    same plan. It\'s as much a practical guide to getting real work out of a 24 GB card as it is a contest: how to
    choose the model, the quant, and the context window so a long agent run has the room to finish without compacting,
    the agentic-coding tips I use and teach professionally, and one structural advantage Claude has that doesn\'t show up
    in any model card.
</p>
<p class="lead">
    This is not meant to be a fair fight. Quant levels and context windows are chosen to fit 24 GB of VRAM under
    realistic conditions, not to give each model its theoretical best precision. The point isn\'t &ldquo;who wins a
    level playing field&rdquo;; it\'s &ldquo;given the constraints anyone would actually face, what does the gap look
    like for real work?&rdquo;
</p>',
        'og_image' => '/img/blog/open_graph/claude-vs-local.svg',
        'blog_image' => '/img/blog/open_graph/claude-vs-local.svg',
        'published_time' => '2026-06-02',
        'modified_time' => '2026-06-05',
        'tags' => ['LLMs', 'Claude', 'Qwen', 'Local Inference', 'LM Studio', 'Agentic Coding'],
        'section' => 'Technology',
        'extra_css' => null,
        'extra_js' => null,
    ],
    [
        'slug' => 'small-brains-big-test',
        'title' => 'Small Brains, Big Test',
        'subtitle' => 'Can Small LLMs Pass the CISSP?',
        'description' => 'Not exactly CISSP prep, but close enough. Sixteen small open-weight models, 1,303 practice questions. A capability ceiling, measurable contamination, and questions every model got wrong.',
        'html_description' => '<p class="lead mt-2">
    I\'ve been meaning to prep for and take the CISSP exam for a while now. This work does not advance
    that goal, but it\'s easy to pretend it does. Sixteen small open-weight models, 1,303 practice questions,
    64 configurations. The results reveal a clean capability ceiling, a contamination pattern hiding in plain
    sight, and a handful of questions that stumped every single model, including the ones that probably cheated.
</p>',
        'og_image' => '/img/blog/open_graph/small-brains-big-test.svg',
        'blog_image' => '/img/blog/open_graph/small-brains-big-test.svg',
        'published_time' => '2026-02-24',
        'modified_time' => '2026-02-24',
        'tags' => ['LLMs', 'Local Inference', 'Qwen', 'Cybersecurity', 'Claude', 'Ollama'],
        'section' => 'Technology',
        'extra_css' => ['/generated/cissp-charts.css'],
        'extra_js' => ['/generated/cissp-charts.js'],
    ],
    [
        'slug' => 'ncc-not-complete-but-capable',
        'title' => 'NCC: Not Complete, but Capable',
        'subtitle' => 'A C Compiler in Rust',
        'description' => 'A deep dive into building a C compiler in Rust. From lexing to linking, I walk through NCC\'s architecture, the decisions behind it, and lessons learned implementing a substantial subset of C.',
        'html_description' => '<p class="lead mt-2">
    I recently reached a major milestone in the development of my hobby compiler <strong>Not Completely C</strong>.
    In this post, I share the journey that brought me here, demonstrate how NCC can compile non-trivial programs,
    and provide a detailed walkthrough of the compiler\'s architecture; from lexing source code to emitting machine code and linking executables.
</p>',
        'og_image' => '/img/blog/ncc_not_complete/ncc-not-complete-but-capable-v2.svg',
        'blog_image' => '/img/blog/ncc_not_complete/ncc-not-complete-but-capable-v2.svg',
        'published_time' => '2026-01-17',
        'modified_time' => '2026-01-17',
        'tags' => ['Compilers', 'C', 'Rust', 'Systems Programming', 'Computer Science'],
        'section' => 'Technology',
        'extra_css' => null,
        'extra_js' => null,
    ],
    [
        'slug' => 'when-five-plus-five-equals-eleven',
        'title' => 'When 5 + 5 Equals 11',
        'subtitle' => 'Reflections on Writing a C Compiler, Part One',
        'description' => 'How a simple expression like i + i++ can produce different results across compilers. A deep dive into C\'s undefined behavior and building a compiler that makes predictable choices.',
        'html_description' => '<p class="lead mt-2">
    How complex can a five character expression be?
    The seemingly innocent <code>i + i++</code> produces different results on different compilers, exposing one of C\'s most insidious features: undefined behavior.
    In this post, I\'ll explore how something as fundamental as the order of expression evaluation became a minefield in C, why modern compilers still disagree on basic operations, and how I\'m addressing these issues in my hobby compiler NCC.
    Along the way, we\'ll peek under the hood at compiler intermediate representations and discover why "simple" languages can be surprisingly complex.
</p>',
        'og_image' => '/img/blog/open_graph/when-five-plus-five-equals-eleven.svg',
        'blog_image' => '/img/blog/open_graph/when-five-plus-five-equals-eleven.svg',
        'published_time' => '2025-08-31',
        'modified_time' => '2026-01-18',
        'tags' => ['Compilers', 'C', 'Rust', 'Systems Programming', 'Computer Science'],
        'section' => 'Technology',
        'extra_css' => null,
        'extra_js' => null,
    ],
    [
        'slug' => 'a_subtle_python_threading_bug',
        'title' => 'A Subtle Python Threading Bug',
        'subtitle' => 'That Isn\'t About Threading',
        'description' => 'Debugging a mysterious bug in Python\'s concurrent execution that has nothing to do with race conditions.',
        'html_description' => '<p class="lead mt-2">
        I recently encountered a puzzling bug in code that uses Python\'s threading module for parallel processing.
        The code looks perfectly reasonable, but produces completely wrong results.
        Can you spot the issue?
    </p>',
        'og_image' => '/img/blog/open_graph/a_subtle_python_threading_bug.svg',
        'blog_image' => '/img/blog/open_graph/a_subtle_python_threading_bug.svg',
        'published_time' => '2025-08-22',
        'modified_time' => '2025-08-22',
        'tags' => ['Python', 'Programming Languages', 'Computer Science'],
        'section' => 'Technology',
        'extra_css' => null,
        'extra_js' => null,
    ],
    [
        'slug' => 'secure-scalable-home-web-hosting',
        'title' => 'Secure, Scalable Home Web Hosting',
        'subtitle' => 'with HAProxy, pfSense, Let\'s Encrypt & Cloudflare',
        'description' => 'Host multiple domains and subdomains on one IP using pfSense, HAProxy, Let\'s Encrypt, and Cloudflare, with centralized SSL and traffic management.',
        'html_description' => '<p class="lead">
    In this article, I\'ll walk you through a <b>comprehensive</b> setup for self-hosting web applications.
    This configuration is ideal for both homelabs and small production environments, enabling you to host multiple websites on a single IP address and port.
    We\'ll focus on security, flexibility, and centralized management.
</p>
<p class="lead">
    Using <strong>pfSense</strong> as our base operating system, router, and firewall, we\'ll leverage the <strong>ACME protocol</strong> for automated SSL certificate management,
    <strong>HAProxy</strong> for reverse proxying, and <strong>Cloudflare</strong> for DNS and CDN services.
</p>
<p class="lead">
    I\'ll explain the role of each component and guide you through every step of the process-including how to avoid some common gotchas.
    By the end, you\'ll have a secure, scalable setup for hosting multiple services using domain and subdomain-based routing, with centralized SSL and traffic management.
</p>',
        'og_image' => '/img/blog/open_graph/secure-scalable-home-web-hosting.svg',
        'blog_image' => null,
        'published_time' => '2025-04-03',
        'modified_time' => '2025-04-03',
        'tags' => ['Networking', 'Cybersecurity', 'Web Development'],
        'section' => 'Technology',
        'extra_css' => null,
        'extra_js' => null,
    ],
];
