// Dev utility: compose the claude-vs-local OG title card from real brand logos.
//
// Pulls the exact vector paths for the Claude Code, Qwen, and LM Studio
// "Combine + Color" logos out of the installed @lobehub/icons package and lays
// them either side of the in-post decision radar: Claude Code on the left, the
// local stack (LM Studio over Qwen) on the right. The output SVG is
// self-contained (paths inlined) so it commits cleanly and needs no
// node_modules at build time.
//
// Re-run after `npm i @lobehub/icons` if the upstream logos change:
//   node scripts/generate_titlecard_svg.mjs

import { readFileSync, writeFileSync } from 'node:fs';

const PKG = 'node_modules/@lobehub/icons/es';
const OUT = 'www/img/blog/open_graph/claude-vs-local.svg';

// All `<path>` blocks from a compiled component, in document order, with the
// path data and any fillOpacity (used for LM Studio's two-tone glyph).
const dPaths = (file) => {
  const text = readFileSync(`${PKG}/${file}`, 'utf8');
  return text.split('_jsx("path"').slice(1).map((b) => ({
    d: (b.match(/d:\s*"([^"]+)"/) || [])[1],
    fillOpacity: (b.match(/fillOpacity:\s*"?([0-9.]+)"?/) || [])[1] || null,
  })).filter((p) => p.d);
};
// Longest single path (for the single-path Claude/Qwen marks + wordmarks).
const dPath = (file) => dPaths(file).map((p) => p.d).sort((a, b) => b.length - a.length)[0];

const claudeMark = dPath('ClaudeCode/components/Color.js'); // 24x24, fill #D97757
const claudeText = dPath('ClaudeCode/components/Text.js');  // 56x24
const qwenMark = dPath('Qwen/components/Color.js');         // 24x24, gradient
const qwenText = dPath('Qwen/components/Text.js');          // 75x24

// LM Studio: Avatar mark (gradient chip + two-tone bars) + multi-path wordmark.
const lmsMono = dPaths('LmStudio/components/Mono.js');      // 24x24, two paths
const lmsFaint = lmsMono.find((p) => p.fillOpacity)?.d;    // full-width bars @ .3
const lmsSolid = lmsMono.find((p) => !p.fillOpacity)?.d;   // filled portion @ 1
const lmsText = dPaths('LmStudio/components/Text.js').map((p) => p.d); // 138x24, many paths

const WORDMARK = '#2a2520'; // dark wordmark text (matches the card title)

// Compose one logo (mark + wordmark) centered at (cx, cy) at mark height S.
// markInner/textInner are raw SVG fragments in their own coordinate systems
// (mark = 24x24; text = its own viewBox, width textW).
function logo({ cx, cy, S, markInner, textInner, textW, textMul, spaceMul }) {
  const markScale = S / 24;
  const gap = S * spaceMul;
  const textH = S * textMul;
  const textScale = textH / 24;
  const textWpx = textW * textScale;
  const textX = S + gap;
  const textY = (S - textH) / 2;
  const x = cx - (textX + textWpx) / 2;
  const y = cy - S / 2;
  return `  <g transform="translate(${x.toFixed(2)} ${y.toFixed(2)})">
    <g transform="scale(${markScale.toFixed(4)})">${markInner}</g>
    <g transform="translate(${textX.toFixed(2)} ${textY.toFixed(2)}) scale(${textScale.toFixed(4)})" fill="${WORDMARK}" fill-rule="evenodd">${textInner}</g>
  </g>`;
}

const claudeLogo = logo({
  cx: 190, cy: 322, S: 50,
  markInner: `<path d="${claudeMark}" fill="#D97757" fill-rule="evenodd" clip-rule="evenodd"/>`,
  textInner: `<path d="${claudeText}"/>`, textW: 56, textMul: 0.75, spaceMul: 0.3,
});

// Right side = local stack: Qwen (the model — co-headliner with Claude) on top,
// LM Studio (the runtime that serves it — deliberately smaller) below, joined by "+".
const qwenLogo = logo({
  cx: 1005, cy: 292, S: 48,
  markInner: `<path d="${qwenMark}" fill="url(#qwenGrad)" fill-rule="nonzero"/>`,
  textInner: `<path d="${qwenText}"/>`, textW: 75, textMul: 0.7, spaceMul: 0.2,
});

const lmStudioLogo = logo({
  cx: 1005, cy: 361, S: 30,
  markInner: `<rect width="24" height="24" rx="2.4" fill="url(#lmStudioGrad)"/>`
    + `<g transform="translate(3.6 3.6) scale(0.7)" fill="#fff"><path fill-opacity=".3" d="${lmsFaint}"/><path d="${lmsSolid}"/></g>`,
  textInner: lmsText.map((d) => `<path d="${d}"/>`).join(''), textW: 138, textMul: 0.6, spaceMul: 0.3,
});

const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630">
  <defs>
    <style>
      .hook     { font-family: Georgia, 'Times New Roman', serif; font-size: 50px; font-weight: bold; fill: #2a2520; }
      .hooksub  { font-family: Georgia, 'Times New Roman', serif; font-size: 21px; fill: #5c4033; }
      .tag      { font-family: 'Segoe UI', Arial, sans-serif; font-size: 17px; fill: #5c4033; }
      .plus     { font-family: 'Segoe UI', Arial, sans-serif; font-size: 26px; fill: #5c4033; fill-opacity: 0.55; }
      .caption  { font-family: 'Segoe UI', Arial, sans-serif; font-size: 17px; fill: #5c4033; }
      .brand    { font-family: Georgia, 'Times New Roman', serif; font-size: 16px; fill: #5c4033; }
    </style>
    <linearGradient id="qwenGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#6336E7"/>
      <stop offset="100%" stop-color="#6F69F7"/>
    </linearGradient>
    <linearGradient id="lmStudioGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#6C78EF"/>
      <stop offset="1" stop-color="#4F14BE"/>
    </linearGradient>
    <linearGradient id="leftWash" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#D97757" stop-opacity="0.20"/>
      <stop offset="1" stop-color="#D97757" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="rightWash" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#6336E7" stop-opacity="0"/>
      <stop offset="1" stop-color="#6336E7" stop-opacity="0.20"/>
    </linearGradient>
  </defs>

  <!-- Parchment background -->
  <rect fill="#D4C4A8" width="1200" height="630"/>

  <!-- Camp washes (coral=Claude left, purple=local right) -->
  <rect x="0"   y="8" width="520" height="614" fill="url(#leftWash)"/>
  <rect x="680" y="8" width="520" height="614" fill="url(#rightWash)"/>

  <!-- Green accent bars (site brand) -->
  <rect x="0" y="0"   width="1200" height="8" fill="#0bab64"/>
  <rect x="0" y="622" width="1200" height="8" fill="#0bab64"/>

  <!-- Hook -->
  <text class="hook"    x="600" y="70"  text-anchor="middle">Should you go local?</text>
  <text class="hooksub" x="600" y="102" text-anchor="middle">Planning, then building &#8212; a real coding task</text>

  <!-- ===== CENTER: decision radar emblem (center 600,340  R=150) ===== -->
  <polygon points="600,190 729.9,265 729.9,415 600,490 470.1,415 470.1,265" fill="none" stroke="#5c4033" stroke-opacity="0.20" stroke-width="1.5"/>
  <polygon points="600,265 664.95,302.5 664.95,377.5 600,415 535.05,377.5 535.05,302.5" fill="none" stroke="#5c4033" stroke-opacity="0.14" stroke-width="1"/>
  <g stroke="#5c4033" stroke-opacity="0.14" stroke-width="1">
    <line x1="600" y1="340" x2="600"   y2="190"/>
    <line x1="600" y1="340" x2="729.9" y2="265"/>
    <line x1="600" y1="340" x2="729.9" y2="415"/>
    <line x1="600" y1="340" x2="600"   y2="490"/>
    <line x1="600" y1="340" x2="470.1" y2="415"/>
    <line x1="600" y1="340" x2="470.1" y2="265"/>
  </g>
  <!-- Local (Qwen purple) lobe -->
  <polygon points="600,265 716.91,272.5 716.91,407.5 600,490 548.04,370 574.02,325"
           fill="#6336E7" fill-opacity="0.20" stroke="#6336E7" stroke-width="3" stroke-linejoin="round"/>
  <!-- Claude (clay) lobe -->
  <polygon points="600,190 651.96,310 664.95,377.5 600,370 483.09,407.5 470.1,265"
           fill="#D97757" fill-opacity="0.30" stroke="#C2603C" stroke-width="3" stroke-linejoin="round"/>
  <g fill="#6336E7"><circle cx="600" cy="265" r="3.5"/><circle cx="716.91" cy="272.5" r="3.5"/><circle cx="716.91" cy="407.5" r="3.5"/><circle cx="600" cy="490" r="3.5"/><circle cx="548.04" cy="370" r="3.5"/><circle cx="574.02" cy="325" r="3.5"/></g>
  <g fill="#C2603C"><circle cx="600" cy="190" r="3.5"/><circle cx="651.96" cy="310" r="3.5"/><circle cx="664.95" cy="377.5" r="3.5"/><circle cx="600" cy="370" r="3.5"/><circle cx="483.09" cy="407.5" r="3.5"/><circle cx="470.1" cy="265" r="3.5"/></g>

  <!-- ===== LEFT: Claude Code ===== -->
${claudeLogo}
  <text class="tag" x="190" y="398" text-anchor="middle">cloud &#183; 1M context</text>

  <!-- ===== RIGHT: local stack (Qwen + LM Studio) ===== -->
${qwenLogo}
  <text class="plus" x="1005" y="338" text-anchor="middle">+</text>
${lmStudioLogo}
  <text class="tag" x="1005" y="400" text-anchor="middle">local &#183; 24 GB &#183; 163k context</text>

  <!-- Caption -->
  <text class="caption" x="600" y="548" text-anchor="middle" fill-opacity="0.75">Claude wins quality &amp; throughput &#8212; local wins privacy &amp; control</text>

  <!-- Branding -->
  <text class="brand" x="600" y="600" text-anchor="middle">johnhringiv.com</text>
</svg>
`;

writeFileSync(OUT, svg);
console.log(`Wrote ${OUT} (claude ${claudeMark.length}b, qwen ${qwenMark.length}b, lmstudio ${(lmsSolid||'').length}b + ${lmsText.length} text paths)`);
