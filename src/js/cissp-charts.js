/**
 * CISSP Benchmark Interactive Visualizations
 * Uses D3 v7. Data is provided by PHP via window.CISSP_MODELS and window.CISSP_PER_QUESTION.
 */
import { select } from 'd3-selection';
import { extent, min, max, sum, range } from 'd3-array';
import { scaleLinear, scaleBand, scaleSqrt, scalePoint, scaleSequential, scaleDiverging } from 'd3-scale';
import { axisLeft, axisBottom } from 'd3-axis';
import { interpolateRdYlGn, interpolateBlues } from 'd3-scale-chromatic';
import { color } from 'd3-color';
import { line } from 'd3-shape';

// ─── Constants ───────────────────────────────────────────────────────────────

const FAMILY_COLORS = {
    'Llama':    'oklch(52% 0.16 275)',  // indigo
    'Gemma':    'oklch(52% 0.14 250)',  // royal blue
    'Gemma 3n': 'oklch(52% 0.12 185)', // deep teal
    'Qwen':     'oklch(52% 0.16 355)', // crimson (Chinese models)
    'DeepSeek': 'oklch(52% 0.16 355)', // same as Qwen — Qwen3 fine-tune
    'Mistral':  'oklch(52% 0.16 55)',  // orange (Mistral brand)
    'Phi':      'oklch(52% 0.14 300)', // violet
    'SmolLM':   'oklch(52% 0.10 130)', // sage
};

const QUANT_OPACITY = {
    'fp16':    1.0,
    'q8_0':    0.75,
    'q6_K':    0.65,
    'q5_K_M':  0.58,
    'q5_K_S':  0.55,
    'q4_K_M':  0.45,
    'q4_K_S':  0.40,
    'unknown': 0.5,
};



const DOMAIN_LABELS = {
    0: 'Practice',
    1: 'D1: Security & Risk',
    2: 'D2: Asset Security',
    3: 'D3: Architecture',
    4: 'D4: Network',
    5: 'D5: IAM',
    6: 'D6: Assessment',
    7: 'D7: Operations',
    8: 'D8: Software Dev',
};

const DOMAIN_SHORT = {
    0: 'Practice',
    1: 'D1: Risk Mgmt',
    2: 'D2: Asset Sec',
    3: 'D3: Architecture',
    4: 'D4: Network',
    5: 'D5: IAM',
    6: 'D6: Assessment',
    7: 'D7: Operations',
    8: 'D8: Software Dev',
};

const BASE_MODELS = [
    { base: 'llama3.1_8b-instruct',         name: 'Llama 3.1 8B',  family: 'Llama'    },
    { base: 'llama3.2_3b-instruct',         name: 'Llama 3.2 3B',  family: 'Llama'    },
    { base: 'llama3.2_1b-instruct',         name: 'Llama 3.2 1B',  family: 'Llama'    },
    { base: 'gemma3_4b-it',                 name: 'Gemma 3 4B',    family: 'Gemma'    },
    { base: 'gemma3_1b-it',                 name: 'Gemma 3 1B',    family: 'Gemma'    },
    { base: 'gemma3n_e4b-it',               name: 'G3n E4B',       family: 'Gemma 3n' },
    { base: 'gemma3n_e2b-it',               name: 'G3n E2B',       family: 'Gemma 3n' },
    { base: 'qwen3_8b',                     name: 'Qwen3 8B',      family: 'Qwen'     },
    { base: 'qwen3_4b',                     name: 'Qwen3 4B',      family: 'Qwen'     },
    { base: 'qwen3_1.7b',                   name: 'Qwen3 1.7B',    family: 'Qwen'     },
    { base: 'ministral-3_8b-instruct-2512', name: 'Ministral 8B',  family: 'Mistral'  },
    { base: 'ministral-3_3b-instruct-2512', name: 'Ministral 3B',  family: 'Mistral'  },
    { base: 'mistral_7b-instruct',          name: 'Mistral 7B',    family: 'Mistral'  },
    { base: 'deepseek-r1_8b-0528-qwen3',    name: 'DeepSeek R1',   family: 'DeepSeek' },
    { base: 'phi4-mini_3.8b',               name: 'Phi-4 Mini',    family: 'Phi'      },
    { base: 'smollm3_3b',                   name: 'SmolLM3 3B',    family: 'SmolLM'   },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function baseOf(key) {
    return key.replace(/-(fp16|q[0-9_a-zA-Z]+)$/, '');
}

function keysForBases(selectedBases, quantFilter, allModels) {
    return allModels
        .filter(m => selectedBases.has(baseOf(m.key)))
        .filter(m => quantFilter.has(m.quantization))
        .map(m => m.key);
}

// ─── Shared Tooltip ──────────────────────────────────────────────────────────

function createTooltip() {
    let tip = document.getElementById('cissp-tooltip');
    if (!tip) {
        tip = document.createElement('div');
        tip.id = 'cissp-tooltip';
        tip.className = 'cissp-tooltip';
        document.body.appendChild(tip);
    }
    return tip;
}

function showTooltip(tip, html, event) {
    tip.innerHTML = html;
    tip.style.display = 'block';
    positionTooltip(tip, event);
}

function positionTooltip(tip, event) {
    const x = event.pageX + 12;
    const y = event.pageY - 28;
    tip.style.left = Math.min(x, window.innerWidth - tip.offsetWidth - 16) + 'px';
    tip.style.top = Math.max(y, 8) + 'px';
}

function hideTooltip(tip) {
    tip.style.display = 'none';
}

// ─── Chart 1: Accuracy vs Speed Scatter ──────────────────────────────────────

class ScatterChart {
    constructor(containerId, models) {
        this.el = document.getElementById(containerId);
        if (!this.el) return;
        this.models = models;
        this.xMode = 'toks';
        this.tip = createTooltip();
        this._lastSelectedBases = new Set(BASE_MODELS.map(m => m.base));
        this._renderControls(); // also initializes this._lastQuantFilter
        this.svg = null;
        document.addEventListener('modelfilter', (e) => {
            this._lastSelectedBases = e.detail.selectedBases;
            this.update(this._lastSelectedBases, this._lastQuantFilter);
        });
        this.update(this._lastSelectedBases, this._lastQuantFilter);
        let _resizeTimer;
        new ResizeObserver(() => {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(() => this.update(this._lastSelectedBases, this._lastQuantFilter), 100);
        }).observe(this.el);
    }

    _renderControls() {
        const ctrl = document.createElement('div');
        ctrl.className = 'cissp-chart-controls';

        const label = document.createElement('span');
        label.textContent = 'X-axis: ';
        label.className = 'cissp-ctrl-label';

        const btnToks = document.createElement('button');
        btnToks.textContent = 'Tokens/sec';
        btnToks.className = 'cissp-btn cissp-btn-active';
        btnToks.addEventListener('click', () => {
            this.xMode = 'toks';
            btnToks.className = 'cissp-btn cissp-btn-active';
            btnTime.className = 'cissp-btn';
            this.update(this._lastSelectedBases, this._lastQuantFilter);
        });

        const btnTime = document.createElement('button');
        btnTime.textContent = 'Wall time (min)';
        btnTime.className = 'cissp-btn';
        btnTime.addEventListener('click', () => {
            this.xMode = 'time';
            btnTime.className = 'cissp-btn cissp-btn-active';
            btnToks.className = 'cissp-btn';
            this.update(this._lastSelectedBases, this._lastQuantFilter);
        });

        ctrl.append(label, btnToks, btnTime);
        this.el.appendChild(ctrl);

        // Quant precision picker (inline, owned by ScatterChart)
        const CORE_QUANTS = [
            { value: 'fp16',   label: 'FP16'   },
            { value: 'q8_0',   label: 'Q8_0'   },
            { value: 'q4_K_M', label: 'Q4_K_M' },
        ];
        const EXT_QUANTS = [
            { value: 'q6_K',   label: 'Q6_K'   },
            { value: 'q5_K_M', label: 'Q5_K_M' },
            { value: 'q5_K_S', label: 'Q5_K_S' },
            { value: 'q4_K_S', label: 'Q4_K_S' },
        ];

        this._lastQuantFilter = new Set(['fp16']);
        this._quantPills = {};

        const quantSection = document.createElement('div');
        quantSection.className = 'cissp-quant-section';

        const quantLabel = document.createElement('span');
        quantLabel.className = 'cissp-quant-label';
        quantLabel.textContent = 'Scatter precision:';
        quantSection.appendChild(quantLabel);

        for (const [opts, extraClass] of [[CORE_QUANTS, ''], [EXT_QUANTS, 'cissp-quant-ext-row']]) {
            const row = document.createElement('div');
            row.className = 'cissp-pills' + (extraClass ? ' ' + extraClass : '');
            for (const opt of opts) {
                const active = this._lastQuantFilter.has(opt.value);
                const pill = document.createElement('button');
                pill.className = 'cissp-pill' + (active ? ' active' : '');
                pill.textContent = opt.label;
                pill.style.borderColor = '#666';
                pill.style.background = active ? '#666' : 'transparent';
                pill.style.color = active ? '#fff' : '#666';
                pill.addEventListener('click', () => {
                    if (this._lastQuantFilter.has(opt.value)) {
                        this._lastQuantFilter.delete(opt.value);
                    } else {
                        this._lastQuantFilter.add(opt.value);
                    }
                    this._syncQuantPills();
                    this.update(this._lastSelectedBases, this._lastQuantFilter);
                });
                this._quantPills[opt.value] = pill;
                row.appendChild(pill);
            }
            quantSection.appendChild(row);
        }

        this.el.appendChild(quantSection);
    }

    _syncQuantPills() {
        for (const [value, pill] of Object.entries(this._quantPills)) {
            const active = this._lastQuantFilter.has(value);
            pill.className = 'cissp-pill' + (active ? ' active' : '');
            pill.style.background = active ? '#666' : 'transparent';
            pill.style.color = active ? '#fff' : '#666';
        }
    }

    update(selectedBases, quantFilter) {
        this._lastSelectedBases = selectedBases;
        this._lastQuantFilter = quantFilter;
        const keySet = new Set(keysForBases(selectedBases, quantFilter, this.models));
        const data = this.models.filter(m => keySet.has(m.key));
        this._draw(data);
    }

    _draw(data) {
        const margin = { top: 20, right: 20, bottom: 50, left: 60 };
        const width = this.el.clientWidth - margin.left - margin.right;
        const height = 380 - margin.top - margin.bottom;

        select(this.el).select('svg').remove();

        const svg = select(this.el).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const xKey = this.xMode === 'toks' ? 'avg_tokens_per_sec' : 'wall_time_min';
        const xLabel = this.xMode === 'toks' ? 'Tokens / second' : 'Wall time (minutes)';

        const xExt = extent(data, d => d[xKey]);
        const yExt = [
            Math.max(0, min(data, d => d.accuracy * 100) - 3),
            Math.min(100, max(data, d => d.accuracy * 100) + 3)
        ];

        const xScale = scaleLinear().domain(xExt.map((v, i) => v * (i === 0 ? 0.92 : 1.08))).range([0, width]).nice();
        const yScale = scaleLinear().domain(yExt).range([height, 0]).nice();

        // Bubble size scale based on peak VRAM
        const vramExt = extent(data, d => d.peak_vram_mb || 4000);
        const rScale = scaleSqrt().domain([0, vramExt[1]]).range([4, 20]);

        // Grid lines
        svg.append('g').attr('class', 'cissp-grid')
            .call(axisLeft(yScale).ticks(6).tickSize(-width).tickFormat(''))
            .select('.domain').remove();

        // Axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(axisBottom(xScale).ticks(6));

        svg.append('g').call(axisLeft(yScale).ticks(6).tickFormat(d => d + '%'));

        // Axis labels
        svg.append('text')
            .attr('x', width / 2).attr('y', height + 42)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text(xLabel);

        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -height / 2).attr('y', -48)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Accuracy (%)');

        const tip = this.tip;

        // Bubbles
        svg.selectAll('circle')
            .data(data)
            .join('circle')
            .attr('cx', d => xScale(d[xKey]))
            .attr('cy', d => yScale(d.accuracy * 100))
            .attr('r', d => rScale(d.peak_vram_mb || 6000))
            .attr('fill', d => FAMILY_COLORS[d.family] || '#888')
            .attr('fill-opacity', d => QUANT_OPACITY[d.quantization] ?? 0.7)
            .attr('stroke', '#fff')
            .attr('stroke-width', 1.5)
            .style('cursor', 'pointer')
            .on('mousemove', (event, d) => {
                showTooltip(tip, `
                    <strong>${d.display_name}</strong><br>
                    Quant: ${d.quantization}<br>
                    Accuracy: ${(d.accuracy * 100).toFixed(1)}%<br>
                    ${this.xMode === 'toks' ? 'Tokens/sec' : 'Wall time'}: ${this.xMode === 'toks' ? d.avg_tokens_per_sec.toFixed(1) : d.wall_time_min.toFixed(1) + ' min'}<br>
                    Peak VRAM: ${d.peak_vram_mb ? (d.peak_vram_mb / 1024).toFixed(1) + ' GB' : 'N/A'}
                `, event);
            })
            .on('mouseleave', () => hideTooltip(tip));

        // Labels for bubbles
        const onlyFP16 = data.every(d => d.quantization === 'fp16');
        svg.selectAll('text.bubble-label')
            .data(data)
            .join('text')
            .attr('class', 'bubble-label')
            .attr('x', d => xScale(d[xKey]))
            .attr('y', d => yScale(d.accuracy * 100) - rScale(d.peak_vram_mb || 6000) - 4)
            .attr('text-anchor', 'middle')
            .attr('font-size', '10px')
            .attr('fill', '#444')
            .text(d => onlyFP16 ? d.short_name : `${d.short_name} ${d.quantization.toUpperCase()}`);
    }
}

// ─── Chart 2: Domain Heatmap ──────────────────────────────────────────────────

class HeatmapChart {
    constructor(containerId, models) {
        this.el = document.getElementById(containerId);
        if (!this.el) return;
        this.models = models;
        this.tip = createTooltip();
        this.sortDomain = null;
        this.sortAsc = false;
        document.addEventListener('modelfilter', (e) => {
            this.update(e.detail.selectedBases);
        });
        this.update(new Set(BASE_MODELS.map(m => m.base)));
        let _resizeTimer;
        new ResizeObserver(() => {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(() => this._draw(this._sorted()), 100);
        }).observe(this.el.parentElement || this.el);
    }

    update(selectedBases) {
        // Always FP16 only, default order matches BASE_MODELS
        const baseOrder = BASE_MODELS.map(m => m.base);
        this._baseData = this.models
            .filter(m => m.quantization === 'fp16' && selectedBases.has(baseOf(m.key)))
            .sort((a, b) => baseOrder.indexOf(baseOf(a.key)) - baseOrder.indexOf(baseOf(b.key)));
        this._draw(this._sorted());
    }

    _sorted() {
        if (this.sortDomain === null) return [...this._baseData];
        const d = this.sortDomain;
        return [...this._baseData].sort((a, b) => {
            const av = a.domain_accuracy?.[d] ?? 0;
            const bv = b.domain_accuracy?.[d] ?? 0;
            return this.sortAsc ? av - bv : bv - av;
        });
    }

    _draw(data) {
        select(this.el).select('svg').remove();
        if (data.length === 0) return;

        const domains = [1, 2, 3, 4, 5, 6, 7, 8, 0];
        const margin = { top: 20, right: 20, bottom: 55, left: 105 };
        const cellH = 22;
        const cellW = Math.max(90, Math.floor((this.el.clientWidth - margin.left - margin.right) / domains.length));
        const width = cellW * domains.length;
        const height = cellH * data.length;

        const svg = select(this.el).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // Color scale: red (50%) → yellow (70%) → green (90%)
        const colorScale = scaleSequential()
            .domain([0.25, 0.95])
            .interpolator(interpolateRdYlGn);

        const tip = this.tip;

        // Column headers (clickable to sort)
        const self = this;
        svg.selectAll('text.col-header')
            .data(domains)
            .join('text')
            .attr('class', 'col-header')
            .attr('x', (d, i) => i * cellW + cellW / 2)
            .attr('y', -6)
            .attr('text-anchor', 'middle')
            .attr('font-size', '11px')
            .attr('fill', d => d === self.sortDomain ? 'oklch(48% 0.17 155)' : '#555')
            .attr('font-weight', d => d === self.sortDomain ? '700' : 'normal')
            .style('cursor', 'pointer')
            .text(d => DOMAIN_SHORT[d] + (d === self.sortDomain ? (self.sortAsc ? ' ▲' : ' ▼') : ' ⇅'))
            .on('click', (event, d) => {
                if (self.sortDomain === d) {
                    if (!self.sortAsc) { self.sortAsc = true; }
                    else { self.sortDomain = null; }
                } else {
                    self.sortDomain = d;
                    self.sortAsc = false;
                }
                self._draw(self._sorted());
            });

        // Row groups
        const rows = svg.selectAll('g.row')
            .data(data)
            .join('g')
            .attr('class', 'row')
            .attr('transform', (d, i) => `translate(0,${i * cellH})`);

        // Row labels
        rows.append('text')
            .attr('x', -6).attr('y', cellH / 2 + 4)
            .attr('text-anchor', 'end')
            .attr('font-size', '11px')
            .attr('fill', d => FAMILY_COLORS[d.family] || '#333')
            .text(d => d.display_name);

        // Cells
        rows.selectAll('rect')
            .data(d => domains.map(dom => ({
                model: d,
                domain: dom,
                accuracy: d.domain_accuracy?.[dom] ?? null
            })))
            .join('rect')
            .attr('x', (d, i) => i * cellW)
            .attr('y', 0)
            .attr('width', cellW - 2)
            .attr('height', cellH - 2)
            .attr('rx', 2)
            .attr('fill', d => d.accuracy !== null ? colorScale(d.accuracy) : '#eee')
            .on('mousemove', (event, d) => {
                if (d.accuracy === null) return;
                showTooltip(tip, `
                    <strong>${d.model.display_name}</strong><br>
                    ${DOMAIN_LABELS[d.domain]}<br>
                    Accuracy: ${(d.accuracy * 100).toFixed(1)}%
                `, event);
            })
            .on('mouseleave', () => hideTooltip(tip));

        // Cell text
        rows.selectAll('text.cell-text')
            .data(d => domains.map(dom => ({
                accuracy: d.domain_accuracy?.[dom] ?? null
            })))
            .join('text')
            .attr('class', 'cell-text')
            .attr('x', (d, i) => i * cellW + cellW / 2 - 1)
            .attr('y', cellH / 2 + 4)
            .attr('text-anchor', 'middle')
            .attr('font-size', '10px')
            .attr('fill', d => {
                if (d.accuracy === null) return '#aaa';
                const bg = color(colorScale(d.accuracy));
                return (bg.r * 0.299 + bg.g * 0.587 + bg.b * 0.114) > 140 ? '#111' : '#eee';
            })
            .text(d => d.accuracy !== null ? Math.round(d.accuracy * 100) + '%' : '');

        // Color legend
        const legendW = 160;
        const legendX = width / 2 - legendW / 2;
        const legendY = height + 30;

        const defs = svg.append('defs');
        const grad = defs.append('linearGradient').attr('id', 'heatmap-grad');
        for (let i = 0; i <= 10; i++) {
            grad.append('stop')
                .attr('offset', (i * 10) + '%')
                .attr('stop-color', colorScale(0.25 + (i / 10) * 0.7));
        }

        svg.append('rect')
            .attr('x', legendX).attr('y', legendY)
            .attr('width', legendW).attr('height', 10)
            .attr('fill', 'url(#heatmap-grad)');

        svg.append('text').attr('x', legendX).attr('y', legendY + 22)
            .attr('font-size', '10px').attr('fill', '#555').text('25%');
        svg.append('text').attr('x', legendX + legendW / 2).attr('y', legendY + 22)
            .attr('text-anchor', 'middle').attr('font-size', '10px').attr('fill', '#555').text('60%');
        svg.append('text').attr('x', legendX + legendW).attr('y', legendY + 22)
            .attr('text-anchor', 'end').attr('font-size', '10px').attr('fill', '#555').text('95%');
    }
}

// ─── Chart 3: Quantization ────────────────────────────────────────────────────

class QuantChart {
    constructor(containerId, models) {
        this.el = document.getElementById(containerId);
        if (!this.el) return;
        this.models = models;
        this.tip = createTooltip();
        this.deltaQuant = 'q8_0';
        this._lastBases = new Set(BASE_MODELS.map(m => m.base));
        document.addEventListener('modelfilter', (e) => {
            this.update(e.detail.selectedBases);
        });
        this.update(this._lastBases);
        let _resizeTimer;
        new ResizeObserver(() => {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(() => this._draw(this._lastBases), 100);
        }).observe(this.el);
    }

    update(selectedBases) {
        this._lastBases = selectedBases;
        this._draw(selectedBases);
    }

    _draw(selectedBases) {
        select(this.el).select('svg').remove();
        select(this.el).select('.cissp-quant-panels').remove();

        if (selectedBases.size === 0) return;

        // Group all models by base
        const byBase = {};
        for (const m of this.models) {
            const base = baseOf(m.key);
            if (!byBase[base]) byBase[base] = [];
            byBase[base].push(m);
        }

        const container = document.createElement('div');
        container.className = 'cissp-quant-panels';
        this.el.appendChild(container);

        this._drawDeltaPanel(container, selectedBases, byBase);
        this._drawCombinedPanel(container, selectedBases, byBase);
    }

    _drawCombinedPanel(container, selectedBases, byBase) {
        const panel = document.createElement('div');
        panel.className = 'cissp-quant-panel';
        const title = document.createElement('h4');
        title.className = 'cissp-panel-title';
        title.textContent = 'Peak VRAM & runtime';
        panel.appendChild(title);
        container.appendChild(panel);

        const baseOrder = BASE_MODELS.map(m => m.base);
        const modelData = [...selectedBases]
            .sort((a, b) => baseOrder.indexOf(a) - baseOrder.indexOf(b))
            .map(base => {
                const runs = byBase[base] || [];
                const fp16 = runs.find(m => m.quantization === 'fp16');
                const q8   = runs.find(m => m.quantization === 'q8_0');
                const q4   = runs.find(m => m.quantization === 'q4_K_M');
                if (!fp16 || fp16.peak_vram_mb === null) return null;
                const fp16_sec = fp16.wall_time_min * 60;
                return {
                    base,
                    name: fp16.short_name,
                    family: fp16.family,
                    fp16_vram: fp16.peak_vram_mb / 1024,
                    q8_vram:   (q8?.peak_vram_mb  ?? fp16.peak_vram_mb) / 1024,
                    q4_vram:   (q4?.peak_vram_mb  ?? fp16.peak_vram_mb) / 1024,
                    fp16_sec,
                    q8_sec: q8 ? q8.wall_time_min * 60 : fp16_sec,
                    q4_sec: q4 ? q4.wall_time_min * 60 : fp16_sec,
                };
            })
            .filter(Boolean);

        if (modelData.length === 0) return;

        // Sort by FP16 VRAM descending — matches standalone VRAM panel ordering
        modelData.sort((a, b) => b.fp16_vram - a.fp16_vram);

        const margin  = { top: 20, right: 110, bottom: 70, left: 60 };
        const hVram   = 120;
        const hTime   = 90;
        const width   = Math.max(400, this.el.clientWidth - margin.left - margin.right);

        const svg = select(panel).append('svg')
            .attr('width',  width + margin.left + margin.right)
            .attr('height', hVram + hTime + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const zeroY = hVram;  // y-coord of the dividing line

        const xScale = scaleBand()
            .domain(modelData.map(d => d.base))
            .range([0, width])
            .padding(0.25);

        // VRAM scale: 0 at zeroY, maxVram at y=0 (grows upward)
        const maxVram    = max(modelData, d => d.fp16_vram) * 1.12;
        const yVram      = scaleLinear().domain([0, maxVram]).range([zeroY, 0]);

        // Runtime scale: 0 at zeroY, grows downward (max = longest FP16 run)
        const maxRuntime = max(modelData, d => d.fp16_sec) * 1.12;
        const yTime      = scaleLinear().domain([0, maxRuntime]).range([0, hTime]);

        // Grid (upper section only)
        svg.append('g').attr('class', 'cissp-grid')
            .call(axisLeft(yVram).ticks(4).tickSize(-width).tickFormat(''))
            .select('.domain').remove();

        // Dividing line
        svg.append('line')
            .attr('x1', 0).attr('x2', width)
            .attr('y1', zeroY).attr('y2', zeroY)
            .attr('stroke', '#bbb').attr('stroke-width', 1);

        // VRAM y-axis (left, upper)
        svg.append('g')
            .call(axisLeft(yVram).ticks(4).tickFormat(d => d.toFixed(0) + ' GB'));

        // Runtime y-axis (left, lower) — origin translated to zeroY
        svg.append('g')
            .attr('transform', `translate(0,${zeroY})`)
            .call(axisLeft(
                scaleLinear().domain([0, maxRuntime]).range([0, hTime])
            ).ticks(3).tickFormat(d => d > 0 ? d + 's' : ''));

        // x-axis tick marks at dividing line
        svg.append('g')
            .attr('transform', `translate(0,${zeroY})`)
            .call(axisBottom(xScale).tickFormat(() => '').tickSize(4));

        const tip = this.tip;

        // VRAM stacked bars — upward from zeroY
        for (const m of modelData) {
            const color = FAMILY_COLORS[m.family] || '#888';
            const segs = [
                { bot: 0,         top: m.q4_vram,   op: 1.0  },
                { bot: m.q4_vram, top: m.q8_vram,   op: 0.55 },
                { bot: m.q8_vram, top: m.fp16_vram, op: 0.3  },
            ];
            for (const s of segs) {
                const sy = yVram(s.top);
                const sh = yVram(s.bot) - yVram(s.top);
                if (sh < 0.5) continue;
                svg.append('rect')
                    .attr('x', xScale(m.base)).attr('y', sy)
                    .attr('width', xScale.bandwidth()).attr('height', sh)
                    .attr('fill', color).attr('fill-opacity', s.op)
                    .on('mousemove', ev => showTooltip(tip, `
                        <strong>${m.name}</strong><br>
                        FP16: ${m.fp16_vram.toFixed(1)} GB<br>
                        Q8_0: ${m.q8_vram.toFixed(1)} GB<br>
                        Q4_K_M: ${m.q4_vram.toFixed(1)} GB
                    `, ev))
                    .on('mouseleave', () => hideTooltip(tip));
            }
        }

        // Runtime stacked bars — downward from zeroY, mirroring VRAM opacity convention:
        //   top segment (lightest, touches dividing line): FP16 − Q8 overhead
        //   middle segment: Q8 − Q4 overhead
        //   bottom segment (darkest): Q4 base runtime
        for (const m of modelData) {
            const fp16H  = yTime(m.fp16_sec);
            const q8H    = yTime(m.q8_sec);
            const q4H    = yTime(m.q4_sec);
            const segs = [
                { y: zeroY,          h: q4H,         op: 1.0  },  // Q4 base
                { y: zeroY + q4H,    h: q8H  - q4H,  op: 0.55 },  // Q8−Q4 overhead
                { y: zeroY + q8H,    h: fp16H - q8H, op: 0.3  },  // FP16−Q8 overhead
            ];
            for (const s of segs) {
                if (s.h < 0.5) continue;
                svg.append('rect')
                    .attr('x', xScale(m.base)).attr('y', s.y)
                    .attr('width', xScale.bandwidth()).attr('height', s.h)
                    .attr('fill', '#6b7280').attr('fill-opacity', s.op)
                    .on('mousemove', ev => showTooltip(tip, `
                        <strong>${m.name}</strong><br>
                        FP16: ${m.fp16_sec.toFixed(0)} s<br>
                        Q8_0: ${m.q8_sec.toFixed(0)} s<br>
                        Q4_K_M: ${m.q4_sec.toFixed(0)} s
                    `, ev))
                    .on('mouseleave', () => hideTooltip(tip));
            }
        }

        // Model labels below the time savings section
        svg.selectAll('text.comb-label')
            .data(modelData)
            .join('text')
            .attr('class', 'comb-label')
            .attr('transform', d => {
                const cx = xScale(d.base) + xScale.bandwidth() / 2;
                return `translate(${cx},${zeroY + hTime + 8}) rotate(-40)`;
            })
            .attr('text-anchor', 'end')
            .attr('font-size', '10px')
            .attr('fill', d => FAMILY_COLORS[d.family] || '#444')
            .text(d => d.name);

        // Axis labels
        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -(hVram / 2)).attr('y', -48)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Peak VRAM (GB)');
        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -(zeroY + hTime / 2)).attr('y', -48)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Runtime (s)');

        // Legend (right side) — same items for both sections
        const legendX = width + 10;
        const legendItems = [
            { label: 'Q4_K_M',  op: 1.0  },
            { label: '+ Q8_0',  op: 0.55 },
            { label: '+ FP16',  op: 0.3  },
        ];
        legendItems.forEach((item, i) => {
            svg.append('rect')
                .attr('x', legendX).attr('y', i * 18)
                .attr('width', 12).attr('height', 12)
                .attr('fill', '#888').attr('fill-opacity', item.op).attr('rx', 2);
            svg.append('text')
                .attr('x', legendX + 16).attr('y', i * 18 + 10)
                .attr('font-size', '10px').attr('fill', '#555')
                .text(item.label);
        });

        const note = document.createElement('p');
        note.className = 'cissp-panel-note';
        note.textContent = 'Combined VRAM & runtime at FP16, Q8_0, and Q4_K_M.';
        panel.appendChild(note);
    }

    _drawDeltaPanel(container, selectedBases, byBase) {
        const AVAILABLE_QUANTS = ['q8_0', 'q4_K_M'];
        const QUANT_LABELS = { q8_0: 'Q8_0', q4_K_M: 'Q4_K_M' };
        const QUANT_ORDER  = [this.deltaQuant];

        // Build per-model data: collect all available quant deltas vs FP16
        const modelData = [];
        for (const base of selectedBases) {
            const runs = byBase[base] || [];
            const fp16 = runs.find(m => m.quantization === 'fp16');
            if (!fp16) continue;

            const quants = QUANT_ORDER
                .map(q => ({ q, run: runs.find(m => m.quantization === q) }))
                .filter(({ run }) => run)
                .map(({ q, run }) => ({
                    quant: q,
                    delta: (run.accuracy - fp16.accuracy) * 100,
                    q_acc: run.accuracy * 100,
                    fp16_acc: fp16.accuracy * 100,
                }));

            if (quants.length === 0) continue;

            // Sort key: q4_K_M if available, else the most-compressed present
            const sortDelta = (quants.find(q => q.quant === 'q4_K_M') ?? quants[quants.length - 1]).delta;

            modelData.push({
                base,
                name: fp16.short_name,
                family: fp16.family,
                fp16_acc: fp16.accuracy * 100,
                sortDelta,
                quants,
            });
        }

        const panelA = document.createElement('div');
        panelA.className = 'cissp-quant-panel';

        // Title row with quant toggle pills
        const titleRow = document.createElement('div');
        titleRow.className = 'cissp-delta-title-row';
        const titleA = document.createElement('h4');
        titleA.className = 'cissp-panel-title';
        titleA.textContent = 'Accuracy drop vs FP16 — by quantization level';
        titleRow.appendChild(titleA);
        const toggleRow = document.createElement('div');
        toggleRow.className = 'cissp-delta-toggle-row';
        AVAILABLE_QUANTS.forEach(q => {
            const pill = document.createElement('button');
            pill.className = 'cissp-delta-toggle' + (this.deltaQuant === q ? ' active' : '');
            pill.textContent = QUANT_LABELS[q];
            pill.addEventListener('click', () => {
                if (this.deltaQuant !== q) {
                    this.deltaQuant = q;
                    this._draw(this._lastBases);
                }
            });
            toggleRow.appendChild(pill);
        });
        titleRow.appendChild(toggleRow);
        panelA.appendChild(titleRow);
        container.appendChild(panelA);

        if (modelData.length === 0) {
            const p = document.createElement('p');
            p.className = 'cissp-placeholder';
            p.textContent = 'No models have quantization benchmark data.';
            panelA.appendChild(p);
            return;
        }

        // Sort by q4_K_M delta (most hurt → leftmost)
        modelData.sort((a, b) => a.sortDelta - b.sortDelta);

        const margin = { top: 30, right: 20, bottom: 70, left: 65 };
        const width = Math.max(400, this.el.clientWidth - margin.left - margin.right);
        const height = 260 - margin.top - margin.bottom;

        const svg = select(panelA).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const xScale = scaleBand()
            .domain(modelData.map(d => d.base))
            .range([0, width])
            .padding(0.25);

        const allDeltas = modelData.flatMap(d => d.quants.map(q => q.delta));
        const yMin = Math.min(min(allDeltas) - 1, -0.5);
        const yMax = Math.max(max(allDeltas) + 1, 0.5);

        const yScale = scaleLinear()
            .domain([yMin, yMax])
            .range([height, 0])
            .nice();

        // Grid
        svg.append('g').attr('class', 'cissp-grid')
            .call(axisLeft(yScale).ticks(5).tickSize(-width).tickFormat(''))
            .select('.domain').remove();

        // Zero reference line
        const y0 = yScale(0);
        svg.append('line')
            .attr('x1', 0).attr('x2', width)
            .attr('y1', y0).attr('y2', y0)
            .attr('stroke', '#999').attr('stroke-dasharray', '4,2').attr('stroke-width', 1);

        // Axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(axisBottom(xScale).tickFormat(() => ''));

        svg.append('g').call(axisLeft(yScale).ticks(5).tickFormat(d => (d >= 0 ? '+' : '') + d.toFixed(1) + '%'));

        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -height / 2).attr('y', -52)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Accuracy change (pp)');

        const tip = this.tip;

        // Mini-bars: each model slot is divided equally among its quant levels
        for (const m of modelData) {
            const slotX = xScale(m.base);
            const slotW = xScale.bandwidth();
            const nQ = m.quants.length;
            const barW = slotW / nQ;
            const color = FAMILY_COLORS[m.family] || '#888';

            m.quants.forEach((q, qi) => {
                const bx = slotX + qi * barW;
                svg.append('rect')
                    .attr('x', bx)
                    .attr('y', q.delta < 0 ? y0 : yScale(q.delta))
                    .attr('width', Math.max(1, barW - 1))
                    .attr('height', Math.abs(yScale(q.delta) - y0))
                    .attr('fill', color)
                    .attr('rx', 1)
                    .on('mousemove', (event) => {
                        showTooltip(tip, `
                            <strong>${m.name}</strong><br>
                            ${q.quant}: ${q.q_acc.toFixed(1)}%<br>
                            Δ FP16: ${q.delta >= 0 ? '+' : ''}${q.delta.toFixed(1)} pp
                        `, event);
                    })
                    .on('mouseleave', () => hideTooltip(tip));
            });
        }

        // Rotated model name labels
        svg.selectAll('text.bar-label')
            .data(modelData)
            .join('text')
            .attr('class', 'bar-label')
            .attr('transform', d => {
                const cx = xScale(d.base) + xScale.bandwidth() / 2;
                return `translate(${cx},${height + 12}) rotate(-40)`;
            })
            .attr('text-anchor', 'end')
            .attr('font-size', '10px')
            .attr('fill', d => FAMILY_COLORS[d.family] || '#444')
            .text(d => d.name);

        const note = document.createElement('p');
        note.className = 'cissp-panel-note';
        note.textContent = 'Accuracy drop vs FP16 per model. Toggle Q8_0 / Q4_K_M above.';
        panelA.appendChild(note);
    }

}

// ─── Standalone ladder chart (full quant ladder, static — not filter-driven) ─

function drawLadderPanel(containerEl, panelEl, bases, byBase, tip) {
        const margin = { top: 20, right: 20, bottom: 50, left: 60 };
        const width = Math.max(400, containerEl.clientWidth - margin.left - margin.right);
        const height = 300 - margin.top - margin.bottom;

        const svg = select(panelEl).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // Collect all quant levels used by these bases
        const allQuants = new Set();
        for (const base of bases) {
            for (const m of (byBase[base] || [])) {
                allQuants.add(m.quantization);
            }
        }

        const quantOrder = ['fp16', 'q8_0', 'q6_K', 'q5_K_M', 'q5_K_S', 'q4_K_M', 'q4_K_S']
            .filter(q => allQuants.has(q));

        const xScale = scalePoint()
            .domain(quantOrder)
            .range([0, width])
            .padding(0.3);

        const allAccuracies = [];
        for (const base of bases) {
            for (const m of (byBase[base] || [])) {
                if (quantOrder.includes(m.quantization)) {
                    allAccuracies.push(m.accuracy * 100);
                }
            }
        }

        const yMin = Math.max(0, min(allAccuracies) - 3);
        const yMax = Math.min(100, max(allAccuracies) + 3);
        const yScale = scaleLinear().domain([yMin, yMax]).range([height, 0]).nice();

        // Grid
        svg.append('g').attr('class', 'cissp-grid')
            .call(axisLeft(yScale).ticks(5).tickSize(-width).tickFormat(''))
            .select('.domain').remove();

        // Axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(axisBottom(xScale));

        svg.append('g').call(axisLeft(yScale).ticks(5).tickFormat(d => d + '%'));

        svg.append('text')
            .attr('x', width / 2).attr('y', height + 42)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Quantization level');

        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -height / 2).attr('y', -48)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Accuracy (%)');

        const lineGenerator = line()
            .x(d => xScale(d.quantization))
            .y(d => yScale(d.accuracy * 100))
            .defined(d => d !== null);

        for (const base of bases) {
            const runs = (byBase[base] || [])
                .filter(m => quantOrder.includes(m.quantization))
                .sort((a, b) => quantOrder.indexOf(a.quantization) - quantOrder.indexOf(b.quantization));

            if (runs.length === 0) continue;
            const color = FAMILY_COLORS[runs[0].family] || '#888';

            svg.append('path')
                .datum(runs)
                .attr('fill', 'none')
                .attr('stroke', color)
                .attr('stroke-width', 2)
                .attr('d', lineGenerator);

            svg.selectAll(`circle.${base.replace(/[^a-z0-9]/gi, '_')}`)
                .data(runs)
                .join('circle')
                .attr('cx', d => xScale(d.quantization))
                .attr('cy', d => yScale(d.accuracy * 100))
                .attr('r', 5)
                .attr('fill', color)
                .attr('stroke', '#fff')
                .attr('stroke-width', 1.5)
                .on('mousemove', (event, d) => {
                    showTooltip(tip, `
                        <strong>${d.display_name}</strong><br>
                        Quant: ${d.quantization}<br>
                        Accuracy: ${(d.accuracy * 100).toFixed(1)}%<br>
                        Δ FP16: ${(d.delta_fp16 !== null ? (d.delta_fp16 >= 0 ? '+' : '') + d.delta_fp16.toFixed(1) : 'N/A')}%
                    `, event);
                })
                .on('mouseleave', () => hideTooltip(tip));

            // Label at FP16 point
            const fp16run = runs.find(r => r.quantization === 'fp16');
            if (fp16run) {
                svg.append('text')
                    .attr('x', xScale('fp16'))
                    .attr('y', yScale(fp16run.accuracy * 100) - 8)
                    .attr('text-anchor', 'middle')
                    .attr('font-size', '10px')
                    .attr('fill', color)
                    .text(fp16run.short_name);
            }
        }
}

// ─── Ladder chart init (static, not filter-driven) ───────────────────────────

function initLadderChart(containerId, models) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const byBase = {};
    for (const m of models) {
        const base = baseOf(m.key);
        if (!byBase[base]) byBase[base] = [];
        byBase[base].push(m);
    }
    const extendedBases = BASE_MODELS.map(m => m.base).filter(b => (byBase[b] || []).length > 3);
    if (extendedBases.length === 0) return;
    const container = document.createElement('div');
    container.className = 'cissp-quant-panels';
    el.appendChild(container);
    const panelEl = document.createElement('div');
    panelEl.className = 'cissp-quant-panel';
    container.appendChild(panelEl);
    const tip = createTooltip();
    drawLadderPanel(el, panelEl, extendedBases, byBase, tip);
    let _resizeTimer;
    new ResizeObserver(() => {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(() => {
            panelEl.innerHTML = '';
            drawLadderPanel(el, panelEl, extendedBases, byBase, tip);
        }, 100);
    }).observe(el);
}

// ─── Chart 4: Question Difficulty ────────────────────────────────────────────

class DifficultyChart {
    constructor(containerId, perQuestion, models) {
        this.el = document.getElementById(containerId);
        if (!this.el) return;
        this.perQuestion = perQuestion;
        this.models = models;
        this.tip = createTooltip();
        document.addEventListener('modelfilter', (e) => {
            this.update(e.detail.selectedBases);
        });
        this._lastBases = new Set(BASE_MODELS.map(m => m.base));
        this.update(this._lastBases);
        let _resizeTimer;
        new ResizeObserver(() => {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(() => this.update(this._lastBases), 100);
        }).observe(this.el);
    }

    update(selectedBases) {
        this._lastBases = selectedBases;
        const fp16Keys = this.models
            .filter(m => m.quantization === 'fp16' && selectedBases.has(baseOf(m.key)))
            .map(m => m.key);
        if (!this.perQuestion || fp16Keys.length === 0) return;

        const n = fp16Keys.length;
        const dist = new Array(n + 1).fill(0);
        const byBucket = new Array(n + 1).fill(null).map(() => []);

        for (const q of this.perQuestion) {
            const correct = fp16Keys.filter(k => q.results[k] === 1).length;
            dist[correct]++;
            if (byBucket[correct].length < 5) byBucket[correct].push(q.qid);
        }

        this._draw(dist, byBucket, n);
    }

    _draw(dist, byBucket, maxN) {
        const margin = { top: 20, right: 20, bottom: 50, left: 60 };
        const width = this.el.clientWidth - margin.left - margin.right;
        const height = 320 - margin.top - margin.bottom;

        select(this.el).select('svg').remove();

        const svg = select(this.el).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const xScale = scaleBand()
            .domain(range(0, maxN + 1))
            .range([0, width])
            .padding(0.1);

        const yScale = scaleLinear()
            .domain([0, max(dist) * 1.1])
            .range([height, 0]);

        function bucketColor(n, maxN) {
            const pct = maxN > 0 ? n / maxN : 0;
            if (pct <= 0.2) return '#ef4444';
            if (pct <= 0.45) return '#f97316';
            if (pct <= 0.7)  return '#eab308';
            return '#22c55e';
        }

        // Grid
        svg.append('g').attr('class', 'cissp-grid')
            .call(axisLeft(yScale).ticks(5).tickSize(-width).tickFormat(''))
            .select('.domain').remove();

        // Axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(axisBottom(xScale).tickValues(
                range(0, maxN + 1, maxN > 8 ? 2 : 1)
            ));

        svg.append('g').call(axisLeft(yScale).ticks(5));

        svg.append('text')
            .attr('x', width / 2).attr('y', height + 42)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text(`Models correct (out of ${maxN})`);

        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -height / 2).attr('y', -48)
            .attr('text-anchor', 'middle').attr('class', 'cissp-axis-label')
            .text('Number of questions');

        const total = sum(dist);
        const tip = this.tip;

        // Bind {count, bucket} so the event handler has the bucket index directly
        const barData = dist.map((count, bucket) => ({ count, bucket }));

        svg.selectAll('rect')
            .data(barData)
            .join('rect')
            .attr('x', d => xScale(d.bucket))
            .attr('y', d => yScale(d.count))
            .attr('width', xScale.bandwidth())
            .attr('height', d => height - yScale(d.count))
            .attr('fill', d => bucketColor(d.bucket, maxN))
            .attr('rx', 2)
            .on('mousemove', (event, d) => {
                const pct = total > 0 ? ((d.count / total) * 100).toFixed(1) : 0;
                showTooltip(tip, `
                    <strong>${d.bucket} of ${maxN} models correct</strong><br>
                    Questions: ${d.count} (${pct}%)
                `, event);
            })
            .on('mouseleave', () => hideTooltip(tip));
    }
}

// ─── Chart 5: Agreement Matrix ────────────────────────────────────────────────

class AgreementMatrix {
    constructor(containerId, models) {
        this.el = document.getElementById(containerId);
        if (!this.el) return;
        this.models = models;
        this.tip = createTooltip();
        document.addEventListener('modelfilter', (e) => {
            this._update(e.detail.selectedBases);
        });
        this._update(new Set(BASE_MODELS.map(m => m.base)));
        let _resizeTimer;
        new ResizeObserver(() => {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(() => this._draw(this._fp16), 100);
        }).observe(this.el.parentElement || this.el);
    }

    _update(selectedBases) {
        const fp16 = this.models
            .filter(m => m.quantization === 'fp16' && selectedBases.has(baseOf(m.key)))
            .sort((a, b) => a.family.localeCompare(b.family) || a.key.localeCompare(b.key));
        this._fp16 = fp16;
        this._draw(fp16);
    }

    _draw(fp16Models) {
        if (!window.CISSP_AGREEMENT || fp16Models.length === 0) return;

        const keys = fp16Models.map(m => m.key);
        const n = keys.length;

        // Jaccard similarity from pre-computed agreement data
        const { wrong_counts, pairs } = window.CISSP_AGREEMENT;
        const matrix = [];
        for (let i = 0; i < n; i++) {
            matrix[i] = [];
            for (let j = 0; j < n; j++) {
                if (i === j) { matrix[i][j] = { jaccard: 1, inter: 0, union: 0 }; continue; }
                const ki = keys[i], kj = keys[j];
                const pairKey = ki < kj ? `${ki}|${kj}` : `${kj}|${ki}`;
                const inter = pairs[pairKey] ?? 0;
                const union = (wrong_counts[ki] ?? 0) + (wrong_counts[kj] ?? 0) - inter;
                matrix[i][j] = { jaccard: union > 0 ? inter / union : 0, inter, union };
            }
        }

        const margin = { top: 30, right: 80, bottom: 120, left: 160 };
        const cell = Math.max(22, Math.floor((this.el.clientWidth - margin.left - margin.right) / n));
        const width = cell * n;
        const height = cell * n;

        select(this.el).select('svg').remove();

        const svg = select(this.el).append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const colorScale = scaleSequential()
            .domain([0, 1])
            .interpolator(interpolateBlues);

        const tip = this.tip;

        // Row labels
        svg.selectAll('text.row-label')
            .data(fp16Models)
            .join('text')
            .attr('class', 'row-label')
            .attr('x', -6)
            .attr('y', (d, i) => i * cell + cell / 2 + 4)
            .attr('text-anchor', 'end')
            .attr('font-size', '11px')
            .attr('fill', d => FAMILY_COLORS[d.family] || '#333')
            .text(d => d.short_name);

        // Col labels
        svg.selectAll('text.col-label')
            .data(fp16Models)
            .join('text')
            .attr('class', 'col-label')
            .attr('transform', (d, i) => `translate(${i * cell + cell / 2 + 4},${height + 8}) rotate(45)`)
            .attr('text-anchor', 'start')
            .attr('font-size', '11px')
            .attr('fill', d => FAMILY_COLORS[d.family] || '#333')
            .text(d => d.short_name);

        // Cells
        for (let i = 0; i < n; i++) {
            svg.selectAll(`rect.row-${i}`)
                .data(matrix[i])
                .join('rect')
                .attr('x', (d, j) => j * cell)
                .attr('y', i * cell)
                .attr('width', cell - 1)
                .attr('height', cell - 1)
                .attr('rx', 1)
                .attr('fill', d => colorScale(d.jaccard))
                .on('mousemove', (event, d) => {
                    const j = matrix[i].indexOf(d);
                    if (i === j) {
                        showTooltip(tip, `<strong>${fp16Models[i].display_name}</strong><br>Self`, event);
                        return;
                    }
                    showTooltip(tip, `
                        <strong>${fp16Models[i].short_name}</strong> vs <strong>${fp16Models[j].short_name}</strong><br>
                        Jaccard: ${(d.jaccard * 100).toFixed(1)}%<br>
                        Agreed on wrong answer: ${d.inter} questions<br>
                        Total wrong (either): ${d.union}
                    `, event);
                })
                .on('mouseleave', () => hideTooltip(tip));

            // Cell text for diagonal
            svg.append('text')
                .attr('x', i * cell + cell / 2)
                .attr('y', i * cell + cell / 2 + 4)
                .attr('text-anchor', 'middle')
                .attr('font-size', '9px')
                .attr('fill', '#fff')
                .text('1.0');
        }

        // Color legend
        const legendW = 120;
        const legendX = width / 2 - legendW / 2;
        const legendY = height + 80;

        const defs = svg.append('defs');
        const grad = defs.append('linearGradient').attr('id', 'agreement-grad');
        for (let i = 0; i <= 10; i++) {
            grad.append('stop').attr('offset', (i * 10) + '%').attr('stop-color', colorScale(i / 10));
        }
        svg.append('rect').attr('x', legendX).attr('y', legendY)
            .attr('width', legendW).attr('height', 10)
            .attr('fill', 'url(#agreement-grad)');
        svg.append('text').attr('x', legendX).attr('y', legendY + 22)
            .attr('font-size', '10px').attr('fill', '#555').text('0%');
        svg.append('text').attr('x', legendX + legendW).attr('y', legendY + 22)
            .attr('text-anchor', 'end').attr('font-size', '10px').attr('fill', '#555').text('100%');
        svg.append('text').attr('x', legendX + legendW / 2).attr('y', legendY - 4)
            .attr('text-anchor', 'middle').attr('font-size', '10px').attr('fill', '#555')
            .text('Jaccard similarity (wrong-answer sets)');
    }
}

// ─── Sortable Tables ─────────────────────────────────────────────────────────

function familyForText(text) {
    const t = text.toLowerCase();
    if (t.includes('gemma 3n') || t.includes('gemma3n') || t.includes('g3n')) return 'Gemma 3n';
    if (t.includes('gemma')) return 'Gemma';
    if (t.includes('llama')) return 'Llama';
    if (t.includes('qwen')) return 'Qwen';
    if (t.includes('ministral') || t.includes('mistral')) return 'Mistral';
    if (t.includes('deepseek'))                    return 'DeepSeek';
    if (t.includes('phi-4') || t.includes('phi4')) return 'Phi';
    if (t.includes('smollm'))                      return 'SmolLM';
    return null;
}

function applyRow(trEl, d, options) {
    const { rowShadeCol, rowScale, deltaCols = [], cellHeatCols = [], cellHeatColors = [] } = options;

    // Re-set cell HTML from original captured markup
    select(trEl).selectAll('td').data(d.cells).join('td').html(c => c);

    const tds = trEl.querySelectorAll('td');

    // Family color dot on model name (col 0)
    if (tds[0]) {
        const family = familyForText(tds[0].textContent.trim());
        if (family) {
            const dot = document.createElement('span');
            dot.className = 'cissp-family-dot';
            dot.style.background = FAMILY_COLORS[family] || '#888';
            tds[0].prepend(dot);
        }
    }

    // Continuous row shading (baseline table)
    if (rowScale != null && rowShadeCol != null) {
        const val = d.vals[rowShadeCol];
        if (typeof val === 'number') {
            const c = color(rowScale(val));
            c.opacity = 0.2;
            trEl.style.backgroundColor = c.formatRgb();
        } else {
            trEl.style.backgroundColor = '';
        }
    }

    // Diverging cell colors for delta columns (quant table)
    if (deltaCols.length) {
        const deltaScale = scaleDiverging(interpolateRdYlGn).domain([-5, 0, 5]);
        deltaCols.forEach(ci => {
            const val = d.vals[ci];
            if (typeof val === 'number' && tds[ci]) {
                const c = color(deltaScale(val));
                c.opacity = 0.35;
                tds[ci].style.backgroundColor = c.formatRgb();
            }
        });
    }

    // Per-column cell heat (domain table)
    cellHeatCols.forEach((ci, i) => {
        const val = d.vals[ci];
        if (typeof val === 'number' && tds[ci] && cellHeatColors[i]) {
            const c = color(cellHeatColors[i](val));
            c.opacity = 0.3;
            tds[ci].style.backgroundColor = c.formatRgb();
        }
    });
}

function makeSortable(tableId, options = {}) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const ths = [...table.querySelectorAll('thead th')];

    // Capture initial row data before any DOM changes
    const rows = [...table.querySelectorAll('tbody tr')].map((tr, i) => ({
        cells: [...tr.querySelectorAll('td')].map(td => td.innerHTML),
        vals:  [...tr.querySelectorAll('td')].map(td => {
            const n = parseFloat(td.textContent.replace(/[%+, ]/g, ''));
            return isNaN(n) ? td.textContent.trim() : n;
        }),
        idx: i,
    }));

    let sortCol = -1, sortAsc = true;

    ths.forEach((th, ci) => {
        th.classList.add('cissp-th-sortable');
        const ind = document.createElement('span');
        ind.className = 'cissp-sort-ind';
        ind.textContent = ' ⇅';
        th.appendChild(ind);
        th.addEventListener('click', () => {
            if (sortCol === ci) {
                if (sortAsc) { sortAsc = false; }
                else { sortCol = -1; }
            } else {
                sortCol = ci;
                sortAsc = true;
            }
            render();
        });
    });

    function render() {
        ths.forEach((th, ci) => {
            th.querySelector('.cissp-sort-ind').textContent =
                ci === sortCol ? (sortAsc ? ' ▲' : ' ▼') : ' ⇅';
        });

        const sorted = [...rows];
        if (sortCol >= 0) {
            sorted.sort((a, b) => {
                const va = a.vals[sortCol], vb = b.vals[sortCol];
                const cmp = (typeof va === 'number' && typeof vb === 'number')
                    ? va - vb : String(va).localeCompare(String(vb));
                return sortAsc ? cmp : -cmp;
            });
        }

        select(table).select('tbody').selectAll('tr')
            .data(sorted)
            .join('tr')
            .each(function(d) { applyRow(this, d, options); });
    }

    render();
}


function filterTableRows(selectedBases) {
    const selectedFamilies = new Set(
        [...selectedBases]
            .map(base => BASE_MODELS.find(m => m.base === base)?.family)
            .filter(Boolean)
    );
    const tableIds = ['cissp-table-baseline', 'cissp-table-quant'];
    for (const id of tableIds) {
        const table = document.getElementById(id);
        if (!table) continue;
        table.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.dataset.base) {
                tr.style.display = selectedBases.has(tr.dataset.base) ? '' : 'none';
            } else {
                const text = tr.querySelector('td')?.textContent || '';
                const family = familyForText(text);
                tr.style.display = (!family || selectedFamilies.has(family)) ? '' : 'none';
            }
        });
    }
}

function initModelTableFilter() {
    const table = document.getElementById('cissp-table-models');
    if (!table) return;
    const selectedBases = new Set(BASE_MODELS.map(m => m.base));
    table.querySelectorAll('tbody tr').forEach(tr => {
        const base = tr.dataset.base;
        if (!base) return;
        tr.addEventListener('click', () => {
            if (selectedBases.has(base)) selectedBases.delete(base);
            else selectedBases.add(base);
            tr.classList.toggle('cissp-struck', !selectedBases.has(base));
            document.dispatchEvent(new CustomEvent('modelfilter', {
                detail: { selectedBases: new Set(selectedBases) }
            }));
        });
    });
}

function initFilterBadge() {
    const badge = document.getElementById('cissp-filter-badge');
    const countEl = document.getElementById('cissp-filter-badge-count');
    const table = document.getElementById('cissp-table-models');
    if (!badge || !table) return;

    const total = BASE_MODELS.length;
    let tableVisible = true;
    let selectedCount = total;

    const obs = new IntersectionObserver(entries => {
        tableVisible = entries[0].isIntersecting;
        updateBadge();
    }, { threshold: 0 });
    obs.observe(table);

    document.addEventListener('modelfilter', e => {
        selectedCount = e.detail.selectedBases.size;
        updateBadge();
    });

    function updateBadge() {
        const isFiltered = selectedCount < total;
        const show = isFiltered && !tableVisible;
        badge.hidden = !show;
        if (show) countEl.textContent = `${selectedCount} / ${total} models`;
    }
}

function initBaselineQuant(tableId, models) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const LEVELS = [
        { value: 'fp16',   label: 'FP16'   },
        { value: 'q8_0',   label: 'Q8_0'   },
        { value: 'q4_K_M', label: 'Q4_K_M' },
    ];
    const TOTAL_Q = 1303;
    const ACTIVE_BG     = 'oklch(60% 0.17 155)';
    const ACTIVE_BORDER = 'oklch(55% 0.17 155)';

    // Index CISSP_MODELS by base key + quantization
    const byBase = {};
    models.forEach(m => {
        const base = baseOf(m.key);
        if (!byBase[base]) byBase[base] = {};
        byBase[base][m.quantization] = m;
    });

    function modelStats(m) {
        if (!m) return null;
        return {
            acc:          m.accuracy * 100,
            correct:      Math.round(m.accuracy * TOTAL_Q),
            tokensPerSec: m.avg_tokens_per_sec,
            wallTime:     m.wall_time_min,
            vramGb:       m.peak_vram_mb != null ? m.peak_vram_mb / 1024 : null,
        };
    }

    function fmtParams(m) {
        if (!m) return '';
        if (m.params_total !== m.params_eff) {
            return m.params_total + 'B/' + m.params_eff + 'B*';
        }
        return m.params_eff + 'B';
    }

    // Build row data from BASE_MODELS + CISSP_MODELS
    const rows = BASE_MODELS
        .map(bm => {
            const entry = byBase[bm.base];
            if (!entry?.fp16) return null;
            return {
                base:    bm.base,
                name:    entry.fp16.display_name,
                family:  bm.family,
                params:  fmtParams(entry.fp16),
                quant: {
                    fp16:   modelStats(entry.fp16),
                    q8_0:   modelStats(entry.q8_0),
                    q4_K_M: modelStats(entry.q4_K_M),
                },
            };
        })
        .filter(Boolean);

    const [accMin, accMax] = extent(rows, r => r.quant.fp16?.acc ?? 0);
    const rowScale = scaleSequential(interpolateRdYlGn).domain([accMin, accMax]);

    let currentLevel = 'fp16';
    let sortCol = -1, sortAsc = true;

    // Build pill selector and insert above the table-responsive wrapper
    const selWrap = document.createElement('div');
    selWrap.className = 'cissp-baseline-quant-sel';
    const lbl = document.createElement('span');
    lbl.className = 'cissp-quant-label';
    lbl.textContent = 'Precision:';
    const pillsRow = document.createElement('div');
    pillsRow.className = 'cissp-pills';
    const pillEls = {};
    LEVELS.forEach(lv => {
        const pill = document.createElement('button');
        const active = lv.value === 'fp16';
        pill.className = 'cissp-pill' + (active ? ' active' : '');
        pill.textContent = lv.label;
        pill.style.background   = active ? ACTIVE_BG     : 'transparent';
        pill.style.borderColor  = active ? ACTIVE_BORDER : '#bbb';
        pill.addEventListener('click', () => {
            currentLevel = lv.value;
            Object.entries(pillEls).forEach(([v, p]) => {
                const on = v === lv.value;
                p.classList.toggle('active', on);
                p.style.background  = on ? ACTIVE_BG     : 'transparent';
                p.style.borderColor = on ? ACTIVE_BORDER : '#bbb';
            });
            sortCol = -1; sortAsc = true;
            render();
        });
        pillEls[lv.value] = pill;
        pillsRow.appendChild(pill);
    });
    selWrap.append(lbl, pillsRow);
    table.closest('.table-responsive').before(selWrap);

    // Sortable column headers
    const COL_KEYS = ['name', 'params', 'acc', 'correct', 'vramGb', 'tokensPerSec', 'wallTime'];
    const ths = [...table.querySelectorAll('thead th')];
    ths.forEach((th, ci) => {
        th.classList.add('cissp-th-sortable');
        const ind = document.createElement('span');
        ind.className = 'cissp-sort-ind';
        ind.textContent = ' ⇅';
        th.appendChild(ind);
        th.addEventListener('click', () => {
            if (sortCol === ci) { if (sortAsc) sortAsc = false; else sortCol = -1; }
            else { sortCol = ci; sortAsc = true; }
            render();
        });
    });

    function getCellVal(row, ci) {
        const key = COL_KEYS[ci];
        if (key === 'name' || key === 'params') return row[key];
        const q = row.quant[currentLevel];
        return q?.[key] ?? null;
    }

    const tbody = table.querySelector('tbody');

    function render() {
        ths.forEach((th, ci) => {
            th.querySelector('.cissp-sort-ind').textContent =
                ci === sortCol ? (sortAsc ? ' ▲' : ' ▼') : ' ⇅';
        });

        const sorted = [...rows];
        if (sortCol >= 0) {
            sorted.sort((a, b) => {
                const va = getCellVal(a, sortCol), vb = getCellVal(b, sortCol);
                if (va == null && vb == null) return 0;
                if (va == null) return 1;
                if (vb == null) return -1;
                const cmp = (typeof va === 'number' && typeof vb === 'number')
                    ? va - vb : String(va).localeCompare(String(vb));
                return sortAsc ? cmp : -cmp;
            });
        } else {
            sorted.sort((a, b) => (b.quant[currentLevel]?.acc ?? 0) - (a.quant[currentLevel]?.acc ?? 0));
        }

        tbody.innerHTML = '';
        sorted.forEach(r => {
            const q = r.quant[currentLevel];
            const tr = document.createElement('tr');
            tr.dataset.base = r.base;

            // Model name with family dot
            const tdName = document.createElement('td');
            const dot = document.createElement('span');
            dot.className = 'cissp-family-dot';
            dot.style.background = FAMILY_COLORS[r.family] || '#888';
            tdName.append(dot, r.name);
            tr.appendChild(tdName);

            // Params (static)
            const tdParams = document.createElement('td');
            tdParams.textContent = r.params;
            tr.appendChild(tdParams);

            // Data columns from current quant level
            const tdAcc = document.createElement('td');
            tdAcc.textContent = q ? q.acc.toFixed(1) + '%' : '—';
            tr.appendChild(tdAcc);

            const tdCorrect = document.createElement('td');
            tdCorrect.textContent = q ? q.correct.toLocaleString() : '—';
            tr.appendChild(tdCorrect);

            const tdVram = document.createElement('td');
            tdVram.textContent = q?.vramGb != null ? q.vramGb.toFixed(1) : '—';
            tr.appendChild(tdVram);

            const tdTok = document.createElement('td');
            tdTok.textContent = q ? q.tokensPerSec.toFixed(1) : '—';
            tr.appendChild(tdTok);

            const tdWall = document.createElement('td');
            tdWall.textContent = q ? q.wallTime.toFixed(1) : '—';
            tr.appendChild(tdWall);

            // Row shading by accuracy
            const c = color(rowScale(q?.acc ?? accMin));
            c.opacity = 0.2;
            tr.style.backgroundColor = c.formatRgb();

            tbody.appendChild(tr);
        });
    }

    render();
}

function initQuantDeltaTable(tableId, models) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const byBase = {};
    models.forEach(m => {
        const base = baseOf(m.key);
        if (!byBase[base]) byBase[base] = {};
        byBase[base][m.quantization] = m;
    });

    const accScale  = scaleDiverging(interpolateRdYlGn).domain([-5, 0, 5]);
    const tokScale  = scaleDiverging(interpolateRdYlGn).domain([-40, 0, 40]);
    // Negative VRAM delta = less VRAM = good = green
    const vramScale = scaleDiverging(interpolateRdYlGn).domain([2000, 0, -12000]);

    function colorCell(td, scale, val) {
        const c = color(scale(val));
        c.opacity = 0.35;
        td.style.backgroundColor = c.formatRgb();
    }

    function fmtDelta(val, decimals, suffix) {
        const sign = val > 0 ? '+' : '';
        return `${sign}${val.toFixed(decimals)}${suffix}`;
    }

    const rowData = BASE_MODELS
        .map(bm => {
            const entry = byBase[bm.base];
            if (!entry?.fp16) return null;
            const fp16 = entry.fp16;
            const q8   = entry.q8_0;
            const q4   = entry.q4_K_M;
            return {
                base:    bm.base,
                name:    bm.name,
                family:  bm.family,
                fp16Acc: fp16.accuracy * 100,
                accQ8:   q8  ? (q8.accuracy  - fp16.accuracy)  * 100 : null,
                accQ4:   q4  ? (q4.accuracy  - fp16.accuracy)  * 100 : null,
                tokQ8:   q8  ? q8.avg_tokens_per_sec  - fp16.avg_tokens_per_sec  : null,
                tokQ4:   q4  ? q4.avg_tokens_per_sec  - fp16.avg_tokens_per_sec  : null,
                vramQ8:  (q8  && fp16.peak_vram_mb && q8.peak_vram_mb)  ? q8.peak_vram_mb  - fp16.peak_vram_mb  : null,
                vramQ4:  (q4  && fp16.peak_vram_mb && q4.peak_vram_mb)  ? q4.peak_vram_mb  - fp16.peak_vram_mb  : null,
            };
        })
        .filter(Boolean)
        .sort((a, b) => b.fp16Acc - a.fp16Acc);

    const COL_KEYS = ['name', 'accQ8', 'accQ4', 'tokQ8', 'tokQ4', 'vramQ8', 'vramQ4'];
    let sortCol = -1, sortAsc = true;

    const ths = [...table.querySelectorAll('thead th')];
    ths.forEach((th, ci) => {
        th.classList.add('cissp-th-sortable');
        const ind = document.createElement('span');
        ind.className = 'cissp-sort-ind';
        ind.textContent = ' ⇅';
        th.appendChild(ind);
        th.addEventListener('click', () => {
            if (sortCol === ci) sortAsc = !sortAsc;
            else { sortCol = ci; sortAsc = true; }
            render();
        });
    });

    const tbody = table.querySelector('tbody');

    function render() {
        ths.forEach((th, ci) => {
            th.querySelector('.cissp-sort-ind').textContent =
                ci === sortCol ? (sortAsc ? ' ▲' : ' ▼') : ' ⇅';
        });

        const sorted = [...rowData];
        if (sortCol >= 0) {
            const key = COL_KEYS[sortCol];
            sorted.sort((a, b) => {
                const va = a[key], vb = b[key];
                if (va == null && vb == null) return 0;
                if (va == null) return 1;
                if (vb == null) return -1;
                const cmp = typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb));
                return sortAsc ? cmp : -cmp;
            });
        }

        tbody.innerHTML = '';
        sorted.forEach(d => {
            const tr = document.createElement('tr');
            tr.dataset.base = d.base;

            const tdName = document.createElement('td');
            const dot = document.createElement('span');
            dot.className = 'cissp-family-dot';
            dot.style.background = FAMILY_COLORS[d.family] || '#888';
            tdName.append(dot, d.name);
            tr.appendChild(tdName);

            [
                { val: d.accQ8,  fmt: v => fmtDelta(v, 1, 'pp'), scale: accScale  },
                { val: d.accQ4,  fmt: v => fmtDelta(v, 1, 'pp'), scale: accScale  },
                { val: d.tokQ8,  fmt: v => fmtDelta(v, 1, ''),   scale: tokScale  },
                { val: d.tokQ4,  fmt: v => fmtDelta(v, 1, ''),   scale: tokScale  },
                { val: d.vramQ8, fmt: v => fmtDelta(v / 1024, 1, ' GB'), scale: vramScale },
                { val: d.vramQ4, fmt: v => fmtDelta(v / 1024, 1, ' GB'), scale: vramScale },
            ].forEach(({ val, fmt, scale }) => {
                const td = document.createElement('td');
                td.textContent = val != null ? fmt(val) : '—';
                if (val != null) colorCell(td, scale, val);
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
    }

    render();
}

function initTables(models) {
    makeSortable('cissp-table-models',   {});
    initBaselineQuant('cissp-table-baseline', models);
    initQuantDeltaTable('cissp-table-quant', models);

    document.addEventListener('modelfilter', (e) => {
        filterTableRows(e.detail.selectedBases);
    });
}

// ─── Main init ───────────────────────────────────────────────────────────────

function init() {
    if (typeof window.CISSP_MODELS === 'undefined' || typeof window.CISSP_PER_QUESTION === 'undefined') {
        console.warn('CISSP chart data not available. Skipping chart initialization.');
        return;
    }

    const models = window.CISSP_MODELS;
    const perQuestion = window.CISSP_PER_QUESTION;

    // Enrich models with computed fields
    const fp16Map = {};
    for (const m of models) {
        if (m.quantization === 'fp16') fp16Map[m.key.replace(/-fp16$/, '')] = m.accuracy;
    }

    for (const m of models) {
        // Remap 'Other' family to specific families
        if (m.family === 'Other') {
            if (m.key.startsWith('deepseek'))  m.family = 'DeepSeek';
            else if (m.key.startsWith('phi4')) m.family = 'Phi';
            else if (m.key.startsWith('smollm')) m.family = 'SmolLM';
        }

        m.wall_time_min = m.total_time_sec / 60;
        const base = baseOf(m.key);
        m.delta_fp16 = fp16Map[base] !== undefined
            ? (m.accuracy - fp16Map[base]) * 100
            : null;
    }

    // Init charts
    new ScatterChart('cissp-scatter', models);
    new HeatmapChart('cissp-heatmap', models);
    new QuantChart('cissp-quant', models);
    initLadderChart('cissp-quant-ladder', models);
    new DifficultyChart('cissp-difficulty', perQuestion, models);
    new AgreementMatrix('cissp-agreement', models);
    initTables(models);
    initModelTableFilter();
    initFilterBadge();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
