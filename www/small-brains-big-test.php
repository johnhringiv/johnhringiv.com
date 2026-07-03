<?php
require_once "includes/classes.php";

// ── Load CISSP benchmark data from SQLite ────────────────────────────────────
$cissp_models_json = 'null';
$cissp_per_question_json = 'null';
$cissp_agreement_json = 'null';

try {
    $db = SiteDB::get();
    if ($db) {

        // Query 1: per-run aggregated data (CISSP_MODELS)
        $result = $db->query(<<<SQL
            SELECT
                r.id,
                r.model_key,
                r.model_family,
                r.params_effective,
                r.params_total,
                r.quantization,
                r.display_name,
                r.short_name,
                r.accuracy,
                r.total_time_sec,
                r.avg_tokens_per_sec,
                r.peak_vram_mb,
                r.avg_vram_mb,
                r.total_eval_tokens,
                r.total_prompt_tokens
            FROM runs r
            ORDER BY r.model_family, r.params_effective, r.model_key
        SQL);

        $models_raw = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $models_raw[$row['id']] = [
                'id'               => (int)$row['id'],
                'key'              => $row['model_key'],
                'family'           => $row['model_family'],
                'params_eff'       => (float)$row['params_effective'],
                'params_total'     => (float)$row['params_total'],
                'quantization'     => $row['quantization'],
                'display_name'     => $row['display_name'],
                'short_name'       => $row['short_name'],
                'accuracy'         => (float)$row['accuracy'],
                'total_time_sec'   => (float)$row['total_time_sec'],
                'avg_tokens_per_sec' => (float)$row['avg_tokens_per_sec'],
                'peak_vram_mb'     => $row['peak_vram_mb'] !== null ? (float)$row['peak_vram_mb'] : null,
                'avg_vram_mb'      => $row['avg_vram_mb']   !== null ? (float)$row['avg_vram_mb']  : null,
                'total_eval_tokens'    => $row['total_eval_tokens']    !== null ? (int)$row['total_eval_tokens']    : null,
                'total_prompt_tokens'  => $row['total_prompt_tokens']  !== null ? (int)$row['total_prompt_tokens']  : null,
                'domain_accuracy'  => [],
            ];
        }

        // Domain accuracy
        if (!empty($models_raw)) {
            $da_result = $db->query('SELECT run_id, domain, accuracy FROM domain_accuracy');
            while ($row = $da_result->fetchArray(SQLITE3_ASSOC)) {
                $rid = (int)$row['run_id'];
                if (isset($models_raw[$rid])) {
                    $models_raw[$rid]['domain_accuracy'][(int)$row['domain']] = (float)$row['accuracy'];
                }
            }
        }

        $cissp_models_json = json_encode(array_values($models_raw), JSON_THROW_ON_ERROR);

        // Query 2: per-question results shaped as {qid, domain, results:{key:0|1,...}}
        // Only FP16 runs — DifficultyChart and AgreementMatrix both filter to FP16 keys
        $qr_result = $db->query(<<<SQL
            SELECT qr.question_id, qr.domain, qr.correct, r.model_key
            FROM question_results qr
            JOIN runs r ON r.id = qr.run_id
            WHERE r.quantization = 'fp16'
            ORDER BY qr.question_id
        SQL);

        $questions = [];
        while ($row = $qr_result->fetchArray(SQLITE3_ASSOC)) {
            $qid = $row['question_id'];
            if (!isset($questions[$qid])) {
                $questions[$qid] = [
                    'qid'     => $qid,
                    'domain'  => (int)$row['domain'],
                    'results' => [],
                ];
            }
            $questions[$qid]['results'][$row['model_key']] = $row['correct'] ? 1 : 0;
        }

        $cissp_per_question_json = json_encode(array_values($questions), JSON_THROW_ON_ERROR);

        // Query 3a: per-model wrong counts (FP16 only)
        $wrong_result = $db->query(<<<SQL
            SELECT r.model_key, COUNT(*) as wrong_count
            FROM question_results qr
            JOIN runs r ON r.id = qr.run_id
            WHERE r.quantization = 'fp16' AND qr.correct = 0
            GROUP BY r.model_key
        SQL);

        $agreement_wrong = [];
        while ($row = $wrong_result->fetchArray(SQLITE3_ASSOC)) {
            $agreement_wrong[$row['model_key']] = (int)$row['wrong_count'];
        }

        // Query 3b: pairwise both-wrong counts (FP16 only, key_a < key_b)
        $pair_result = $db->query(<<<SQL
            SELECT ra.model_key as key_a, rb.model_key as key_b, COUNT(*) as both_wrong
            FROM question_results qa
            JOIN runs ra ON ra.id = qa.run_id AND ra.quantization = 'fp16' AND qa.correct = 0
            JOIN question_results qb
                ON qb.question_id = qa.question_id AND qb.correct = 0
            JOIN runs rb ON rb.id = qb.run_id AND rb.quantization = 'fp16'
            WHERE ra.model_key < rb.model_key
            GROUP BY ra.model_key, rb.model_key
        SQL);

        $agreement_pairs = [];
        while ($row = $pair_result->fetchArray(SQLITE3_ASSOC)) {
            $agreement_pairs[$row['key_a'] . '|' . $row['key_b']] = (int)$row['both_wrong'];
        }

        $cissp_agreement_json = json_encode([
            'wrong_counts' => $agreement_wrong,
            'pairs'        => $agreement_pairs,
        ], JSON_THROW_ON_ERROR);

    }
} catch (Exception $e) {
    // Charts will skip silently if data unavailable
    error_log('CISSP DB error: ' . $e->getMessage());
}

$page_info = PageInfo::fromDB('small-brains-big-test');
include_once "includes/top.php";
?>
    <div class="container blog-post pb-2">
        <article>
            <?php $page_info->renderFullHeader(); ?>

            <?php echo $page_info->html_description ?>

            <section>
                <h2 id="why-small-models" class="fw-bolder mb-4 mt-5">
                    <a href="#why-small-models" class="text-reset text-decoration-none">Why Small Models Matter</a>
                </h2>
                <p>
                    Frontier models require an estimated 20x&ndash;50x the computation of an 8B model that runs
                    locally. That's not a cost problem you can throw money at. For workloads like real-time log
                    analysis or high-throughput classification, the math doesn't work regardless of budget. Faster
                    hardware helps, but a 20x compute multiplier doesn't shrink with a better GPU; it shrinks
                    with a smaller model.
                </p>
                <p>
                    Most enterprises aren't there yet. The current priority is adoption and enablement, and
                    frontier API calls are the path of least resistance. But the workloads that actually need
                    AI at scale, private deployments, edge compute, high-volume inference, will require smaller,
                    purpose-built models running on local hardware.
                </p>
                <p>
                    Here I benchmark sixteen popular small open-weight models, assess their capabilities, and
                    evaluate how they hold up when quantized for constrained hardware. All of it runs on my
                    personal desktop.
                </p>
            </section>

            <section>
                <h2 id="why-the-cissp" class="fw-bolder mb-4 mt-5">
                    <a href="#why-the-cissp" class="text-reset text-decoration-none">Why the CISSP?</a>
                </h2>
                <p>
                    The CISSP (Certified Information Systems Security Professional) is a legitimate benchmark.
                    It's a notoriously broad and difficult exam: eight domains spanning risk management, cryptography,
                    identity systems, secure development, and more. I wouldn't go as far as to say passing the CISSP
                    qualifies a model for a security task, but it's a meaningful indicator in that direction.
                </p>
                <p>
                    This post establishes the baseline: how well do popular small open-weight models perform on CISSP
                    questions, zero-shot, with no fine-tuning? The answer will tell us which models are worth investing
                    fine-tuning compute in, and how much room there is to improve.
                </p>
            </section>

            <section>
                <h2 id="model-selection" class="fw-bolder mb-4 mt-5">
                    <a href="#model-selection" class="text-reset text-decoration-none">Model Selection</a>
                </h2>
                <p>I limited my search to models fitting the following criteria:</p>
                <ul>
                    <li>Open-weight</li>
                    <li>8 billion parameters or fewer</li>
                    <li>Popular first-party base models (no community fine-tunes)</li>
                    <li>Available officially through Ollama (for convenience)</li>
                </ul>
                <p>
                    An exception was made for SmolLM3, which was imported from HuggingFace GGUF. As the only
                    open-data model on the list, it felt important to include.
                </p>
                <p>
                    An exception was also made for DeepSeek R1 8B, which is a fine-tuned variant of Qwen3 rather
                    than an independent base model. It was included because it is a highly-popular officially released
                    DeepSeek model available through Ollama.
                </p>
                <p>
                    The hard cap of 8B parameters was set with future fine-tuning and NPU deployment in mind.
                    Models up to 12B would likely fit on my 4090 at full precision, but would be difficult
                    or inviable for future plans.
                </p>
                <p>
                    After filtering, sixteen models spanning eight families and three size classes (1–2B, 3–4B,
                    7–8B) made the cut.
                </p>
                <div class="table-responsive cissp-table-wrapper">
                    <table id="cissp-table-models">
                        <caption>Click any row to include or exclude that model from the charts and tables below.</caption>
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Params</th>
                                <th>Provider</th>
                                <th>License</th>
                                <th>Training Cutoff</th>
                                <th>Released</th>
                                <th>Data Risk†</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-base="llama3.1_8b-instruct"><td>Llama 3.1 8B Instruct</td><td>8B</td><td>Meta</td><td>Llama 3.1</td><td>Dec 2023</td><td>Jul 2024</td><td>Clean</td></tr>
                            <tr data-base="llama3.2_3b-instruct"><td>Llama 3.2 3B Instruct</td><td>3B</td><td>Meta</td><td>Llama 3.2</td><td>Dec 2023</td><td>Sep 2024</td><td>Clean</td></tr>
                            <tr data-base="llama3.2_1b-instruct"><td>Llama 3.2 1B Instruct</td><td>1B</td><td>Meta</td><td>Llama 3.2</td><td>Dec 2023</td><td>Sep 2024</td><td>Clean</td></tr>
                            <tr data-base="gemma3_4b-it"><td>Gemma 3 4B IT</td><td>4B</td><td>Google</td><td>Gemma</td><td>Aug 2024</td><td>Mar 2025</td><td>Possible</td></tr>
                            <tr data-base="gemma3_1b-it"><td>Gemma 3 1B IT</td><td>1B</td><td>Google</td><td>Gemma</td><td>Aug 2024</td><td>Mar 2025</td><td>Possible</td></tr>
                            <tr data-base="gemma3n_e4b-it"><td>Gemma 3n E4B IT</td><td>8B/4B*</td><td>Google</td><td>Gemma</td><td>Jun 2024</td><td>Jun 2025</td><td>Borderline</td></tr>
                            <tr data-base="gemma3n_e2b-it"><td>Gemma 3n E2B IT</td><td>6B/2B*</td><td>Google</td><td>Gemma</td><td>Jun 2024</td><td>Jun 2025</td><td>Borderline</td></tr>
                            <tr data-base="phi4-mini_3.8b"><td>Phi-4 Mini</td><td>3.8B</td><td>Microsoft</td><td>MIT</td><td>Jun 2024</td><td>Feb 2025</td><td>Borderline</td></tr>
                            <tr data-base="mistral_7b-instruct"><td>Mistral 7B v0.3 Instruct</td><td>7B</td><td>Mistral AI</td><td>Apache 2.0</td><td>Undisclosed</td><td>May 2024</td><td>Clean</td></tr>
                            <tr data-base="ministral-3_8b-instruct-2512"><td>Ministral 3 8B Instruct</td><td>8B</td><td>Mistral AI</td><td>Apache 2.0</td><td>Undisclosed</td><td>Dec 2025</td><td>Unknown</td></tr>
                            <tr data-base="ministral-3_3b-instruct-2512"><td>Ministral 3 3B Instruct</td><td>3B</td><td>Mistral AI</td><td>Apache 2.0</td><td>Undisclosed</td><td>Dec 2025</td><td>Unknown</td></tr>
                            <tr data-base="deepseek-r1_8b-0528-qwen3"><td>DeepSeek R1 8B (Qwen3)</td><td>8B</td><td>DeepSeek</td><td>MIT</td><td>Undisclosed</td><td>May 2025</td><td>Unknown</td></tr>
                            <tr data-base="qwen3_8b"><td>Qwen3 8B</td><td>8B</td><td>Alibaba</td><td>Apache 2.0</td><td>Undisclosed</td><td>Apr 2025</td><td>Unknown</td></tr>
                            <tr data-base="qwen3_4b"><td>Qwen3 4B</td><td>4B</td><td>Alibaba</td><td>Apache 2.0</td><td>Undisclosed</td><td>Apr 2025</td><td>Unknown</td></tr>
                            <tr data-base="qwen3_1.7b"><td>Qwen3 1.7B</td><td>1.7B</td><td>Alibaba</td><td>Apache 2.0</td><td>Undisclosed</td><td>Apr 2025</td><td>Unknown</td></tr>
                            <tr data-base="smollm3_3b"><td>SmolLM3 3B</td><td>3B</td><td>HuggingFace</td><td>Apache 2.0</td><td>Jun 2025</td><td>Jul 2025</td><td>Documented</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="cissp-table-notes">
                    <p>*Gemma 3n uses selective parameter activation (MatFormer): total parameters are 8B (E4B) and 6B (E2B),
                    but only 4B/2B are active at runtime. Memory footprint reflects the full parameter count.</p>
                    <p>†Data risk relative to ISC2 Practice Tests publication (June 2024), see <a href="#contamination">The Contamination Question</a>.</p>
                </div>

                <h3 id="notable-exclusions" class="fw-bolder mb-3 mt-4">
                    <a href="#notable-exclusions" class="text-reset text-decoration-none">Notable Exclusions</a>
                </h3>
                <p>
                    Two candidates were excluded for producing unstructured output, a problem that would follow them
                    into any inference pipeline. <strong>Phi-4 Mini Reasoning</strong> ignores <code>think: false</code>
                    and spends its entire token budget on chain-of-thought before producing an answer letter.
                    The older <strong>DeepSeek R1 Qwen distills</strong> (7B and 1.5B, January 2025) emit verbose
                    multi-paragraph explanations rather than concise answers, averaging 54–98 tokens per question
                    versus ~2 for well-behaved models, with the 1.5B hitting the output cap on 29% of questions.
                    The newer R1 8B (May 2025, Qwen3-based) follows concise output instructions correctly and is included.
                </p>

                <h3 id="quantization-levels" class="fw-bolder mb-3 mt-4">
                    <a href="#quantization-levels" class="text-reset text-decoration-none">Quantization Levels</a>
                </h3>
                <p class="lead shadow-sm py-2 ps-2 rounded-3">
                    <b class="fs-5">Quantization:</b> Reducing model weight precision from 16-bit floats (FP16)
                    to lower bit-widths (ex: Q8_0) to shrink memory footprint and improve inference speed,
                    at some cost to accuracy.
                </p>
                <div class="test-info small">
                    <b>Reading the notation:</b> The number is the bit-width. <code>_0</code> denotes
                    simple uniform quantization. <code>_K</code> denotes K-quants, importance-aware quantization
                    that selectively preserves higher precision for weights that matter most. The trailing
                    <code>_M</code> (medium) and <code>_S</code> (small) indicate the super-block size variant;
                    <code>_M</code> typically preserves slightly more accuracy at a marginally larger file size.
                </div>
                <p>
                    Every model was evaluated at FP16, Q8_0, and Q4_K_M. Llama 3.x and Mistral 7B also have
                    Q6_K, Q5_K_M, Q5_K_S, and Q4_K_S available in Ollama's library, allowing a finer-grained view of the accuracy-vs-compression curve.
                </p>
                <p>
                    All models use publisher-provided GGUF files converted from BF16, a training optimized representation, to FP16 weights.
                </p>

                <h3 id="security-alignment-warning" class="fw-bolder mb-3 mt-4">
                    <a href="#security-alignment-warning" class="text-reset text-decoration-none">Security and Alignment Warning: Qwen and DeepSeek</a>
                </h3>
                <div class="test-fail">
                    <strong>The Qwen and DeepSeek model families have numerous and severe security risks and are not recommended for use.</strong>
                    They are included due to their popularity.
                </div>
                <p>
                    Both families feature robust censorship aligned with the Chinese Communist Party baked directly into
                    the model weights. Any deployment should consider the direct negative impact of this censorship as
                    well as the risk of additional unknown alignment directives.
                </p>
                <p>
                    The DeepSeek iOS app is effectively spyware, providing ample justification to treat all of their
                    products as poisoned. While hiding backdoors or malware directly in model weights would be difficult,
                    it is not impossible for a sophisticated state-sponsored adversary.
                </p>
                <p>
                    Both models are evaluated here on locally-hosted GGUF weights via Ollama.
                    Their results are reported honestly, including the high accuracy scores, which may owe more to
                    training data contamination than architectural merit. They are included for completeness of the
                    capability comparison. Again, I strongly recommend these models not be used for production
                    deployments, especially in security-adjacent workloads.
                </p>
            </section>

            <section>
                <h2 id="methodology" class="fw-bolder mb-4 mt-5">
                    <a href="#methodology" class="text-reset text-decoration-none">Methodology</a>
                </h2>
                <p>
                    Extraction of the evaluation set and creation of the test harness were <s>vibe coded</s>
                    engineered with Claude Code.
                </p>

                <h3 id="evaluation-set" class="fw-bolder mb-3 mt-4">
                    <a href="#evaluation-set" class="text-reset text-decoration-none">ISC2 Official CISSP Practice Tests (2024)</a>
                </h3>
                <p>
                    The evaluation set is the 2024 ISC2 Official CISSP Practice Tests: 1,306 questions covering all eight
                    CISSP domains.
                </p>
                <p>
                    Preparing the dataset required significant cleanup. 53 image-dependent questions were recovered by
                    incorporating visual content as ASCII diagrams, Mermaid flowcharts, or inline text. Errors touching
                    roughly 30% of the dataset required manual or AI-augmented cleanup. The final dataset was verified
                    through multiple independent passes: automated mismatch detection, source cross-referencing, and
                    post-evaluation analysis where questions that every model answered incorrectly were investigated
                    individually to distinguish genuinely hard questions from data errors. Even with Claude's help
                    automating the detection and cross-referencing, verification took over a full work day.
                </p>
                <p>
                    After filtering out 3 remaining image-dependent questions, I arrive at <strong>1,303 evaluation questions</strong>:
                </p>
                <ul>
                    <li><strong>803 domain-tagged questions</strong> from chapters covering individual domains (Domains 1–8)</li>
                    <li><strong>500 practice test questions</strong> from mixed-domain practice exams (chapters 9–12), tagged as domain "Unspecified"</li>
                </ul>
                <p>
                    This includes all three question types: 1,246 single-answer, 44 multi-select, and 16 matching questions.
                    The matching questions, which ask the model to pair numbered items with lettered descriptions, are
                    included in scoring and reveal an interesting failure mode discussed below.
                </p>
                <div class="table-responsive cissp-table-wrapper">
                    <table id="cissp-table-domains">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th title="Percentage of the real CISSP exam allocated to this domain">Exam Weight</th>
                                <th>Questions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>1. Security and Risk Management</td><td>15%</td><td>100</td></tr>
                            <tr><td>2. Asset Security</td><td>10%</td><td>105</td></tr>
                            <tr><td>3. Security Architecture and Engineering</td><td>13%</td><td>101</td></tr>
                            <tr><td>4. Communication and Network Security</td><td>13%</td><td>100</td></tr>
                            <tr><td>5. Identity and Access Management</td><td>13%</td><td>100</td></tr>
                            <tr><td>6. Security Assessment and Testing</td><td>12%</td><td>100</td></tr>
                            <tr><td>7. Security Operations</td><td>13%</td><td>100</td></tr>
                            <tr><td>8. Software Development Security</td><td>11%</td><td>100</td></tr>
                            <tr><td>Practice Tests (mixed)</td><td>—</td><td>500</td></tr>
                            <tr class="fw-bold"><td>Total (after filtering 3 image-dependent)</td><td></td><td>1,303</td></tr>
                        </tbody>
                    </table>
                </div>

                <h3 id="harness-scoring" class="fw-bolder mb-3 mt-4">
                    <a href="#harness-scoring" class="text-reset text-decoration-none">Harness &amp; Scoring</a>
                </h3>
                <p>
                    Each model was prompted with a standardized format. For single-answer questions (95% of the set):
                </p>
                <?php include "generated/highlighted-shiki/small-brains-big-test/prompt.html"; ?>
                <p>
                    Multi-select questions used adapted instructions asking for comma-separated letters.
                </p>
                <p><strong>Key evaluation parameters:</strong></p>
                <ul>
                    <li><strong>Ollama API</strong> with <code>temperature=0.0</code> (deterministic) and <code>num_predict=150</code> (short answers)</li>
                    <li><strong>Answer extraction</strong> via regex with multiple fallback patterns, confirmed with LLM-as-judge validation and human review of disagreements</li>
                    <li><strong>Scoring</strong>: exact match for single-answer, exact set match for multi-select (no partial credit)</li>
                    <li><strong>VRAM monitoring</strong> via <code>nvidia-smi</code> polling during inference</li>
                    <li><strong>Hardware</strong>: NVIDIA RTX 4090 (24GB), Ollama running natively on Windows, harness calling localhost:11434</li>
                </ul>
                <p>
                    Results below are from a single trial run. A second full trial confirmed that accuracy is perfectly
                    reproducible at temperature 0: every model returned <mark>identical scores across both runs</mark>, with the sole
                    exception of the Ministral family, which showed minor non-determinism.
                    Performance metrics (tok/s, wall time) varied slightly between runs depending on background system load;
                    I report numbers from the cleaner of the two runs. The two trials totaled roughly 7 hours of GPU compute time.
                </p>

            </section>

            <section>
                <h2 id="fp16-baseline" class="fw-bolder mb-4 mt-5">
                    <a href="#fp16-baseline" class="text-reset text-decoration-none">Results: The FP16 Baseline</a>
                </h2>
                <p>
                    The primary benchmark runs every model at FP16 with no quantization to establish the baseline.
                    This measures each model's true knowledge ceiling before compression artifacts enter the picture.
                    Use the precision selector to compare Q8_0 and Q4_K_M results in the same table; the tier analysis below uses FP16 as the reference.
                </p>
                <div class="table-responsive">
                    <table id="cissp-table-baseline">
                        <caption>Row shading reflects accuracy.<br><small>*Total/active params</small></caption>
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Params</th>
                                <th>Accuracy</th>
                                <th>Correct / 1,303</th>
                                <th>VRAM (GB)</th>
                                <th>Tokens/sec</th>
                                <th>Wall (min)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p>
                    Random guessing on four-option multiple choice gives 25%. With that baseline in mind, the results
                    split into clear tiers:
                </p>
                <p>
                    <strong>Tier 1: 75–82% (undisclosed training cutoffs).</strong> Ministral 3 8B leads at 81.9%,
                    followed closely by Qwen3 8B at 80.8%. DeepSeek R1 8B and Qwen3 4B round out this tier at 76–77%.
                    These are the models with undisclosed training cutoffs released in 2025, more on this in a moment.
                </p>
                <p>
                    <strong>Tier 2: 70–75%.</strong> Llama 3.1 8B is the clean anchor at 72.8% with a definitively
                    pre-test training cutoff (December 2023). Ministral 3 3B outscores it at 74.5% but carries the same
                    undisclosed-cutoff caveat as its 8B sibling. Gemma 3n E4B (74.3%) and E2B (71.0%) are borderline:
                    their June 2024 cutoff lands right around the book's publication date. Gemma 3 4B rounds out the
                    tier in the low 70s.
                </p>
                <p>
                    <strong>Tier 3: 59–69%.</strong> Qwen3 1.7B is the standout here: 69.0% from just 1.7B parameters
                    is remarkable. Phi-4 Mini (65.1%) and Llama 3.2 3B (63.5%) round out the viable middle. SmolLM3 3B
                    (63.5%) lands at the bottom of this tier, consistent with expectations for a 3B model trained on
                    open datasets (FineWeb-Edu) rather than domain-specific technical material.
                </p>
                <p>
                    <strong>Tier 4: Below 55%.</strong> Mistral 7B v0.3 at 49.6% is the biggest disappointment in the
                    lineup. A 7B model that scores below a coin flip and gets outperformed by Qwen3 1.7B, a model with
                    4x fewer parameters. It's hard to justify 7B of VRAM for performance that a 1.7B model beats by
                    20 points. Gemma 3 1B at 45.8% is respectable for 1B parameters but not useful out of the box.
                </p>
                <p>
                    <strong>Below viable.</strong> Llama 3.2 1B (25.0%) is statistically indistinguishable from random guessing.
                </p>
            </section>

            <section>
                <h2 id="contamination" class="fw-bolder mb-4 mt-5">
                    <a href="#contamination" class="text-reset text-decoration-none">The Contamination Question</a>
                </h2>
                <p>
                    Remember those training cutoff dates? The ISC2 Official Practice Tests were published in
                    <strong>June 2024</strong>. That date defines the Data Risk column in the model table above:
                </p>
                <ul>
                    <li><strong>Clean</strong>: cutoff definitively predates the book</li>
                    <li><strong>Borderline</strong>: cutoff at or near publication date</li>
                    <li><strong>Possible</strong>: cutoff after publication</li>
                    <li><strong>Documented</strong>: post-publication but full training recipe published</li>
                    <li><strong>Unknown</strong>: cutoff undisclosed</li>
                </ul>
                <p>
                    The pattern is hard to ignore. Controlling for size class, models with confirmed pre-June-2024 cutoffs
                    top out at 72.8% (Llama 3.1 8B) and 74.3% (Gemma 3n E4B). Models with undisclosed cutoffs in the same
                    size class jump to 77–82%.
                </p>
                <p>
                    <strong>SmolLM3 is an interesting control.</strong> Its June 2025 cutoff is well after the book's
                    publication, but HuggingFace documents the full training mixture: FineWeb-Edu (70%), Stack-Edu-Python
                    (20%), and FineMath (10%): curated web content scored for educational quality, not book corpora. CISSP
                    study material from websites, forums, and Quizlet pages could easily appear in FineWeb-Edu, but the
                    questions themselves may not. SmolLM3's 63.5% at 3B matches Llama 3.2 3B (63.5%, Dec 2023 cutoff)
                    almost exactly, reinforcing that genuine 3B capability without contamination lands in the low 60s.
                </p>
                <p>
                    The cleanest high score is <strong>Gemma 3n E4B at 74.3%</strong> with a June 2024 cutoff, where
                    training data collection almost certainly predates the book's publication date. <strong>Llama 3.1 8B at 72.8%</strong>
                    with a December 2023 cutoff is definitively clean. These two models, from different providers and
                    architectures, landing within 1.5 points of each other suggests that <mark>~73–74% is the genuine
                    capability ceiling for 4–8B models on this material without contamination</mark>.
                </p>
                <p>
                    Advancements in this field happen rapidly, and newer architectures do tend to score higher across
                    the board. But a consistent 8-point jump that tracks perfectly with undisclosed training cutoffs
                    and late release dates is still a large delta to explain by architecture alone.
                </p>
                <p>
                    This isn't just a CISSP finding: it's a concrete example of how training data contamination inflates
                    LLM benchmark scores when the benchmark is a published book. The training cutoff dates turned this
                    benchmark into an inadvertent contamination study.
                </p>
                <p>
                    <strong>For my purposes, it doesn't change the plan.</strong> I'm tinkering, not publishing a
                    leaderboard. But it does mean that fine-tuning improvements should be measured against the clean
                    baselines (Llama 3.1 8B and Gemma 3n E4B), not against potentially contaminated scores.
                </p>
            </section>

            <section>
                <h2 id="domain-breakdown" class="fw-bolder mb-4 mt-5">
                    <a href="#domain-breakdown" class="text-reset text-decoration-none">Domain Breakdown</a>
                </h2>
                <p>
                    Not all CISSP domains are created equal. Here's how the selected models perform across the eight domains,
                    plus the mixed practice tests (all FP16):
                </p>
                <div class="cissp-chart-section">
                    <h3 id="chart-heatmap" class="fw-bolder mb-3 mt-4">
                        <a href="#chart-heatmap" class="text-reset text-decoration-none">Domain Accuracy Heatmap (FP16)</a>
                    </h3>
                    <div id="cissp-heatmap"></div>
                </div>
                <p>
                    A few things stand out. Ministral 8B and Qwen3 8B are strong across the board, with no domain below 76%.
                    Ministral peaks on Domain 4 (Network Security) at 86.0%, Qwen3 peaks on Domain 3 (Architecture) at 86.9%.
                </p>
                <p>
                    Gemma 3n E4B and Llama 3.1 8B are remarkably consistent: their per-domain scores track within 1–7
                    points of each other across all eight domains, despite completely different architectures and providers.
                    This is consistent with both models operating at the genuine capability ceiling for their size class.
                    Llama's strongest domain is D6 (Security Assessment) at 79.0%, covering vulnerability scanning, pen
                    testing, and audit techniques that appear frequently in general cybersecurity training data.
                    Practice test scores track closely with domain-specific scores: there's no significant difficulty
                    gap between domain-tagged questions and the mixed practice exams.
                </p>
                <p>
                    Comparing model accuracy against how human candidates rank domain difficulty reveals an interesting inversion.
                    Domain 4 (Network Security), which humans find relatively approachable, is also where models score highest (68.4%).
                    The inversion is Domain 1 (Risk Management), consistently rated the hardest for humans because it requires thinking like a manager and memorizing frameworks like NIST and ISO 27001.
                    Models score nearly as well there (68.2%), likely because those frameworks are heavily represented in training data.
                </p>
                <p>
                    The harder story is D6 (Security Assessment) and D7 (Security Operations), which are the 2nd and
                    3rd hardest domains for humans and also the weakest for models (65.2% and 65.3% respectively).
                    Humans struggle there because those domains reward hands-on experience; models struggle because the
                    questions test applied reasoning that doesn't reduce to pattern matching. The overall domain range
                    for models is only about 3 points (65–68%), far flatter than the variance human candidates show
                    depending on their professional background.
                </p>
            </section>

            <section>
                <h2 id="how-many-bits" class="fw-bolder mb-4 mt-5">
                    <a href="#how-many-bits" class="text-reset text-decoration-none">How Many Bits Do You Actually Need?</a>
                </h2>
                <p>
                    When it comes to running LLMs locally there's a single dominating question: how much VRAM do I need?
                    Quantization is a popular method to make these large models quite a bit smaller, but at what cost?
                    Across 64 configurations, here's what the data shows.
                </p>

                <h3 id="fp16-vs-q8-vs-q4" class="fw-bolder mb-3 mt-4">
                    <a href="#fp16-vs-q8-vs-q4" class="text-reset text-decoration-none">FP16 vs. Q8 vs. Q4</a>
                </h3>
                <div class="cissp-chart-section">
                    <h3 id="chart-quant" class="fw-bolder mb-3 mt-4">
                        <a href="#chart-quant" class="text-reset text-decoration-none">Quantization Accuracy Curves</a>
                    </h3>
                    <div id="cissp-quant"></div>
                </div>
                <div class="table-responsive">
                    <table id="cissp-table-quant">
                        <caption>All values are deltas vs. FP16. Accuracy in percentage points; VRAM in GB.</caption>
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th title="Accuracy delta at Q8_0 vs FP16 (percentage points)">Q8 Acc Δ</th>
                                <th title="Accuracy delta at Q4_K_M vs FP16 (percentage points)">Q4 Acc Δ</th>
                                <th title="Tokens/sec delta at Q8_0 vs FP16">Q8 Tok/s Δ</th>
                                <th title="Tokens/sec delta at Q4_K_M vs FP16">Q4 Tok/s Δ</th>
                                <th title="Peak VRAM delta at Q8_0 vs FP16 (GB). Negative = less VRAM.">Q8 VRAM Δ</th>
                                <th title="Peak VRAM delta at Q4_K_M vs FP16 (GB). Negative = less VRAM.">Q4 VRAM Δ</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p>
                    <strong>Q8 is essentially transparent; Q4 is where the field splits.</strong> For knowledge-retrieval
                    tasks like this benchmark, no model lost more than 0.6 points at Q8, and several scored marginally
                    higher. At Q4 the picture diverges: Ministral 3 8B, Qwen3 8B, and DeepSeek R1 8B remain virtually
                    flat with spreads under 0.5 points across the full range, while other models begin to show meaningful
                    losses. Whatever the source of their high scores, the knowledge survives aggressive compression intact.
                </p>
                <p>
                    <strong>VRAM savings are real; time savings aren't.</strong> Moving from FP16 to Q4_K_M
                    cuts memory requirements roughly in half, making deployments that were previously impossible on consumer hardware routine.
                    Inference speed is a different story: most models run only marginally faster at lower precision, typically 1.2–1.5× over FP16
                    wall time, and some show no meaningful speedup at all. The practical implication is to
                    <mark>choose a quantization level based on your VRAM budget, not inference speed requirements</mark>.
                </p>
                <p>
                    <strong>Llama 3.1 8B pays the steepest price among competitive models.</strong> A 3.8-point drop
                    at Q4 is the largest loss in the top half of the field, and it has real consequences: Q4 Llama 3.1 8B
                    lands below the FP16 score of Gemma 3n E4B, a model with a cleaner quantization story.
                </p>

                <h3 id="llama-quant-curve" class="fw-bolder mb-3 mt-4">
                    <a href="#llama-quant-curve" class="text-reset text-decoration-none">The Full Quantization Curve</a>
                </h3>
                <p>
                    The Llama family and Mistral 7B have seven precision levels each, giving a finer view of the
                    accuracy-vs-compression curve. Llama 3.1 8B is the most instructive:
                </p>
                <div class="cissp-chart-section">
                    <figure style="width: 100%">
                        <div id="cissp-quant-ladder"></div>
                        <figcaption>Accuracy at each of seven quantization levels for the Llama family and Mistral 7B.</figcaption>
                    </figure>
                </div>
                <p>
                    The curve is non-monotonic: Q5_K_S (72.6%) nearly matches FP16 (72.8%) while Q6_K drops to 70.8%.
                    This isn't an error. K-quant variants use importance-aware quantization that can preserve critical
                    weights better than uniform higher-bit schemes. The practical upshot: <mark>Q5_K_S at roughly 5.3GB
                    delivers 99.8% of FP16 accuracy at 1.7× the inference speed.</mark>
                </p>
            </section>

            <section>
                <h2 id="performance" class="fw-bolder mb-4 mt-5">
                    <a href="#performance" class="text-reset text-decoration-none">Performance and Efficiency</a>
                </h2>
                <p>
                    All evaluations ran on a single NVIDIA RTX 4090. The full 16-model benchmark for FP16 completed in under 65 minutes. All runs used pre-loaded, warmed-up models; timing reflects steady-state inference, not cold-start load time.
                </p>

                <div class="cissp-chart-section">
                    <h3 id="chart-scatter" class="fw-bolder mb-3 mt-4">
                        <a href="#chart-scatter" class="text-reset text-decoration-none">Accuracy vs. Speed</a>
                    </h3>
                    <figure style="width: 100%">
                        <div id="cissp-scatter"></div>
                        <figcaption>FP16 accuracy vs. inference speed. Bubble size = peak VRAM. Toggle X-axis between tokens/sec and total wall time.</figcaption>
                    </figure>
                </div>

                <p>
                    <strong>Speed tracks size with few surprises.</strong> Models cluster tightly by parameter count in both tokens/sec
                    and wall time. Qwen3 1.7B leads on wall time and is within striking distance of Llama 3.2 1B on
                    tokens/sec. Llama 3.2 1B edges it on raw throughput, but at near-random accuracy that lead is academic.
                </p>
                <p>
                    <strong>The Gemma family is noticeably slower than same-size competitors.</strong> Gemma 3 1B is the smallest model
                    tested, yet it's slower than Llama 3.2 3B. This is likely due to architecture differences: Gemma 3
                    uses a different attention mechanism that's compute-heavy relative to its parameter count.
                </p>
                <p>
                    <strong>Gemma 3n's hidden cost.</strong> The E2B/E4B "effective" parameter counts are misleading.
                    The full 6B/8B weights still load into memory, so these models run like 8B models, not 2–4B ones
                    (7+ min vs. 2–3 min for comparable models). The MatFormer selective-activation architecture is also
                    tricky for general inference backends, and Gemma 3n's verbose outputs compound the wall time penalty further.
                </p>
                <div class="test-warn small">
                    <strong>Note:</strong> These figures use Ollama (llama.cpp), convenient but not peak-performance.
                    llama.cpp targets single-session inference on consumer hardware and lacks the continuous batching and
                    kernel fusion that purpose-built serving stacks use to maximize GPU utilization. Tokens/sec are valid
                    for relative comparison between models but will understate what vLLM, TensorRT-LLM, or OpenVINO GenAI
                    can achieve.
                </div>
            </section>

            <section>
                <h2 id="question-difficulty" class="fw-bolder mb-4 mt-5">
                    <a href="#question-difficulty" class="text-reset text-decoration-none">Question Difficulty</a>
                </h2>
                <p>
                    Across all 16 models at FP16, I can identify which questions are universally easy, universally hard,
                    or divisive.
                </p>
                <div class="cissp-chart-section">
                    <h3 id="chart-difficulty" class="fw-bolder mb-3 mt-4">
                        <a href="#chart-difficulty" class="text-reset text-decoration-none">Question Difficulty Distribution</a>
                    </h3>
                    <figure style="width: 100%">
                        <div id="cissp-difficulty"></div>
                        <figcaption>How many of the selected FP16 models answered each question correctly. Red = universally hard, green = universally easy.</figcaption>
                    </figure>
                </div>

                <h3 id="impossible-questions" class="fw-bolder mb-3 mt-4">
                    <a href="#impossible-questions" class="text-reset text-decoration-none">The Questions Every Model Got Wrong</a>
                </h3>
                <p>
                    <strong>27 questions were answered incorrectly by every single FP16 model</strong>, zero out of 16.
                    I manually reviewed them and confirmed they are legitimate hard questions with correct answers per the
                    ISC2 Common Body of Knowledge (CBK). No dataset errors.
                </p>
                <p>
                    One example captures the flavor. Consider this Domain 1 question:
                </p>
                <div class="test-fail my-4">
                    <p><em>Which one of the following actions might be taken as part of a business continuity plan?</em></p>
                    <p class="mb-0">A. Restoring from backup tapes</p>
                    <p class="mb-0">B. Implementing RAID</p>
                    <p class="mb-0">C. Relocating to a cold site</p>
                    <p class="mb-0">D. Restarting business operations</p>
                </div>
                <p>
                    Every model chose A, C, or D, all disaster recovery actions. The correct answer is B: implementing
                    RAID is a <em>proactive</em> measure that prevents downtime, which is what BCP is about. Restoring,
                    relocating, and restarting are all <em>reactive</em>: they happen after a disaster, making them DR,
                    not BCP. BCP is about continuity (keep running), DR is about recovery (get back up). Models conflate
                    the two because both involve backups, failover, and resilience. The CISSP exam specifically tests whether
                    you understand the boundary.
                </p>
                <p>
                    Across the impossible questions, four failure patterns emerge:
                </p>

                <h4 id="pattern-1" class="fw-bolder mb-2 mt-4">Pattern 1: ISC2-Specific Terminology Traps</h4>
                <p>
                    Models apply general IT knowledge where the CISSP uses its own vocabulary. Calling ISC2's
                    <em>clipping</em> by its practitioner name <em>thresholding</em> is one example; marking
                    <em>Preventive</em> for a question about warning signs instead of <em>Directive</em> is another.
                    The knowledge isn't wrong; the label is.
                </p>

                <h4 id="pattern-2" class="fw-bolder mb-2 mt-4">Pattern 2: Multi-Select With a Convincing Wrong Option</h4>
                <p>
                    On multi-select questions, models consistently include one distractor that sounds correct but is
                    specifically excluded by the CBK. They add threat modeling as something that reduces threat vectors,
                    not recognizing that the CBK treats threats as external and therefore irreducible. They also skip
                    individual contributors as recipients of audit reports, assuming only management receives them.
                    The model needs to learn not just what is true, but what the <em>CISSP</em> considers true.
                </p>

                <h4 id="pattern-3" class="fw-bolder mb-2 mt-4">Pattern 3: "Which Is NOT True" Requiring Exact CBK Knowledge</h4>
                <p>
                    Questions that invert the usual pattern ask which statement is <em>false</em>, requiring knowledge
                    precise enough to spot the one exception. Models assign classification authority to the system owner
                    rather than the data owner, where the CBK draws a clear line between the two roles. They also count
                    a PIN and a password as two factors, missing that both are Type 1 and the CBK counts factor types,
                    not credentials. Spotting the false claim requires knowing the CBK precisely enough to identify the
                    one exception to an otherwise true rule.
                </p>

                <h4 id="pattern-4" class="fw-bolder mb-2 mt-4">Pattern 4: Counterintuitive ISC2 Positions</h4>
                <p>
                    The CISSP sometimes takes positions that contradict current industry practice or common intuition.
                    Models assume modern Bluetooth has sufficient encryption for confidential data; the CBK says it does not.
                    They also jump straight to remediation when a vulnerability is found, where the CBK requires validating
                    the finding first. These are genuinely surprising answers. The exam tests the <em>ISC2 body of knowledge</em>,
                    not industry consensus, and every model applies general knowledge instead.
                </p>
                <p>
                    All 27 questions are teaching moments, not bugs. They map precisely to the kind of knowledge fine-tuning
                    can inject: ISC2-specific terminology, CBK-specific positions, and the precise distinctions the exam tests.
                    These are the teachable weaknesses.
                </p>

                <h3 id="matching-questions" class="fw-bolder mb-3 mt-4">
                    <a href="#matching-questions" class="text-reset text-decoration-none">Matching Questions: Right Knowledge, Wrong Bindings</a>
                </h3>
                <p>
                    The 16 matching questions are the hardest question type in the benchmark. Average accuracy across all
                    FP16 models was 29%, with four models scoring 0%. I initially suspected a bug in response formatting
                    or evaluation, but models responded with valid, simply incorrect selections. Models know the
                    definitions but can't correctly assign five items to five descriptions simultaneously.
                </p>

                <h3 id="gimmes" class="fw-bolder mb-3 mt-4">
                    <a href="#gimmes" class="text-reset text-decoration-none">The Gimmes</a>
                </h3>
                <p>
                    <strong>47 questions (3.6%) were answered correctly by every FP16 model.</strong>
                    These cover foundational concepts: what directory service underlies Active Directory, what GDPR requires
                    for EU data, what trademark protection covers. Even Llama 3.2 1B, essentially random on most questions, gets these right.
                </p>
            </section>

            <section>
                <h2 id="model-agreement" class="fw-bolder mb-4 mt-5">
                    <a href="#model-agreement" class="text-reset text-decoration-none">Model Agreement</a>
                </h2>
                <div class="cissp-chart-section">
                    <h3 id="chart-agreement" class="fw-bolder mb-3 mt-4">
                        <a href="#chart-agreement" class="text-reset text-decoration-none">Model Agreement Matrix (FP16)</a>
                    </h3>
                    <figure style="width: 100%; max-width: 750px; margin: 0 auto">
                        <div id="cissp-agreement"></div>
                        <figcaption>Jaccard similarity of wrong-answer sets between FP16 models. Darker = more often wrong on the same questions.</figcaption>
                    </figure>
                </div>
                <p>
                    The agreement matrix above measures Jaccard similarity between wrong-answer sets: the fraction of
                    questions that two models both got wrong out of all questions either got wrong. A few patterns
                    reinforce findings from other analyses.
                </p>
                <p>
                    <strong>Gemma 3n E2B and E4B are nearly identical (0.595).</strong> The highest pairwise similarity
                    in the matrix. They share the same MatFormer architecture and training data, just at different
                    activation levels; they fail on almost exactly the same questions. A useful sanity check that
                    the metric is capturing something structural.
                </p>
                <p>
                    <strong>Llama 3.2 3B and Phi-4 Mini are the most similar cross-family pair (0.562).</strong> Both
                    are clean-baseline models in the 3–4B class (December 2023 and June 2024 cutoffs respectively),
                    and they share 335 wrong answers. Those shared failures represent questions that genuinely strain
                    the 3–4B capability tier, not contamination artifacts, but hard CISSP material that smaller models
                    miss regardless of architecture. That cluster of 335 questions is a direct fine-tuning target.
                </p>
                <p>
                    <strong>DeepSeek R1 and Qwen3 8B show the highest pairwise similarity (0.480).</strong>
                    This is unsurprising as DeepSeek R1 8B is a fine-tuned variant of Qwen3. Their shared errors
                    reflect that relationship, not question difficulty, making it less useful as a signal than the
                    clean-baseline pairs.
                </p>
                <p>
                    <strong>Ministral 8B and Mistral 7B have the lowest same-family similarity (0.222).</strong>
                    Despite sharing the Mistral brand, their error sets are nearly orthogonal. A "model family" is
                    a genealogical label, not a capability claim.
                </p>
            </section>

            <section>
                <h2 id="takeaways" class="fw-bolder mb-4 mt-5">
                    <a href="#takeaways" class="text-reset text-decoration-none">Takeaways</a>
                </h2>
                <p>
                    These are small models, though calling an 8B model "small" invites debate. No one is deploying a
                    production pipeline to take the CISSP, but a model that can reason across eight domains of
                    professional certification material at 73% accuracy, zero-shot, is doing something real. The
                    economics of frontier models rule them out for a large class of tasks: private deployments,
                    high-volume inference, edge compute. The results here are evidence that the small-model tier is
                    capable of serious work.
                </p>
                <p>
                    <strong>1. The clean ceiling is ~73–74%.</strong> Llama 3.1 8B (72.8%, Dec 2023 cutoff) and Gemma 3n
                    E4B (74.3%, Jun 2024 cutoff) converge from different architectures and providers. This is likely the
                    genuine capability limit for 4–8B models on CISSP material without test contamination. Fine-tuning
                    improvements should be measured against this baseline.
                </p>
                <p>
                    <strong>2. Training data contamination is real and measurable.</strong> Models with undisclosed cutoffs
                    released in late 2024–2025 score 8 points higher than clean models of the same size. This was an
                    inadvertent contamination study, and it's worth being honest about.
                </p>
                <p>
                    <strong>3. The 3–4B class is surprisingly competitive.</strong> Gemma 3n E4B (74.3%), Gemma 3 4B
                    (69.8%), and Qwen3 4B (76.0%) are all within striking distance of the 8B models. Architecture and
                    training data quality matter far more than raw parameter count.
                </p>
                <p>
                    <strong>4. Qwen3 1.7B is the most impressive result per parameter.</strong> 69.0% from 1.7B
                    parameters is remarkable, outperforming Phi-4 Mini (3.8B) and Llama 3.2 3B despite being half
                    their size. Its training cutoff is undisclosed, though, so contamination may play a role.
                </p>
                <p>
                    <strong>5. Architecture affects inference speed more than parameter count does.</strong> Gemma 3
                    and Gemma 3n are noticeably slower than same-size models from other families; Gemma 3n E4B takes
                    over twice as long as Llama 3.1 8B despite similar size. When inference speed matters, benchmark
                    it directly; parameter count won't tell you.
                </p>
                <p>
                    <strong>6. Q8 is a safe default; Q4 is where models split.</strong> No model lost more than 0.6
                    points at Q8. At Q4, some models (Qwen3 8B, Ministral 8B, DeepSeek R1) stay virtually flat;
                    others show meaningful losses. VRAM savings are real: Q4 roughly halves memory requirements,
                    but inference speed gains are marginal. Choose quantization based on VRAM budget, not speed.
                </p>
            </section>

            <section>
                <h2 id="whats-next" class="fw-bolder mb-4 mt-5">
                    <a href="#whats-next" class="text-reset text-decoration-none">What's Next</a>
                </h2>
                <p>Three goals drive the next phase:</p>
                <ol>
                    <li>
                        <strong>Build a worthwhile dataset.</strong> Source material is the ISC2 Common Body of Knowledge
                        and broader cybersecurity references. Questions are generated from that material using open LLMs,
                        keeping the dataset grounded in source text rather than frontier model outputs.
                    </li>
                    <li>
                        <strong>Explore fine-tuning methodology.</strong> This is as much about learning the craft as the
                        results: what data formats work, how much data is enough, where different model sizes respond
                        differently to the same training signal.
                    </li>
                    <li>
                        <strong>Produce a hardware-optimized variant.</strong> The end target is an NPU-deployable model:
                        battery-constrained, quantized, running locally on consumer silicon. The model selection below
                        is shaped by what runs well on that hardware.
                    </li>
                </ol>
                <p>
                    CISSP accuracy is the measuring stick throughout. These models have clean, well-characterized
                    baselines, so gains from fine-tuning will reflect genuine learning rather than contamination artifacts.
                </p>
                <p>
                    After weighing accuracy, speed, quantization resilience, and contamination risk, I'm narrowing to
                    <strong>three models spanning three size classes and two architecture families</strong>:
                </p>
                <ul>
                    <li>
                        <strong>Llama 3.1 8B</strong>: clean accuracy leader at 72.8%, definitively pre-test cutoff
                        (December 2023), proven OpenVINO NPU support.
                    </li>
                    <li>
                        <strong>Gemma 3 4B</strong>: 69.8% with a borderline cutoff (August 2024), genuine 4B parameters,
                        and a different architecture from Llama. At INT8 on the NPU that's roughly 4GB, leaving ample
                        headroom for KV cache.
                    </li>
                    <li>
                        <strong>Gemma 3 1B</strong>: 45.8% baseline, the stress test. If fine-tuning can push a 1B model
                        from the mid-40s into the 60s, that's a stronger result than improving a 72.8% model to 80%.
                        Same architecture as the 4B, so gains between the two isolate the effect of parameter count.
                    </li>
                </ul>
                <p>
                    Three models, two architectures, three size classes, all with clean-enough baselines. Fine-tuning
                    gains measured against these starting points will reflect genuine learning, not contamination artifacts.
                    If you know of a clean cybersecurity benchmark to evaluate against, I'd like to hear about it.
                </p>
                <p>
                    The deeper question is whether small models can teach themselves. If the training questions are
                    generated by the same small models that will be fine-tuned on them, the experiment becomes
                    self-referential: can a model improve from its own outputs when those outputs are grounded in
                    source material? That's the question the next post sets out to answer.
                </p>

                <hr class="my-5">

                <p class="text-muted">
                    <em>All models were evaluated via Ollama on Windows with a single NVIDIA RTX 4090.
                    Evaluation harness and analysis scripts: <a href="https://github.com/johnhringiv/cissp-model-eval">johnhringiv/cissp-model-eval</a>.</em>
                </p>
            </section>

        <?php include("includes/say_hello.html") ?>
        </article>
    </div>
<a id="cissp-filter-badge" href="#model-selection" hidden aria-live="polite" aria-label="Filter active">
    <span id="cissp-filter-badge-count"></span> · Edit ↑
</a>
<script nonce="<?= $csp_nonce ?>">
window.CISSP_MODELS = <?= $cissp_models_json ?>;
window.CISSP_PER_QUESTION = <?= $cissp_per_question_json ?>;
window.CISSP_AGREEMENT = <?= $cissp_agreement_json ?>;
</script>
<?php include "includes/footer.php"; ?>
