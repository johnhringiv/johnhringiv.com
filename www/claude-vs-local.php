<?php
require_once "includes/classes.php";
require_once "includes/radar.php";

$page_info = PageInfo::fromDB('claude-vs-local');

// Companion experiment repository — single source of truth for all artifact links.
$experiment_repo = "https://github.com/johnhringiv/claude-vs-local";

include_once "includes/top.php";
?>
<div class="container blog-post themed-tables pb-2">
    <article>
        <?php $page_info->renderFullHeader(); ?>

        <?php echo $page_info->html_description ?>

        <section>
            <h2 id="who-is-this-for" class="fw-bolder mb-4 mt-5">
                <a href="#who-is-this-for" class="text-reset text-decoration-none">Who Is This For?</a>
            </h2>
            <p>
                Let's be honest about the audience. <strong>This is for tinkerers and people with strict privacy requirements.</strong> It is not a cost play.
            </p>
            <p>
                An RTX 4090 costs ~$1,600&ndash;2,000. A Claude Max subscription starts at $100/month. That's 16&ndash;20 months of Claude for the price of the GPU alone, and that's before the rest of the rig, the electricity, or your time configuring llama.cpp settings.
                And if you already own the hardware, the experience gap between Claude Code and local models is significant enough that the subscription is likely worth it. If you can afford a $2,000 GPU, you can afford $100/month.
            </p>
            <p>
                Running locally costs you two kinds of parallelism. The first is the <strong>human kind</strong>: with a subscription you can keep one Claude session writing code in this repo, another helping debug an unrelated bug, and a third drafting an email. Three simultaneous conversations, each with its own state, none of them fighting over your local hardware. With one 24 GB GPU and one loaded model, you have one conversation at a time. Period.
            </p>
            <p>
                The second is the <strong>agent kind</strong>, and it's the one that matters more. Claude Code can spawn subagents that explore in parallel: three reads of three different directories, each running concurrently in its own fresh context and returning a summary. The obvious benefit is wall-clock speed. The bigger one is <strong>context efficiency</strong>, because the main conversation never sees the raw subagent transcripts, only the distilled summaries. A local single-conversation agent has to do all of that exploration <em>in the same thread it'll later use for planning and writing code</em>, so every grep, every file read, and every dead end becomes permanent context bloat.
            </p>
            <p>The real reasons to go local:</p>
            <ul>
                <li><strong>Privacy:</strong> your code never leaves your machine. No API calls, no telemetry, no third-party data processing.</li>
                <li><strong>Tinkering:</strong> you want to understand how local inference works, optimize VRAM budgets, and experiment with open models.</li>
                <li><strong>Availability:</strong> no rate limits, no outages, no dependency on someone else's infrastructure.</li>
                <li><strong>Offline use:</strong> works without internet, on a plane, in an air-gapped environment.</li>
            </ul>
        </section>

        <section>
            <h2 id="the-task" class="fw-bolder mb-4 mt-5">
                <a href="#the-task" class="text-reset text-decoration-none">The Task</a>
            </h2>
            <p>
                The test is a real coding task on a real codebase: <strong>design a complete Playwright E2E test suite for <a href="https://github.com/johnhringiv/gotflashes">gotflashes</a> (pinned at commit <a href="https://github.com/johnhringiv/gotflashes/commit/22d2155fb49a8d6e29bdbc1c979ecdf93f2fdd48"><code>22d2155</code></a>), an open-source Laravel 12 + Livewire v3 sailing-activity tracker.</strong> The app has 186 PHPUnit tests covering its data layer but zero browser coverage. The gap is everything that only matters in a browser: flatpickr multi-date pickers, TomSelect dropdowns, Livewire <code>morph.updated</code> race conditions, toast notifications, DaisyUI modals, and HTML quality across public, authenticated, and admin pages. A real Playwright suite has to complement PHPUnit, not duplicate it.
            </p>
            <p>
                Each agent gets <a href="<?= $experiment_repo ?>/blob/main/prompts/planning-prompt.md">a single prompt</a> asking for a complete seven-section plan (a test inventory through to HTML validation) covering seven feature areas (auth through admin), five named JavaScript modules, and the cross-feature flows. What separates the plans is three load-bearing constraints the prompt foregrounds: dynamic dates (the app derives allowed ranges from <code>now()</code>), Laravel-seeded fixtures with <code>storageState</code> auth, and observable-state waits (not <code>waitForTimeout</code>) for Livewire DOM transitions. A good plan folds those in as premises; a weak one treats them as checkboxes.
            </p>
            <p>
                Five model arms run that prompt. Four locally-hosted configurations through <strong>OpenCode</strong> (the open-source coding agent), and Claude Opus 4.7 through <strong>Claude Code</strong> with the 1M-token context window enabled and reasoning effort set to extra-high, against my existing Claude Max subscription.
            </p>
            <p>
                The task is deliberately multi-skill. Codebase comprehension across 40-odd files. Specification writing with a prescribed structure. Instruction-following (exactly seven sections, no extras, no matter how much the model wants to add a section on accessibility or performance). And constraint awareness for the three load-bearing concerns above. A plan that's right on structure but wrong on selectors is half a plan; a plan that names the right selectors but adds three unrequested subsections is also half a plan. The grading reflects that.
            </p>

            <h3 id="why-two-harnesses" class="fw-bolder mb-3 mt-4">
                <a href="#why-two-harnesses" class="text-reset text-decoration-none">Why two harnesses?</a>
            </h3>
            <p>
                A purist would run all five arms through a single coding-agent wrapper (same tool-call protocol, same UI, same conventions) to keep everything controlled but the model. I didn't. Claude ran in <strong>Claude Code</strong> (Anthropic's official CLI, against my Claude Max subscription), and the local models ran in <strong>OpenCode</strong> (open source). Three reasons:
            </p>
            <ol>
                <li><strong>Cost.</strong> Running Claude inside OpenCode means paying the Anthropic API per token, on top of the Claude subscription I already pay for. The subscription's value proposition is &ldquo;all you can eat&rdquo;; switching to metered API billing for the same model defeats it. Most people who'd reach for Claude on a real coding task are paying for the subscription, not the API.</li>
                <li><strong>The reverse doesn't work well.</strong> Using third-party models <em>inside</em> Claude Code exists as a workaround, but it isn't well supported: you can't set context size, sampler parameters aren't first-class, and the OpenAI-compatible endpoint conventions are second-class citizens. Losing the context-size knob is the worst of those: <strong>the agent needs to know its real window to manage compaction at the right threshold</strong>, and compaction is a capability event, not just a workflow event (see <a href="#fitting-local-arms">Fitting the Local Arms</a> below). If Claude Code assumes a 200k window while the local model is actually loaded at 163k, the compaction trigger fires too late (hitting hard context-limit errors mid-run) or never at all (truncation begins silently and the agent stops noticing what it can no longer see). On 24 GB of VRAM context is also the binding hardware constraint, so you need that knob for two reasons, not one.</li>
                <li><strong>It fits the proprietary-vs-open-source story.</strong> Claude Code is closed; OpenCode is open. Each agent is being used the way someone who'd committed to that side of the divide would actually use it. &ldquo;Claude through OpenCode&rdquo; or &ldquo;Qwen through Claude Code&rdquo; are exotic configurations; &ldquo;Claude Code with Claude&rdquo; and &ldquo;OpenCode with local models&rdquo; are what people run.</li>
            </ol>
            <p>
                The cost is that some differences between the runs aren't purely about the underlying model. Claude Code spawns subagents; OpenCode (currently) doesn't. Claude Code has a dedicated Plan Mode artifact; OpenCode's plan mode lives in a chat message. I call those out where they affect the results; they're named confounds, not hidden ones.
            </p>

            <h3 id="why-we-judge-on-plans" class="fw-bolder mb-3 mt-4">
                <a href="#why-we-judge-on-plans" class="text-reset text-decoration-none">Why I judge on plans</a>
            </h3>
            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: always start in plan mode.</strong> A plan is fast to read, and reviewing it does three jobs at once: it confirms the model actually understood the task, it exposes the model's biases before they become code (the assumptions that steer it somewhere you didn't want to go), and it makes the code review that follows fast. Once you've signed off on the plan, the diff is mostly what you expected, so you're skimming for deviations instead of reverse-engineering intent. Catch the wrong direction in a two-minute read, not a debug cycle per file.
                </p>
            </div>
            <p>
                Evaluating that plan is the core of this experiment. The plan is the artifact worth grading on its own.
            </p>
            <p>
                A plan is the model's first-pass thinking made visible: which files it read, what it's committing to, and, most usefully, where its biases are pointing it. A model that's decided the app uses <code>/flashes</code> routes, or that <code>waitForTimeout</code> is a fine way to wait on Livewire, tells you so in the plan, before that assumption has metastasized into forty spec files. Those biases don't disappear at implementation time; they become bugs and rework. Plan quality is a leading indicator of what the implementation looks like: bad plan, noisy implementation; good plan, clean one.
            </p>
            <p>
                The plan is also independently easier to measure. Plans take minutes where implementations take hours. They fit on a few pages and read side-by-side; implementations are tens of thousands of lines you'd never compare manually. If you want to answer &ldquo;is this model good enough for real coding work&rdquo; without committing a day to each candidate, the plan answers most of the question.
            </p>
            <p>
                The implementation experiment comes later in the post, and it produces one more useful data point that emerges naturally from the design. Once I name a winning plan, the obvious move is to hand it to a local model and see what falls out. That's the hybrid pattern the vendors themselves recommend (Anthropic suggests Opus to plan, Sonnet to execute), and it answers whether the cloud is really needed for the whole pipeline or only the design phase.
            </p>
            <p>
                The rest goes in three parts: first the local-inference setup and the dial-in surprises (the ones that cost me a weekend), then the five-arm plan comparison, then the Claude-vs-local implementation head-to-head. If you're here for the verdict and not the VRAM math, skip ahead to <a href="#how-each-plan-performed">How Each Plan Performed</a>.
            </p>
        </section>

        <section>
            <h2 id="hardware-and-software" class="fw-bolder mb-4 mt-5">
                <a href="#hardware-and-software" class="text-reset text-decoration-none">Hardware &amp; Software: A Dev Machine, Not a Dedicated Inference Box</a>
            </h2>
            <p>
                This is important context for everything that follows: <strong>these models are running on my personal workstation in its standard/everyday configuration.</strong>
                Multiple monitors are plugged into the RTX 4090.
                Browsers, Slack, IDE, Discord: they're all running while LM Studio loads a 17 GB model.
                The Desktop Window Manager (DWM) is compositing windows on the same GPU that's doing inference.
                This isn't a sterile lab setup with displays routed to an iGPU; it's a daily-driver workstation that occasionally hosts an LLM.
            </p>
            <p>
                That matters because <strong>DWM and other GPU clients consume 1.5&ndash;4 GB of VRAM</strong>, and that number drifts as you work. Every context-size and KV-quant choice in this post is downstream of that constraint. I considered moving displays to the AMD iGPU to reclaim that VRAM. I decided not to: it'd mean tearing down a dev environment to validate a tradeoff most readers won't make either. The numbers in this post are what you get when local LLM inference shares the GPU with your day-to-day work. If that's your actual scenario, the findings transfer cleanly. If you're running headless or on a dedicated inference rig, treat these numbers as conservative.
            </p>
            <table>
                <thead>
                    <tr><th>Component</th><th>Detail</th></tr>
                </thead>
                <tbody>
                    <tr><td>GPU</td><td>NVIDIA RTX 4090 (24,564 MiB VRAM)</td></tr>
                    <tr><td>Display setup</td><td>Multiple monitors plugged into the 4090 (not iGPU-routed)</td></tr>
                    <tr><td>CPU</td><td>AMD Ryzen 9 9950X3D</td></tr>
                    <tr><td>Inference runtime</td><td>LM Studio 0.4.12 (Build 1), llama.cpp CUDA 12 runtime v2.14.0</td></tr>
                    <tr><td>Coding agent</td><td>OpenCode (models registered with the context limits from <a href="#the-models">The Models</a> table below)</td></tr>
                    <tr><td>OS</td><td>Windows 11 (LM Studio native; both coding agents ran under WSL2)</td></tr>
                    <tr><td>Observed DWM + apps baseline</td><td>~1.5 GB at clean boot, drifting to ~3&ndash;4 GB during normal use</td></tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2 id="the-models" class="fw-bolder mb-4 mt-5">
                <a href="#the-models" class="text-reset text-decoration-none">The Models</a>
            </h2>

            <p>
                The question this experiment asks is narrower than &ldquo;what's the best open-weight coding model?&rdquo; It's <strong>what's the best you can do with open weights on a flagship consumer GPU?</strong> The unconstrained leaderboards (Aider, SWE-bench, LiveCodeBench) are topped by large mixture-of-experts models (DeepSeek, Kimi, and the like) that need far more than 24 GB and never get to run here. Draw the box at a single 24 GB card and the field thins fast; the champion of <em>that</em> bracket is Qwen, the strongest open-weight family that fits, usually named alongside Mistral's Devstral for local work. So reaching for a Qwen variant isn't what's being tested; it's the premise. What's being tested is whether the best answer to that constrained question lands close enough to the closed-weight leader to be worth running locally at all.
            </p>
            <p class="text-muted">
                <small>Scope note: &ldquo;flagship consumer GPU&rdquo; means GPU VRAM: the 4090's 24 GB. I'm deliberately not counting the other local route, where you offload a much larger model to CPU or run it on a Mac's unified memory at a handful of tokens per second. That works, and plenty of people do it, but an interactive coding agent generating at 3 tok/s isn't a workflow I'd use. Throughput is part of the question here, not just whether the weights fit.</small>
            </p>
            <p>
                The experiment tests four local configurations because model size forces a three-way tradeoff: <strong>capability</strong> (larger models reason better), <strong>context window</strong> (larger models leave less room for context), and <strong>speed</strong> (larger models generate fewer tokens per second). A 9B model fits 262k context at full precision; a 27B model at the same precision fits under 100k. The real question is which point on the capability/context/speed curve produces the best plan on 24 GB of VRAM.
            </p>
            <p>
                Five arms total: four local Qwen configurations plus Claude Opus 4.7 as the cloud baseline. Local models from Unsloth's GGUF releases. Qwen 3.6 ships in two sizes only (27B dense, 35B-A3B MoE), so the small/long-context slot stays on Qwen 3.5-9B.
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Arm</th>
                            <th>Source</th>
                            <th>Quant</th>
                            <th>File size</th>
                            <th>Active params/tok</th>
                            <th>Context (loaded)</th>
                            <th>Gen tok/s</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Qwen 3.5-9B</td>
                            <td><code>unsloth/Qwen3.5-9B-GGUF</code></td>
                            <td>UD-Q8_K_XL</td>
                            <td>12.7 GB</td>
                            <td>9B (dense)</td>
                            <td>262,144</td>
                            <td>52-57</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-27B (Q4 arm)</td>
                            <td><code>unsloth/Qwen3.6-27B-GGUF</code></td>
                            <td>UD-Q4_K_XL</td>
                            <td>17.6 GB</td>
                            <td>27B (dense)</td>
                            <td>98,304</td>
                            <td>43 &rarr; 22 (throttled)</td>
                        </tr>
                        <tr>
                            <td><strong>Qwen 3.6-27B (Q3 follow-up)</strong></td>
                            <td><code>unsloth/Qwen3.6-27B-GGUF</code></td>
                            <td><strong>UD-Q3_K_XL</strong></td>
                            <td>16.3 GB</td>
                            <td>27B (dense)</td>
                            <td><strong>163,840</strong></td>
                            <td><strong>50.8</strong></td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-35B-A3B</td>
                            <td><code>unsloth/Qwen3.6-35B-A3B-GGUF</code></td>
                            <td>UD-Q4_K_XL</td>
                            <td>23 GB</td>
                            <td>3B (MoE, 8+1 of 256 experts)</td>
                            <td>131,072</td>
                            <td>45-78</td>
                        </tr>
                        <tr>
                            <td>Claude Opus 4.7 (cloud baseline)</td>
                            <td>Anthropic API</td>
                            <td>&mdash;</td>
                            <td>&mdash;</td>
                            <td>&mdash;</td>
                            <td>1,000,000 (effective: higher via subagents)</td>
                            <td>~60-100</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3 id="kv-cache-why-q8" class="fw-bolder mb-3 mt-4">
                <a href="#kv-cache-why-q8" class="text-reset text-decoration-none">KV cache: why Q8, not FP16 and not Q5_1</a>
            </h3>
            <p class="lead shadow-sm py-2 ps-2 rounded-3">
                <b class="fs-5">KV cache:</b> the keys and values each attention layer computes per token, held in VRAM so they aren't recomputed on every following token. It grows linearly with context length (a longer conversation costs more memory), which is why, on a fixed 24 GB, context size and VRAM are the same knob.
            </p>
            <div class="test-info small">
                <b>Reading the precision:</b> <kbd>FP16</kbd> stores each cached value in a full 16 bits; <kbd>Q8</kbd> and <kbd>Q5_1</kbd> pack it into 8 or ~5. Same bit-width idea as the model-weight quants (<kbd>UD-Q4_K_XL</kbd> and friends) in the arms table above, just applied to the cache, not the weights.
            </div>
            <p>
                All arms use <strong>Q8/Q8</strong> KV cache. FP16 (llama.cpp's default) doesn't fit: the 27B at 163k context needs ~10.8 GB just for KV, blowing past 24 GB once you add the model itself. Q8 halves that cost for a quality loss small enough that broad consensus treats Q8 KV as effectively lossless, and its unpack is nearly free: an 8-bit value is a whole byte, so dequant is one scale-multiply on a contiguous read, and it stays on the fast attention kernel. It's the free downward step.
            </p>
            <p>
                Going lower backfires. Q5_1 saves another ~1.3 GB of VRAM on paper, but 5-bit values aren't byte-aligned: each one has to be bit-unpacked, off the fast kernel path, on every attention pass, every layer, every step. On a 24 GB GPU with limited compute headroom, that overhead came directly out of throughput: gen dropped from 43 &rarr; 18 tok/s. Worse, at high context fill the model started emitting completions with <strong>zero content tokens</strong>: pure thinking-mode reasoning that hit <code>max_tokens</code> before producing a final answer. Not gibberish; not a wrong answer; an empty response. I went back to Q8 across the board.
            </p>

        </section>

        <section>
            <h2 id="inference-settings" class="fw-bolder mb-4 mt-5">
                <a href="#inference-settings" class="text-reset text-decoration-none">Inference Settings</a>
            </h2>

            <h3 id="lm-studio-load-tab" class="fw-bolder mb-3 mt-4">
                <a href="#lm-studio-load-tab" class="text-reset text-decoration-none">LM Studio Load tab (applies to all arms)</a>
            </h3>
            <div class="row" style="align-items: start;">
                <div class="col-md-6">
                    <ul>
                        <li><b>Max Concurrent Predictions</b> &rarr; <kbd>1</kbd></li>
                        <li><b>Evaluation Batch Size</b> &rarr; <kbd>512</kbd></li>
                        <li><b>Flash Attention</b> &rarr; <kbd>ON</kbd></li>
                        <li><b>Unified KV Cache</b> &rarr; <kbd>ON</kbd></li>
                        <li><b>K Cache / V Cache</b> &rarr; <kbd>Q8_0 / Q8_0</kbd></li>
                        <li><b>Try mmap()</b> &rarr; <kbd>ON</kbd></li>
                        <li><b>Keep Model in Memory</b> &rarr; <kbd>ON</kbd></li>
                        <li><b>Offload KV Cache to GPU Memory</b> &rarr; <kbd>ON</kbd></li>
                        <li><b>Limit model offload to dedicated GPU memory</b> &rarr; <kbd><strong>OFF</strong></kbd></li>
                    </ul>
                </div>
                <figure class="col-md-6 image-modal-content">
                    <?php echo responsiveImage(
                        '/img/blog/claude_vs_local/qwen_3.6_load.png',
                        'column',
                        'LM Studio load tab settings for the Qwen 3.6 27B model',
                        'img-fluid rounded shadow-sm',
                        ['(min-width: 768px) 25vw', '50vw'],
                        [],
                        'max-height: 320px; width: auto;'
                    ); ?>
                </figure>
            </div>
            <p>
                One prediction at a time is all the VRAM and compute allow. A few of the rest are load-bearing on a 24 GB box: <strong>Offload KV Cache to GPU</strong> keeps the cache on the card, where every attention head re-reads it each token, so letting it spill to system RAM collapses generation. <strong>Limit model offload to dedicated GPU memory</strong> must be OFF, a laptop/shared-memory setting that silently caps GPU layers even when VRAM is free. And Flash Attention plus the Q8 K/V cache are what make the quantized cache from the <a href="#kv-cache-why-q8">section above</a> actually fit. The rest you can leave at LM Studio's defaults.
            </p>

            <h3 id="qwen-configuration" class="fw-bolder mb-3 mt-4">
                <a href="#qwen-configuration" class="text-reset text-decoration-none">Qwen Configuration</a>
            </h3>
            <div class="row" style="align-items: start;">
                <div class="col-md-6">
                    <p>
                        Per <a href="https://unsloth.ai/docs/models/qwen3.6">Unsloth's Qwen 3.6 documentation</a> for &ldquo;Precise Coding Tasks&rdquo; in thinking mode:
                    </p>
                    <ul>
                        <li><b>Temperature</b> &rarr; <kbd>0.6</kbd></li>
                        <li><b>Top-K</b> &rarr; <kbd>20</kbd></li>
                        <li><b>Top-P</b> &rarr; <kbd>0.95</kbd></li>
                        <li><b>Min-P</b> &rarr; <kbd>0.0</kbd></li>
                        <li><b>Repeat penalty</b> &rarr; <kbd>1.0</kbd></li>
                        <li><b>Presence penalty</b> &rarr; <kbd><strong>0.0</strong></kbd></li>
                        <li><b>Thinking</b> &rarr; <kbd><strong>ON</strong></kbd></li>
                    </ul>
                    <p>
                        (3.6's <em>non-thinking</em> coding profile uses presence_penalty=1.5, but Unsloth explicitly recommends thinking mode for code-focused work on 3.6. I followed that across all Qwen 3.6 arms. The Qwen 3.5-9B used the same precise-coding profile with thinking disabled by default.)
                    </p>
                </div>
                <figure class="col-md-6 image-modal-content">
                    <?php echo responsiveImage(
                        '/img/blog/claude_vs_local/qwen_3.6_inference.png',
                        'column',
                        'LM Studio inference settings for the Qwen 3.6 models',
                        'img-fluid rounded shadow-sm',
                        ['(min-width: 768px) 25vw', '50vw'],
                        [],
                        'max-height: 380px; width: auto;'
                    ); ?>
                </figure>
            </div>
        </section>

        <section>
            <h2 id="fitting-local-arms" class="fw-bolder mb-4 mt-5">
                <a href="#fitting-local-arms" class="text-reset text-decoration-none">Fitting the Local Arms on 24 GB</a>
            </h2>
            <p>
                What matters for local inference speed isn't whether the model <em>fits</em> at load time, but how much VRAM is free <em>during generation</em>, for the dynamic compute buffers attention needs. The two numbers diverge sharply: the 27B at 110k showed 3.9 GB free right after loading but only ~265 MB free under nvidia-smi mid-generation. And the gap is unforgiving: once it forces KV cache to spill to CPU, generation craters from 33 to 5&ndash;10 tok/s, because KV is re-read by every attention head every token. The column that matters in the budget below isn't &ldquo;free at load,&rdquo; it's <strong>live free during gen</strong>.
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Arm</th>
                            <th>Context</th>
                            <th>Free at load</th>
                            <th>Live free during gen</th>
                            <th>Stability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Qwen 3.5-9B</td>
                            <td>262k Q8</td>
                            <td>~8,200 MiB</td>
                            <td>~4,500 MiB</td>
                            <td>Bulletproof</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-27B (Q4_K_XL)</td>
                            <td>98k Q8</td>
                            <td>3,270 MiB</td>
                            <td>220 MiB</td>
                            <td>VRAM-sensitive</td>
                        </tr>
                        <tr>
                            <td><strong>Qwen 3.6-27B (Q3_K_XL)</strong></td>
                            <td><strong>163k</strong> Q8</td>
                            <td>4,088 MiB</td>
                            <td><strong>676 MiB</strong></td>
                            <td><strong>Healthy</strong></td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-35B-A3B (5 CPU layers)</td>
                            <td>131k Q8</td>
                            <td>3,321 MiB</td>
                            <td>95-179 MiB across fill</td>
                            <td>OK</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted">
                <small>Full per-arm MiB breakdown (model and KV-cache footprints) is in <a href="<?= $experiment_repo ?>/blob/main/docs/dialed-in-settings.md"><code>docs/dialed-in-settings.md</code></a>.</small>
            </p>
            <p>
                The four arms are really three models, and three different answers to one question: what do you give up to fit a capable coder in 24 GB?
            </p>

            <figure class="radar-row">
                <?php
                // Stat-wheels — qualitative silhouettes of each arm's tradeoff. Scores are
                // normalized 0–10 from the tables above: Context (log-scaled, 262k = 10),
                // Speed (gen tok/s), Precision (quant fidelity: Q8=10, Q4=7, Q3=5),
                // Headroom (live-free VRAM + stability rating), Plan (rubric mean ×1).
                echo render_radar('27B · Q4', ['Context' => 4.0, 'Speed' => 6.5, 'Precision' => 7.0, 'Headroom' => 3.0, 'Plan' => 4.8]);
                echo render_radar('27B · Q3', ['Context' => 7.1, 'Speed' => 8.0, 'Precision' => 5.0, 'Headroom' => 7.0, 'Plan' => 6.3]);
                echo render_radar('35B-A3B', ['Context' => 5.8, 'Speed' => 5.5, 'Precision' => 7.0, 'Headroom' => 5.0, 'Plan' => 6.5]);
                echo render_radar('9B', ['Context' => 10.0, 'Speed' => 10.0, 'Precision' => 10.0, 'Headroom' => 10.0, 'Plan' => 4.7]);
                ?>
                <figcaption class="text-muted">
                    <small>Each axis 0&ndash;10, normalized from the tables above (context is log-scaled; precision is quant fidelity, not output quality). Shapes are for comparing <em>tradeoff silhouettes</em>, not exact values; the receipts are in the tables. Note the 9B fills every hardware axis yet collapses on <em>Plan</em>: all the headroom in the world, weakest output.</small>
                </figcaption>
            </figure>

            <h3 id="trade-precision" class="fw-bolder mb-3 mt-4">
                <a href="#trade-precision" class="text-reset text-decoration-none">27B: trade precision for context</a>
            </h3>
            <p>
                The dense 27B at UD-Q4_K_XL is the obvious &ldquo;quality&rdquo; pick, Unsloth's highest-quality 4-bit quant, imatrix-calibrated to protect the attention layers that carry meaning. Unsloth's docs put its recommended minimum context at 110k, which is also where it dials in on clean VRAM. But on a daily-driver desktop I could only fit <strong>98k</strong> before live-free VRAM dropped into the thrashing zone, already below that recommended floor, and at 98k the planning task wanted more state than that, so the run hit OpenCode's compaction trigger twice.
            </p>

            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: compaction degrades capability, not just speed.</strong> When OpenCode hits its compaction trigger (around 70% of the context window), it summarizes prior context to free room. The summary preserves <em>what the agent remembers it knew</em>, not the raw evidence. If the dropped content was load-bearing (route definitions, real selectors, schema columns), the agent confidently invents replacements for what it can no longer see. Compaction reads as a workflow inconvenience but it's actually a quality event: a model that hits compaction at the wrong moment loses fidelity in ways no benchmark tracks.
                </p>
            </div>

            <p>
                Rather than cut context further (which just triggers compaction earlier), I dropped one bit of precision instead. UD-Q3_K_XL is only ~0.3 GB smaller on disk but ~3 GB smaller on the GPU (<strong>13,110</strong> vs 16,104 MiB; Q3 packs denser than the file-size delta suggests), and that headroom bought <strong>163k context</strong> (more than the Q4 dial-in target) and lifted clean-probe generation from 43 to 51 tok/s. The lesson cuts against instinct: on VRAM-bound hardware, don't reach for the highest-precision quant that <em>fits</em>; reach for the highest-precision quant that fits <em>with room for a healthy compute buffer</em>.
            </p>

            <h3 id="trade-density" class="fw-bolder mb-3 mt-4">
                <a href="#trade-density" class="text-reset text-decoration-none">35B-A3B: trade density for cheap offload</a>
            </h3>
            <p>
                The 35B is a mixture-of-experts model: 35B total params, but only ~3B active per token (8 routed experts + 1 shared). That rewrites the offload math. The 23 GB file won't fit whole, but because each token only touches the active experts, pushing layers to CPU is cheap (roughly 1.5 tok/s per offloaded layer versus 2&ndash;3 for a dense model), and the model keeps running on a sliver of live-free VRAM (95&ndash;179 MiB) where the dense 27B would tank. MoE is the one architecture here that degrades gracefully when it spills. (Don't force the expert count down to save memory, though: Qwen 3.6 is trained for its 9 active experts, and overriding that just costs quality.)
            </p>

            <h3 id="the-9b-anchor" class="fw-bolder mb-3 mt-4">
                <a href="#the-9b-anchor" class="text-reset text-decoration-none">9B: trade scale for headroom</a>
            </h3>
            <p>
                The 9B is the counterpoint on the other axis: far smaller in total than the 27B, but every one of its params is active each token, roughly three times the active compute of the 35B MoE. Small total plus full precision is why it fits 262k context with ~4.5 GB to spare and never feels VRAM pressure. It's the control arm: when something with no fitting constraints still does something interesting, you know it's the model, not the hardware.
            </p>

            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: long sessions get slower, then dumber.</strong> Every token an agent generates attends to the whole conversation so far, so generation slows as context fills, and every harness eventually compacts to stay under its limit. The same factors are in play on the cloud; the Claude API's vast resources just mask them, so you feel it as latency and cost rather than a visible collapse. On a fixed 24 GB you feel it sharply: the growing KV cache craters throughput and forces compaction at a far lower ceiling (163k here vs the 1M Claude ran with). Either way, scope each session to one task and start fresh rather than letting one balloon.
                </p>
            </div>
        </section>

        <section>
            <h2 id="experiment-results" class="fw-bolder mb-4 mt-5">
                <a href="#experiment-results" class="text-reset text-decoration-none">Experiment Results: Five-Arm Plan Comparison</a>
            </h2>
            <p>
                I ran the same planning prompt across all five arms against the gotflashes codebase: ~50k tokens of code, templates, and migrations to read before writing a line of plan.
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Arm</th>
                            <th>Context window</th>
                            <th>Peak used</th>
                            <th>Tokens generated</th>
                            <th>Wall-clock</th>
                            <th>Compactions</th>
                            <th>Operator pauses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Claude Opus 4.7 (xhigh, via Claude Code)</td>
                            <td>1M</td>
                            <td>~103k</td>
                            <td>~92k</td>
                            <td>8:14</td>
                            <td>0 (3 subagents)</td>
                            <td>0</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.5-9B (UD-Q8_K_XL)</td>
                            <td>262k</td>
                            <td>~75k</td>
                            <td>~15k</td>
                            <td>4:29</td>
                            <td>0</td>
                            <td>0</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-35B-A3B (UD-Q4_K_XL)</td>
                            <td>131k</td>
                            <td>~105k</td>
                            <td>~13k</td>
                            <td>~6:00</td>
                            <td>1</td>
                            <td>2</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-27B (UD-Q4_K_XL)</td>
                            <td>98k</td>
                            <td>~70k</td>
                            <td>~25k</td>
                            <td>22:36</td>
                            <td>2</td>
                            <td>4</td>
                        </tr>
                        <tr>
                            <td>Qwen 3.6-27B (UD-Q3_K_XL)</td>
                            <td>163k</td>
                            <td>~105k</td>
                            <td>~12k</td>
                            <td>7:15</td>
                            <td>1</td>
                            <td>3</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>
                The gap between window and peak-used is the tell: OpenCode compacts near 70% of the window, so the local arms never reach their ceilings. The Q4 27B topped out around 70k against a task that wanted ~100k of state (which is why it compacted twice and thrashed), while the 9B coasted at 75k of its 262k and Claude used ~103k of its 1M without coming close.
            </p>
            <p>
                The tokens-generated column tells the other half: Claude spent multiples more producing its plan (~92k tokens against 12&ndash;25k for each local arm), and that figure is <em>mostly extended reasoning</em> (Claude ran at extra-high effort). It's also main-thread only: the three subagents it ran in parallel generated roughly 26k more on top, putting Claude's true total near 118k. More inference thrown at the same prompt, for the top-ranked plan. Whether that's worth it is the rest of the post.
            </p>
            <p class="text-muted"><small>
                The two tokenize differently (Claude's tokenizer runs denser, especially on code), so read the multiple as approximate, not a clean ratio. Even discounted for that, the bulk of the gap is real reasoning, not accounting.
            </small></p>

            <p>
                One operational wrinkle: OpenCode's Plan Mode sometimes pauses to ask the operator a clarifying question; Claude Code never did. Pauses tracked compactions: once compaction dropped load-bearing context, the model asked rather than guessed. I answered minimally to keep human input uniform.
            </p>

            <h3 id="how-each-plan-performed" class="fw-bolder mb-3 mt-4">
                <a href="#how-each-plan-performed" class="text-reset text-decoration-none">How each plan performed</a>
            </h3>
            <p>
                The numbers above are what each run cost; whether the plan that came out is any <em>good</em> is another matter. Here's what that comes down to: the same feature area (Authentication), from Claude's plan and the Q4 27B's:
            </p>
            <div class="row" style="align-items: start;">
                <div class="col-md-6">
                    <p class="mb-2"><strong>Claude</strong></p>
                    <ul>
                        <li><code>it('registers a new user with district + fleet and lands on /logbook')</code> &mdash; Pre: anon, districts/fleets seeded. Verifies: navigation to <code>/logbook</code>, success toast, new user row in DB.</li>
                        <li><code>it('shows a validation error when password and confirmation differ')</code> &mdash; Verifies: Livewire re-renders with <code>.input-error</code>, no navigation.</li>
                        <li><code>it('logs in with valid credentials and redirects to /logbook')</code></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <p class="mb-2"><strong>Qwen 3.6-27B Q4</strong></p>
                    <ul>
                        <li><code>A-01 /register</code> &mdash; valid data creates a user and logs in.</li>
                        <li><code>A-08 /login</code> &mdash; valid credentials redirect to <code>/flashes</code>.</li>
                        <li><code>A-10 /login</code> &mdash; redirect to <code>/login</code> when hitting <code>/flashes</code> unauthenticated.</li>
                    </ul>
                </div>
            </div>
            <p class="text-muted"><small>
                Same feature area, two plans, lightly condensed. Claude commits to the real route (<code>/logbook</code>) with per-test preconditions and assertions; Q4 routes everything through the hallucinated <code>/flashes</code> and never gets past a one-line description. A wrong route in the plan becomes a 404 in every spec built from it.
            </small></p>
            <p>
                That difference (real routes and assertions versus made-up routes and one-liners) is the kind of thing the rubric has to score. I graded all five on six dimensions (Structure / Constraints / Specificity / Coverage / Grounding / Conciseness), 1&ndash;10 each, unweighted mean, best to worst left to right:
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Dimension</th><th>Claude</th><th>35B-A3B</th><th>27B Q3</th><th>27B Q4</th><th>9B</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Structure</td><td>10</td><td>8</td><td>7</td><td>5</td><td>5</td></tr>
                        <tr><td>Constraints</td><td>10</td><td>7</td><td>7</td><td>5</td><td>5</td></tr>
                        <tr><td>Specificity</td><td>10</td><td>6</td><td>6</td><td>4</td><td>4</td></tr>
                        <tr><td>Coverage</td><td>10</td><td>8</td><td>5</td><td>5</td><td>8</td></tr>
                        <tr><td>Grounding</td><td>10</td><td>4</td><td>6</td><td>3</td><td>3</td></tr>
                        <tr><td>Conciseness</td><td>9</td><td>6</td><td>7</td><td>7</td><td>3</td></tr>
                        <tr><td><strong>Mean</strong></td><td><strong>9.8</strong></td><td><strong>6.5</strong></td><td><strong>6.3</strong></td><td><strong>4.8</strong></td><td><strong>4.7</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <blockquote>
                <p>
                    Full disclosure: the grading was done by Claude (Opus 4.7), with heavy input from me. This would be a conflict of interest if models had feelings about winning; they don't. It does muddle the methodology, and I won't pretend otherwise; this is the level of rigor a side project gets once it's months past the weekend I budgeted for it. The <a href="<?= $experiment_repo ?>/blob/main/grading/plan-grading.md">rubric</a> and all five plans are in the repo: grade them yourself if you'd rather not take a robot's word for it.
                </p>
            </blockquote>

            <div class="plan-card plan-card--claude">
                <h3 id="claude-opus-plan" class="fw-bolder mb-3 mt-4">
                    <a href="#claude-opus-plan" class="text-reset text-decoration-none">Claude Opus 4.7 <span class="plan-card-score">9.8</span>: implementable end-to-end</a>
                </h3>
                <p>
                    The only plan that picks up the prompt's three foregrounded constraints (dynamic dates via <code>DateRangeService</code>, Laravel-seeded fixtures + <code>storageState</code> auth, and JS-timing-as-the-risk-surface for Livewire morphs) and uses them as load-bearing premises rather than checklist items. Section 6 (the JavaScript integration testing section) uses observable-state waits everywhere (<code>_flatpickr.selectedDates.length</code>, <code>.has-entry</code> class transitions, <code>morph.added</code>/<code>morph.updated</code> event sequences) instead of the <code>waitForTimeout</code> anti-pattern the prompt explicitly warned against. Selectors match the real codebase: <code>#date-picker</code> / <code>#date-picker-single</code>, <code>#activity_type</code> / <code>_edit</code>, <code>#district-select</code> / <code>#fleet-select</code>, the actual <code>&lt;div class=&quot;alert alert-${type}&quot;&gt;</code> toast structure. Proposes a concrete <code>app/Console/Commands/E2eSeedCommand.php</code> with named scenarios (<code>base</code>, <code>fresh-user</code>, <code>many-flashes</code>, <code>admin</code>, <code>tiered</code>, <code>leaderboard</code>), implementable as-is.
                </p>
                <p>
                    Minor blemishes: a TODO leak (&ldquo;relax via count later if needed&rdquo;), a <code>process.env.PW_PROJECT</code> reference to a variable Playwright doesn't actually use (real: <code>PLAYWRIGHT_PROJECT</code> or <code>test.info().project.name</code>), and a year-dropdown test gated on <code>January &amp;&amp; currentYear &gt; START_YEAR</code> that won't run in CI 11 months a year. Real bugs but trivially fixable.
                </p>
                <p>
                    Reads like a senior engineer who has run a Playwright + Livewire suite before, knew where Livewire bites, and pre-empted the bites.
                </p>
                <div class="plan-card-footer">
                    <p>Full plan: <a href="<?= $experiment_repo ?>/blob/main/plans/claude-plan.md"><code>plans/claude-plan.md</code></a> (782 lines)</p>
                </div>
            </div>

            <div class="plan-card plan-card--qwen">
            <h3 id="qwen-35b-plan" class="fw-bolder mb-3 mt-4">
                <a href="#qwen-35b-plan" class="text-reset text-decoration-none">Qwen 3.6-35B-A3B <span class="plan-card-score">6.5</span>: looks polished, won't run as written</a>
            </h3>
            <p>
                Structurally faithful: all 7 sections in order, A-G feature areas mapped 1:1 to the prompt, <code>describe</code>/<code>it</code> naming, covers all 5 JS modules. Adds a sensible &ldquo;Progress &amp; Awards&rdquo; sub-grouping that maps cleanly to real ProgressCard behavior.
            </p>
            <p>
                But the selectors are extensively confabulated. The date picker is consistently called <code>#flash-date-picker</code> (real: <code>#date-picker</code>). Toast selectors are wrong: <code>.alert-title</code>, <code>.alert-body</code>, <code>.toast-icon</code>. The real toast has no title/body split and no <code>.toast-icon</code> class. The TomSelect option selector is given as <code>.ts-dropdown option</code>, but real options carry class <code>.option</code> and aren't <code>&lt;option&gt;</code> tags. Sailor-logs filter IDs are off by a prefix.
            </p>
            <p>
                Section 7 also expands beyond the prompt's &ldquo;exactly 7 sections&rdquo; constraint with unrequested &ldquo;CSS/visual quality checks&rdquo; and &ldquo;Accessibility quality checks&rdquo; subsections. The seeding section is internally contradictory: claims <code>storageState</code> but the <code>regularUser</code> fixture logs in via UI every time, and <code>FLASH_SEEDING_STRATEGY</code> hardcodes dates contradicting the dynamic-date constraint the same plan acknowledges elsewhere.
            </p>
            <p>
                The pattern is: <strong>wide coverage, confidently expressed, mostly wrong about the actual DOM contracts of the libraries the app uses.</strong> A senior engineer reading this plan would say &ldquo;I'd have to fix the selectors on basically every spec before any of them runs.&rdquo;
            </p>
            <div class="plan-card-footer">
                <p>Full plan: <a href="<?= $experiment_repo ?>/blob/main/plans/qwen-3.6-35b-a3b-plan.md"><code>plans/qwen-3.6-35b-a3b-plan.md</code></a> (787 lines)</p>
            </div>
            </div>

            <div class="plan-card plan-card--qwen">
            <h3 id="qwen-27b-q3-plan" class="fw-bolder mb-3 mt-4">
                <a href="#qwen-27b-q3-plan" class="text-reset text-decoration-none">Qwen 3.6-27B Q3_K_XL <span class="plan-card-score">6.3</span>: narrower but better-grounded</a>
            </h3>
            <p>
                Selector accuracy is meaningfully better than 35B in the places that matter: gets <code>#date-picker</code> / <code>#date-picker-single</code>, <code>#activity_type</code> / <code>_edit</code>, <code>#sailing_type</code> / <code>_edit</code>, and, crucially, the correct sailor-logs filter IDs (<code>#sailor-logs-district-select</code> / <code>#sailor-logs-fleet-select</code>). Admin routes correct. Calls out the <code>requestAnimationFrame</code> cycle and <code>morph.added</code> reinit explicitly. Storage-state pattern described correctly. Tiered-user fixtures (<code>tier1User</code>, <code>tier2User</code>, <code>tier3User</code>, <code>capUser</code>, <code>overCapUser</code>) are well-thought-out for the milestone and non-sailing-cap tests.
            </p>
            <p>
                But <strong>two of the prompt's seven feature areas are missing</strong>: Profile Management and Cross-feature Flows. The A-G framing was renamed and these two were dropped on the floor. A handful of profile tests are sprinkled into the Authentication describe-block (&ldquo;shows pending email alert in profile after email change&rdquo;), but there's no district/fleet edit coverage, no profile personal-info edit coverage, no register&rarr;profile flow, no log&rarr;leaderboard flow, no milestone-cross-feature tests.
            </p>
            <p>
                Some selectors are still off (toast <code>.toast-icon</code> / <code>.toast-message</code> / <code>.toast-close</code> are invented; verification banner is referenced as <code>.verification-banner</code> class when it's actually <code>#verification-banner</code> ID; TomSelect dropdown classes are wrong). Still uses <code>waitForTimeout(100)</code>/<code>waitForTimeout(200)</code> in JS sections.
            </p>
            <p>
                My read matches the grader's: of the four Qwen plans, <strong>Q3 is the strongest candidate for actually implementing</strong>. The missing feature areas are a <em>structural gap</em> that an implementation agent can recover from by re-reading the codebase. Confabulated selectors are a <em>grounding failure</em> that costs a debug cycle per spec. Q3 has more of the former and less of the latter. And, notably, Q3 also caught real Livewire-event-driven behaviors Claude missed (sailor-logs mutual filter clearing, URL&rarr;state on tab switching), so it's not purely a damage-minimization pick.
            </p>
            <div class="plan-card-footer">
                <p>Full plan: <a href="<?= $experiment_repo ?>/blob/main/plans/qwen-3.6-27b-q3-plan.md"><code>plans/qwen-3.6-27b-q3-plan.md</code></a> (763 lines)</p>
            </div>
            </div>

            <div class="plan-card plan-card--qwen">
            <h3 id="qwen-27b-q4-plan" class="fw-bolder mb-3 mt-4">
                <a href="#qwen-27b-q4-plan" class="text-reset text-decoration-none">Qwen 3.6-27B Q4_K_XL <span class="plan-card-score">4.8</span>: confidently wrong about URLs</a>
            </h3>
            <p>
                Shortest plan (416 lines) and the most damaging failure mode: <strong>route hallucinations throughout</strong>. <code>/flashes</code> for the logbook (real: <code>/logbook</code>), <code>/flashes (profile section)</code> for the profile page (real: <code>/profile</code> is its own route), <code>/admin/awards</code> and <code>/admin/logs</code> for admin (real: <code>/admin/fulfillment</code> and <code>/admin/sailor-logs</code>). Every spec navigating to these routes would 404 on the first call. Q4 also invents a whole <code>date-of-birth-validator.js</code> module that doesn't exist (DOB logic lives in <code>user-profile-form.js</code>).
            </p>
            <p>
                Section 7 adds unrequested Accessibility and Performance Baselines subsections despite &ldquo;no additional sections&rdquo; in the prompt. Profile management is folded into a single tab with seven tests, all addressed via the wrong route.
            </p>
            <p>
                The underlying VRAM/compaction story is in <a href="#fitting-local-arms">Fitting the Local Arms</a> above; this critique sticks to what's in the resulting plan.
            </p>
            <div class="plan-card-footer">
                <p>Full plan: <a href="<?= $experiment_repo ?>/blob/main/plans/qwen-3.6-27b-q4-plan.md"><code>plans/qwen-3.6-27b-q4-plan.md</code></a> (416 lines)</p>
            </div>
            </div>

            <div class="plan-card plan-card--qwen">
            <h3 id="qwen-9b-plan" class="fw-bolder mb-3 mt-4">
                <a href="#qwen-9b-plan" class="text-reset text-decoration-none">Qwen 3.5-9B <span class="plan-card-score">4.7</span>: quantity over precision</a>
            </h3>
            <p>
                Highest raw test count (~100 enumerated test cases across A-G) and includes <code>/export/user-data</code> in HTML validation that other plans missed. The 1,327 lines look impressive in word count.
            </p>
            <p>But:</p>
            <ul>
                <li>Uses snake_case <code>test_user_can_visit_home_page</code>-style names throughout Section 1 instead of the requested <code>describe</code>/<code>it</code> naming, a structural prompt-adherence failure</li>
                <li>Hardcoded <code>2026-05-15</code> dates in <code>FLASH_SEEDING_STRATEGY</code> despite a separate <code>DATE_STRATEGY</code> block correctly computing dynamic dates, directly contradicting the prompt's date-handling constraint</li>
                <li>Invents Livewire action names (<code>wire:click="open-edit-modal"</code>; the real method dispatches as <code>openEditModal($flashId)</code>)</li>
                <li>1,327 lines mostly consisting of repetitive code snippets that re-explain the same Playwright pattern</li>
                <li>Adds a closing &ldquo;Summary&rdquo; section duplicating the seven sections in bullet form</li>
            </ul>
            <p>
                The 9B's volume is the failure mode. Many specs that need rework, many that wouldn't run because of the date and <code>wire:click</code> hallucinations. Mostly redeemed by sheer enumerative breadth: when you list every conceivable test case, you happen to catch ones the bigger models missed (rate-limit tests, audit-log checks, empty-export blocking). But a senior engineer trying to use this as a starting point would spend most of the time deleting.
            </p>
            <div class="plan-card-footer">
                <p>Full plan: <a href="<?= $experiment_repo ?>/blob/main/plans/qwen-3.5-9b-plan.md"><code>plans/qwen-3.5-9b-plan.md</code></a> (1,327 lines)</p>
            </div>
            </div>

            <h3 id="cross-plan-patterns" class="fw-bolder mb-4 mt-5">
                <a href="#cross-plan-patterns" class="text-reset text-decoration-none">Cross-plan patterns that recurred</a>
            </h3>
            <p>Four findings from the grade that show up in multiple Qwen plans and never in Claude's:</p>
            <ol>
                <li><strong><code>waitForTimeout</code> as a default wait strategy.</strong> All four Qwen plans use it after Livewire interactions. The prompt's Section 6 framing was a direct invitation to design observable-state-based waits. Only Claude picked up the signal.</li>
                <li><strong>Hallucinated selectors cluster around third-party libraries.</strong> Toast structure and TomSelect classes get confabulated across multiple plans. These are libraries whose documented class names are similar across versions and easy to invent from training data; the smaller and lower-quant the model, the more aggressive the confabulation.</li>
                <li><strong>Section 7 attracts unrequested bloat.</strong> Three of four Qwen plans added Accessibility / Performance / CSS-quality subsections despite the prompt's explicit &ldquo;no additional sections&rdquo; line. Claude's Section 7 stays inside the requested scope.</li>
                <li><strong>Plan size is not plan quality.</strong> The longest plan (9B at 1,327 lines) ranked last. The shortest local plan (Q4 at 416 lines) ranked fourth. Claude's 782-line plan ranked first. The shape of the work (what claims it makes, how grounded each claim is, whether instruction-following held) dominates the line count.</li>
            </ol>
            <p>
                There's also an <em>inverse</em> pattern worth flagging: although no Qwen plan approached Claude's overall quality, each one caught at least a few real test cases Claude omitted. The 9B's exhaustive enumeration surfaced registration rate-limiting, admin audit-logging, and explicit non-sailing-day counting semantics. Q3 caught the sailor-logs mutual filter clearing and the URL&rarr;state direction on tab switching. 35B-A3B caught the <code>yacht_club</code> profile field and broke tie-breaking into discrete tests. About a dozen cases in total: not enough to change the ranking, but enough that the honest takeaway is <strong>&ldquo;Claude's structure plus the Qwen-caught cases,&rdquo;</strong> not &ldquo;use Claude's plan as-is and discard the rest.&rdquo;
            </p>

            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: for discovery work, run it more than once.</strong> For enumeration tasks (test cases, edge cases, failure modes), the variance that hurts when you want one right answer helps when you want a <em>complete</em> one. Even the four weaker plans here each caught real cases Claude missed. Run the same prompt across several models, or just several times against the same one, and each pass surfaces something the others didn't; union the results and hand them to your best model to dedup. You're not picking a winner; you're harvesting coverage.
                </p>
            </div>
        </section>

        <hr class="my-5">

        <section>
            <h2 id="phase-2-implementation" class="fw-bolder mb-4 mt-5">
                <a href="#phase-2-implementation" class="text-reset text-decoration-none">Phase 2: Implementation, Claude Code vs Qwen 3.6-27B Q3 on the Same Plan</a>
            </h2>
            <p>
                With all five plans written and graded, it's finally time to write some code. Phase 1 showed a clear frontier edge in <em>planning</em>: Claude's plan out-scored every local arm by a wide margin. Phase 2 asks whether that extra capability is still needed once a plan is in hand, or whether the design was the hard part and a capable-enough local model can take it from there. It also partially tests the leading-indicator claim from earlier, that a model's plan quality foreshadows how it implements: if the stronger planner also builds more from the same plan, that's a point in its favor.
            </p>
            <p>
                To answer that, both arms work from a single combined plan, the <a href="<?= $experiment_repo ?>/blob/main/plans/synthesis-plan.md">synthesis plan</a> (~830 lines), built on the winning Opus plan as its base and augmented with the roughly dozen real cases Claude missed that the local arms caught (the inverse pattern from the last section). Assembling it this way puts that section's advice into practice: aggregate several runs into one result more comprehensive than any single arm produced.
            </p>
            <p>
                Two models then implement that plan head-to-head, on the same setup as Phase 1: <strong>Claude Code on Opus 4.7</strong> (1M context, extra-high reasoning) and <strong>Qwen 3.6-27B Q3_K_XL via OpenCode</strong> on the same 24 GB GPU. Both get the same <a href="<?= $experiment_repo ?>/blob/main/prompts/implementation-prompt.md">implementation prompt</a>, the same <code>gotflashes</code> codebase, and one hard constraint: <strong>test code only; the app stays read-only</strong> (bar the one artisan seed command the plan itself calls for), so neither can &ldquo;fix&rdquo; the app to make a failing test pass. Claude building the plan is the all-cloud baseline; Q3 building it is the cloud-plan / local-execute hybrid, worth flagging on its own: going local isn't all-or-nothing. Keep the cloud for the design it's best at and run the bulk of the work on your own hardware.
            </p>
            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: an agent that operates a real app does real things; cap its blast radius.</strong> Phase 1 was read-only: agents read the codebase and wrote plans. Implementation is different: the agent <em>runs</em> your app, so anything that app does in production (emails, webhooks, database writes, external API calls), it'll trigger for real, often as a side effect of making a test pass. The risk is sharpest on a hobby setup, where there's usually one environment and the agent inherits whatever real credentials your dev config holds. A safe override only protects you if every entry point loads it: Laravel ships an <code>.env.testing</code>, but <code>php artisan serve</code> won't pick it up unless you boot with <code>APP_ENV=testing</code>, exactly the kind of gap an agent wanders straight through. So prompts asking for safe behavior aren't enough, and neither is a safe config the agent can sidestep; only enforced, infrastructure-layer guards are. Those working in an enterprise setting should have an isolated environment where the agent never sees a production secret. For a hobby project you may need to get more creative: fake credentials, a fail-safe allowlist (the <a href="<?= $experiment_repo ?>/tree/main/safety">harness I wrote for exactly this</a>), and a check before each run.
                </p>
            </div>
            <p>
                An alternative design would have had each model implement its <em>own</em> plan. I rejected that: pairing a weak plan with a weak implementer compounds the two failures and tells you little about implementation ability in isolation. Holding the plan fixed (the same strong plan for both) is what isolates the part Phase 2 is trying to measure.
            </p>
            <blockquote>
                <p>
                    Why Q3 as the local arm, and not the 35B-A3B that edged it in Phase 1? It was a near-tie (6.3 vs 6.5), and the rubric scored plan-<em>writing</em>; implementation rewards different things. It rewards grounding (real selectors and routes), which was Q3's edge (6 to 4 over the 35B); the 35B's edge was coverage, but a hallucinated <code>#flash-date-picker</code> costs a debug cycle in every spec, where a missing feature area can be recovered by re-reading the code. It rewards headroom: Q3 loads at 163k context, the 35B only 131k on a sliver of live-free memory, and a multi-hour run is where that gap bites. And it rewards reasoning per token: Q3 is the dense 27B with every parameter active, where the 35B fires just ~3B of its experts. Q3 is also the config the setup dialed in, the local arm a real user would run.
                </p>
            </blockquote>

            <h3 id="headline-results" class="fw-bolder mb-3 mt-4">
                <a href="#headline-results" class="text-reset text-decoration-none">Headline results</a>
            </h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Metric</th><th>Claude Code (Opus 4.7, 1M ctx)</th><th>Qwen 3.6-27B Q3 (OpenCode, 163k ctx)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Total wall-clock (first prompt &rarr; last response)</td><td>3 h 47 min</td><td>3 h 5 min</td></tr>
                        <tr><td>Operator-wait time (idle waiting on me)</td><td>4 min 10 sec</td><td>26 min 37 sec</td></tr>
                        <tr><td>Agent-only time (compute and tool use)</td><td><strong>3 h 43 min</strong></td><td><strong>2 h 39 min</strong></td></tr>
                        <tr><td>Operator interventions</td><td><strong>1</strong> (a single &ldquo;continue&rdquo;)</td><td><strong>7</strong> (6 unblock-stalls + 1 explicit nudge)</td></tr>
                        <tr><td>Context compactions during the run</td><td><strong>0</strong></td><td><strong>4</strong> (~70% trigger, 163k window)</td></tr>
                        <tr><td>Peak context used (single turn)</td><td>322,672 tokens (1M ctx)</td><td>~134k (per OpenCode TUI at end)</td></tr>
                        <tr><td>Median per-turn context</td><td>222,555 tokens</td><td>(compaction-clamped)</td></tr>
                        <tr><td>Spec files written (<code>*.spec.ts</code>)</td><td>39</td><td>39</td></tr>
                        <tr><td>Total test files (specs + helpers, lines)</td><td>52 files / 2,868 lines</td><td>53 files / 2,708 lines</td></tr>
                        <tr><td>Tests authored</td><td>203</td><td>140</td></tr>
                        <tr><td>Passing (final run)</td><td>114</td><td>74</td></tr>
                        <tr><td>Failing</td><td>52</td><td>52</td></tr>
                        <tr><td>Skipped</td><td>37 (project-gated HTML checks)</td><td>14</td></tr>
                        <tr><td><code>test.fixme()</code> calls</td><td>17</td><td>9</td></tr>
                        <tr><td><code>test.skip()</code> calls</td><td>8</td><td>16</td></tr>
                        <tr><td><code>waitForTimeout</code> calls (<strong>prompt forbids</strong>)</td><td><strong>0</strong></td><td><strong>32</strong> across 15 files</td></tr>
                        <tr><td>App-code touches</td><td>0 (clean separation)</td><td>0 (clean separation)</td></tr>
                        <tr><td><code>KNOWN-APP-ISSUES.md</code> written?</td><td><strong>Yes: 5 blockers documented</strong></td><td><strong>No</strong></td></tr>
                    </tbody>
                </table>
            </div>

            <h3 id="local-model-wasnt-slow" class="fw-bolder mb-3 mt-4">
                <a href="#local-model-wasnt-slow" class="text-reset text-decoration-none">The local model wasn't the slow one</a>
            </h3>
            <p>
                Strip out the time the agent spent idle waiting for me to type a nudge and the wall-clocks flip: Claude needed <strong>3 h 43 min</strong> of compute and tool-use time, Q3 needed <strong>2 h 39 min</strong>. Q3 was actually 1 h 4 min <em>faster</em> in pure agent-time. Q3 ran at ~50 tok/s on local hardware; Claude ran on Opus 4.7 API with extra-high reasoning generating a lot more thinking tokens for a lot more tests. So the local model isn't slower in compute; the gap is in <em>what gets produced</em> during that time.
            </p>
            <p>
                That difference shows up first in coverage. The synthesis plan enumerated 109 test cases across 7 feature areas (auth, logbook, leaderboard, profile, admin, export, cross-feature flows). Both arms had the same plan. Static counts of <code>test(...)</code> calls per feature folder:
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Feature area</th><th>Plan</th><th>Claude</th><th>Q3</th><th>Cl/Pl</th><th>Q3/Pl</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>A. auth</td><td>19</td><td>19</td><td>9</td><td>100%</td><td><strong>47%</strong></td></tr>
                        <tr><td>B. logbook</td><td>22</td><td>22</td><td>19</td><td>100%</td><td>86%</td></tr>
                        <tr><td>C. leaderboard</td><td>16</td><td>17</td><td>13</td><td>106%</td><td>81%</td></tr>
                        <tr><td>D. profile</td><td>15</td><td>16</td><td>12</td><td>107%</td><td>80%</td></tr>
                        <tr><td>E. admin</td><td>29</td><td>30</td><td>20</td><td>103%</td><td><strong>69%</strong></td></tr>
                        <tr><td>F. export</td><td>2</td><td>2</td><td>2</td><td>100%</td><td>100%</td></tr>
                        <tr><td>G. flows</td><td>6</td><td>6</td><td>7</td><td>100%</td><td>117%</td></tr>
                        <tr><td><strong>TOTAL (A&ndash;G)</strong></td><td><strong>109</strong></td><td><strong>112</strong></td><td><strong>82</strong></td><td><strong>103%</strong></td><td><strong>75%</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Claude wrote every plan-specified case and added three more (one each in leaderboard, profile, admin). Q3 stopped 27 short on the plan, with the gap concentrated in the two largest feature areas: auth (10 missing test cases, more than half the section) and admin (9 missing). These are entire test cases the plan named and Q3 simply didn't implement. The pass-rate-table-only view doesn't surface this; an agent could pass 100% of an implementation that covers half the plan, and the rubric should care about <em>both</em>.
            </p>
            <p>
                What gets produced: Claude wrote 45% more tests (203 vs 140), needed <strong>one</strong> unblock prompt over the whole run, and <strong>never hit context compaction</strong> thanks to the 1M-token context carrying the long task. Q3 wrote a narrower suite, hit compaction four times, needed <strong>seven</strong> operator interventions, and ended at ~65% of Claude's passing-test count.
            </p>
            <p>
                The asymmetry that matters isn't speed; it's <strong>how often the run drags you back in</strong>. Q3 needed seven hands-on moments to Claude's one. The idle minutes between them only measure how long I happened to be elsewhere; the real cost is each interruption itself: the context-switch back into a run you'd meant to fire and forget. That's the experience gap.
            </p>

            <h3 id="compaction-was-the-dividing-line" class="fw-bolder mb-3 mt-4">
                <a href="#compaction-was-the-dividing-line" class="text-reset text-decoration-none">Compaction was the dividing line</a>
            </h3>
            <p>
                Back in Phase 1, the 27B showed compaction degrading capability by dropping load-bearing context into a lossy summary. Phase 2 made the same point with a different lever. Claude's 1M-token run never approached the compaction trigger over a 3 h 47 min stretch of file reads, test writing, and <code>npx playwright test</code> runs. Q3 at 163k context hit compaction at the 18-minute, 60-minute, 95-minute, and 135-minute marks, and three of those four compactions were immediately followed by an operator &ldquo;continue&rdquo; message, because the post-compaction agent stalled instead of resuming.
            </p>
            <p>
                The 1M ceiling wasn't decorative: <strong>Claude's peak per-turn input context across the implementation run was 322,672 tokens</strong> (median 222k), well past the 200k Opus standard window. At standard 200k context, Claude would have compacted multiple times; by my count, the median turn alone would have crossed a 70% trigger. The 1M-token ceiling is load-bearing specifically for long-horizon implementation, not for medium-horizon planning. If you're picking where to spend an upgraded-context budget, that's the answer: impl needs it; planning doesn't.
            </p>
            <p>
                This is the strongest piece of evidence in the experiment that <strong>context window size is a capability-level feature, not a comfort feature</strong>, for long-horizon implementation tasks.
            </p>

            <h3 id="test-code-quality" class="fw-bolder mb-3 mt-4">
                <a href="#test-code-quality" class="text-reset text-decoration-none">Test-code quality, dimension by dimension</a>
            </h3>
            <p>
                Pass rate and plan coverage are the loud numbers. The quiet ones are the per-test quality signals: how each agent chose to write the tests they did write. Six dimensions, programmatic counts, mixed picture:
            </p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Dimension</th><th>Claude</th><th>Q3</th><th>Winner</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Selector strategy: <code>getByRole(...)</code> calls</td><td>9</td><td><strong>79</strong></td><td>Q3</td></tr>
                        <tr><td>Selector strategy: class-chain <code>locator('.xxx')</code> (lower is better)</td><td>105</td><td><strong>26</strong></td><td>Q3</td></tr>
                        <tr><td>Domain helpers: <code>utils/livewire.ts</code> <code>waitForLivewireIdle</code> uses</td><td>1</td><td><strong>50</strong></td><td>Q3</td></tr>
                        <tr><td>Assertions per test (<code>expect(...)</code> / test block)</td><td><strong>1.3</strong></td><td>1.0</td><td>Claude</td></tr>
                        <tr><td>Strong-assertion ratio (toBe/toEqual/toHaveText/etc. vs total)</td><td>24%</td><td>22%</td><td>tied</td></tr>
                        <tr><td><code>test.beforeEach</code> blocks (per-test setup discipline)</td><td><strong>19</strong></td><td>5</td><td>Claude</td></tr>
                        <tr><td>Test names &lt; 30 chars (lower is more descriptive)</td><td><strong>11</strong></td><td>18</td><td>Claude</td></tr>
                        <tr><td><code>: any</code> / <code>as any</code> usages (lower is better TypeScript hygiene)</td><td><strong>7</strong></td><td>15</td><td>Claude</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                <strong>Selectors and helpers (Q3 wins, real).</strong> <code>getByRole</code> is Playwright's recommended primary locator strategy: it queries the accessibility tree, which is stable across CSS class renames. Q3 wrote 79 <code>getByRole(...)</code> calls; Claude wrote 9. Conversely, Claude reached for class-chain selectors (<code>locator('.daisy-ui-class-name')</code>) 105 times; Q3 used them 26 times. Q3 also invested in <code>utils/livewire.ts</code> (a 92-line helper with a <code>waitForLivewireIdle</code> function used 50 times across specs); Claude's equivalent helper exists but is used once. Q3 wrote the more Playwright-idiomatic test code.
            </p>
            <p>
                <strong>Assertion density (Claude wins).</strong> 1.3 <code>expect(...)</code> calls per test vs Q3's 1.0. Concretely: Claude tests average a state-change action plus a multi-property verification; Q3's average is closer to &ldquo;do the thing, check one observable.&rdquo;
            </p>
            <p>
                <strong>Test isolation (Claude wins, materially).</strong> Claude wrote 19 <code>test.beforeEach</code> blocks for explicit per-test state setup; Q3 wrote 5. Claude's 3.8&times; more frequent <code>beforeEach</code> usage means more tests own their own preconditions.
            </p>
            <p>
                <strong>Net read.</strong> If I were assembling the final suite, I'd graft Q3's <code>getByRole</code> habit and its <code>waitForLivewireIdle</code> helper onto Claude's stronger isolation, denser assertions, and tighter types: mine the local arm for style, keep the cloud arm for discipline. Claude isn't uniformly better; it's that where Q3 wins it's a code-<em>style</em> call, and where Claude wins it's <em>discipline</em>.
            </p>

            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: a repeated mismatch belongs in <code>CLAUDE.md</code> / <code>AGENTS.md</code>, not a per-file correction.</strong> Most of these dimensions are <em>preferences</em>: <code>getByRole</code> over class chains, a <code>beforeEach</code> per spec, no <code>: any</code>. When a model keeps making the same call you wouldn't, that's not a bug to fix test-by-test; it's a missing line in your agent-instructions file. Write the rule down once (<code>CLAUDE.md</code> for Claude Code, <code>AGENTS.md</code> for OpenCode and most others) and it steers every file the agent touches afterward. My rule of thumb: a divergence you see once is a correction; the same one three times is a documentation gap.
                </p>
            </div>

            <h3 id="constraint-compliance" class="fw-bolder mb-3 mt-4">
                <a href="#constraint-compliance" class="text-reset text-decoration-none">Constraint compliance: the prompt asked for no waitForTimeout</a>
            </h3>
            <p>
                Claude used <code>waitForTimeout</code> <strong>zero times</strong> across its 39 spec files. Q3 used it <strong>32 times across 15 files</strong>, including in its own <code>utils/livewire.ts</code> helper, so the pattern is baked into Q3's wait strategy, not isolated to a handful of tests it gave up on. Those 32 <code>waitForTimeout</code> calls are the flake-source in Q3's suite that doesn't show up in the pass-rate table. On a faster CI, Q3's tests would start failing in patterns Claude's wouldn't, even before any code change.
            </p>
            <p>
                The clearest illustration is that same <code>utils/livewire.ts</code> helper: the one the quality table just credited Q3 for leaning on 50&times;, and whose whole job is to replace <code>waitForTimeout</code> with observable-state waiting. Both arms wrote one, and both stub the actual idle-detection (Livewire 3 exposes no idle flag); the fallback is the tell.
            </p>
            <div class="row" style="align-items: start;">
                <div class="col-md-6">
                    <p class="mb-2"><strong>Claude: <code>utils/livewire.ts</code></strong></p>
                    <?php include "generated/highlighted-shiki/claude-vs-local/claude-livewire-idle.html"; ?>
                </div>
                <div class="col-md-6">
                    <p class="mb-2"><strong>Q3: <code>utils/livewire.ts</code></strong></p>
                    <?php include "generated/highlighted-shiki/claude-vs-local/q3-livewire-idle.html"; ?>
                </div>
            </div>
            <p>
                Claude falls back to <code>waitForLoadState('networkidle')</code>. Q3 hardcodes two fixed sleeps into the very helper meant to avoid them, and reaches for an <code>lw.numpy</code> property that doesn't exist on Livewire (every branch returns <code>true</code> regardless, so the &ldquo;state check&rdquo; is decorative). That helper is called 50&times; across the specs; it's why the <code>waitForTimeout</code> count is structural, not a few stragglers.
            </p>
            <p>
                This is the gap between &ldquo;the agent compiles and runs&rdquo; and &ldquo;the agent reads the prompt carefully.&rdquo; Q3 produced tests; Claude produced tests <em>that follow the constraints the operator set</em>.
            </p>
            <p>
                Which sharpens the tip above, and points at something deeper than style. Documenting a preference in <code>CLAUDE.md</code> or <code>AGENTS.md</code> only helps with a model that honors its instructions, and that's exactly why Claude's gaps are fixable: it follows the rules it's given, so a new rule lands. Q3 ignored an explicit, prompt-level constraint 32 times; one more line in <code>AGENTS.md</code> wouldn't have changed that. Claude's misses are <em>preference gaps a rule closes</em>; Q3's are <em>rule-following gaps no rule reaches</em>. The cloud model's behavior is steerable in a way the local one's isn't.
            </p>

            <h3 id="reading-q3-board" class="fw-bolder mb-3 mt-4">
                <a href="#reading-q3-board" class="text-reset text-decoration-none">What Q3's pass/fail board actually tells you</a>
            </h3>
            <p>
                74 passing, 52 failing reads like an ordinary mid-run result. Look closely at both columns, though, and neither means quite what the number suggests: the red overstates how many distinct problems there are, and the green overstates how much actually works.
            </p>
            <p><strong>The red.</strong> The two arms' 52 failures cluster very differently:</p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Claude's 52 failures</th><th>Q3's 52 failures</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>21 &mdash; admin dashboard (<code>wire:model.live</code> selector timing)</td><td><strong>18 &mdash; date picker + Livewire idle timing</strong> (across 6 specs)</td></tr>
                        <tr><td>15 &mdash; <code>SESSION_SECURE_COOKIE</code> blocks fresh contexts (documented, fixme'd)</td><td>11 &mdash; profile pages (personal-info 5, district-fleet 3, banner 1, etc.)</td></tr>
                        <tr><td>6 &mdash; profile/form selectors (regression in last in-session run)</td><td>10 &mdash; JS-integration (TomSelect 6, toast 4)</td></tr>
                        <tr><td>5 &mdash; calendar date exhaustion (seed fills most allowed dates)</td><td>13 &mdash; HTML-validation + assorted (HTML 5, nav 2, password-toggle 2, etc.)</td></tr>
                    </tbody>
                </table>
            </div>
            <p>
                Claude's failures cluster around <strong>specific, named technical blockers</strong>, four of which it documented in <code>KNOWN-APP-ISSUES.md</code> (next section). Q3's cluster around <strong>a single root cause repeated across surfaces</strong>: the date-picker + Livewire <em>idle-timing</em> pattern (a <code>waitForTimeout</code>/wait problem) accounts for 18 of its 52 failures across 6 spec files. So Q3's 52 isn't 52 problems; it's largely one fix it found late and never generalized. Claude's failures look like a mature suite hitting environmental edges; Q3's look like an apprentice that discovered the fix and didn't go back and apply it.
            </p>
            <p>
                <strong>The green, and this is the part that should worry you.</strong> Q3 wrote tests that pass on the day they were <em>written</em>, not in general. Several of its passes aren't evidence the tests are right; they're latent date-<em>value</em> bugs (a different failure class from the idle-timing cluster above) that just didn't happen to fire on the day the suite ran. Both arms got the same warning about dynamic dates and the same domain quirks (January grace period; future dates beyond today+1 rejected), and both exported the same date helpers (<code>todayISO</code>, <code>isoDaysAgo</code>, <code>isoDaysAhead</code>). Only Claude used them everywhere. Three places Q3 didn't:
            </p>
            <p>
                <strong>1. <code>logbook/edit.spec.ts</code> and <code>logbook/delete.spec.ts</code> reach past their own helpers.</strong> Q3 wrote a private <code>logFlash(page, activityType, dayOfMonth)</code> function that constructs dates as <code>new Date(now.getFullYear(), now.getMonth(), dayOfMonth)</code> directly, bypassing the helper module that owns date computation. Called with <code>dayOfMonth</code> values of 2, 3, and 6. If you run these tests on day 1&ndash;5 of any month, the seeded date is in the future and the app rejects dates beyond today+1.
            </p>
            <p>
                <strong>2. <code>E2eSeedCommand::seedManyFlashes</code> silently creates 0 or 1 flashes instead of 20</strong>, every month of the year. The loop is <code>while (count($dates) &lt; 20 &amp;&amp; $d-&gt;lte($minDate))</code> with <code>$d</code> starting at &ldquo;Jan 1 of current year&rdquo; and the bound being <code>$minDate</code> (the <em>earliest allowed</em> date). The fix is one character (<code>$maxDate</code> instead of <code>$minDate</code>). The pagination tests that depend on this scenario can't possibly hit their threshold.
            </p>
            <p>
                <strong>3. Grace-period test handling diverges.</strong> The plan called for a &ldquo;returns 403 when posting an edit for an out-of-range flash&rdquo; test, which requires seeding a flash with a date the app would normally reject. Claude marked this <code>test.fixme()</code> and added an entry to <code>KNOWN-APP-ISSUES.md</code> explaining why test code can't bypass the <code>DateRangeService</code> validator. Q3 wrote the test, but it uses <code>waitForTimeout(1000)</code> and the same <code>wire.set('dates', ...)</code> routing pattern that broke 44 times against the wrong Livewire component.
            </p>
            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: an agent satisfies the feedback loop, not the goal behind it.</strong> Give it a green-tests target and it will reliably get the tests green&mdash;but <em>how</em> it gets there isn't necessarily what you wanted. A test can pass because it's right, or because it hard-codes today's date, seeds the wrong fixtures, or asserts nothing load-bearing: same checkmark, different reality. So audit the green column, not just the red, and look hardest where the signal is easy to game&mdash;dates, randomness, external state. The wider the gap between the metric the agent optimizes (tests pass today) and the outcome you actually care about (tests still pass next month), the more of it surfaces later as bugs.
                </p>
            </div>
            <p>
                None of this dents the 74-passing count. Bug&nbsp;1 passes on May&nbsp;26 and fails in the first week of any month; Bug&nbsp;2 fails every day, but Q3 logged its symptoms as generic &ldquo;leaderboard&rdquo; failures and never traced them. So both columns mislead in the same direction: the red is mostly one un-generalized fix wearing 18 disguises, and the green is a snapshot of one day's luck. Claude's board is the opposite: its red is a labeled to-do list, and its green holds up next month because it ran dates through the helpers everywhere and wrote down what it couldn't clear. That last habit (documenting a dead end instead of leaving it to surface later) is the next section.
            </p>

            <h3 id="known-app-issues" class="fw-bolder mb-3 mt-4">
                <a href="#known-app-issues" class="text-reset text-decoration-none">The KNOWN-APP-ISSUES.md story</a>
            </h3>
            <figure class="my-4 image-modal-content">
                <?php echo responsiveImage(
                    '/img/blog/claude_vs_local/claude-code-delivery.png',
                    'standard',
                    "Claude Code's end-of-run delivery summary table showing categories (Config/setup, Smoke, Auth, Logbook, etc.), file counts per category, test counts including which are marked test.fixme, and a categorised breakdown of the 52 failing tests.",
                    'img-fluid mx-auto d-block rounded shadow-sm',
                    ['(min-width: 1140px) 1140px', '100vw']
                ); ?>
                <figcaption class="figure-caption text-center mt-2">Claude Code's delivery summary at end of the implementation run: 55 files committed across 12 feature areas, with per-area test counts and the four named failure clusters.</figcaption>
            </figure>
            <p>
                Claude wrote a 5-entry <code>KNOWN-APP-ISSUES.md</code> documenting limitations it ran into: hashed password-reset tokens that test code can't extract, rate-limiter state with no test-only reset hook, grace-period validation that rejects seeded historical dates, <code>SESSION_SECURE_COOKIE=true</code> blocking HTTP fresh-context logins (affecting 15 tests), email-change verification tokens not accessible from tests. Each entry names the affected spec, the underlying issue, the suggested fix, and links the tests that use <code>test.fixme()</code> to point back to the entry. One entry, verbatim:
            </p>
            <?php include "generated/highlighted-shiki/claude-vs-local/known-app-issue-4.html"; ?>
            <p>
                <small>One of five entries. Full file: <a href="<?= $experiment_repo ?>/blob/main/gotflashes_claude_impl/KNOWN-APP-ISSUES.md"><code>KNOWN-APP-ISSUES.md</code></a>.</small>
            </p>
            <p>
                Q3 didn't create the file at all. When Q3 hit blockers, it retried until the operator stepped in, covered in detail in <a href="#q3-self-direction">Q3's self-direction problem</a> below.
            </p>

            <p>
                <strong>Postscript: structured failure reporting led me to a solution Claude didn't see.</strong> When I sat down to plan closing out the remaining failures, I read through <code>KNOWN-APP-ISSUES.md</code> carefully (the password-reset token hashing entry, the rate-limit-state entry, the <code>SESSION_SECURE_COOKIE</code> entry), and partway through the list, it clicked: most of these are <em>Laravel-side test affordances</em> that <strong>Pest 4's new browser-testing primitives already provide</strong>. The conclusion didn't come from Claude. Claude didn't know about Pest 4 specifically; it didn't propose a framework migration as the fix. What it <em>did</em> do was lay the constraints out in a form clean enough that a better path became visible to me as a reader. The shape of Claude's report did more analytical work than its content. This is the underrated half of &ldquo;good failure documentation pays you back&rdquo;: sometimes the payoff isn't a fix the agent could write, it's a fix you can see <em>because</em> the agent wrote the constraints down well.
            </p>

            <h3 id="q3-self-direction" class="fw-bolder mb-3 mt-4">
                <a href="#q3-self-direction" class="text-reset text-decoration-none">Q3's self-direction problem: looping, then stopping short</a>
            </h3>
            <p>
                The back half of Q3's run exposed a self-monitoring gap: the agent couldn't tell when it had stopped making progress. It surfaced twice: once mid-run as a blind retry loop, and once at the very end as a premature stop.
            </p>
            <p>
                Mid-run (around step 83, about 90 minutes in), Q3 wrote a test for the multi-date logbook page that called <code>comp.set('dates', [d])</code> directly via <code>page.evaluate</code>. The <code>/logbook</code> route renders multiple Livewire components on the same page, and Q3's call routed to the wrong one (<code>email-verification-banner</code>), which has no <code>$dates</code> property: a clean HTTP 500 with a <code>PublicPropertyNotFoundException</code> in <code>storage/logs/laravel.log</code>.
            </p>
            <p>
                Q3 re-ran the failing test. Same error. Re-ran it again. Same error. <strong>The structured Laravel log accumulated the same exception 44 times.</strong> The agent's main loop went idle at step 83 with no fix applied and no recognition that it was looping.
            </p>
            <p>
                Notably, this isn't the model being incapable: Q3 found the right diagnosis the moment I handed it a hint. The failure is <em>self-monitoring</em>: Q3 treated each retry as fresh, reading the error, trying a tweak, failing, then treating the next attempt as a new problem. Nothing in its loop recognized &ldquo;I've seen this exact exception ten times in a row; my approach isn't converging.&rdquo; On a hands-off run, that meant I had to step in with the actual diagnosis (three sentences, one architectural hint), which unlocked another ~50 minutes of useful work before the next stall. So the operator-burden gap is bigger than the bare intervention count suggests: Q3's nudges weren't attention-cost &ldquo;continue&rdquo;s, they were <em>real diagnostic work</em>, reading logs, spotting the wrong-component routing, handing back the fix.
            </p>
            <p>
                The second instance showed up at the very end, and it wasn't obvious until I read the session export carefully. After the seventh and final nudge, Q3's last 50 minutes looked like this:
            </p>
            <figure class="my-4 image-modal-content">
                <?php echo responsiveImage(
                    '/img/blog/claude_vs_local/q3-opencode-session-end.png',
                    'standard',
                    "Q3's OpenCode TUI at session end, context bar showing 134.3k of 163k tokens used and a summary categorising the remaining failures by date-picker, TomSelect, profile, etc.",
                    'img-fluid mx-auto d-block rounded shadow-sm',
                    ['(min-width: 1140px) 1140px', '100vw']
                ); ?>
                <figcaption class="figure-caption text-center mt-2">Q3's OpenCode TUI at session end: 134.3k / 163k context (~82% full), final summary categorising the remaining failures, the &ldquo;I should wrap up&rdquo; framing the operator accepted as the stop signal.</figcaption>
            </figure>
            <ul>
                <li>Tightened its own Playwright timeout from 60 s to 30 s (a config change, not a fix).</li>
                <li>Re-ran the suite. New numbers: 74 passing, 52 failing, <strong>slightly worse than the in-session baseline of 75 / 51 it had ten minutes earlier</strong>. The shorter timeout pushed one passing test into the &ldquo;timeout&rdquo; bucket.</li>
                <li>Diagnosed the issue accurately: &ldquo;tests pass in isolation but time out in the full suite due to session expiry from the global-setup storage state becoming stale during the ~1.5 minute run.&rdquo;</li>
                <li>For the 16 date-picker tests: noted &ldquo;need the same <code>pickDates</code> + <code>waitForLivewireIdle</code> fix applied.&rdquo; Explicitly identified the fix, knew where to apply it, did not apply it.</li>
                <li>Wrote a status summary and stopped.</li>
            </ul>
            <p>
                That is not &ldquo;Q3 completed the run.&rdquo; Q3 made the suite marginally worse, identified what would fix its largest failure cluster, declined to apply that fix, and self-declared finished. The 74/52/14 result is <strong>Q3's self-chosen stopping point</strong>, not its best effort on the plan.
            </p>
            <p>
                Claude's run had no analogue. Its single operator intervention was a bare &ldquo;continue&rdquo; 2 h 14 m in: an unblock for a paused-but-not-stuck state, not a diagnosis for a misunderstood error. It ran longer, hit no compaction, committed 55 files with a delivery message, and documented in its 5-blocker <code>KNOWN-APP-ISSUES.md</code> exactly why each deferred test was deferred. Q3 looped, stopped short, and left no such record.
            </p>

            <h3 id="three-real-bugs" class="fw-bolder mb-3 mt-4">
                <a href="#three-real-bugs" class="text-reset text-decoration-none">What plan-driven testing can't reach: three real bugs neither suite caught</a>
            </h3>
            <p>
                I know three production bugs in this app that neither agent's tests would catch, and they sort into two kinds, the second of which plan-driven testing structurally can't reach.
            </p>
            <p>
                <strong>Bug 1: the automated database backup doesn't work.</strong> The app ships a <code>db:backup</code> artisan command and a Laravel scheduler entry. Backup is broken in production. <code>grep -niE 'backup' tests/</code> on either tree returns zero hits inside spec files.
            </p>
            <p>
                <strong>Bug 2: logging in after a long idle returns a &ldquo;Page Expired&rdquo; (HTTP 419) instead of authenticating cleanly.</strong> Laravel's default session and CSRF-token lifetime is 120 minutes; a login form left open longer than that has a stale <code>_token</code> value that Laravel rejects before evaluating the credentials. Neither arm tested the idle-expiry path.
            </p>
            <p>
                <strong>Bug 3: the CSV export and the leaderboard can credit the same year's flashes to different fleets.</strong> <code>ExportController</code> resolves a flash's club affiliation by <em>exact-year match</em> (no carry-forward); the leaderboard and <code>User::membershipForYear()</code> <em>carry forward</em> the most recent prior membership when a year has no row of its own. So for any sailor with such a gap, the same flashes can land in different fleets/districts on the export than on the leaderboard. Each path is individually &ldquo;correct&rdquo;; they just encode different answers to &ldquo;what fleet was this sailor in that year?&rdquo;
            </p>
            <p>
                All three are real defects; what differs is <em>why</em> the suites missed them. For Bugs 1 and 2 the miss is a <strong>documentation-chain gap</strong>, not an agent-capability gap: each agent tested what the plan named, and the plan was derived from the PRD. The backup command exists in the codebase but never made it into <code>docs/prd.md</code> or <code>CLAUDE.md</code>, so nothing pointed the plan at it; the idle-login &ldquo;Page Expired&rdquo; is a corner of a documented feature the PRD covers only at a high level. Neither miss is the model being dim: it's the model faithfully covering the inputs it was handed, and the bug wasn't in them. Bug 3 is a different kind of miss: a <strong>cross-feature consistency bug</strong>. Each feature behaves as documented in isolation; the defect lives in the <em>seam</em> between them, where two code paths answer the same question differently. No per-feature plan catches it, because no single feature is wrong: you'd only find it by asserting that export and leaderboard <em>agree</em>, which takes a human who suspects they might not.
            </p>
            <p>
                The broader takeaway: <strong>the chain &ldquo;plan &rarr; tests &rarr; coverage&rdquo; is only as strong as its weakest link</strong>, and that link is upstream of any model choice. Bugs 1 and 2 trace to a documentation chain that didn't carry the feature forward; Bug 3 shows the ceiling is lower still: some bugs live in the seams between features that are each individually correct and individually documented, and no plan built feature-by-feature thinks to test the seam. The Phase 2 experiment runs Claude vs Q3 on a level playing field; both arms got the same plan; the plan had these holes; both arms had them. The model swap fixes none of it.
            </p>
        </section>

        <hr class="my-5">

        <section>
            <h2 id="the-structural-gap" class="fw-bolder mb-4 mt-5">
                <a href="#the-structural-gap" class="text-reset text-decoration-none">The Structural Gap: Subagents and Parallelism</a>
            </h2>
            <p>
                The most under-appreciated difference between Claude Code and local agents isn't model quality; it's <strong>task delegation</strong>. During this experiment's Playwright planning task, the Claude Code (Opus 4.7) run spawned <strong>three subagents</strong> to research different parts of the codebase. The Qwen arms running through OpenCode could not do this; everything was one linear conversation in one context window. Two practical consequences:
            </p>
            <p>
                <strong>1. Subagents are effectively &ldquo;free&rdquo; context.</strong> When Claude spawns a subagent to investigate a directory or summarize a set of files, the subagent runs in its own fresh context window. Only the summary it returns counts against the main conversation. A planning task that pulls in 200k tokens of file content via three parallel subagent reads might leave the main context window holding only 5&ndash;10k tokens of summary. The user's effective working context is multiplied without changing the model or hardware.
            </p>
            <p>
                Locally, every file read goes into the same context budget. I watched OpenCode hit context compaction once on the 35B-A3B at 131k context after reading the same set of files, and compaction <em>summarizes prior context away</em>, losing detail. On a Claude subscription using subagents, the same exploration would barely move the main context's needle.
            </p>
            <p>
                <strong>2. Parallel task execution is real wall-clock savings.</strong> Three subagent researches in parallel finish in roughly the time of the slowest one, not the sum. On broad codebase exploration this can be a 3x speedup with no model change. A local rig can't even spin up a second OpenCode session without doubling VRAM, which a 24 GB GPU can't do for these model sizes.
            </p>
            <p>
                This is the gap that doesn't show up in benchmark charts. Claude isn't just smarter per token; it's a fundamentally different agent architecture. For workflows that can be decomposed into parallel subtasks (exploration, multi-file refactors, comparative research), the cloud lead is <em>structural</em>, not marginal. Local LLMs are competing on raw model capability with a hand tied behind their back.
            </p>
            <p>
                Not every cloud edge is a capability edge, though. Prompt caching, for instance, lets the cloud re-read its growing context at a fraction of the input price each turn, so long sessions stay cheap to <em>run</em>: a cost advantage, not a quality one. Locally it's moot: you don't pay per token, and LM Studio reuses the KV prefix for the same prefill benefit. Worth separating out, because unlike the subagent gap this one says nothing about which model writes the better plan.
            </p>
            <div class="agentic-tip">
                <p>
                    <strong>Agentic-coding tip: parallel agents need a parallel dev setup.</strong> The flip side of all that parallelism: the moment you run two agents at once (two sessions, or a cloud arm and a local arm side by side), they collide on the single-tenant assumptions your dev box was built on. Here, both implementers defaulted their Playwright <code>webServer</code> to <code>php artisan serve</code> on <code>127.0.0.1:8000</code>; the second silently failed to bind, every test failed on navigation, and the agent, with no way to see the real cause, started &ldquo;fixing&rdquo; a Playwright config that was never wrong. Give each agent its own port, database, and scratch directory up front (I now pin <code>--port=8001</code> / <code>8002</code> in the prompt). Anything two agents share, they will eventually fight over.
                </p>
            </div>
        </section>

        <section>
            <h2 id="should-you-go-local" class="fw-bolder mb-4 mt-5">
                <a href="#should-you-go-local" class="text-reset text-decoration-none">Should you go local?</a>
            </h2>

            <figure class="radar-row radar-compare">
                <?php
                // Decision tradeoff — unlike the grade-gap radars, these axes cross: Claude
                // wins quality/parallelism/speed, local wins privacy/availability/control. The
                // shapes are opinionated and qualitative (this whole section is a judgment call),
                // ordered so each side's strengths cluster into one lobe.
                $decision_axes = ['Quality', 'Parallelism', 'Speed', 'Privacy', 'Availability', 'Control'];
                $radar2 = render_radar_overlay($decision_axes, [
                    ['name' => 'Local (Qwen 27B Q3)', 'variant' => 'qwen',
                     'scores' => ['Quality' => 5, 'Parallelism' => 2, 'Speed' => 4, 'Privacy' => 10, 'Availability' => 9, 'Control' => 9]],
                    ['name' => 'Claude Code', 'variant' => 'claude',
                     'scores' => ['Quality' => 10, 'Parallelism' => 10, 'Speed' => 9, 'Privacy' => 2, 'Availability' => 5, 'Control' => 4]],
                ]);
                echo $radar2['svg'];
                echo $radar2['legend'];
                ?>
                <figcaption class="text-muted">
                    <small>The actual decision, as a shape: Claude owns quality, parallelism and speed; local owns privacy, availability (offline / no rate limits) and control (own the stack, tinker, no external dependency). Unlike the grading radars above, these axes <em>cross</em>. The privacy / availability / control side is genuinely &ldquo;which corner you value,&rdquo; but Quality and Parallelism aren't a wash: there it's plainly &ldquo;who's better,&rdquo; and it's Claude. Opinionated and qualitative, not measured. Speed folds in operator burden; cost cuts both ways depending on how much you run, so it isn't a clean axis here.</small>
                </figcaption>
            </figure>

            <p>
                Read the shape honestly: going local means accepting a real step down in coding capability (and the hard structural ceiling of one model, one session, no subagents) in exchange for privacy, control, and independence from anyone else's infrastructure. With that trade on the table:
            </p>
            <p><strong>Go local if:</strong></p>
            <ul>
                <li>You have strict privacy or air-gap requirements where code-leaves-machine is non-negotiable</li>
                <li>You enjoy tinkering with inference optimization (the VRAM-vs-context-vs-quant trilemma is genuinely interesting)</li>
                <li>You want zero dependency on external services (rate limits, outages, billing)</li>
                <li>You're comfortable with the <strong>structural gap</strong>: no parallel subagents, no parallel sessions, one model at a time, one conversation at a time</li>
                <li>The Q3-style &ldquo;best Qwen at the task&rdquo; outcome (~6.3 on a 10-scale rubric, second-tier failure modes) is acceptable for your work</li>
            </ul>
            <p><strong>Stick with Claude Code if:</strong></p>
            <ul>
                <li>You want the best coding quality available: the grade gap is real, not marginal</li>
                <li>You value your time. The hour spent debugging VRAM pressure is gone forever</li>
                <li>You need concurrent sessions, parallel subagents, or any kind of context-multiplication via delegation</li>
                <li>You're working on multi-file refactors where the agent needs to hold multiple files in mind simultaneously: Claude's subagent architecture handles this; locals can't</li>
            </ul>
            <p>
                <strong>One note on cost, since the radar leaves it out.</strong> Priced at Anthropic's published Opus rates &mdash; the 1M-context window bills this experiment's many 200K+ token turns at the long-context premium &mdash; the deduplicated token usage (via <code>ccusage</code>) puts the Claude side at roughly <strong>$300 of API spend</strong> (about $175 before that premium), nearly all of it cache reads re-processing the growing context. (<code>ccusage</code>'s own total comes out far lower, ~$55, because it has no list price for this model and falls back; $300 is the figure at Opus's actual rates.) That's about three months of the $100/month subscription I pay, so for anything past very occasional use the subscription is the cheaper path, and metered API only wins if you'd run this a couple of times a year. Either way, that's no reason for an individual to buy a $2,000 GPU; Claude is cheap at this cadence, and local was never the cost play. The economics only flip at the other end: enterprises don't get the Max plan, so a team is on metered API or an enterprise contract where that per-run cost is live and compounds with volume &mdash; and a privacy- or compliance-bound org already eyeing self-hosting is who the $0-per-run rig genuinely pencils out for.
            </p>
            <p>
                <strong>The practical hybrid workflow</strong> for those with the hardware: run Claude Code for serious agentic work (planning, multi-file refactors, agentic test generation), and reach for local when you specifically need offline / privacy / no-cloud-dependency on a focused single-task session. For that local arm, the rule that falls out of this experiment is simple: <strong>run the biggest <em>dense</em> model you can fit while still leaving enough context to finish the task without compacting.</strong> Density is what buys reasoning (every parameter active on every token), and the context headroom is what keeps a long run from tripping the compaction that quietly costs you capability. On 24 GB that points straight at the dense 27B at a quant that clears ~160k context, the Q3 config this experiment kept coming back to. The 35B-A3B MoE is for fast scratch work only (wide context, lower per-spec accuracy), and the 9B is a pure long-context fallback you'll discard a lot of.
            </p>
        </section>

        <section>
            <h2 id="conclusion" class="fw-bolder mb-4 mt-5">
                <a href="#conclusion" class="text-reset text-decoration-none">Conclusion: impressive, not yet a daily driver</a>
            </h2>
            <p>
                The hypothesis I opened with (<em>plan quality predicts implementation quality</em>) held: the model that wrote the best plan wrote the cleanest implementation, and Q3, the strongest of the local plans, was the strongest of the local implementers. Phase 1's cheap signal was a real one, and the cheapest practical takeaway in the experiment falls out of it: to size up a model for your own codebase, read the plan it writes before committing hours to an implementation that will inherit the same flaws. But the headline is the bigger question the whole experiment was chasing, and the honest answer is two-sided: <strong>what a single 24 GB card buys you for agentic coding is genuinely impressive, and not yet a daily driver against the cloud.</strong>
            </p>
            <p>
                The impressive half first. Q3 took Claude's plan and turned it into a working Playwright suite (74 passing tests) in <strong>2 h 39 min of agent-only compute</strong>, on the same GPU that was driving my monitors. And its output isn't a worse-shaped Claude implementation; it's a <em>different</em> shape: more Playwright-idiomatic in its selectors, more invested in domain helpers, just lower in coverage and looser on discipline. A year ago I wouldn't have bet on a local model getting this close on a real codebase.
            </p>
            <p>
                The &ldquo;not yet&rdquo; lives in four things the post built up. <strong>Constraint compliance:</strong> Q3 ignored an explicit, prompt-level rule 32 times, and that's the un-fixable kind, because a line in <code>AGENTS.md</code> only steers a model that follows the rules it's already given. <strong>Trust:</strong> some of its passing tests pass on the day they were written, not in general: latent date bugs, and fixes it found but never generalized. <strong>Self-direction:</strong> it looped on one error 44 times, stopped short of a fix it had already identified, and cost seven operator interventions getting there. And the <strong>structural gap:</strong> no subagents, no parallel sessions, so it competes on raw model capability with a hand tied behind its back.
            </p>
            <p>
                That's why the hybrid (cloud to plan, local to execute) only half works. You get a suite at the end, real coverage for a few hours of local compute; you don't get something you'd ship without a full review pass. And the parts that <em>are</em> broken can take longer to hunt down than they'd have taken to write right. It's a real workflow for a focused, private, single-task session, not a hands-off one.
            </p>
            <p>
                And if there's one transferable lesson from running five arms at the same task, it's that the artifact worth carrying forward was never any single plan: it's the <strong>synthesis</strong>. When you compare models on real work, the deliverable is rarely the best single output; it's what you get from stitching the wins together.
            </p>
            <p>
                I opened the whole thing by saying this isn't a cost play, and the numbers bear that out: the gap to Claude is real, and on a dev machine you pay for it in VRAM math and debugging time. But that was never the point for me: I wanted to know whether the self-hosted setup I enjoy tinkering with is <em>viable</em> for real work, and the two-sided answer above is a better one than I'd have bet on a year ago. The trend line is the encouraging part. The whole experiment (the five plans, both test trees, the grading, the raw session logs, warts and all) is in the <a href="<?= $experiment_repo ?>">companion repo</a> if you want to run your own.
            </p>
        </section>

        <?php include("includes/say_hello.html") ?>
    </article>
</div>

<?php include "includes/image_modal.php"; ?>
<?php include "includes/footer.php"; ?>
