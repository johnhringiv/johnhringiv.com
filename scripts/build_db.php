<?php
/**
 * Unified database builder: reads JSON source files → populates www/generated/site.db
 * Run from project root: php scripts/build_db.php
 * Idempotent: drops and recreates all tables on each run.
 */

$db_path = __DIR__ . '/../www/generated/site.db';
$www_root = __DIR__ . '/../www';

// ─── Input files ─────────────────────────────────────────────────────────────
$cissp_file    = __DIR__ . '/../data/cissp_results.json';
$research_file = __DIR__ . '/../data/research.php';
$press_file    = __DIR__ . '/../data/press.php';
$blog_file     = __DIR__ . '/../data/blog_posts.php';

// ─── Model metadata for CISSP ────────────────────────────────────────────────
const MODEL_META = [
    'llama3.1_8b-instruct'           => ['Llama',    8.0,  8.0],
    'llama3.2_3b-instruct'           => ['Llama',    3.0,  3.0],
    'llama3.2_1b-instruct'           => ['Llama',    1.0,  1.0],
    'gemma3_4b-it'                   => ['Gemma',    4.0,  4.0],
    'gemma3_1b-it'                   => ['Gemma',    1.0,  1.0],
    'gemma3n_e4b-it'                 => ['Gemma 3n', 4.0,  8.0],
    'gemma3n_e2b-it'                 => ['Gemma 3n', 2.0,  6.0],
    'phi4-mini_3.8b'                 => ['Phi',      3.8,  3.8],
    'mistral_7b-instruct'            => ['Mistral',  7.0,  7.0],
    'ministral-3_8b-instruct-2512'   => ['Mistral',  8.0,  8.0],
    'ministral-3_3b-instruct-2512'   => ['Mistral',  3.0,  3.0],
    'deepseek-r1_8b-0528-qwen3'     => ['DeepSeek', 8.0,  8.0],
    'qwen3_8b'                       => ['Qwen',     8.0,  8.0],
    'qwen3_4b'                       => ['Qwen',     4.0,  4.0],
    'qwen3_1.7b'                     => ['Qwen',     1.7,  1.7],
    'smollm3_3b'                     => ['SmolLM',   3.0,  3.0],
];

const DISPLAY_NAMES = [
    'llama3.1_8b-instruct'         => 'Llama 3.1 8B',
    'llama3.2_3b-instruct'         => 'Llama 3.2 3B',
    'llama3.2_1b-instruct'         => 'Llama 3.2 1B',
    'gemma3_4b-it'                 => 'Gemma 3 4B',
    'gemma3_1b-it'                 => 'Gemma 3 1B',
    'gemma3n_e4b-it'               => 'Gemma 3n E4B',
    'gemma3n_e2b-it'               => 'Gemma 3n E2B',
    'phi4-mini_3.8b'               => 'Phi-4 Mini',
    'mistral_7b-instruct'          => 'Mistral 7B',
    'ministral-3_8b-instruct-2512' => 'Ministral 3 8B',
    'ministral-3_3b-instruct-2512' => 'Ministral 3 3B',
    'deepseek-r1_8b-0528-qwen3'   => 'DeepSeek R1 8B',
    'qwen3_8b'                     => 'Qwen3 8B',
    'qwen3_4b'                     => 'Qwen3 4B',
    'qwen3_1.7b'                   => 'Qwen3 1.7B',
    'smollm3_3b'                   => 'SmolLM3 3B',
];

const SHORT_NAMES = [
    'llama3.1_8b-instruct'         => 'Llama 3.1',
    'llama3.2_3b-instruct'         => 'Llama 3.2 3B',
    'llama3.2_1b-instruct'         => 'Llama 3.2 1B',
    'gemma3_4b-it'                 => 'Gemma 3 4B',
    'gemma3_1b-it'                 => 'Gemma 3 1B',
    'gemma3n_e4b-it'               => 'Gemma 3n E4B',
    'gemma3n_e2b-it'               => 'Gemma 3n E2B',
    'phi4-mini_3.8b'               => 'Phi-4 Mini',
    'mistral_7b-instruct'          => 'Mistral 7B',
    'ministral-3_8b-instruct-2512' => 'Ministral 8B',
    'ministral-3_3b-instruct-2512' => 'Ministral 3B',
    'deepseek-r1_8b-0528-qwen3'   => 'DeepSeek R1',
    'qwen3_8b'                     => 'Qwen3 8B',
    'qwen3_4b'                     => 'Qwen3 4B',
    'qwen3_1.7b'                   => 'Qwen3 1.7B',
    'smollm3_3b'                   => 'SmolLM3 3B',
];

function derive_model_meta(string $model_key): array {
    $best_prefix = '';
    $best_meta = ['Other', 0.0, 0.0];
    foreach (MODEL_META as $prefix => $meta) {
        if (str_starts_with($model_key, $prefix) && strlen($prefix) > strlen($best_prefix)) {
            $best_prefix = $prefix;
            $best_meta = $meta;
        }
    }
    return $best_meta;
}

function derive_display_name(string $model_key): string {
    $base = preg_replace('/-(fp16|q[0-9_a-zA-Z]+)$/', '', $model_key);
    return DISPLAY_NAMES[$base] ?? $base;
}

function derive_short_name(string $model_key): string {
    $base = preg_replace('/-(fp16|q[0-9_a-zA-Z]+)$/', '', $model_key);
    return SHORT_NAMES[$base] ?? $base;
}

function derive_quantization(string $model_key): string {
    if (preg_match('/-((fp16|q[0-9_a-zA-Z]+))$/', $model_key, $m)) {
        return $m[1];
    }
    return 'unknown';
}

function load_json(string $path): array {
    if (!file_exists($path)) {
        fwrite(STDERR, "ERROR: Input file not found: $path\n");
        exit(1);
    }
    $data = json_decode(file_get_contents($path), true);
    if ($data === null) {
        fwrite(STDERR, "ERROR: Failed to parse JSON: $path\n");
        exit(1);
    }
    return $data;
}

// ─── Remove old DB and create fresh ──────────────────────────────────────────
$dir = dirname($db_path);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (file_exists($db_path)) {
    unlink($db_path);
}

$db = new SQLite3($db_path);
$db->enableExceptions(true);
$db->exec('PRAGMA synchronous=NORMAL');

// ═════════════════════════════════════════════════════════════════════════════
// CISSP Tables
// ═════════════════════════════════════════════════════════════════════════════
echo "── CISSP data ──\n";

$db->exec(<<<SQL
CREATE TABLE runs (
  id INTEGER PRIMARY KEY,
  model_key TEXT NOT NULL,
  model_family TEXT NOT NULL,
  params_effective REAL NOT NULL,
  params_total REAL NOT NULL,
  quantization TEXT NOT NULL,
  display_name TEXT NOT NULL,
  short_name TEXT NOT NULL,
  accuracy REAL NOT NULL,
  total_time_sec REAL NOT NULL,
  avg_tokens_per_sec REAL NOT NULL,
  peak_vram_mb REAL,
  avg_vram_mb REAL,
  total_eval_tokens INTEGER,
  total_prompt_tokens INTEGER
)
SQL);

$db->exec(<<<SQL
CREATE TABLE domain_accuracy (
  run_id INTEGER NOT NULL REFERENCES runs(id),
  domain INTEGER NOT NULL,
  correct INTEGER NOT NULL,
  total INTEGER NOT NULL,
  accuracy REAL NOT NULL
)
SQL);

$db->exec(<<<SQL
CREATE TABLE question_results (
  run_id INTEGER NOT NULL REFERENCES runs(id),
  question_id TEXT NOT NULL,
  domain INTEGER NOT NULL,
  correct INTEGER NOT NULL
)
SQL);

if (file_exists($cissp_file)) {
    $cissp_data = load_json($cissp_file);
    $runs = $cissp_data['runs'] ?? [];

    $stmt_run = $db->prepare(<<<SQL
    INSERT INTO runs (model_key, model_family, params_effective, params_total, quantization,
      display_name, short_name, accuracy, total_time_sec, avg_tokens_per_sec,
      peak_vram_mb, avg_vram_mb, total_eval_tokens, total_prompt_tokens)
    VALUES (:model_key, :model_family, :params_effective, :params_total, :quantization,
      :display_name, :short_name, :accuracy, :total_time_sec, :avg_tokens_per_sec,
      :peak_vram_mb, :avg_vram_mb, :total_eval_tokens, :total_prompt_tokens)
    SQL);

    $stmt_domain = $db->prepare(<<<SQL
    INSERT INTO domain_accuracy (run_id, domain, correct, total, accuracy)
    VALUES (:run_id, :domain, :correct, :total, :accuracy)
    SQL);

    $stmt_question = $db->prepare(<<<SQL
    INSERT INTO question_results (run_id, question_id, domain, correct)
    VALUES (:run_id, :question_id, :domain, :correct)
    SQL);

    $runs_inserted = 0;
    $questions_inserted = 0;

    foreach ($runs as $run) {
        $model_key = $run['model_key'];
        echo "  Processing $model_key...\n";

        [$family, $params_eff, $params_total] = derive_model_meta($model_key);
        $quantization = derive_quantization($model_key);

        $stmt_run->bindValue(':model_key', $model_key);
        $stmt_run->bindValue(':model_family', $family);
        $stmt_run->bindValue(':params_effective', $params_eff);
        $stmt_run->bindValue(':params_total', $params_total);
        $stmt_run->bindValue(':quantization', $quantization);
        $stmt_run->bindValue(':display_name', derive_display_name($model_key));
        $stmt_run->bindValue(':short_name', derive_short_name($model_key));
        $stmt_run->bindValue(':accuracy', (float)($run['accuracy'] ?? 0));
        $stmt_run->bindValue(':total_time_sec', (float)($run['total_time_sec'] ?? 0));
        $stmt_run->bindValue(':avg_tokens_per_sec', (float)($run['avg_tokens_per_sec'] ?? 0));
        $stmt_run->bindValue(':peak_vram_mb', isset($run['peak_vram_mb']) ? (float)$run['peak_vram_mb'] : null, SQLITE3_FLOAT);
        $stmt_run->bindValue(':avg_vram_mb', isset($run['avg_vram_mb']) ? (float)$run['avg_vram_mb'] : null, SQLITE3_FLOAT);
        $stmt_run->bindValue(':total_eval_tokens', isset($run['total_eval_tokens']) ? (int)$run['total_eval_tokens'] : null, SQLITE3_INTEGER);
        $stmt_run->bindValue(':total_prompt_tokens', isset($run['total_prompt_tokens']) ? (int)$run['total_prompt_tokens'] : null, SQLITE3_INTEGER);
        $stmt_run->execute();

        $run_id = $db->lastInsertRowID();
        $runs_inserted++;

        foreach ($run['domain_accuracy'] ?? [] as $domain => $da) {
            $stmt_domain->bindValue(':run_id', $run_id);
            $stmt_domain->bindValue(':domain', (int)$domain);
            $stmt_domain->bindValue(':correct', (int)($da['correct'] ?? 0));
            $stmt_domain->bindValue(':total', (int)($da['total'] ?? 0));
            $stmt_domain->bindValue(':accuracy', (float)($da['accuracy'] ?? 0));
            $stmt_domain->execute();
        }

        $db->exec('BEGIN');
        foreach ($run['question_results'] ?? [] as $idx => $qr) {
            $stmt_question->bindValue(':run_id', $run_id);
            $stmt_question->bindValue(':question_id', (string)$idx);
            $stmt_question->bindValue(':domain', (int)($qr['domain'] ?? 0));
            $stmt_question->bindValue(':correct', ($qr['correct'] ?? false) ? 1 : 0);
            $stmt_question->execute();
            $questions_inserted++;
        }
        $db->exec('COMMIT');
    }

    echo "  Runs inserted:      $runs_inserted\n";
    echo "  Questions inserted: $questions_inserted\n";

    // Create CISSP indexes
    $db->exec('CREATE INDEX idx_qr_run_id ON question_results(run_id)');
    $db->exec('CREATE INDEX idx_qr_question_id ON question_results(question_id)');
    $db->exec('CREATE INDEX idx_runs_model_key ON runs(model_key)');
    $db->exec('CREATE INDEX idx_runs_quantization ON runs(quantization)');
} else {
    echo "  Skipping CISSP (no data file)\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// Research Table
// ═════════════════════════════════════════════════════════════════════════════
echo "── Research data ──\n";

$db->exec(<<<SQL
CREATE TABLE research (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  authors TEXT NOT NULL,
  venue TEXT NOT NULL,
  date TEXT NOT NULL,
  date_display TEXT NOT NULL,
  image TEXT,
  image_alt TEXT,
  caption TEXT,
  description TEXT NOT NULL,
  links TEXT NOT NULL,
  category TEXT NOT NULL,
  sort_order INTEGER NOT NULL
)
SQL);

$research_data = include $research_file;
$stmt = $db->prepare(<<<SQL
INSERT INTO research (slug, title, authors, venue, date, date_display, image, image_alt, caption, description, links, category, sort_order)
VALUES (:slug, :title, :authors, :venue, :date, :date_display, :image, :image_alt, :caption, :description, :links, :category, :sort_order)
SQL);

foreach ($research_data as $i => $r) {
    $stmt->bindValue(':slug', $r['slug']);
    $stmt->bindValue(':title', $r['title']);
    $stmt->bindValue(':authors', json_encode($r['authors']));
    $stmt->bindValue(':venue', $r['venue']);
    $stmt->bindValue(':date', $r['date']);
    $stmt->bindValue(':date_display', $r['date_display']);
    $image = !empty($r['image']) ? ($r['image'][0] === '/' ? $r['image'] : '/' . $r['image']) : null;
    $stmt->bindValue(':image', $image, $image === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':image_alt', $r['image_alt'], $r['image_alt'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':caption', $r['caption'], $r['caption'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':description', $r['description']);
    $stmt->bindValue(':links', json_encode($r['links']));
    $stmt->bindValue(':category', $r['category']);
    $stmt->bindValue(':sort_order', $i);
    $stmt->execute();
}
echo "  Research entries: " . count($research_data) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// Press Table
// ═════════════════════════════════════════════════════════════════════════════
echo "── Press data ──\n";

$db->exec(<<<SQL
CREATE TABLE press (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  url TEXT NOT NULL,
  publication TEXT NOT NULL,
  date TEXT NOT NULL,
  date_display TEXT NOT NULL,
  image TEXT NOT NULL,
  image_alt TEXT NOT NULL,
  caption TEXT,
  description TEXT NOT NULL,
  description_html TEXT,
  sort_order INTEGER NOT NULL
)
SQL);

$press_data = include $press_file;
$stmt = $db->prepare(<<<SQL
INSERT INTO press (slug, title, url, publication, date, date_display, image, image_alt, caption, description, description_html, sort_order)
VALUES (:slug, :title, :url, :publication, :date, :date_display, :image, :image_alt, :caption, :description, :description_html, :sort_order)
SQL);

foreach ($press_data as $i => $p) {
    $stmt->bindValue(':slug', $p['slug']);
    $stmt->bindValue(':title', $p['title']);
    $stmt->bindValue(':url', $p['url']);
    $stmt->bindValue(':publication', $p['publication']);
    $stmt->bindValue(':date', $p['date']);
    $stmt->bindValue(':date_display', $p['date_display']);
    $image = !empty($p['image']) ? ($p['image'][0] === '/' ? $p['image'] : '/' . $p['image']) : null;
    $stmt->bindValue(':image', $image);
    $stmt->bindValue(':image_alt', $p['image_alt']);
    $stmt->bindValue(':caption', $p['caption'], $p['caption'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':description', $p['description']);
    $stmt->bindValue(':description_html', $p['description_html'], $p['description_html'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':sort_order', $i);
    $stmt->execute();
}
echo "  Press entries: " . count($press_data) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// Blog Posts Table
// ═════════════════════════════════════════════════════════════════════════════
echo "── Blog posts data ──\n";

$db->exec(<<<SQL
CREATE TABLE blog_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  subtitle TEXT,
  description TEXT NOT NULL,
  html_description TEXT,
  og_image TEXT NOT NULL,
  blog_image TEXT,
  published_time TEXT NOT NULL,
  modified_time TEXT NOT NULL,
  tags TEXT NOT NULL,
  section TEXT NOT NULL DEFAULT 'Technology',
  extra_css TEXT,
  extra_js TEXT,
  sort_order INTEGER NOT NULL
)
SQL);

$blog_data = include $blog_file;
$stmt = $db->prepare(<<<SQL
INSERT INTO blog_posts (slug, title, subtitle, description, html_description, og_image, blog_image, published_time, modified_time, tags, section, extra_css, extra_js, sort_order)
VALUES (:slug, :title, :subtitle, :description, :html_description, :og_image, :blog_image, :published_time, :modified_time, :tags, :section, :extra_css, :extra_js, :sort_order)
SQL);

foreach ($blog_data as $i => $b) {
    $stmt->bindValue(':slug', $b['slug']);
    $stmt->bindValue(':title', $b['title']);
    $stmt->bindValue(':subtitle', $b['subtitle'], $b['subtitle'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':description', $b['description']);
    $stmt->bindValue(':html_description', $b['html_description'], $b['html_description'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $og_image = !empty($b['og_image']) ? ($b['og_image'][0] === '/' ? $b['og_image'] : '/' . $b['og_image']) : null;
    $blog_image = !empty($b['blog_image']) ? ($b['blog_image'][0] === '/' ? $b['blog_image'] : '/' . $b['blog_image']) : null;
    $stmt->bindValue(':og_image', $og_image);
    $stmt->bindValue(':blog_image', $blog_image, $blog_image === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':published_time', $b['published_time']);
    $stmt->bindValue(':modified_time', $b['modified_time']);
    $stmt->bindValue(':tags', json_encode($b['tags']));
    $stmt->bindValue(':section', $b['section']);
    $stmt->bindValue(':extra_css', $b['extra_css'] !== null ? json_encode($b['extra_css']) : null, $b['extra_css'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':extra_js', $b['extra_js'] !== null ? json_encode($b['extra_js']) : null, $b['extra_js'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':sort_order', $i);
    $stmt->execute();
}
echo "  Blog entries: " . count($blog_data) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// Build-Time Validation
// ═════════════════════════════════════════════════════════════════════════════
echo "── Validation ──\n";
$errors = [];

foreach ($blog_data as $b) {
    $slug = $b['slug'];

    // 1. Blog slug ↔ PHP file
    $php_file = $www_root . '/' . $slug . '.php';
    if (!file_exists($php_file)) {
        $errors[] = "Blog slug '$slug' has no matching PHP file: $php_file";
    }

    // 2. Required fields
    foreach (['title', 'description', 'og_image', 'published_time', 'modified_time'] as $field) {
        if (empty($b[$field])) {
            $errors[] = "Blog '$slug' missing required field: $field";
        }
    }

    // 3. Date sanity
    $pub = $b['published_time'];
    $mod = $b['modified_time'];
    if ($pub && $mod && $mod < $pub) {
        $errors[] = "Blog '$slug': modified_time ($mod) < published_time ($pub)";
    }

    // Validate ISO 8601 dates
    foreach (['published_time' => $pub, 'modified_time' => $mod] as $field => $val) {
        $dt = DateTime::createFromFormat('Y-m-d', $val);
        if (!$dt) {
            $dt = DateTime::createFromFormat(DateTime::ATOM, $val);
        }
        if (!$dt) {
            $errors[] = "Blog '$slug': invalid date for $field: $val";
        }
    }

    // 4. OG image exists (SVG source or generated PNG)
    $og = $b['og_image'];
    $og_abs = $www_root . $og;
    $og_svg = preg_replace('/\.png$/', '.svg', $og_abs);
    if (!file_exists($og_abs) && !file_exists($og_svg)) {
        $errors[] = "Blog '$slug': og_image not found: $og (checked $og_abs and $og_svg)";
    }
}

if (!empty($errors)) {
    fwrite(STDERR, "\nBuild validation FAILED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - $e\n");
    }
    exit(1);
}

echo "  All validations passed\n";

// ─── Summary ─────────────────────────────────────────────────────────────────
$db_size = filesize($db_path);
echo "\nDone!\n";
echo "  DB size: " . number_format($db_size / 1024 / 1024, 2) . " MB\n";
echo "  DB path: $db_path\n";
