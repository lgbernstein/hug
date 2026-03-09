<?php
// Simple auth — change this password
$ADMIN_PASS = 'hug2026';
session_start();
if (isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === $ADMIN_PASS) { $_SESSION['hug_admin'] = true; }
    else { $_SESSION['hug_admin_error'] = 'Wrong password'; }
}
if (isset($_GET['logout'])) { unset($_SESSION['hug_admin']); }
if (empty($_SESSION['hug_admin'])) {
    $err = $_SESSION['hug_admin_error'] ?? '';
    unset($_SESSION['hug_admin_error']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><script src="https://cdn.tailwindcss.com"></script></head>';
    echo '<body class="min-h-screen flex items-center justify-center" style="background:#060b18;color:#e2e8f0">';
    echo '<form method="POST" class="p-8 rounded-2xl text-center space-y-4" style="background:#111a2e;border:1px solid rgba(255,255,255,0.05)">';
    echo '<h1 class="text-lg font-bold">HUG COACH Admin</h1>';
    if ($err) echo '<p class="text-red-400 text-sm">' . htmlspecialchars($err) . '</p>';
    echo '<input type="password" name="admin_pass" placeholder="Password" autofocus class="w-full bg-black/30 border border-white/10 rounded-lg px-4 py-2 text-white text-center focus:outline-none focus:border-indigo-500">';
    echo '<button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 py-2 rounded-lg text-sm font-semibold">Login</button>';
    echo '</form></body></html>';
    exit;
}

$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed'); }

// Schema migrations — only check once per session
if (empty($_SESSION['hug_schema_ok'])) {
    $colCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'answer_hu'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE hungarian_prep ADD COLUMN answer_hu TEXT DEFAULT NULL AFTER answer_en");
    }
    $whoCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'who'");
    if ($whoCheck && $whoCheck->num_rows === 0) {
        $conn->query("ALTER TABLE hungarian_prep ADD COLUMN `who` VARCHAR(10) DEFAULT 'All' AFTER category");
    }
    $_SESSION['hug_schema_ok'] = true;
}

$message = '';
$preview = [];

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'preview') {
        $raw = trim($_POST['tsv'] ?? '');
        $format = $_POST['format'] ?? '3col';
        $defaultCat = trim($_POST['default_cat'] ?? 'General');
        if ($raw) {
            $lines = array_filter(explode("\n", $raw), 'trim');
            foreach ($lines as $line) {
                $cols = explode("\t", $line);
                $cols = array_map('trim', $cols);
                if (count($cols) < 2) continue;
                $row = ['question_hu' => $cols[0], 'answer_en' => '', 'answer_hu' => '', 'category' => $defaultCat, 'who' => 'All'];
                if ($format === '2col_hu') {
                    $row['answer_hu'] = $cols[1];
                } elseif ($format === '2col') {
                    $row['answer_en'] = $cols[1];
                } elseif ($format === '3col') {
                    $row['answer_hu'] = $cols[1] ?? '';
                    $row['answer_en'] = $cols[2] ?? '';
                } elseif ($format === '4col') {
                    $row['answer_hu'] = $cols[1] ?? '';
                    $row['answer_en'] = $cols[2] ?? '';
                    $row['category']  = $cols[3] ?? $defaultCat;
                } elseif ($format === '5col') {
                    $row['answer_hu'] = $cols[1] ?? '';
                    $row['answer_en'] = $cols[2] ?? '';
                    $row['category']  = $cols[3] ?? $defaultCat;
                    $row['who']       = in_array($cols[4] ?? '', ['Maria','Larry','All']) ? $cols[4] : 'All';
                }
                $preview[] = $row;
            }
        }
    }

    if ($_POST['action'] === 'import') {
        $rows = json_decode($_POST['rows_json'], true);
        $imported = 0;
        $skipped = 0;
        if ($rows) {
            $stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_hu, answer_en, category, `who`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_hu=VALUES(answer_hu), answer_en=VALUES(answer_en), category=VALUES(category), `who`=VALUES(`who`)");
            foreach ($rows as $r) {
                $q = trim($r['question_hu'] ?? '');
                if (!$q) { $skipped++; continue; }
                $ah = trim($r['answer_hu'] ?? '') ?: null;
                $ae = trim($r['answer_en'] ?? '');
                $cat = trim($r['category'] ?? 'General');
                $rw = in_array($r['who'] ?? '', ['Maria','Larry','All']) ? $r['who'] : 'All';
                $stmt->bind_param('sssss', $q, $ah, $ae, $cat, $rw);
                if ($stmt->execute()) { $imported++; } else { $skipped++; }
            }
            $stmt->close();
        }
        $message = "Imported $imported phrases" . ($skipped ? ", skipped $skipped" : '') . '.';
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM hungarian_prep WHERE id = $id");
        $message = "Deleted phrase #$id.";
    }

    if ($_POST['action'] === 'delete_bulk') {
        $ids = json_decode($_POST['delete_ids'] ?? '[]', true);
        if ($ids) {
            $safe = array_map('intval', $ids);
            $inList = implode(',', $safe);
            $conn->query("DELETE FROM hungarian_prep WHERE id IN ($inList)");
            $deleted = $conn->affected_rows;
            $message = "Deleted $deleted phrases.";
        }
    }

    if ($_POST['action'] === 'update_all' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE hungarian_prep SET question_hu=?, answer_hu=?, answer_en=?, category=?, `who`=? WHERE id=?");
        $q  = trim($_POST['question_hu'] ?? '');
        $ah = trim($_POST['answer_hu'] ?? '') ?: null;
        $ae = trim($_POST['answer_en'] ?? '');
        $cat = trim($_POST['category'] ?? 'General');
        $pw = in_array(trim($_POST['phrase_who'] ?? ''), ['All','Maria','Larry']) ? trim($_POST['phrase_who']) : 'All';
        $stmt->bind_param('sssssi', $q, $ah, $ae, $cat, $pw, $id);
        $stmt->execute();
        $stmt->close();
        $message = "Updated phrase #$id.";
    }

    // ── Bio actions ──
    if ($_POST['action'] === 'bio_add') {
        $sn = trim($_POST['subject_name'] ?? '');
        $sn = in_array($sn, ['Maria','Larry']) ? $sn : 'Maria';
        $fl = trim($_POST['fact_label_hu'] ?? '');
        $fv = trim($_POST['fact_value_hu'] ?? '');
        if ($fl === '' || $fv === '') {
            $message = "All bio fields are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO user_bios (subject_name, fact_label_hu, fact_value_hu) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $sn, $fl, $fv);
            $stmt->execute();
            $stmt->close();
            $message = "Added bio fact for $sn.";
        }
    }

    if ($_POST['action'] === 'bio_update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sn = trim($_POST['subject_name'] ?? '');
        $sn = in_array($sn, ['Maria','Larry']) ? $sn : 'Maria';
        $fl = trim($_POST['fact_label_hu'] ?? '');
        $fv = trim($_POST['fact_value_hu'] ?? '');
        if ($fl === '' || $fv === '') {
            $message = "All bio fields are required.";
        } else {
            $stmt = $conn->prepare("UPDATE user_bios SET subject_name=?, fact_label_hu=?, fact_value_hu=? WHERE id=?");
            $stmt->bind_param('sssi', $sn, $fl, $fv, $id);
            $stmt->execute();
            $stmt->close();
            $message = "Updated bio #$id.";
        }
    }

    if ($_POST['action'] === 'bio_delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM user_bios WHERE id = $id");
        $message = "Deleted bio #$id.";
    }

    if ($_POST['action'] === 'bio_delete_bulk') {
        $ids = json_decode($_POST['delete_ids'] ?? '[]', true);
        if ($ids) {
            $safe = array_map('intval', $ids);
            $inList = implode(',', $safe);
            $conn->query("DELETE FROM user_bios WHERE id IN ($inList)");
            $deleted = $conn->affected_rows;
            $message = "Deleted $deleted bio facts.";
        }
    }

    if ($_POST['action'] === 'migrate_hu') {
        $ids = json_decode($_POST['migrate_ids'] ?? '[]', true);
        if ($ids) {
            $migrated = 0;
            $stmt = $conn->prepare("UPDATE hungarian_prep SET answer_hu = answer_en, answer_en = '' WHERE id = ? AND (answer_hu IS NULL OR answer_hu = '')");
            foreach ($ids as $mid) {
                $mid = (int)$mid;
                $stmt->bind_param('i', $mid);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $migrated++;
            }
            $stmt->close();
            $message = "Migrated $migrated rows: moved answer_en → answer_hu.";
        }
    }
}

// AJAX: AI generate answer or question via Gemini
if (isset($_GET['ajax']) && in_array($_GET['action'] ?? '', ['ai_answer', 'ai_question'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $question_hu = trim($_POST['question_hu'] ?? '');
    $answer_en = trim($_POST['answer_en'] ?? '');
    $answer_hu = trim($_POST['answer_hu'] ?? '');

    $apiKey = $env['GEMINI_KEY'] ?? '';
    if (!$apiKey) { echo json_encode(['error' => 'No Gemini API key']); exit; }

    if ($action === 'ai_answer') {
        $prompt = "You are a Hungarian language expert helping someone prepare for a simplified naturalization interview.\n\n"
            . "The interviewer asks: \"$question_hu\"\n"
            . ($answer_en ? "The intended meaning in English: \"$answer_en\"\n" : "")
            . "\nGenerate a grammatically correct, formal Hungarian answer that a native Hungarian speaker would give in this interview context. "
            . "Use formal speech. Keep it concise (1-2 sentences). "
            . "Reply with ONLY the Hungarian answer text, nothing else.";
    } else {
        $context = $answer_hu ?: $answer_en;
        $prompt = "You are a Hungarian language expert helping someone prepare for a simplified naturalization interview.\n\n"
            . "The answer in the database is: \"$context\"\n"
            . ($answer_en ? "English meaning: \"$answer_en\"\n" : "")
            . "\nGenerate the formal Hungarian interview question that an interviewer would ask to elicit this answer. "
            . "Use formal speech. Keep it concise and natural. "
            . "Reply with ONLY the Hungarian question text, nothing else.";
    }

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 256]
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) { echo json_encode(['error' => 'Gemini API error']); exit; }

    $data = json_decode($response, true);
    $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    $text = trim($text, '"\'');
    echo json_encode(['result' => $text]);
    exit;
}

// AJAX: AI generate a group of related phrases
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'ai_group') {
    header('Content-Type: application/json');
    $topic = trim($_POST['topic'] ?? '');
    if (!$topic) { echo json_encode(['error' => 'No topic provided']); exit; }

    $apiKey = $env['GEMINI_KEY'] ?? '';
    if (!$apiKey) { echo json_encode(['error' => 'No Gemini API key']); exit; }

    $prompt = "You are a Hungarian language expert creating study materials for someone preparing for a simplified naturalization interview.\n\n"
        . "Topic: \"$topic\"\n\n"
        . "Generate a comprehensive set of related Hungarian phrases/words for studying this topic.\n\n"
        . "Examples of what topics might look like:\n"
        . "- \"mi question words\" → all question words starting with mi (mi, mit, mikor, miért, milyen, etc.) with example sentences\n"
        . "- \"menni conjugation present\" → all present tense forms of menni (megyek, mész, megy, etc.)\n"
        . "- \"family vocabulary\" → family member words and common sentences about family\n"
        . "- \"numbers 1-20\" → Hungarian numbers with pronunciation hints\n"
        . "- \"greetings\" → common Hungarian greetings and polite phrases\n\n"
        . "For each item, provide:\n"
        . "1. The Hungarian phrase/word (as a question or prompt to practice)\n"
        . "2. A Hungarian answer or example sentence using it\n"
        . "3. The English translation\n\n"
        . "Reply ONLY with a JSON array of objects, each with keys: \"question_hu\", \"answer_hu\", \"answer_en\"\n"
        . "Generate 8-15 items. Make them practical and useful for interview prep.\n"
        . "Example output:\n"
        . "[{\"question_hu\":\"Mi a neve?\",\"answer_hu\":\"A nevem...\",\"answer_en\":\"What is your name?\"}]";

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 2048]
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 25
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) { echo json_encode(['error' => 'Gemini API error (HTTP ' . $httpCode . ')']); exit; }

    $data = json_decode($response, true);
    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $content = preg_replace('/^```json\s*/i', '', trim($content));
    $content = preg_replace('/\s*```$/i', '', trim($content));
    $items = json_decode(trim($content), true);

    if (!is_array($items)) { echo json_encode(['error' => 'AI returned invalid format', 'raw' => $content]); exit; }

    echo json_encode(['items' => $items]);
    exit;
}

// Fetch all phrases for management (filtering is client-side)
$catFilter = $conn->real_escape_string(trim($_GET['cat'] ?? ''));
$sql = "SELECT * FROM hungarian_prep";
if ($catFilter) $sql .= " WHERE category = '$catFilter'";
$sql .= " ORDER BY category, question_hu";
$phrases = $conn->query($sql);

// Get categories for filter
$cats = $conn->query("SELECT DISTINCT category FROM hungarian_prep ORDER BY category");
$catList = [];
if ($cats) { while ($c = $cats->fetch_assoc()) $catList[] = $c['category']; }

$totalCount = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep")->fetch_assoc()['c'] ?? 0;

// Fetch all bios
$bios = $conn->query("SELECT * FROM user_bios ORDER BY subject_name, fact_label_hu");
$bioCount = $conn->query("SELECT COUNT(*) AS c FROM user_bios")->fetch_assoc()['c'] ?? 0;

// Find rows where answer_en looks Hungarian (has HU diacritics) and answer_hu is empty
$migrateRows = [];
$mRes = $conn->query("SELECT id, question_hu, answer_en, category FROM hungarian_prep WHERE (answer_hu IS NULL OR answer_hu = '') AND answer_en != '' AND (answer_en REGEXP '[áéíóöőúüű]' OR answer_en REGEXP '^(Igen|Nem|A |Az |Szeretek|Tudok|Kétszer)')");
if ($mRes) { while ($mr = $mRes->fetch_assoc()) $migrateRows[] = $mr; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HUG COACH Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { font-family: 'Inter', system-ui, sans-serif; }
body { background: #060b18; color: #e2e8f0; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
</style>
</head>
<body class="min-h-screen p-4 md:p-8">
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-xl">&#x1f1ed;&#x1f1fa;</span>
            <div>
                <h1 class="text-lg font-bold text-white">HUG COACH Admin</h1>
                <p class="text-xs text-slate-500"><?php echo $totalCount; ?> phrases in database</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="?logout=1" class="text-xs text-slate-500 hover:text-red-400 font-semibold">Logout</a>
            <a href="index.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">&larr; Back to App</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-500/10 border border-green-500/20 rounded-xl px-4 py-3 text-sm text-green-400"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- IMPORT SECTION -->
    <div class="bg-[#111a2e] border border-white/5 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Import Phrases</h2>
        <p class="text-xs text-slate-500 mb-4">Paste tab-separated data from Google Sheets. Select format to match your columns.</p>

        <form method="POST">
            <input type="hidden" name="action" value="preview">
            <div class="flex flex-wrap gap-3 mb-3">
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="2col_hu" checked class="accent-indigo-500"> 2-col: Question HU | Answer HU
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="2col" class="accent-indigo-500"> 2-col: Hungarian | English
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="3col" class="accent-indigo-500"> 3-col: Hungarian | Answer HU | English
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="4col" class="accent-indigo-500"> 4-col: Hungarian | Answer HU | English | Category
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                    <input type="radio" name="format" value="5col" class="accent-indigo-500"> 5-col: Hungarian | Answer HU | English | Category | Who
                </label>
            </div>
            <div class="flex gap-3 mb-3">
                <div class="flex-1">
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Default Category</label>
                    <input type="text" name="default_cat" value="General" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>
            <textarea name="tsv" rows="8" placeholder="Paste TSV here — one phrase per line, tabs between columns&#10;&#10;Example (3-col):&#10;Hogy hívják?&#9;A nevem Bernstein Lawrence.&#9;What is your name?&#10;Hol lakik?&#9;Budapesten lakom.&#9;Where do you live?"
                class="w-full bg-[#0c1222] border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-indigo-500 resize-y mb-3"><?php echo htmlspecialchars($_POST['tsv'] ?? ''); ?></textarea>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Preview Import</button>
        </form>

        <?php if ($preview): ?>
        <div class="mt-4 border-t border-white/5 pt-4">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Preview (<?php echo count($preview); ?> rows)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-slate-500 uppercase tracking-wider">
                        <th class="text-left py-2 px-2">Question (HU)</th>
                        <th class="text-left py-2 px-2">Answer (HU)</th>
                        <th class="text-left py-2 px-2">English</th>
                        <th class="text-left py-2 px-2">Category</th>
                        <th class="text-left py-2 px-2">Who</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($preview as $p): ?>
                    <tr class="border-t border-white/5">
                        <td class="py-2 px-2 text-white"><?php echo htmlspecialchars($p['question_hu']); ?></td>
                        <td class="py-2 px-2 text-green-400"><?php echo htmlspecialchars($p['answer_hu']); ?></td>
                        <td class="py-2 px-2 text-slate-400"><?php echo htmlspecialchars($p['answer_en']); ?></td>
                        <td class="py-2 px-2 text-slate-500"><?php echo htmlspecialchars($p['category']); ?></td>
                        <td class="py-2 px-2 text-slate-500"><?php echo htmlspecialchars($p['who'] ?? 'All'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="POST" class="mt-3">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="rows_json" value="<?php echo htmlspecialchars(json_encode($preview)); ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Confirm Import (<?php echo count($preview); ?> phrases)</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- AI GROUP GENERATOR -->
    <div class="bg-[#0e1a2e] border border-violet-500/10 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-2">AI Group Generator</h2>
        <p class="text-xs text-slate-500 mb-4">Generate a set of related phrases using AI. Great for verb conjugations, vocabulary groups, question words, etc.</p>

        <div class="flex gap-3 mb-3">
            <input type="text" id="groupTopic" placeholder='e.g. "mi question words", "menni conjugation", "family vocabulary"'
                class="flex-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
            <input type="text" id="groupCategory" placeholder="Category name" value=""
                class="w-40 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
            <button onclick="generateGroup()" id="groupGenBtn" class="bg-violet-600 hover:bg-violet-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all shrink-0">Generate</button>
        </div>

        <div id="groupStatus" class="text-xs text-slate-500 hidden mb-3"></div>

        <!-- Preview table for generated group -->
        <div id="groupPreview" class="hidden">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider">Preview <span id="groupCount" class="text-violet-400"></span></h3>
                <div class="flex gap-2">
                    <button onclick="saveGroup()" class="bg-green-600 hover:bg-green-500 px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Save All to Database</button>
                    <button onclick="clearGroup()" class="bg-slate-700 hover:bg-slate-600 px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Clear</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-slate-500 uppercase tracking-wider text-[10px]">
                        <th class="py-2 px-2 w-6"><input type="checkbox" id="groupSelectAll" checked onchange="document.querySelectorAll('.group-cb').forEach(function(c){c.checked=this.checked}.bind(this))" class="accent-violet-500"></th>
                        <th class="text-left py-2 px-2">Question (HU)</th>
                        <th class="text-left py-2 px-2">Answer (HU)</th>
                        <th class="text-left py-2 px-2">English</th>
                    </tr></thead>
                    <tbody id="groupBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DATA FIX: Hungarian in wrong column -->
    <?php if ($migrateRows): ?>
    <div class="bg-[#1a1a0e] border border-yellow-500/20 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-yellow-400 uppercase tracking-wider mb-2">Data Fix: Hungarian Answers in English Column</h2>
        <p class="text-xs text-slate-400 mb-4"><?php echo count($migrateRows); ?> rows have Hungarian text in the "English" column with no Hungarian answer. Review and migrate them.</p>
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-500 uppercase tracking-wider text-[10px]">
                    <th class="text-left py-2 px-2 w-8"><input type="checkbox" id="migrateAll" checked onchange="document.querySelectorAll('.migrate-cb').forEach(c=>c.checked=this.checked)" class="accent-yellow-500"></th>
                    <th class="text-left py-2 px-2">#</th>
                    <th class="text-left py-2 px-2">Question (HU)</th>
                    <th class="text-left py-2 px-2">Currently in "English" column</th>
                    <th class="text-left py-2 px-2">Category</th>
                </tr></thead>
                <tbody>
                <?php foreach ($migrateRows as $mr): ?>
                <tr class="border-t border-white/5">
                    <td class="py-2 px-2"><input type="checkbox" class="migrate-cb accent-yellow-500" value="<?php echo $mr['id']; ?>" checked></td>
                    <td class="py-2 px-2 text-slate-600"><?php echo $mr['id']; ?></td>
                    <td class="py-2 px-2 text-white"><?php echo htmlspecialchars($mr['question_hu']); ?></td>
                    <td class="py-2 px-2 text-yellow-400"><?php echo htmlspecialchars($mr['answer_en']); ?></td>
                    <td class="py-2 px-2 text-slate-500"><?php echo htmlspecialchars($mr['category']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="POST" onsubmit="return confirmMigrate(this)">
            <input type="hidden" name="action" value="migrate_hu">
            <input type="hidden" name="migrate_ids" id="migrateIds">
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-500 px-5 py-2 rounded-lg text-sm font-semibold text-black transition-all">Move selected to Answer (HU) column</button>
            <span class="text-xs text-slate-500 ml-3">This moves text from answer_en → answer_hu and clears answer_en</span>
        </form>
    </div>
    <?php endif; ?>

    <!-- MANAGE SECTION -->
    <div class="bg-[#111a2e] border border-white/5 rounded-2xl p-6">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Manage Phrases</h2>

        <!-- Search + Filter -->
        <div class="flex flex-wrap gap-2 mb-4">
            <div class="flex-1 min-w-[200px] relative">
                <input type="text" id="liveSearch" placeholder="Search..." autofocus
                    class="w-full bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 pr-8 text-sm text-white focus:outline-none focus:border-indigo-500">
                <button id="clearSearch" onclick="document.getElementById('liveSearch').value='';filterTable()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white text-sm hidden">&times;</button>
            </div>
            <select id="whoSelect" class="bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                <option value="">All People</option>
                <option value="All">General (All)</option>
                <option value="Maria">Maria</option>
                <option value="Larry">Larry</option>
            </select>
            <select id="catSelect" class="bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($catList as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $catFilter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold self-center mr-1">Show missing:</span>
            <button onclick="toggleMissing('ah')" id="btn-ah" class="border border-yellow-500/30 text-yellow-500/70 hover:bg-yellow-500/10 text-xs px-3 py-1 rounded transition-all">Empty HU Answer</button>
            <button onclick="toggleMissing('ae')" id="btn-ae" class="border border-yellow-500/30 text-yellow-500/70 hover:bg-yellow-500/10 text-xs px-3 py-1 rounded transition-all">Empty English</button>
            <button onclick="toggleMissing('')" id="btn-all" class="border border-slate-500/30 text-slate-400 hover:bg-slate-500/10 text-xs px-3 py-1 rounded transition-all">Show All</button>
        </div>

        <!-- Bulk action bar -->
        <div id="bulkBar" class="hidden mb-4 flex items-center gap-3 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
            <span id="bulkCount" class="text-xs text-red-400 font-semibold"></span>
            <button onclick="bulkDelete()" class="bg-red-600 hover:bg-red-500 px-4 py-1.5 rounded-lg text-xs font-semibold text-white transition-all">Delete Selected</button>
            <button onclick="clearSelection()" class="text-xs text-slate-400 hover:text-white transition-colors">Clear</button>
        </div>

        <!-- Phrases Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-500 tracking-wider text-[10px]">
                    <th class="py-2 px-2 w-6"><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.row-cb').forEach(function(c){c.checked=this.checked}.bind(this));updateBulkBar()" class="accent-red-500 cursor-pointer"></th>
                    <th class="text-left py-2 px-2 w-8">#</th>
                    <th class="text-left py-2 px-2">Question (HU)</th>
                    <th class="text-left py-2 px-2">Answer (HU)</th>
                    <th class="text-left py-2 px-2">English</th>
                    <th class="text-left py-2 px-2">Category</th>
                    <th class="text-left py-2 px-2">Who</th>
                    <th class="text-left py-2 px-2 w-32">Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($phrases): while ($p = $phrases->fetch_assoc()): ?>
                <tr class="border-t border-white/5 hover:bg-white/[0.02] phrase-row" id="row-<?php echo $p['id']; ?>"
                    data-id="<?php echo $p['id']; ?>"
                    data-q="<?php echo htmlspecialchars($p['question_hu'], ENT_QUOTES); ?>"
                    data-ah="<?php echo htmlspecialchars($p['answer_hu'] ?? '', ENT_QUOTES); ?>"
                    data-ae="<?php echo htmlspecialchars($p['answer_en'] ?? '', ENT_QUOTES); ?>"
                    data-cat="<?php echo htmlspecialchars($p['category'], ENT_QUOTES); ?>"
                    data-who="<?php echo htmlspecialchars($p['who'] ?? 'All', ENT_QUOTES); ?>">
                    <td class="py-2 px-2"><input type="checkbox" class="row-cb accent-red-500 cursor-pointer" value="<?php echo $p['id']; ?>" onchange="updateBulkBar()"></td>
                    <td class="py-2 px-2 text-slate-600"><?php echo $p['id']; ?></td>
                    <td class="py-2 px-2 text-white font-medium cell-q"><?php echo htmlspecialchars($p['question_hu']); ?></td>
                    <td class="py-2 px-2 cell-ah <?php echo $p['answer_hu'] ? 'text-green-400' : 'text-yellow-500/50 italic'; ?>"><?php echo $p['answer_hu'] ? htmlspecialchars($p['answer_hu']) : '(missing)'; ?></td>
                    <td class="py-2 px-2 text-slate-400 cell-ae"><?php echo htmlspecialchars($p['answer_en'] ?? ''); ?></td>
                    <td class="py-2 px-2 cell-cat"><a href="?cat=<?php echo urlencode($p['category']); ?>" class="text-slate-500 hover:text-indigo-400 underline decoration-dotted underline-offset-2 transition-colors"><?php echo htmlspecialchars($p['category']); ?></a></td>
                    <td class="py-2 px-2 text-slate-500 cell-who"><?php echo htmlspecialchars($p['who'] ?? 'All'); ?></td>
                    <td class="py-2 px-2 space-x-1 whitespace-nowrap">
                        <button onclick="editRow(<?php echo $p['id']; ?>)" class="border border-indigo-500/50 text-indigo-400 hover:bg-indigo-500/20 hover:text-indigo-300 transition-colors text-xs font-semibold px-2 py-1 rounded">Edit</button>
                        <button onclick="aiGenerate(<?php echo $p['id']; ?>,'ai_answer',event)" class="border border-violet-500/50 text-violet-400 hover:bg-violet-500/20 hover:text-violet-300 transition-colors text-xs px-2 py-1 rounded" title="AI Generate Hungarian Answer">AI Ans</button>
                        <button onclick="aiGenerate(<?php echo $p['id']; ?>,'ai_question',event)" class="border border-cyan-500/50 text-cyan-400 hover:bg-cyan-500/20 hover:text-cyan-300 transition-colors text-xs px-2 py-1 rounded" title="AI Generate Question">AI Q</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this phrase?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="border border-red-500/30 text-slate-600 hover:bg-red-500/20 hover:text-red-400 transition-colors text-xs px-2 py-1 rounded">Del</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- PERSONAL BIOS SECTION -->
    <div class="bg-[#111a2e] border border-white/5 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Personal Bios <span class="text-slate-500 font-normal">(<?php echo $bioCount; ?>)</span></h2>
            <button onclick="openBioAdd()" class="bg-green-600 hover:bg-green-500 px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">+ Add Fact</button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Personal facts used in interview mode (PERSONAL category). Labels are in English, values in Hungarian.</p>

        <!-- Bio filter -->
        <div class="flex flex-wrap gap-2 mb-4">
            <input type="text" id="bioSearch" placeholder="Search bios..."
                class="flex-1 min-w-[200px] bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
            <select id="bioWhoFilter" class="bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                <option value="">All People</option>
                <option value="Maria">Maria</option>
                <option value="Larry">Larry</option>
            </select>
        </div>

        <!-- Bio bulk bar -->
        <div id="bioBulkBar" class="hidden mb-4 flex items-center gap-3 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
            <span id="bioBulkCount" class="text-xs text-red-400 font-semibold"></span>
            <button onclick="bioBulkDelete()" class="bg-red-600 hover:bg-red-500 px-4 py-1.5 rounded-lg text-xs font-semibold text-white transition-all">Delete Selected</button>
            <button onclick="bioClearSelection()" class="text-xs text-slate-400 hover:text-white transition-colors">Clear</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-500 tracking-wider text-[10px]">
                    <th class="py-2 px-2 w-6"><input type="checkbox" id="bioSelectAll" onchange="document.querySelectorAll('.bio-cb').forEach(function(c){c.checked=this.checked}.bind(this));updateBioBulkBar()" class="accent-red-500 cursor-pointer"></th>
                    <th class="text-left py-2 px-2 w-8">#</th>
                    <th class="text-left py-2 px-2">Person</th>
                    <th class="text-left py-2 px-2">Label (EN)</th>
                    <th class="text-left py-2 px-2">Value (HU)</th>
                    <th class="text-left py-2 px-2 w-20">Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($bios): while ($b = $bios->fetch_assoc()): ?>
                <tr class="border-t border-white/5 hover:bg-white/[0.02] bio-row"
                    data-id="<?php echo $b['id']; ?>"
                    data-sn="<?php echo htmlspecialchars($b['subject_name'], ENT_QUOTES); ?>"
                    data-fl="<?php echo htmlspecialchars($b['fact_label_hu'], ENT_QUOTES); ?>"
                    data-fv="<?php echo htmlspecialchars($b['fact_value_hu'], ENT_QUOTES); ?>">
                    <td class="py-2 px-2"><input type="checkbox" class="bio-cb accent-red-500 cursor-pointer" value="<?php echo $b['id']; ?>" onchange="updateBioBulkBar()"></td>
                    <td class="py-2 px-2 text-slate-600"><?php echo $b['id']; ?></td>
                    <td class="py-2 px-2 text-indigo-400 font-medium"><?php echo htmlspecialchars($b['subject_name']); ?></td>
                    <td class="py-2 px-2 text-slate-300"><?php echo htmlspecialchars($b['fact_label_hu']); ?></td>
                    <td class="py-2 px-2 text-green-400"><?php echo htmlspecialchars($b['fact_value_hu']); ?></td>
                    <td class="py-2 px-2 space-x-1 whitespace-nowrap">
                        <button onclick="editBio(<?php echo $b['id']; ?>)" class="border border-indigo-500/50 text-indigo-400 hover:bg-indigo-500/20 hover:text-indigo-300 transition-colors text-xs font-semibold px-2 py-1 rounded">Edit</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this bio fact?')">
                            <input type="hidden" name="action" value="bio_delete">
                            <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="border border-red-500/30 text-slate-600 hover:bg-red-500/20 hover:text-red-400 transition-colors text-xs px-2 py-1 rounded">Del</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeEdit()">
    <div class="bg-[#111a2e] border border-white/10 rounded-2xl p-6 w-full max-w-xl mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Edit Phrase <span id="editId" class="text-indigo-400"></span></h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_all">
            <input type="hidden" name="id" id="editIdInput">
            <div class="space-y-3">
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Question (HU)</label>
                    <div class="flex gap-2 mt-1">
                        <input type="text" name="question_hu" id="editQ" class="flex-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                        <button type="button" onclick="aiFromModal('ai_question')" class="bg-cyan-600 hover:bg-cyan-500 px-3 py-2 rounded-lg text-xs font-semibold transition-all shrink-0" title="AI Generate Question">AI Q</button>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Answer (HU)</label>
                    <div class="flex gap-2 mt-1">
                        <input type="text" name="answer_hu" id="editAH" class="flex-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-green-400 focus:outline-none focus:border-indigo-500">
                        <button type="button" onclick="aiFromModal('ai_answer')" class="bg-violet-600 hover:bg-violet-500 px-3 py-2 rounded-lg text-xs font-semibold transition-all shrink-0" title="AI Generate Answer">AI Ans</button>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">English</label>
                    <input type="text" name="answer_en" id="editAE" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Category</label>
                        <input type="text" name="category" id="editCat" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="w-28">
                        <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Who</label>
                        <select name="phrase_who" id="editWho" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                            <option value="All">All</option>
                            <option value="Maria">Maria</option>
                            <option value="Larry">Larry</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="bg-green-600 hover:bg-green-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Save</button>
                <button type="button" onclick="closeEdit()" class="bg-slate-700 hover:bg-slate-600 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Cancel</button>
            </div>
        </form>
        <div id="aiStatus" class="text-xs text-slate-500 hidden"></div>
    </div>
</div>

<!-- Bio Edit/Add Modal -->
<div id="bioModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeBioModal()">
    <div class="bg-[#111a2e] border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider" id="bioModalTitle">Add Bio Fact</h3>
        <form method="POST" id="bioForm">
            <input type="hidden" name="action" id="bioAction" value="bio_add">
            <input type="hidden" name="id" id="bioIdInput" value="">
            <div class="space-y-3">
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Person</label>
                    <select name="subject_name" id="bioSN" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                        <option value="Maria">Maria</option>
                        <option value="Larry">Larry</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Label (English description)</label>
                    <input type="text" name="fact_label_hu" id="bioFL" placeholder="e.g. Favorite food, Birthday, City" class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Value (Hungarian)</label>
                    <input type="text" name="fact_value_hu" id="bioFV" placeholder="e.g. A kedvenc magyar ételem a gulyás." class="w-full mt-1 bg-[#0c1222] border border-white/10 rounded-lg px-3 py-2 text-sm text-green-400 focus:outline-none focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button type="button" onclick="closeBioModal()" class="bg-slate-700 hover:bg-slate-600 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-500 px-5 py-2 rounded-lg text-sm font-semibold transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRow(id) {
    var row = document.getElementById('row-' + id);
    document.getElementById('editIdInput').value = id;
    document.getElementById('editId').textContent = '#' + id;
    document.getElementById('editQ').value = row.dataset.q;
    document.getElementById('editAH').value = row.dataset.ah;
    document.getElementById('editAE').value = row.dataset.ae;
    document.getElementById('editCat').value = row.dataset.cat;
    document.getElementById('editWho').value = row.dataset.who || 'All';
    document.getElementById('aiStatus').classList.add('hidden');
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEdit() {
    document.getElementById('editModal').classList.add('hidden');
}

function aiGenerate(id, action, evt) {
    var row = document.getElementById('row-' + id);
    var btn = (evt || window.event).target;
    var origText = btn.textContent;
    btn.textContent = '...';
    btn.disabled = true;

    var fd = new FormData();
    fd.append('question_hu', row.dataset.q);
    fd.append('answer_en', row.dataset.ae);
    fd.append('answer_hu', row.dataset.ah);

    fetch('admin.php?ajax=1&action=' + action, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            // Open edit modal with AI result pre-filled
            editRow(id);
            if (action === 'ai_answer') {
                document.getElementById('editAH').value = data.result;
            } else {
                document.getElementById('editQ').value = data.result;
            }
            var status = document.getElementById('aiStatus');
            status.textContent = 'AI generated — review and save.';
            status.classList.remove('hidden');
        })
        .catch(function() { alert('AI request failed'); })
        .finally(function() { btn.textContent = origText; btn.disabled = false; });
}

function aiFromModal(action) {
    var status = document.getElementById('aiStatus');
    status.textContent = 'Generating...';
    status.classList.remove('hidden');

    var fd = new FormData();
    fd.append('question_hu', document.getElementById('editQ').value);
    fd.append('answer_en', document.getElementById('editAE').value);
    fd.append('answer_hu', document.getElementById('editAH').value);

    fetch('admin.php?ajax=1&action=' + action, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { status.textContent = data.error; return; }
            if (action === 'ai_answer') {
                document.getElementById('editAH').value = data.result;
            } else {
                document.getElementById('editQ').value = data.result;
            }
            status.textContent = 'AI generated — review and save.';
        })
        .catch(function() { status.textContent = 'AI request failed'; });
}

function updateBulkBar() {
    var checked = document.querySelectorAll('.row-cb:checked');
    var bar = document.getElementById('bulkBar');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        document.getElementById('bulkCount').textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
    }
}

function clearSelection() {
    document.querySelectorAll('.row-cb').forEach(function(c) { c.checked = false; });
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
}

function bulkDelete() {
    var ids = [];
    document.querySelectorAll('.row-cb:checked').forEach(function(c) { ids.push(parseInt(c.value)); });
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' phrases? This cannot be undone.')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    var a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'delete_bulk'; form.appendChild(a);
    var d = document.createElement('input'); d.type = 'hidden'; d.name = 'delete_ids'; d.value = JSON.stringify(ids); form.appendChild(d);
    document.body.appendChild(form);
    form.submit();
}

function confirmMigrate(form) {
    var ids = [];
    document.querySelectorAll('.migrate-cb:checked').forEach(function(cb) { ids.push(parseInt(cb.value)); });
    if (!ids.length) { alert('No rows selected'); return false; }
    document.getElementById('migrateIds').value = JSON.stringify(ids);
    return confirm('Move ' + ids.length + ' answers from English → Hungarian column?');
}

// Missing column filter
var activeMissing = '';
function toggleMissing(field) {
    activeMissing = (activeMissing === field) ? '' : field;
    document.getElementById('btn-ah').classList.toggle('bg-yellow-500/20', activeMissing === 'ah');
    document.getElementById('btn-ae').classList.toggle('bg-yellow-500/20', activeMissing === 'ae');
    filterTable();
}

// Live search + category filter
var searchInput = document.getElementById('liveSearch');
var catSelect = document.getElementById('catSelect');
var whoSelect = document.getElementById('whoSelect');
var clearBtn = document.getElementById('clearSearch');
var debounceTimer;

function filterTable() {
    var term = searchInput.value.toLowerCase();
    var cat = catSelect.value;
    var whoVal = whoSelect.value;
    clearBtn.classList.toggle('hidden', !term);
    var rows = document.querySelectorAll('.phrase-row');
    rows.forEach(function(row) {
        var text = (row.dataset.q + ' ' + row.dataset.ah + ' ' + row.dataset.ae).toLowerCase();
        var matchSearch = !term || text.indexOf(term) !== -1;
        var matchCat = !cat || row.dataset.cat === cat;
        var matchWho = !whoVal || row.dataset.who === whoVal;
        var matchMissing = !activeMissing
            || (activeMissing === 'ah' && !row.dataset.ah)
            || (activeMissing === 'ae' && !row.dataset.ae);
        row.style.display = (matchSearch && matchCat && matchWho && matchMissing) ? '' : 'none';
    });
}

searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(filterTable, 150);
});
catSelect.addEventListener('change', filterTable);
whoSelect.addEventListener('change', filterTable);

// ── Bio functions ──
function openBioAdd() {
    document.getElementById('bioAction').value = 'bio_add';
    document.getElementById('bioIdInput').value = '';
    document.getElementById('bioModalTitle').textContent = 'Add Bio Fact';
    document.getElementById('bioSN').value = 'Maria';
    document.getElementById('bioFL').value = '';
    document.getElementById('bioFV').value = '';
    document.getElementById('bioModal').classList.remove('hidden');
}

function editBio(id) {
    var row = document.querySelector('.bio-row[data-id="' + id + '"]');
    document.getElementById('bioAction').value = 'bio_update';
    document.getElementById('bioIdInput').value = id;
    document.getElementById('bioModalTitle').textContent = 'Edit Bio #' + id;
    document.getElementById('bioSN').value = row.dataset.sn;
    document.getElementById('bioFL').value = row.dataset.fl;
    document.getElementById('bioFV').value = row.dataset.fv;
    document.getElementById('bioModal').classList.remove('hidden');
}

function closeBioModal() {
    document.getElementById('bioModal').classList.add('hidden');
}

function updateBioBulkBar() {
    var checked = document.querySelectorAll('.bio-cb:checked');
    var bar = document.getElementById('bioBulkBar');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        document.getElementById('bioBulkCount').textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
    }
}

function bioClearSelection() {
    document.querySelectorAll('.bio-cb').forEach(function(c) { c.checked = false; });
    document.getElementById('bioSelectAll').checked = false;
    updateBioBulkBar();
}

function bioBulkDelete() {
    var ids = [];
    document.querySelectorAll('.bio-cb:checked').forEach(function(c) { ids.push(parseInt(c.value)); });
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' bio facts? This cannot be undone.')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    var a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'bio_delete_bulk'; form.appendChild(a);
    var d = document.createElement('input'); d.type = 'hidden'; d.name = 'delete_ids'; d.value = JSON.stringify(ids); form.appendChild(d);
    document.body.appendChild(form);
    form.submit();
}

// Bio search + filter
var bioSearchInput = document.getElementById('bioSearch');
var bioWhoFilter = document.getElementById('bioWhoFilter');
var bioDebounce;

function filterBios() {
    var term = bioSearchInput.value.toLowerCase();
    var who = bioWhoFilter.value;
    document.querySelectorAll('.bio-row').forEach(function(row) {
        var text = (row.dataset.sn + ' ' + row.dataset.fl + ' ' + row.dataset.fv).toLowerCase();
        var matchSearch = !term || text.indexOf(term) !== -1;
        var matchWho = !who || row.dataset.sn === who;
        row.style.display = (matchSearch && matchWho) ? '' : 'none';
    });
}

bioSearchInput.addEventListener('input', function() {
    clearTimeout(bioDebounce);
    bioDebounce = setTimeout(filterBios, 150);
});
bioWhoFilter.addEventListener('change', filterBios);

// ── AI Group Generator ──
var generatedItems = [];

function generateGroup() {
    var topic = document.getElementById('groupTopic').value.trim();
    if (!topic) { alert('Enter a topic first'); return; }

    var btn = document.getElementById('groupGenBtn');
    var status = document.getElementById('groupStatus');
    btn.textContent = 'Generating...';
    btn.disabled = true;
    status.textContent = 'Asking AI to generate phrases for "' + topic + '"...';
    status.classList.remove('hidden');

    var catInput = document.getElementById('groupCategory');
    if (!catInput.value.trim()) {
        catInput.value = topic.split(' ').map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1); }).join(' ');
    }

    var fd = new FormData();
    fd.append('topic', topic);

    fetch('admin.php?ajax=1&action=ai_group', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { status.textContent = 'Error: ' + data.error; return; }
            generatedItems = data.items;
            renderGroupPreview();
            status.textContent = 'Generated ' + data.items.length + ' items. Review and save.';
        })
        .catch(function() { status.textContent = 'Request failed'; })
        .finally(function() { btn.textContent = 'Generate'; btn.disabled = false; });
}

function makeCell(text, cls) {
    var td = document.createElement('td');
    td.className = 'py-2 px-2 ' + cls;
    td.textContent = text;
    return td;
}

function renderGroupPreview() {
    var body = document.getElementById('groupBody');
    body.textContent = '';
    generatedItems.forEach(function(item, i) {
        var tr = document.createElement('tr');
        tr.className = 'border-t border-white/5';
        var cbTd = document.createElement('td');
        cbTd.className = 'py-2 px-2';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'group-cb accent-violet-500';
        cb.dataset.idx = i;
        cb.checked = true;
        cbTd.appendChild(cb);
        tr.appendChild(cbTd);
        tr.appendChild(makeCell(item.question_hu, 'text-white'));
        tr.appendChild(makeCell(item.answer_hu || '', 'text-green-400'));
        tr.appendChild(makeCell(item.answer_en || '', 'text-slate-400'));
        body.appendChild(tr);
    });
    document.getElementById('groupCount').textContent = '(' + generatedItems.length + ' items)';
    document.getElementById('groupPreview').classList.remove('hidden');
}

function saveGroup() {
    var cat = document.getElementById('groupCategory').value.trim() || 'Practice';
    var selected = [];
    document.querySelectorAll('.group-cb:checked').forEach(function(cb) {
        selected.push(generatedItems[parseInt(cb.dataset.idx)]);
    });
    if (!selected.length) { alert('No items selected'); return; }

    var rows = selected.map(function(item) {
        return { question_hu: item.question_hu, answer_hu: item.answer_hu || '', answer_en: item.answer_en || '', category: cat, who: 'All' };
    });

    var form = document.createElement('form');
    form.method = 'POST';
    var a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'import'; form.appendChild(a);
    var d = document.createElement('input'); d.type = 'hidden'; d.name = 'rows_json'; d.value = JSON.stringify(rows); form.appendChild(d);
    document.body.appendChild(form);
    form.submit();
}

function clearGroup() {
    generatedItems = [];
    document.getElementById('groupPreview').classList.add('hidden');
    document.getElementById('groupStatus').classList.add('hidden');
}

// Close modals on Escape
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeEdit(); closeBioModal(); } });
</script>

</body>
</html>
