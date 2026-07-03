<?php
/**
 * Lightweight radar / "stat wheel" SVG renderer.
 *
 * No charting library — a radar chart is just a polygon over a polar grid, so
 * this stays dependency-free and matches the site's SVG-native, no-runtime-JS
 * approach (same spirit as the inline mermaid diagrams). All presentation lives
 * in CSS classes (see the .radar-* rules in main.css) so it stays CSP-clean —
 * no inline style attributes.
 *
 * Scores are on a fixed 0–10 scale. Axis order is fixed per chart so silhouettes
 * are directly comparable.
 *
 * Two public helpers:
 *   render_radar($title, $scores)            — one filled polygon (small-multiple
 *                                              "stat wheel", uses RADAR_AXES).
 *   render_radar_overlay($axes, $series, ..) — several polygons on shared axes,
 *                                              for comparing entities on
 *                                              "bigger-is-better" dimensions.
 */

const RADAR_AXES = ['Context', 'Speed', 'Precision', 'Headroom', 'Plan'];

/**
 * Core geometry. $series is a list of:
 *   ['scores' => [axis => 0..10], 'variant' => 'a'|'b', ...]
 * 'variant' selects the .radar-data-{variant} / .radar-dot-{variant} classes.
 */
function _radar_svg(array $axes, array $series, string $caption = '', int $size = 220): string
{
    $n      = count($axes);
    $cx     = $size / 2;
    $cy     = $size / 2 - ($caption !== '' ? 8 : 0);
    $r      = $size * 0.30;          // radius at score = 10
    $labelR = $r + $size * 0.075;    // axis labels sit just outside the grid
    $vbH    = $size + ($caption !== '' ? 6 : 0);

    $angle = fn(int $i): float => deg2rad(-90 + $i * (360 / $n));
    $pt = function (float $value, float $radius, int $i) use ($cx, $cy, $angle): array {
        $rad = $angle($i);
        $d   = ($value / 10) * $radius;
        return [round($cx + $d * cos($rad), 1), round($cy + $d * sin($rad), 1)];
    };

    $label = $caption !== '' ? htmlspecialchars($caption) : '';
    $aria  = $label !== '' ? $label . ' ' : implode(', ', $axes) . ' ';
    $svg   = '<svg viewBox="0 0 ' . $size . ' ' . $vbH . '" role="img" '
           . 'aria-label="' . htmlspecialchars($aria) . 'radar" class="radar-wheel">';

    // Concentric grid rings (2,4,6,8,10).
    foreach ([2, 4, 6, 8, 10] as $ring) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            [$x, $y] = $pt($ring, $r, $i);
            $pts[] = "$x,$y";
        }
        $svg .= '<polygon points="' . implode(' ', $pts) . '" class="radar-grid"/>';
    }

    // Spokes + axis labels.
    for ($i = 0; $i < $n; $i++) {
        [$x, $y]   = $pt(10, $r, $i);
        $svg      .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" class="radar-spoke"/>';
        [$lx, $ly] = $pt(10, $labelR, $i);
        $rad       = $angle($i);
        $anchor    = abs(cos($rad)) < 0.3 ? 'middle' : (cos($rad) > 0 ? 'start' : 'end');
        $dy        = sin($rad) > 0.3 ? 8 : (sin($rad) < -0.3 ? -2 : 3);
        $svg      .= '<text x="' . $lx . '" y="' . ($ly + $dy) . '" text-anchor="' . $anchor
                   . '" class="radar-axis-label">' . htmlspecialchars($axes[$i]) . '</text>';
    }

    // Data polygons (drawn in given order; put the "winner" last so it sits on top).
    foreach ($series as $s) {
        $variant = $s['variant'] ?? 'a';
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            [$x, $y] = $pt($s['scores'][$axes[$i]] ?? 0, $r, $i);
            $pts[] = "$x,$y";
        }
        $svg .= '<polygon points="' . implode(' ', $pts) . '" class="radar-data radar-data-' . $variant . '"/>';
    }
    foreach ($series as $s) {
        $variant = $s['variant'] ?? 'a';
        for ($i = 0; $i < $n; $i++) {
            [$x, $y] = $pt($s['scores'][$axes[$i]] ?? 0, $r, $i);
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="2.4" class="radar-dot-' . $variant . '"/>';
        }
    }

    if ($label !== '') {
        $svg .= '<text x="' . $cx . '" y="' . ($size - 1) . '" text-anchor="middle" class="radar-title">' . $label . '</text>';
    }

    return $svg . '</svg>';
}

/** Single "stat wheel" small multiple over the fixed hardware axes. */
function render_radar(string $title, array $scores): string
{
    return _radar_svg(RADAR_AXES, [['scores' => $scores, 'variant' => 'a']], $title, 220);
}

/**
 * Overlaid comparison radar on shared, bigger-is-better axes.
 *
 * @param string[]            $axes   Ordered axis labels.
 * @param array<int,array>    $series Each: ['name'=>, 'scores'=>[axis=>0..10], 'variant'=>'a'|'b'].
 *                                    Order matters — last is drawn on top.
 * @return array{svg:string,legend:string} SVG plus an HTML legend block.
 */
function render_radar_overlay(array $axes, array $series, int $size = 320): array
{
    $svg = _radar_svg($axes, $series, '', $size);

    $legend = '<ul class="radar-legend">';
    foreach ($series as $s) {
        $variant = $s['variant'] ?? 'a';
        $legend .= '<li class="radar-legend-item"><span class="radar-swatch radar-swatch-' . $variant
                 . '"></span>' . htmlspecialchars($s['name'] ?? '') . '</li>';
    }
    $legend .= '</ul>';

    return ['svg' => $svg, 'legend' => $legend];
}
