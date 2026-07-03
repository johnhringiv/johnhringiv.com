# johnhringiv.com Style Guide

This document captures the consistent styling patterns used across the site. These patterns were previously unwritten but consistently applied.

---

## Color Palette

Colors use OKLCH for perceptually uniform manipulation:

| Purpose                            | Color        | OKLCH                 |
|------------------------------------|--------------|-----------------------|
| Primary (accents, navbar, borders) | Green        | `oklch(60% 0.17 155)` |
| Primary hover                      | Darker green | `oklch(55% 0.15 155)` |
| Body text                          | Warm brown   | `oklch(35% 0.04 75)`  |
| Page background                    | Cream        | `oklch(97% 0.025 90)` |
| Card/section background            | Parchment    | `oklch(94% 0.035 85)` |
| Links                              | Rust orange  | `oklch(48% 0.14 50)`  |
| Link hover                         | Darker rust  | `oklch(40% 0.12 50)`  |
| Code block background              | Dark papyrus | `oklch(22% 0.02 60)`  |
| Code block text                    | Light cream  | `oklch(88% 0.03 85)`  |
| Muted text                         | Gray         | `oklch(45% 0.01 250)` |
| Highlight (mark)                   | Light yellow | `oklch(96% 0.07 95)`  |

---

## Heading Patterns

### Section Headers (H2/H3 with underline)
Used for major page sections on Research, Press, Blog pages:
```html
<div class="headline mb-3">
    <h2 class="mt-2">Section Title</h2>
</div>
```
- Dotted gray border below the container
- Solid green (#0bab64) 2px underline on heading text
- Heading displayed as `inline-block`

### Blog Post H1
```html
<h1 class="fw-bolder bg-parchment mt-2">
    Title: <small class="text-muted">Subtitle</small>
</h1>
```
- Green 2px bottom border
- Parchment background
- Subtitle in muted gray, smaller text

### Content Headings (H2-H4)
All content headings follow this pattern:
```html
<h2 id="section-id" class="fw-bolder mb-4 mt-5">
    <a href="#section-id" class="text-reset text-decoration-none">Heading Text</a>
</h2>
```
- `fw-bolder` for font weight
- `mb-4 mt-5` spacing (more top margin for section separation)
- Self-referential anchor link for direct linking
- `text-reset text-decoration-none` removes link styling

### Heading Spacing Reference
| Element | Classes |
|---------|---------|
| H2 (major section) | `fw-bolder mb-4 mt-5` |
| H3 (subsection) | `fw-bolder mb-3 mt-4` or `fw-bolder mb-4 mt-5` |
| H4 (sub-subsection) | `fw-bolder mb-3 mt-4` or minimal/unstyled |
| H5/H6 (inline labels) | Typically unstyled |

**When to use full styling (with anchor links):**
- Major navigable sections readers might link to directly
- Substantive sub-sections with significant content below them
- Headings that appear in a logical document outline

**When to use minimal/unstyled headings:**
- **Inline labels**: Brief headers for small content blocks (e.g., test case labels, scenario names)
- **H6 scenario labels**: Small headers like "With Left-to-Right" / "With Right-to-Left" showing alternatives
- **Closing asides**: Brief headers like "What's next" at end of articles
- **Publication titles (H4)**: On research page, `<h4><i>Title</i></h4>` for publication names
- **H3 in `.headline` containers**: Use minimal styling (`mt-2`) as the container provides structure

**H3 spacious variant**: Use `mb-4 mt-5` for major subsections that need more visual separation

---

## Paragraph & Text Patterns

### Lead Paragraphs
For opening/introductory paragraphs:
```html
<p class="lead">Opening paragraph text...</p>
```

### Definition Callouts
For highlighting key terms/definitions:
```html
<p class="lead shadow-sm py-2 ps-2 rounded-3">
    <b class="fs-5">Term:</b> Definition text here.
</p>
```
- Light shadow for subtle background effect
- Rounded corners
- Bold term with `fs-5` sizing

---

## Section Spacing

### Between Major Sections
- Horizontal rule: `<hr class="my-5">`
- Or heading with `mt-5` creates natural separation

### Standard Spacing Classes Used
| Class | Purpose |
|-------|---------|
| `mb-3` | Small bottom margin (after minor elements) |
| `mb-4` | Medium bottom margin (after headings) |
| `mb-5` / `my-5` | Large margin (section dividers) |
| `mt-2` | Small top margin (after containers) |
| `mt-4` | Medium top margin (subsections) |
| `mt-5` | Large top margin (major sections) |
| `py-2` | Vertical padding (callouts) |
| `ps-2` / `ps-3` | Left padding (indented content) |
| `pb-2` | Bottom padding (containers) |

---

## Layout Patterns

### Main Page Container
```html
<div class="container-lg bg-parchment rounded mt-xxl-4">
    <!-- Page content -->
</div>
```

### Blog Post Container
```html
<div class="container blog-post pb-2">
    <article>
        <!-- Blog content -->
    </article>
</div>
```

### Two-Column Layouts

**Default (33%/67%)** - Research/Press pages:
```html
<div class="row">
    <figure class="col-md-4 image-modal-content">
        <?php echo responsiveImage(...); ?>
        <figcaption class="figure-caption">Caption</figcaption>
    </figure>
    <div class="col-md-8 float-left">
        <p>Content text...</p>
    </div>
</div>
```

**Layout Variants:**
| Split | Classes | Use Case |
|-------|---------|----------|
| 33%/67% | `col-md-4` / `col-md-8` | Default for research, press |
| 41%/59% | `col-md-5` / `col-md-7` | Better balance for wider images |
| 50%/50% | `col-md-6` / `col-md-6` | Equal-weight content, side-by-side comparisons |

### Centered Content
```html
<div class="text-center my-4">
    <img class="img-fluid mx-auto d-block" style="max-width: 800px;" ...>
</div>
```

---

## List Patterns

### Standard Lists
Default `<ul>` and `<ol>` with Bootstrap styling.

### Inline Lists (horizontal)
```html
<ul class="list-inline ms-4">
    <li class="list-inline-item"><a class="unlink" href="...">Link</a></li>
</ul>
```

### Unstyled Lists with Definitions
```html
<ul class="list-unstyled ps-3">
    <li class="mb-4 fs-5">
        <strong>Term</strong>
        <p class="mb-2 mt-2 fs-6">Description</p>
    </li>
</ul>
```

---

## Code Block Styling

### Including Highlighted Code
```php
<?php include "generated/highlighted-shiki/[blog-name]/[filename].html"; ?>
```

### Visual Properties
- Background: `oklch(22% 0.02 60)` (dark papyrus)
- Text: `#dbd7caee` (light cream)
- Border: `2px solid #444` with `border-radius: 8px`
- Padding: `1em`
- Box shadow for depth
- Line numbers at 50% opacity
- Copy button positioned bottom-right

### Green-tinted Variant
```html
<div class="shiki-bg-green">
    <?php include "generated/highlighted-shiki/..."; ?>
</div>
```
Used to visually distinguish transformation examples or highlight specific code representations.

### Mermaid Diagrams
```php
<?php include "generated/mermaid/[blog-name]/[filename].svg"; ?>
```
Diagrams render inline as SVG. Authoring and generation steps are in [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Configuration UI Patterns

For technical documentation showing settings/configuration:

### Setting Lists
```html
<li><b>Setting Name:</b> <kbd>value</kbd></li>
```

### Semantic Elements
| Element | Usage |
|---------|-------|
| `<kbd>` | Configuration values, constants, paths |
| `<var>` | UI element names, variable placeholders |
| `<mark>` | Important/highlighted text (light yellow background) |

---

## Blockquote Pattern

Blockquotes use a note/tip box style with left accent:
```html
<blockquote>
    <p>Quote or aside text...</p>
    <footer class="blockquote-footer">Attribution <cite>Source</cite></footer>
</blockquote>
```
- Light background (`oklch(97% 0.02 90)`)
- Left border: 4px solid rust/link color
- Right-side rounded corners
- Good for asides, tips, and humorous notes

---

## Affiliated Icons

For displaying partner/technology logos:
```html
<div class="affiliated-icons">
    <div class="icon-container">
        <img src="/img/icons/logo.svg" alt="Logo">
    </div>
</div>
```
- Responsive sizing: 200px on desktop (>435px), 80px on mobile
- Flexbox layout for horizontal arrangement
- Used for technology stack displays (HAProxy, pfSense, Cloudflare, etc.)

---

## Image Patterns

### Responsive Images with Modal
```php
<figure class="col-md-4 image-modal-content">
    <?php echo responsiveImage(
        '/img/path/image.jpg',
        'column',
        'Alt text',
        'img-fluid',
        ['(min-width: 768px) 33vw', '100vw']
    ); ?>
    <figcaption class="figure-caption">Caption <small>(source)</small></figcaption>
</figure>
```
- `image-modal-content` enables click-to-zoom
- `img-fluid` for responsive sizing
- `figure-caption` for styled captions

---

## Badge & Tag Patterns

### Blog Post Tags
```php
<?php foreach ($this->tags as $tag): ?>
    <?= BlogTags::renderBadge($tag) ?>
<?php endforeach; ?>
```
Renders as:
```html
<span class="badge bg-primary text-decoration-none">[Icon] Tag Name</span>
```
- Green background (`bg-primary`)
- Brown text color (`#5c4033`) instead of white

---

## Alert & Callout Patterns

### Bootstrap Alerts
```html
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Warning message</div>
```

### Reveal Answer Pattern (Interactive)
```html
<button data-action="reveal" class="btn btn-reveal-answer my-2">
    Click to reveal the answer
</button>
<p class="hidden-answer p-3 mt-2">
    Hidden content shown on click
</p>
```

---

## Card Pattern

### Homepage/Featured Cards
```html
<div class="card mt-4 mb-4">
    <div class="card-header">Header</div>
    <div class="card-body">
        <p class="card-text abstract">Content...</p>
    </div>
</div>
```

---

## Link Patterns

### Standard Links
Default styling with `color: #9C4A1A` (warm orange).

### Unlink Class (icons/semantic links)
```html
<a class="unlink" href="...">Link text</a>
```
- Inherits parent color
- No underline
- Green hover color (`#0bab64`)

### Abstract Links (research citations)
- Color: `#154734` (dark green)
- Font-weight: `470`

---

## Metadata Pattern (Blog Posts)

```html
<header class="mb-4">
    <div class="mb-3">
        <h1 class="fw-bolder bg-parchment mt-2">Title</h1>
    </div>
    <div class="text-muted fst-italic mb-2">
        Posted on [Date] by John [• Last modified Date]
    </div>
    <!-- Tags -->
</header>
```

---

## Architecture & Build

This guide covers **visual and content patterns only.** Everything else lives in one place to avoid drift:

- **Architecture & conventions** — the data layer (`data/*.php` → `scripts/build_db.php` → `www/generated/site.db`), adding a blog post, build-time validation, key files: see **[CLAUDE.md](../CLAUDE.md)**.
- **Build commands & pipelines** (`build:dev` / `build:prod` / `watch`; CSS, JS, icons, Shiki, Mermaid, images): see **[CONTRIBUTING.md](CONTRIBUTING.md)**.

---

## Summary of Consistent Patterns

1. **Green underlines** on all major headings
2. **Parchment backgrounds** for cards and sections
3. **Anchor links** on all content headings for direct linking
4. **Two-column layouts** with 4/8, 5/7, or 6/6 splits
5. **Lead paragraphs** for introductory text
6. **Shadow callouts** for definitions (`lead shadow-sm py-2 ps-2 rounded-3`)
7. **Flexible heading spacing**: `mb-4 mt-5` for H2; H3/H4 vary by context
8. **Image modals** via `image-modal-content` class
9. **Dark code blocks** with light text, copy buttons, and optional green tint
10. **Blog metadata** in muted italic text below title
11. **Semantic config elements**: `<kbd>`, `<var>`, `<mark>` for technical docs
12. **Mermaid diagrams** included from generated SVGs
13. **OKLCH colors** for perceptually uniform color manipulation
14. **CSS layers** (`@layer`) for specificity control without `!important`
15. **Blockquotes** styled as note/tip boxes with left accent
