<?php
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
$who = isset($_GET['who']) ? $_GET['who'] : 'All';
$who = in_array($who, ['Maria', 'Larry', 'All']) ? $who : 'All';
$cat = $_GET['cat'] ?? 'all';
$cat = in_array($cat, ['all','prep','bios']) ? $cat : 'all';

// Ensure answer_hu column exists (safe for MySQL 5.7)
$colCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'answer_hu'");
$hasAnswerHu = ($colCheck && $colCheck->num_rows > 0);
// Ensure who column exists
$whoCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'who'");
if ($whoCheck && $whoCheck->num_rows === 0) {
    $conn->query("ALTER TABLE hungarian_prep ADD COLUMN `who` VARCHAR(10) DEFAULT 'All' AFTER category");
}

$who_safe   = $conn->real_escape_string($who);
$bio_filter = ($who !== 'All')
    ? "WHERE subject_name = '$who_safe' AND fact_label_hu LIKE '%?'"
    : "WHERE fact_label_hu LIKE '%?'";

// ALL and PHRASES → hungarian_prep only (always Hungarian)
// PERSONAL        → user_bios personal facts (explicit opt-in)
$parts = [];
if ($cat === 'bios') {
    $parts[] = "SELECT fact_label_hu AS q, fact_value_hu AS a, '' AS a_hu, category FROM user_bios $bio_filter";
} else {
    $ahuCol = $hasAnswerHu ? "COALESCE(answer_hu,'')" : "''";
    // Filter by who: show All + user-specific questions
    $whoFilter = ($who !== 'All') ? " WHERE (`who` = 'All' OR `who` = '$who_safe')" : "";
    $parts[] = "SELECT question_hu AS q, answer_en AS a, $ahuCol AS a_hu, category FROM hungarian_prep$whoFilter";
}
$union = implode(' UNION ', $parts);

// AJAX: save a phrase from practice
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'save_phrase') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }
    $q  = trim($_POST['question_hu'] ?? '');
    $ae = trim($_POST['answer_en'] ?? '');
    $ah = trim($_POST['answer_hu'] ?? '') ?: null;
    $pc = trim($_POST['category'] ?? 'Practice');
    if ($q === '') { echo json_encode(['error'=>'No phrase provided']); exit; }
    // Check for duplicate
    $chk = $conn->prepare("SELECT id FROM hungarian_prep WHERE question_hu = ?");
    $chk->bind_param('s', $q);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        echo json_encode(['ok'=>true, 'msg'=>'Already in database']);
        exit;
    }
    $chk->close();
    $stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_hu, answer_en, category, `who`) VALUES (?, ?, ?, ?, 'All')");
    $stmt->bind_param('ssss', $q, $ah, $ae, $pc);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>true, 'msg'=>'Saved!']);
    exit;
}

// AJAX: list all phrases for browser
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'phrases') {
    header('Content-Type: application/json');
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $ahuBrowse = $hasAnswerHu ? "COALESCE(answer_hu,'') AS a_hu," : "'' AS a_hu,";
    $sql = "SELECT question_hu AS q, answer_en AS a, $ahuBrowse category FROM hungarian_prep";
    if ($search) $sql .= " WHERE question_hu LIKE '%$search%' OR answer_en LIKE '%$search%'";
    $sql .= " ORDER BY category, question_hu";
    $result = $conn->query($sql);
    $rows = [];
    if ($result) { while ($r = $result->fetch_assoc()) $rows[] = $r; }
    foreach ($rows as &$row) {
        $q_safe = $conn->real_escape_string($row['q']);
        $sh = $conn->query("SELECT pass_count, fail_count, next_review FROM study_history WHERE phrase='$q_safe' AND who='$who_safe' LIMIT 1");
        $srs = $sh ? $sh->fetch_assoc() : null;
        $row['pass_count'] = (int)($srs['pass_count'] ?? 0);
        $row['fail_count'] = (int)($srs['fail_count'] ?? 0);
        $row['next_review'] = $srs['next_review'] ?? null;
    }
    echo json_encode($rows);
    exit;
}

// AJAX: stats dashboard
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'stats') {
    header('Content-Type: application/json');
    $total = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep")->fetch_assoc()['c'] ?? 0;
    $studied = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe'")->fetch_assoc()['c'] ?? 0;
    $mastered = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND pass_count >= 3")->fetch_assoc()['c'] ?? 0;
    $due = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND next_review <= NOW()")->fetch_assoc()['c'] ?? 0;
    $weak = $conn->query("SELECT phrase, fail_count, pass_count FROM study_history WHERE who='$who_safe' AND fail_count > 0 ORDER BY fail_count DESC LIMIT 8");
    $weakList = [];
    if ($weak) { while ($r = $weak->fetch_assoc()) $weakList[] = $r; }
    $recent = $conn->query("SELECT phrase, pass_count, fail_count, last_seen FROM study_history WHERE who='$who_safe' ORDER BY last_seen DESC LIMIT 8");
    $recentList = [];
    if ($recent) { while ($r = $recent->fetch_assoc()) $recentList[] = $r; }
    echo json_encode(['total'=>(int)$total, 'studied'=>(int)$studied, 'mastered'=>(int)$mastered, 'due'=>(int)$due, 'weak'=>$weakList, 'recent'=>$recentList]);
    exit;
}

// Helper: build SQL WHERE clause from comma-separated tag patterns
function buildTagWhere($tagMatch, $conn) {
    $tags = array_map('trim', explode(',', $tagMatch));
    $clauses = [];
    foreach ($tags as $t) {
        if ($t === '') continue;
        $t = $conn->real_escape_string($t);
        $clauses[] = "tags LIKE '%$t%'";
    }
    return $clauses ? '(' . implode(' OR ', $clauses) . ')' : '0';
}

// AJAX: list drill groups
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'drill_groups') {
    header('Content-Type: application/json');
    $groups = [];
    // Check if tag_match column exists
    $tmCheck = $conn->query("SHOW COLUMNS FROM drill_groups LIKE 'tag_match'");
    $hasTM = ($tmCheck && $tmCheck->num_rows > 0);
    $cols = $hasTM ? "id, name, description, tag_match, source" : "id, name, description, '' AS tag_match, source";
    $r = $conn->query("SELECT $cols FROM drill_groups ORDER BY name");
    if ($r) { while ($row = $r->fetch_assoc()) {
        $tagMatch = $row['tag_match'] ?: $row['name'];
        $where = buildTagWhere($tagMatch, $conn);
        $cnt = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep WHERE $where")->fetch_assoc()['c'] ?? 0;
        $row['phrase_count'] = (int)$cnt;
        $groups[] = $row;
    }}
    echo json_encode($groups);
    exit;
}

// AJAX: get phrases for a drill group (by tag_match)
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'drill_phrases') {
    header('Content-Type: application/json');
    $groupName = $conn->real_escape_string($_GET['tag'] ?? '');
    if (!$groupName) { echo json_encode([]); exit; }
    // Look up tag_match from drill_groups
    $tmCheck = $conn->query("SHOW COLUMNS FROM drill_groups LIKE 'tag_match'");
    $hasTM = ($tmCheck && $tmCheck->num_rows > 0);
    $tagMatch = $groupName;
    if ($hasTM) {
        $lookup = $conn->query("SELECT tag_match FROM drill_groups WHERE name = '$groupName' LIMIT 1");
        if ($lookup && $row = $lookup->fetch_assoc()) {
            $tagMatch = $row['tag_match'] ?: $groupName;
        }
    }
    $where = buildTagWhere($tagMatch, $conn);
    $ahuCol = $hasAnswerHu ? "COALESCE(answer_hu,'')" : "''";
    $whoFilter = ($who !== 'All') ? " AND (`who` = 'All' OR `who` = '$who_safe')" : "";
    $sql = "SELECT question_hu AS q, answer_en AS a, $ahuCol AS a_hu, category, tags
            FROM hungarian_prep
            WHERE ($where OR drill_group = '$groupName')$whoFilter
            ORDER BY RAND()";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// AJAX: list grammar patterns
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'grammar_patterns') {
    header('Content-Type: application/json');
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $tagFilter = $conn->real_escape_string($_GET['tag'] ?? '');
    $sql = "SELECT id, pattern, suffix_words, explanation, part_of_speech, tags FROM grammar_patterns WHERE 1=1";
    if ($search) $sql .= " AND (pattern LIKE '%$search%' OR explanation LIKE '%$search%' OR suffix_words LIKE '%$search%')";
    if ($tagFilter) $sql .= " AND tags LIKE '%$tagFilter%'";
    $sql .= " ORDER BY pattern";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// AJAX: home screen stats (due count, streak, drill groups preview)
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'home_stats') {
    header('Content-Type: application/json');
    $due = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND next_review <= NOW()");
    if ($r) $due = (int)($r->fetch_assoc()['c'] ?? 0);
    $total = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep");
    if ($r) $total = (int)($r->fetch_assoc()['c'] ?? 0);
    $studied = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe'");
    if ($r) $studied = (int)($r->fetch_assoc()['c'] ?? 0);
    $mastered = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND pass_count >= 3");
    if ($r) $mastered = (int)($r->fetch_assoc()['c'] ?? 0);
    // Streak: consecutive days with at least one study_history entry
    $streak = 0;
    $r = $conn->query("SELECT DISTINCT DATE(last_seen) AS d FROM study_history WHERE who='$who_safe' ORDER BY d DESC LIMIT 60");
    if ($r) {
        $today = new DateTime('today');
        $checkDate = clone $today;
        while ($row = $r->fetch_assoc()) {
            $d = new DateTime($row['d']);
            if ($d->format('Y-m-d') === $checkDate->format('Y-m-d')) {
                $streak++;
                $checkDate->modify('-1 day');
            } else { break; }
        }
    }
    // Top 5 drill groups
    $groups = [];
    $tmCheck = $conn->query("SHOW COLUMNS FROM drill_groups LIKE 'tag_match'");
    $hasTM = ($tmCheck && $tmCheck->num_rows > 0);
    $cols = $hasTM ? "id, name, description, tag_match" : "id, name, description, '' AS tag_match";
    $gq = $conn->query("SELECT $cols FROM drill_groups ORDER BY name LIMIT 6");
    if ($gq) { while ($row = $gq->fetch_assoc()) {
        $tagMatch = $row['tag_match'] ?: $row['name'];
        $where = buildTagWhere($tagMatch, $conn);
        $cnt = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep WHERE $where")->fetch_assoc()['c'] ?? 0;
        $row['phrase_count'] = (int)$cnt;
        $groups[] = $row;
    }}
    // Grammar pattern count
    $grammarCount = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM grammar_patterns");
    if ($r) $grammarCount = (int)($r->fetch_assoc()['c'] ?? 0);
    echo json_encode([
        'due' => $due, 'total' => $total, 'studied' => $studied, 'mastered' => $mastered,
        'streak' => $streak, 'groups' => $groups, 'grammar_count' => $grammarCount
    ]);
    exit;
}

// AJAX: AI teach me — generate a mini-lesson for a grammar pattern
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'teach_me') {
    header('Content-Type: application/json');
    $pattern = trim($_POST['pattern'] ?? '');
    $suffix = trim($_POST['suffix_words'] ?? '');
    $explanation = trim($_POST['explanation'] ?? '');
    if (!$pattern) { echo json_encode(['error' => 'No pattern']); exit; }

    $prompt = "You are a Hungarian language tutor helping an English speaker prepare for the simplified naturalization interview (egyszerűsített honosítás).

Teach this grammar pattern: **$pattern**
" . ($suffix ? "Example words/suffixes: $suffix\n" : "") . ($explanation ? "Brief explanation: $explanation\n" : "") . "

Respond in JSON with this exact structure:
{
  \"lesson\": \"A clear, concise explanation (2-3 sentences) of what this pattern means and when to use it. Use simple English.\",
  \"examples\": [
    {\"hu\": \"Hungarian example sentence\", \"en\": \"English translation\", \"highlight\": \"the word(s) showing the pattern\"},
    {\"hu\": \"...\", \"en\": \"...\", \"highlight\": \"...\"},
    {\"hu\": \"...\", \"en\": \"...\", \"highlight\": \"...\"}
  ],
  \"quiz\": [
    {\"prompt\": \"Fill in: Budapesten ___. (I live)\", \"answer\": \"lakom\", \"hint\": \"Use the -k ending for 'I'\"},
    {\"prompt\": \"...\", \"answer\": \"...\", \"hint\": \"...\"},
    {\"prompt\": \"...\", \"answer\": \"...\", \"hint\": \"...\"}
  ],
  \"tip\": \"One practical tip or mnemonic to remember this pattern.\"
}

Give exactly 3 examples and 3 quiz questions. Make them relevant to daily life and interview topics (family, work, where you live, why you want citizenship). Keep quiz prompts as fill-in-the-blank.";

    $apiKey = $env['GEMINI_KEY'];
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=$apiKey";
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 2048, 'responseMimeType' => 'application/json']
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($httpCode !== 200 || !$resp) {
        $errBody = $resp ? json_decode($resp, true) : null;
        $msg = $errBody['error']['message'] ?? ($curlErr ?: "HTTP $httpCode");
        echo json_encode(['error' => 'Gemini API error: ' . $msg]);
        exit;
    }
    $data = json_decode($resp, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $lesson = json_decode($text, true);
    if (!$lesson) { echo json_encode(['error' => 'Failed to parse AI response', 'raw' => $text]); exit; }
    echo json_encode($lesson);
    exit;
}

// AJAX: skill proficiency for current user
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'skill_proficiency') {
    header('Content-Type: application/json');
    $sql = "SELECT skill, pass_count, fail_count, level, last_seen FROM skill_proficiency WHERE who='$who_safe' ORDER BY fail_count DESC, skill";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// Shuffle bypasses SRS, pure random
$shuffle = isset($_GET['shuffle']) && $_GET['shuffle'] === '1';
if ($shuffle) {
    $result = $conn->query("SELECT q, a, a_hu, category FROM ($union) AS phrases ORDER BY RAND() LIMIT 1");
} else {
    // SRS-weighted query
    $srs_sql = "SELECT phrases.q, phrases.a, phrases.a_hu, phrases.category
                FROM ($union) AS phrases
                LEFT JOIN study_history sh ON sh.phrase = phrases.q AND sh.who = '$who_safe'
                ORDER BY CASE WHEN sh.next_review IS NULL OR sh.next_review <= NOW() THEN 0 ELSE 1 END ASC, RAND()
                LIMIT 1";
    $result = $conn->query($srs_sql);
    if (!$result) {
        $result = $conn->query("SELECT q, a, a_hu, category FROM ($union) AS phrases ORDER BY RAND() LIMIT 1");
    }
}
$row     = $result ? $result->fetch_assoc() : null;
$targetQ  = $row['q'] ?? 'No Data Found';
$targetA  = $row['a'] ?? 'Sync n8n';
$targetAH = $row['a_hu'] ?? '';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['q' => $targetQ, 'a' => $targetA, 'a_hu' => $targetAH, 'category' => $row['category'] ?? 'General']);
    exit;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>HUG COACH v6.0</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                surface: { 50: '#0c1222', 100: '#111a2e', 200: '#172032', 300: '#1e293b', 400: '#334155' },
                accent: { DEFAULT: '#6366f1', light: '#818cf8', dark: '#4f46e5' },
            }
        }
    }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { font-family: 'Inter', system-ui, sans-serif; }
body { background: #060b18; color: #e2e8f0; overflow-x: hidden; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
.glass { background: rgba(17, 26, 46, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(99, 102, 241, 0.08); }
.glass-strong { background: rgba(17, 26, 46, 0.95); backdrop-filter: blur(30px); border: 1px solid rgba(99, 102, 241, 0.15); }
.glow-accent { box-shadow: 0 0 30px rgba(99, 102, 241, 0.15), 0 0 60px rgba(99, 102, 241, 0.05); }
.glow-red { box-shadow: 0 0 25px rgba(239, 68, 68, 0.3); }
.glow-green { box-shadow: 0 0 25px rgba(34, 197, 94, 0.3); }
@keyframes mic-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); } }
.mic-active { animation: mic-pulse 1.5s ease-in-out infinite; background: #dc2626 !important; }
.progress-track { background: rgba(99, 102, 241, 0.1); }
.progress-fill { background: linear-gradient(90deg, #6366f1, #a78bfa); transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
.status-dot { width: 10px; height: 10px; border-radius: 50%; transition: all 0.3s; }
.dot-off { background: #334155; }
.dot-warmup { background: #eab308; box-shadow: 0 0 8px #eab308; }
.dot-live { background: #ef4444; box-shadow: 0 0 12px #ef4444; }
.vol-track { width: 48px; height: 4px; background: #1e293b; border-radius: 2px; overflow: hidden; }
.vol-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #22c55e, #4ade80); border-radius: 2px; transition: width 0.05s; }
.listen-blur { filter: blur(16px); cursor: pointer; transition: filter 0.4s ease; user-select: none; }
.modal-backdrop { background: rgba(6, 11, 24, 0.9); backdrop-filter: blur(8px); }
.pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; transition: all 0.2s; cursor: pointer; user-select: none; }
.pill-active { background: #6366f1; color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); }
.pill-inactive { color: #cbd5e1; border: 1px solid rgba(255,255,255,0.15); }
.pill-inactive:hover { color: #f1f5f9; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.25); }
.ctrl-btn { display: flex; align-items: center; justify-content: center; border-radius: 16px; transition: all 0.2s; }
.ctrl-btn:active { transform: scale(0.95); }
.result-pass { border-color: rgba(34, 197, 94, 0.3); background: rgba(34, 197, 94, 0.05); }
.result-fail { border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); }
.phrase-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; border-radius: 12px; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; }
.phrase-item:hover { background: rgba(255,255,255,0.03); border-color: rgba(99, 102, 241, 0.2); }
.mastery-new { background: #475569; }
.mastery-learning { background: #eab308; }
.mastery-known { background: #3b82f6; }
.mastery-mastered { background: #22c55e; }
.question-text { font-size: clamp(1.5rem, 5vw, 3rem); line-height: 1.2; font-weight: 800; letter-spacing: -0.02em; }
.kbd { display: inline-flex; align-items: center; justify-content: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-family: monospace; background: rgba(255,255,255,0.05); color: #64748b; border: 1px solid rgba(255,255,255,0.1); }
.quick-bar { position: fixed; bottom: 0; left: 0; right: 0; display: flex; justify-content: space-around; align-items: center; padding: 8px 16px; background: rgba(17, 26, 46, 0.95); backdrop-filter: blur(30px); z-index: 40; border-top: 1px solid rgba(255,255,255,0.05); }
@media (min-width: 768px) { .quick-bar { position: static; border: none; background: transparent; backdrop-filter: none; justify-content: center; gap: 8px; margin-top: 24px; } }
.view-section { display: none; }
.view-section.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.view-section.active { animation: fadeIn 0.2s ease-out; }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.grammar-card { background: rgba(17, 26, 46, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; transition: all 0.2s; cursor: pointer; }
.grammar-card:hover { border-color: rgba(99, 102, 241, 0.2); background: rgba(17, 26, 46, 0.8); }
.drill-card { background: rgba(17, 26, 46, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 16px 20px; transition: all 0.2s; cursor: pointer; }
.drill-card:hover { border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.05); transform: translateY(-1px); }
.tag-pill { display: inline-flex; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; background: rgba(99, 102, 241, 0.1); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.15); }
.tag-pill-active { background: rgba(99, 102, 241, 0.35); border-color: rgba(99, 102, 241, 0.5); color: #fff; }
select option { background: #111a2e; color: #e2e8f0; }
</style>
</head>
<body class="min-h-screen flex flex-col items-center pb-20 md:pb-6">

<!-- SESSION SUMMARY MODAL -->
<div id="summaryModal" class="hidden fixed inset-0 modal-backdrop flex items-center justify-center z-50 p-4">
    <div class="glass-strong rounded-3xl p-8 text-center max-w-sm w-full shadow-2xl glow-accent">
        <div class="w-16 h-16 rounded-full bg-accent/20 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="trophy" class="w-8 h-8 text-accent-light"></i>
        </div>
        <h2 class="text-xl font-bold text-white mb-1">Session Complete</h2>
        <p class="text-slate-500 text-xs uppercase tracking-widest mb-6">10 questions</p>
        <div class="flex justify-around mb-8">
            <div>
                <div id="summaryPass" class="text-3xl font-black text-green-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Passed</div>
            </div>
            <div class="w-px bg-slate-700/50"></div>
            <div>
                <div id="summaryFail" class="text-3xl font-black text-red-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Missed</div>
            </div>
            <div class="w-px bg-slate-700/50"></div>
            <div>
                <div id="summaryStreak" class="text-3xl font-black text-amber-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Best Streak</div>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="closeSummary(false)"
                class="flex-1 bg-surface-300 hover:bg-surface-400 py-3 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2 text-slate-300">
                Done
            </button>
            <button onclick="closeSummary(true)"
                class="flex-1 bg-accent hover:bg-accent-dark py-3 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2 text-white">
                Keep Going <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
</div>

<!-- PHRASE BROWSER MODAL -->
<div id="browseModal" class="hidden fixed inset-0 modal-backdrop z-50 flex flex-col">
    <div class="glass-strong max-w-2xl w-full mx-auto mt-4 md:mt-12 rounded-t-3xl md:rounded-3xl flex-1 md:flex-initial md:max-h-[80vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-white/5">
            <h2 class="text-lg font-bold flex items-center gap-2"><i data-lucide="book-open" class="w-5 h-5 text-accent-light"></i> Phrase Browser</h2>
            <button onclick="closeBrowse()" class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="px-5 py-3 border-b border-white/5">
            <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2">
                <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                <input id="browseSearch" type="text" placeholder="Search phrases..." oninput="searchPhrases()"
                    class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
            </div>
        </div>
        <div id="browseList" class="flex-1 overflow-y-auto p-4 space-y-1"></div>
        <div class="p-4 border-t border-white/5 text-center">
            <span id="browseCount" class="text-xs text-slate-500"></span>
        </div>
    </div>
</div>

<!-- STATS MODAL -->
<div id="statsModal" class="hidden fixed inset-0 modal-backdrop z-50 flex flex-col">
    <div class="glass-strong max-w-2xl w-full mx-auto mt-4 md:mt-12 rounded-t-3xl md:rounded-3xl flex-1 md:flex-initial md:max-h-[80vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-white/5">
            <h2 class="text-lg font-bold flex items-center gap-2"><i data-lucide="bar-chart-3" class="w-5 h-5 text-accent-light"></i> Progress Dashboard</h2>
            <button onclick="closeStats()" class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div id="statsContent" class="flex-1 overflow-y-auto p-5 space-y-6">
            <p class="text-slate-500 text-sm text-center">Loading...</p>
        </div>
    </div>
</div>

<!-- MAIN APP -->
<div class="w-full max-w-2xl px-4 pt-4 md:pt-8 space-y-4">

    <!-- HEADER -->
    <header class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-accent/20 flex items-center justify-center">
                <span class="text-sm">&#x1f1ed;&#x1f1fa;</span>
            </div>
            <div>
                <span class="text-sm font-bold tracking-wide text-white">HUG COACH</span>
                <span class="text-[10px] text-slate-500 ml-1.5">v6.0</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
        <a href="admin.php" title="Admin" class="p-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition-all">
            <i data-lucide="settings" class="w-4 h-4"></i>
        </a>
        <div class="flex items-center gap-1 bg-surface-100 p-1 rounded-xl border border-white/5">
            <a href="?who=Maria&cat=<?php echo $cat; ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'Maria' ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">Maria</a>
            <a href="?who=Larry&cat=<?php echo $cat; ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'Larry' ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">Larry</a>
            <a href="?who=All&cat=<?php echo $cat; ?>"   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'All'   ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">All</a>
        </div>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: HOME SCREEN -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-home" class="view-section active space-y-4">

    <!-- Streak + Due + Stats Banner -->
    <div class="flex items-center gap-2">
        <div class="glass rounded-xl px-3 py-2 flex items-center gap-1.5">
            <i data-lucide="flame" class="w-4 h-4 text-amber-400"></i>
            <span id="homeStreak" class="text-sm font-black text-amber-400">0</span>
        </div>
        <div id="homeDueBadge" class="hidden glass rounded-xl px-3 py-2 flex items-center gap-1.5">
            <div class="w-2 h-2 rounded-full bg-red-400 animate-pulse"></div>
            <span id="homeDueCount" class="text-xs font-bold text-red-300">0 due</span>
        </div>
        <div class="ml-auto flex items-center gap-2 text-[10px] text-slate-500 font-medium">
            <span><span id="homeStudied" class="text-blue-400 font-bold">—</span> studied</span>
            <span><span id="homeMastered" class="text-green-400 font-bold">—</span> mastered</span>
        </div>
    </div>

    <!-- DRILL MODE BANNER (hidden unless drill active) -->
    <div id="drillBanner" class="hidden flex items-center justify-between bg-accent/10 border border-accent/20 rounded-xl px-4 py-2.5">
        <div class="flex items-center gap-2">
            <i data-lucide="dumbbell" class="w-4 h-4 text-accent-light"></i>
            <span id="drillBannerName" class="text-sm font-bold text-white"></span>
            <span id="drillBannerCount" class="text-[10px] text-slate-400"></span>
        </div>
        <button onclick="closeDrill()" class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold text-slate-400 hover:text-white hover:bg-white/5 transition-all">
            <i data-lucide="x" class="w-3.5 h-3.5"></i> Exit
        </button>
    </div>

    <!-- SESSION PROGRESS -->
    <div class="flex items-center gap-3">
        <div class="flex-1 h-2 progress-track rounded-full overflow-hidden">
            <div id="progressFill" class="h-full progress-fill rounded-full" style="width: 0%"></div>
        </div>
        <span id="progressLabel" class="text-[11px] text-slate-500 font-medium tabular-nums min-w-[3rem] text-right">0 / 10</span>
    </div>

    <!-- MAIN CARD -->
    <main class="glass rounded-3xl overflow-hidden glow-accent">

        <!-- Toolbar -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
            <div class="flex items-center gap-1.5">
                <button onclick="setMode('pronunciation')" id="btnPron" class="pill pill-active">
                    <i data-lucide="mic" class="w-3.5 h-3.5"></i> Pronounce
                </button>
                <button onclick="setMode('interview')" id="btnInterview" class="pill pill-inactive">
                    <i data-lucide="message-square" class="w-3.5 h-3.5"></i> Interview
                </button>
            </div>
            <div class="flex items-center gap-1">
                <button id="listenModeBtn" onclick="toggleListenMode()" title="Listen mode — hides text until you click"
                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-slate-200 hover:text-white hover:bg-white/5 transition-all text-[10px] font-semibold">
                    <i data-lucide="ear" class="w-3.5 h-3.5"></i> Listen
                </button>
                <button id="autoAdvanceBtn" onclick="toggleAutoAdvance()" title="Auto-advance to next question on pass"
                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-slate-200 hover:text-white hover:bg-white/5 transition-all text-[10px] font-semibold">
                    <i data-lucide="timer" class="w-3.5 h-3.5"></i> Auto-Next
                </button>
                <div class="flex items-center gap-1.5">
                    <div id="readyIndicator" class="status-dot dot-off"></div>
                    <div class="vol-track"><div id="volFill" class="vol-fill"></div></div>
                </div>
            </div>
        </div>

        <!-- Strictness + Repeat -->
        <div class="flex items-center justify-between px-5 py-2 border-b border-white/5 gap-3">
            <div class="flex items-center gap-2 flex-1">
                <span class="text-[10px] text-slate-300 font-semibold whitespace-nowrap">Strictness</span>
                <input type="range" id="strictSlider" min="1" max="5" value="2" class="w-20 h-1 accent-indigo-500 cursor-pointer">
                <span id="strictLabel" class="text-[10px] text-indigo-400 font-bold w-16">Meaning</span>
            </div>
            <button id="repeatFailBtn" onclick="toggleRepeatFail()" title="Speak correct answer after a fail"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition-all text-[10px] font-semibold border border-white/5">
                <i data-lucide="repeat" class="w-3 h-3"></i> Repeat on Fail
            </button>
        </div>

        <!-- Category Pills -->
        <div class="flex items-center gap-1.5 px-5 py-2.5 border-b border-white/5">
            <button id="cat-all"  onclick="setCat('all')"  class="pill pill-active">All</button>
            <button id="cat-prep" onclick="setCat('prep')" class="pill pill-inactive">Phrases</button>
            <button id="cat-bios" onclick="setCat('bios')" class="pill pill-inactive">Personal</button>
            <span id="categoryTag" class="ml-auto text-[10px] text-slate-600 font-medium uppercase tracking-wider"></span>
        </div>

        <!-- Question Area -->
        <div class="px-5 pt-6 pb-4 text-center">
            <h1 id="questionText" class="question-text text-white mb-3"><?php echo htmlspecialchars($targetQ); ?></h1>
            <div class="flex justify-center gap-4 mb-2">
                <button id="translateBtn" onclick="toggleTranslation()" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-white/15 text-slate-200 hover:text-blue-400 hover:border-blue-400/40 hover:bg-blue-400/5 transition-all">
                    <i data-lucide="languages" class="w-4 h-4"></i>
                    <span class="text-xs font-semibold">Translate</span>
                </button>
                <button id="phoneticBtn" onclick="togglePhonetic()" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-white/15 text-slate-200 hover:text-amber-400 hover:border-amber-400/40 hover:bg-amber-400/5 transition-all">
                    <i data-lucide="spell-check" class="w-4 h-4"></i>
                    <span class="text-xs font-semibold">Phonetic</span>
                </button>
            </div>
            <p id="inlineTranslation" class="hidden text-blue-300/80 text-sm mt-2 italic"></p>
            <p id="phoneticHint" class="hidden text-amber-300/80 text-sm mt-2 font-mono tracking-wide"></p>
        </div>

        <!-- Listen Button -->
        <div class="px-5 pb-4">
            <button onclick="speak(currentSpeed)" id="listenBtn"
                class="w-full bg-surface-50 border-2 border-accent/30 rounded-2xl py-6 flex flex-col items-center gap-2 group hover:bg-surface-200 hover:border-accent/50 transition-all active:scale-[0.98] shadow-lg shadow-accent/5">
                <i data-lucide="volume-2" class="w-8 h-8 text-accent-light group-hover:scale-110 transition-transform"></i>
                <span id="listenBtnLabel" class="text-[11px] font-bold text-accent-light uppercase tracking-[0.25em]">Listen &amp; Repeat</span>
            </button>
        </div>

        <!-- Speed Control -->
        <div class="flex items-center justify-center gap-2 px-5 pb-4">
            <i data-lucide="gauge" class="w-3.5 h-3.5 text-slate-300"></i>
            <span class="text-[10px] text-slate-300 font-medium">Speed</span>
            <div class="flex gap-1">
                <button onclick="setSpeed(0.5)" class="speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all" data-speed="0.5">0.5x</button>
                <button onclick="setSpeed(0.7)" class="speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all" data-speed="0.7">0.7x</button>
                <button onclick="setSpeed(1.0)" class="speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all" data-speed="1.0">1.0x</button>
                <button onclick="setSpeed(1.3)" class="speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all" data-speed="1.3">1.3x</button>
            </div>
        </div>

        <!-- Result Card -->
        <div id="resultCard" class="hidden mx-5 mb-4 border rounded-2xl p-5 text-center transition-all">
            <div id="matchScore" class="mb-3"></div>
            <p id="transcript" class="text-xs italic text-slate-500 mb-2"></p>
            <button id="playbackBtn" onclick="playMyVoice()"
                class="hidden inline-flex items-center gap-2 bg-surface-300 hover:bg-surface-400 px-4 py-2 rounded-xl text-xs font-semibold text-slate-300 transition-all mt-1">
                <i data-lucide="play" class="w-3.5 h-3.5"></i> Hear Your Answer
            </button>
        </div>

        <!-- Control Bar -->
        <div class="px-5 pb-4">
            <div class="flex items-center justify-center gap-3">
                <button id="slowBtn" onclick="toggleSlow()" title="Slow playback" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-16 h-16 bg-surface-300 hover:bg-surface-400 text-slate-200 hover:text-white">
                    <i data-lucide="snail" class="w-5 h-5"></i>
                    <span class="text-[9px] font-semibold">Slow</span>
                </button>
                <button id="recordBtn" onclick="toggleMic()" title="Mic [Space]" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-20 h-16 bg-green-600 hover:bg-green-500 text-white glow-green">
                    <i id="recordIcon" data-lucide="mic" class="w-6 h-6"></i>
                    <span id="recordLabel" class="text-[9px] font-semibold">Mic</span>
                </button>
                <button onclick="nextQuestion()" title="Next [Enter]" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-16 h-16 bg-accent hover:bg-accent-dark text-white">
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    <span class="text-[9px] font-semibold">Next</span>
                </button>
                <button onclick="shuffleQuestion()" title="Random question" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-16 h-16 bg-surface-300 hover:bg-surface-400 text-slate-200 hover:text-white">
                    <i data-lucide="shuffle" class="w-5 h-5"></i>
                    <span class="text-[9px] font-semibold">Shuffle</span>
                </button>
            </div>
            <div class="flex items-center justify-center gap-5 mt-3 text-xs">
                <span class="flex items-center gap-1 text-green-500/70">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> <span id="sesPass">0</span>
                </span>
                <span class="flex items-center gap-1 text-red-500/70">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i> <span id="sesFail">0</span>
                </span>
                <span class="flex items-center gap-1 text-amber-500/70">
                    <i data-lucide="flame" class="w-3.5 h-3.5"></i> <span id="sesStreak">0</span>
                </span>
            </div>
        </div>

        <!-- Reveal Answer -->
        <div class="px-5 pb-4">
            <details id="revealDetails" class="group">
                <summary class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-medium text-slate-200 hover:text-white hover:bg-white/5 transition-all cursor-pointer list-none border border-white/5">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                    <span class="text-xs font-semibold uppercase tracking-wider">Reveal Answer</span>
                </summary>
                <div class="mt-3 p-5 bg-accent/5 rounded-xl border border-accent/10">
                    <p id="answerText" class="text-lg text-slate-300 italic leading-relaxed"><?php echo htmlspecialchars($targetAH ?: $targetA); ?></p>
                    <?php if ($targetAH && $targetA): ?>
                    <p class="text-sm text-slate-500 mt-2"><?php echo htmlspecialchars($targetA); ?></p>
                    <?php endif; ?>
                </div>
            </details>
        </div>

    </main>

    <!-- Keyboard Shortcuts (desktop) -->
    <div class="hidden md:flex items-center justify-center gap-4 text-[10px] text-slate-300 mt-2">
        <span><span class="kbd">Space</span> Mic</span>
        <span><span class="kbd">&larr;</span> Prev</span>
        <span><span class="kbd">&rarr;</span> Next</span>
        <span><span class="kbd">&uarr;</span> Hear</span>
        <span><span class="kbd">&darr;</span> Reveal</span>
        <span><span class="kbd">Esc</span> Stop</span>
        <span><span class="kbd">S</span> Slow</span>
        <span><span class="kbd">T</span> Translate</span>
        <span><span class="kbd">P</span> Phonetic</span>
    </div>

    <!-- Focused Drill Picker + Grammar -->
    <div class="flex items-center gap-2">
        <div class="flex-1 relative">
            <select id="drillPicker" onchange="onDrillPick(this.value)"
                class="w-full bg-surface-100 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-semibold appearance-none cursor-pointer hover:border-accent/30 transition-all focus:outline-none focus:border-accent/40 pr-10">
                <option value="">Focused Drill...</option>
            </select>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
        </div>
        <button onclick="showView('grammar')" class="bg-surface-100 border border-white/10 rounded-xl px-4 py-3 flex items-center gap-2 text-sm font-semibold text-white hover:border-accent/30 transition-all flex-shrink-0">
            <i data-lucide="book-open" class="w-4 h-4 text-yellow-400"></i>
            <span class="hidden sm:inline">Grammar</span>
        </button>
    </div>

    <!-- Practice Textarea -->
    <div class="glass rounded-3xl overflow-hidden px-5 py-4">
        <div class="flex items-center gap-2 mb-3">
            <i data-lucide="pen-line" class="w-4 h-4 text-slate-500"></i>
            <span class="text-xs font-semibold text-slate-200 uppercase tracking-wider">Practice Any Phrase</span>
            <span class="kbd ml-auto hidden md:inline-flex">Ctrl+Enter</span>
        </div>
        <div class="flex gap-2">
            <textarea id="practiceInput" rows="2" placeholder="Type Hungarian or English here..."
                oninput="this.rows = Math.max(2, this.value.split('\n').length)"
                class="flex-1 bg-surface-50 rounded-xl px-4 py-2.5 text-white text-sm border border-white/5 focus:outline-none focus:border-accent/40 resize-none transition-colors"></textarea>
            <div class="flex flex-col gap-1.5">
                <button onclick="speakPractice()" class="flex-1 bg-surface-300 hover:bg-surface-400 rounded-xl px-3 flex items-center justify-center text-slate-200 hover:text-white transition-all" title="Speak &amp; record">
                    <i data-lucide="volume-2" class="w-4 h-4"></i>
                </button>
                <button onclick="translatePractice()" class="flex-1 bg-surface-300 hover:bg-surface-400 rounded-xl px-3 flex items-center justify-center text-slate-200 hover:text-white transition-all" title="Translate">
                    <i data-lucide="languages" class="w-4 h-4"></i>
                </button>
                <button id="savePhraseBtn" onclick="savePracticePhrase()" class="flex-1 bg-surface-300 hover:bg-surface-400 rounded-xl px-3 flex items-center justify-center text-slate-200 hover:text-white transition-all" title="Save to phrase list">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
        <p id="practiceTranslation" class="hidden text-slate-300 text-sm mt-3 italic px-1"></p>
    </div>

    </div><!-- end view-home -->

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: ALL DRILLS -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-drills" class="view-section hidden space-y-4">

        <div class="flex items-center gap-3">
            <button onclick="goHome()" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </button>
            <h2 class="text-lg font-bold text-white">All Drill Groups</h2>
            <span id="drillGroupCount" class="text-xs text-slate-500 ml-auto"></span>
        </div>

        <div id="drillGroupList" class="space-y-2">
            <p class="text-slate-500 text-sm text-center py-4">Loading drill groups...</p>
        </div>

    </div><!-- end view-drills -->

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: GRAMMAR / LEARN -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-grammar" class="view-section hidden space-y-4">

        <div class="flex items-center gap-3">
            <button onclick="goHome()" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </button>
            <h2 class="text-lg font-bold text-white">Grammar Patterns</h2>
            <span id="grammarCount" class="text-xs text-slate-500 ml-auto"></span>
        </div>

        <!-- Search -->
        <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
            <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
            <input id="grammarSearch" type="text" placeholder="Search patterns..." oninput="searchGrammar()"
                class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
        </div>

        <!-- Tag filter row -->
        <div id="grammarTagFilter" class="flex flex-wrap gap-1.5"></div>

        <!-- Pattern list -->
        <div id="grammarList" class="space-y-2">
            <p class="text-slate-500 text-sm text-center py-4">Loading grammar patterns...</p>
        </div>

        <!-- AI Lesson Panel (hidden until Teach Me is clicked) -->
        <div id="lessonPanel" class="hidden">
            <div class="glass rounded-3xl overflow-hidden glow-accent">
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
                    <h2 id="lessonTitle" class="text-lg font-bold flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-5 h-5 text-yellow-400"></i> <span></span>
                    </h2>
                    <button onclick="closeLesson()" class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div id="lessonContent" class="p-5 space-y-5">
                    <p class="text-slate-400 text-sm text-center py-8">Loading AI lesson...</p>
                </div>
            </div>
        </div>

    </div><!-- end view-grammar -->

</div>

<!-- BOTTOM QUICK BAR -->
<nav id="quickbar-home" class="quick-bar">
    <button onclick="openBrowse()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="book-open" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Browse</span>
    </button>
    <button onclick="openStats()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Stats</span>
    </button>
    <button onclick="speak(currentSpeed, false)" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="volume-2" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Listen</span>
    </button>
    <button onclick="toggleMic()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-green-400 transition-all">
        <i data-lucide="mic" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Mic</span>
    </button>
    <button onclick="nextQuestion()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="skip-forward" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Next</span>
    </button>
</nav>

<script>
// Escape HTML to prevent XSS when inserting dynamic content
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

let targetQ  = <?php echo json_encode($targetQ); ?>;
let targetA  = <?php echo json_encode($targetA); ?>;
let targetAH = <?php echo json_encode($targetAH); ?>;
const who    = <?php echo json_encode($who); ?>;

let currentMode  = localStorage.getItem('hugMode') || 'pronunciation';
if (localStorage.getItem('hugCat') === 'bios') localStorage.removeItem('hugCat');
let cat          = localStorage.getItem('hugCat') || 'all';
let listenMode   = localStorage.getItem('hugListen') === '1';
let currentSpeed = parseFloat(localStorage.getItem('hugSpeed')) || 1.0;
let autoAdvance    = localStorage.getItem('hugAutoAdvance') === '1';
let translateOn    = localStorage.getItem('hugTranslate') === '1';
let phoneticOn     = localStorage.getItem('hugPhonetic') === '1';
let strictness     = parseInt(localStorage.getItem('hugStrict')) || 2;
let repeatOnFail   = localStorage.getItem('hugRepeatFail') === '1';

// Question history for prev/next navigation
var questionHistory = [{ q: targetQ, a: targetA, a_hu: targetAH }];
var historyIndex = 0;

var strictLabels = { 1: 'Relaxed', 2: 'Meaning', 3: 'Balanced', 4: 'Strict', 5: 'Exam' };
document.getElementById('strictSlider').value = strictness;
document.getElementById('strictLabel').textContent = strictLabels[strictness];
document.getElementById('strictSlider').addEventListener('input', function() {
    strictness = parseInt(this.value);
    localStorage.setItem('hugStrict', strictness);
    document.getElementById('strictLabel').textContent = strictLabels[strictness];
});

function toggleRepeatFail() {
    repeatOnFail = !repeatOnFail;
    localStorage.setItem('hugRepeatFail', repeatOnFail ? '1' : '0');
    var btn = document.getElementById('repeatFailBtn');
    if (repeatOnFail) {
        btn.classList.add('bg-indigo-600/30', 'border-indigo-500/50', 'text-white');
        btn.classList.remove('border-white/5', 'text-slate-300');
    } else {
        btn.classList.remove('bg-indigo-600/30', 'border-indigo-500/50', 'text-white');
        btn.classList.add('border-white/5', 'text-slate-300');
    }
}
// Init repeat button state
if (repeatOnFail) toggleRepeatFail();

const indicator = document.getElementById('readyIndicator');
let isListening       = false;
let recTimeout        = null;
let advanceTimeout    = null;
let listenStartTime   = 0;
let isPractice        = false;
let showPlaybackWhenReady = false;
let questionAttempted = false;

// ── Session tracking ──────────────────────────────────────────────────
let sessionPass = 0, sessionFail = 0, sessionStreak = 0, sessionBestStreak = 0, sessionCount = 0;
const SESSION_SIZE = 10;

function updateProgressBar() {
    const pct = Math.min(100, (sessionCount / SESSION_SIZE) * 100);
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressLabel').textContent = sessionCount + ' / ' + SESSION_SIZE;
}

function updateSession(pass) {
    sessionCount++;
    if (pass) {
        sessionPass++;
        sessionStreak++;
        sessionBestStreak = Math.max(sessionBestStreak, sessionStreak);
    } else {
        sessionFail++;
        sessionStreak = 0;
    }
    document.getElementById('sesPass').textContent   = sessionPass;
    document.getElementById('sesFail').textContent   = sessionFail;
    document.getElementById('sesStreak').textContent = sessionStreak;
    updateProgressBar();
    if (sessionCount >= SESSION_SIZE) showSummary();
}

function showSummary() {
    clearTimeout(advanceTimeout);
    document.getElementById('summaryPass').textContent   = sessionPass;
    document.getElementById('summaryFail').textContent   = sessionFail;
    document.getElementById('summaryStreak').textContent = sessionBestStreak;
    document.getElementById('summaryModal').classList.remove('hidden');
    sessionPass = sessionFail = sessionStreak = sessionBestStreak = sessionCount = 0;
    document.getElementById('sesPass').textContent   = '0';
    document.getElementById('sesFail').textContent   = '0';
    document.getElementById('sesStreak').textContent = '0';
    updateProgressBar();
}

function closeSummary(keepGoing) {
    document.getElementById('summaryModal').classList.add('hidden');
    if (keepGoing) nextQuestion();
}

// ── Audio ─────────────────────────────────────────────────────────────
let audioCtx = null, analyser = null, micStream = null, volTimer = null;
let mediaRecorder = null, audioChunks = [], lastRecordingBlob = null;
const volFill = document.getElementById('volFill');

const VAD_THRESHOLD = 8;
const VAD_SILENCE   = 1200;
let vadLastSpeech = 0;
let vadSpeaked    = false;

function startVolume() {
    navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function(stream) {
        micStream = stream;
        audioCtx  = new AudioContext();
        analyser  = audioCtx.createAnalyser();
        analyser.fftSize = 512;
        audioCtx.createMediaStreamSource(stream).connect(analyser);
        var data = new Uint8Array(analyser.frequencyBinCount);
        vadSpeaked    = false;
        vadLastSpeech = Date.now();

        volTimer = setInterval(function() {
            analyser.getByteFrequencyData(data);
            var vol = Math.min(100, (data.reduce(function(a, b) { return a + b; }) / data.length) * 5);
            volFill.style.width = vol + '%';
            if (!isListening || (Date.now() - listenStartTime) < 700) return;
            if (vol > VAD_THRESHOLD) {
                vadLastSpeech = Date.now();
                vadSpeaked    = true;
            } else if (vadSpeaked && (Date.now() - vadLastSpeech) > VAD_SILENCE) {
                vadSpeaked = false;
                if (isListening) recognition.stop();
            }
        }, 50);
        audioChunks = [];
        try {
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function(e) { if (e.data.size > 0) audioChunks.push(e.data); };
            mediaRecorder.onstop = function() {
                lastRecordingBlob = new Blob(audioChunks, { type: 'audio/webm' });
                if (showPlaybackWhenReady) {
                    showPlaybackWhenReady = false;
                    document.getElementById('playbackBtn').classList.remove('hidden');
                }
            };
            mediaRecorder.start();
        } catch(e) { console.log('MediaRecorder:', e); }
    }).catch(function() {});
}

function stopVolume() {
    clearInterval(volTimer);
    volFill.style.width = '0%';
}

function cleanupAudio() {
    clearInterval(volTimer);
    volFill.style.width = '0%';
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch(e) {}
    }
    if (micStream) { micStream.getTracks().forEach(function(t) { t.stop(); }); micStream = null; }
    if (audioCtx)  { audioCtx.close(); audioCtx = null; }
}

function playMyVoice() {
    if (!lastRecordingBlob) return;
    var url = URL.createObjectURL(lastRecordingBlob);
    new Audio(url).play();
}

// ── Voice synthesis ───────────────────────────────────────────────────
var huVoice = null;
function loadVoices() {
    var voices = window.speechSynthesis.getVoices();
    huVoice = voices.find(function(v) { return v.lang === 'hu-HU'; }) ||
              voices.find(function(v) { return v.lang.startsWith('hu'); }) || null;
}
window.speechSynthesis.onvoiceschanged = loadVoices;
loadVoices();

function speak(rate, autoRecord) {
    if (autoRecord === undefined) autoRecord = true;
    window.speechSynthesis.cancel();
    isListening = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}
    // Reset result card so re-listen triggers fresh eval
    document.getElementById('resultCard').classList.add('hidden');
    document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
    document.getElementById('matchScore').textContent = '';
    document.getElementById('transcript').textContent = '';
    document.getElementById('playbackBtn').classList.add('hidden');
    setTimeout(function() {
        var msg = new SpeechSynthesisUtterance(targetQ);
        msg.lang = 'hu-HU';
        msg.rate = rate;
        if (huVoice) msg.voice = huVoice;
        if (autoRecord) { msg.onend = function() { setTimeout(toggleMic, 350); }; }
        window.speechSynthesis.speak(msg);
    }, 50);
}

// ── Speed control ─────────────────────────────────────────────────────
function toggleSlow() {
    var btn = document.getElementById('slowBtn');
    if (currentSpeed === 0.5) {
        setSpeed(1.0);
        btn.classList.remove('bg-amber-600', 'text-white');
        btn.classList.add('bg-surface-300', 'text-slate-200');
    } else {
        setSpeed(0.5);
        btn.classList.remove('bg-surface-300', 'text-slate-200');
        btn.classList.add('bg-amber-600', 'text-white');
    }
    speak(currentSpeed);
}

function setSpeed(speed) {
    currentSpeed = speed;
    localStorage.setItem('hugSpeed', speed);
    var slowBtn = document.getElementById('slowBtn');
    if (speed === 0.5) {
        slowBtn.classList.remove('bg-surface-300', 'text-slate-200');
        slowBtn.classList.add('bg-amber-600', 'text-white');
    } else {
        slowBtn.classList.remove('bg-amber-600', 'text-white');
        slowBtn.classList.add('bg-surface-300', 'text-slate-200');
    }
    document.querySelectorAll('.speed-btn').forEach(function(btn) {
        var s = parseFloat(btn.dataset.speed);
        if (s === speed) {
            btn.className = 'speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all bg-accent/20 text-accent-light';
        } else {
            btn.className = 'speed-btn text-[10px] px-2 py-0.5 rounded-md font-semibold transition-all text-slate-300 hover:text-white';
        }
    });
}

// ── Category filter ───────────────────────────────────────────────────
function setCat(c, skipFetch) {
    cat = c;
    localStorage.setItem('hugCat', c);
    // Exit drill mode when switching categories
    if (drillPhrases.length > 0) closeDrill();
    ['all','prep','bios'].forEach(function(id) {
        var el = document.getElementById('cat-' + id);
        el.className = 'pill ' + (cat === id ? 'pill-active' : 'pill-inactive');
    });
    if (!skipFetch) nextQuestion();
}

// ── Listen mode ───────────────────────────────────────────────────────
function toggleListenMode() {
    listenMode = !listenMode;
    localStorage.setItem('hugListen', listenMode ? '1' : '0');
    applyListenMode();
}

function applyListenMode() {
    var q   = document.getElementById('questionText');
    var btn = document.getElementById('listenModeBtn');
    if (listenMode) {
        q.classList.add('listen-blur');
        q.title = 'Click to reveal';
        q.onclick = revealQuestion;
        btn.classList.add('text-amber-400');
        btn.classList.remove('text-slate-500', 'text-slate-200');
    } else {
        q.classList.remove('listen-blur');
        q.title = '';
        q.onclick = null;
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-slate-200');
    }
}

function revealQuestion() {
    document.getElementById('questionText').classList.remove('listen-blur');
    document.getElementById('questionText').onclick = null;
}

// ── Auto-advance toggle ───────────────────────────────────────────────
function toggleAutoAdvance() {
    autoAdvance = !autoAdvance;
    localStorage.setItem('hugAutoAdvance', autoAdvance ? '1' : '0');
    applyAutoAdvance();
}

function applyAutoAdvance() {
    var btn = document.getElementById('autoAdvanceBtn');
    if (autoAdvance) {
        btn.classList.add('text-accent-light');
        btn.classList.remove('text-slate-500', 'text-slate-200');
    } else {
        btn.classList.remove('text-accent-light');
        btn.classList.add('text-slate-200');
    }
}

// ── Mode toggle ───────────────────────────────────────────────────────
function setMode(mode) {
    currentMode = mode;
    localStorage.setItem('hugMode', mode);
    // Session toolbar buttons
    document.getElementById('btnPron').className = 'pill ' + (mode === 'pronunciation' ? 'pill-active' : 'pill-inactive');
    document.getElementById('btnInterview').className = 'pill ' + (mode === 'interview' ? 'pill-active' : 'pill-inactive');
    document.getElementById('listenBtnLabel').textContent = mode === 'pronunciation' ? 'Listen & Repeat' : 'Hear Question';
}

// ── Next question ─────────────────────────────────────────────────────
function nextQuestion() {
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}

    document.getElementById('practiceTranslation').classList.add('hidden');
    document.getElementById('revealDetails').removeAttribute('open');

    // If in drill mode, advance through drill array
    if (drillPhrases.length > 0) {
        drillIdx++;
        if (drillIdx >= drillPhrases.length) drillIdx = 0;
        loadDrillIntoPlayer();
        return;
    }

    fetch('?who=' + who + '&cat=' + cat + '&ajax=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            targetQ  = data.q;
            targetA  = data.a;
            targetAH = data.a_hu || '';
            // Track history — trim future if we navigated back
            questionHistory = questionHistory.slice(0, historyIndex + 1);
            questionHistory.push({ q: data.q, a: data.a, a_hu: data.a_hu || '', category: data.category || '' });
            historyIndex = questionHistory.length - 1;
            document.getElementById('questionText').textContent = data.q;
            document.getElementById('answerText').textContent   = data.a_hu || data.a;
            document.getElementById('resultCard').classList.add('hidden');
            document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
            document.getElementById('matchScore').textContent   = '';
            document.getElementById('transcript').textContent   = '';
            document.getElementById('playbackBtn').classList.add('hidden');
            document.getElementById('categoryTag').textContent = data.category || '';
            lastRecordingBlob = null;
            if (listenMode) applyListenMode();
            // Auto-fetch translate/phonetic if toggled on
            if (translateOn) fetchTranslation(); else { document.getElementById('inlineTranslation').classList.add('hidden'); document.getElementById('inlineTranslation').textContent = ''; }
            if (phoneticOn) fetchPhonetic(); else { document.getElementById('phoneticHint').classList.add('hidden'); document.getElementById('phoneticHint').textContent = ''; }
            speak(currentSpeed);
        });
}

function prevQuestion() {
    if (historyIndex <= 0) return;
    historyIndex--;
    var h = questionHistory[historyIndex];
    targetQ  = h.q;
    targetA  = h.a;
    targetAH = h.a_hu || '';
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}
    document.getElementById('questionText').textContent = h.q;
    document.getElementById('answerText').textContent   = h.a_hu || h.a;
    document.getElementById('resultCard').classList.add('hidden');
    document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
    document.getElementById('matchScore').textContent   = '';
    document.getElementById('transcript').textContent   = '';
    document.getElementById('playbackBtn').classList.add('hidden');
    document.getElementById('categoryTag').textContent  = h.category || '';
    document.getElementById('revealDetails').removeAttribute('open');
    document.getElementById('practiceTranslation').classList.add('hidden');
    lastRecordingBlob = null;
    if (listenMode) applyListenMode();
    if (translateOn) fetchTranslation(); else { document.getElementById('inlineTranslation').classList.add('hidden'); document.getElementById('inlineTranslation').textContent = ''; }
    if (phoneticOn) fetchPhonetic(); else { document.getElementById('phoneticHint').classList.add('hidden'); document.getElementById('phoneticHint').textContent = ''; }
    speak(currentSpeed);
}

function shuffleQuestion() {
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}

    document.getElementById('practiceTranslation').classList.add('hidden');
    document.getElementById('revealDetails').removeAttribute('open');

    // If in drill mode, shuffle the drill array
    if (drillPhrases.length > 0) {
        for (var i = drillPhrases.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = drillPhrases[i];
            drillPhrases[i] = drillPhrases[j];
            drillPhrases[j] = tmp;
        }
        drillIdx = 0;
        loadDrillIntoPlayer();
        return;
    }

    fetch('?who=' + who + '&cat=' + cat + '&ajax=1&shuffle=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            targetQ  = data.q;
            targetA  = data.a;
            targetAH = data.a_hu || '';
            questionHistory = questionHistory.slice(0, historyIndex + 1);
            questionHistory.push({ q: data.q, a: data.a, a_hu: data.a_hu || '', category: data.category || '' });
            historyIndex = questionHistory.length - 1;
            document.getElementById('questionText').textContent = data.q;
            document.getElementById('answerText').textContent   = data.a_hu || data.a;
            document.getElementById('resultCard').classList.add('hidden');
            document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
            document.getElementById('matchScore').textContent   = '';
            document.getElementById('transcript').textContent   = '';
            document.getElementById('playbackBtn').classList.add('hidden');
            document.getElementById('categoryTag').textContent = data.category || '';
            lastRecordingBlob = null;
            if (listenMode) applyListenMode();
            if (translateOn) fetchTranslation(); else { document.getElementById('inlineTranslation').classList.add('hidden'); document.getElementById('inlineTranslation').textContent = ''; }
            if (phoneticOn) fetchPhonetic(); else { document.getElementById('phoneticHint').classList.add('hidden'); document.getElementById('phoneticHint').textContent = ''; }
            speak(currentSpeed);
        });
}

// ── Speech recognition ────────────────────────────────────────────────
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
if (!SpeechRecognition) {
    document.getElementById('recordBtn').disabled = true;
    document.getElementById('recordBtn').title = 'Speech recognition not supported in this browser';
    document.getElementById('recordLabel').textContent = 'N/A';
}
var recognition = SpeechRecognition ? new SpeechRecognition() : { start:function(){}, stop:function(){}, abort:function(){}, onstart:null, onresult:null, onend:null, onerror:null };
recognition.lang            = 'hu-HU';
recognition.interimResults  = false;
recognition.continuous      = true;
recognition.maxAlternatives = 5;

function setRecordIcon(iconName) {
    var icon = document.getElementById('recordIcon');
    icon.setAttribute('data-lucide', iconName);
    lucide.createIcons({ nodes: [icon] });
}
recognition.onstart = function() {
    isListening     = true;
    listenStartTime = Date.now();
    indicator.className = 'status-dot dot-live';
    var rb = document.getElementById('recordBtn');
    rb.classList.add('mic-active');
    rb.classList.remove('bg-green-600', 'hover:bg-green-500', 'glow-green');
    rb.classList.add('bg-red-600', 'hover:bg-red-500', 'glow-red');
    document.getElementById('recordLabel').textContent = 'Recording';

    setRecordIcon('headphones');
    startVolume();
    recTimeout = setTimeout(function() {
        if (isListening) recognition.stop();
    }, 8000);
};

recognition.onresult = function(event) {
    if (Date.now() - listenStartTime < 700) return;
    if (!isListening) return;
    clearTimeout(recTimeout);
    recognition.stop();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch(e) {}
    }
    stopVolume();

    var lastResult = event.results[event.results.length - 1];
    var result = lastResult[0].transcript.trim();
    var alternatives = [];
    for (var i = 0; i < lastResult.length; i++) {
        var alt = lastResult[i].transcript.trim();
        if (alt && alternatives.indexOf(alt) === -1) alternatives.push(alt);
    }
    isListening = false;
    indicator.className = 'status-dot dot-off';
    var rbReset = document.getElementById('recordBtn');
    rbReset.classList.remove('mic-active', 'bg-red-600', 'hover:bg-red-500', 'glow-red');
    rbReset.classList.add('bg-green-600', 'hover:bg-green-500', 'glow-green');
    document.getElementById('recordLabel').textContent = 'Mic';

    setRecordIcon('mic');

    if (isPractice) {
        isPractice = false;
        var el = document.getElementById('practiceTranslation');
        el.textContent = 'You said: "' + result + '"';
        el.classList.remove('hidden');
        return;
    }

    var resultCard = document.getElementById('resultCard');
    resultCard.classList.remove('hidden', 'result-pass', 'result-fail');
    document.getElementById('transcript').textContent = '"' + result + '"';
    document.getElementById('playbackBtn').classList.add('hidden');

    var scoreDisplay = document.getElementById('matchScore');
    scoreDisplay.textContent = '';
    var evalSpinner = document.createElement('span');
    evalSpinner.className = 'inline-flex items-center gap-2 text-slate-400 text-xs';
    var dot = document.createElement('span');
    dot.className = 'animate-pulse w-2 h-2 rounded-full bg-accent inline-block';
    evalSpinner.appendChild(dot);
    evalSpinner.appendChild(document.createTextNode('Evaluating...'));
    scoreDisplay.appendChild(evalSpinner);

    var fd = new FormData();
    fd.append('target',     targetQ);
    fd.append('transcript', result);
    fd.append('alternatives', JSON.stringify(alternatives));
    fd.append('mode',       currentMode);
    fd.append('who',        who);
    fd.append('strictness', strictness);
    if (targetAH) fd.append('expected_hu', targetAH);
    fetch('eval.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            scoreDisplay.textContent = '';
            var isPass = data.pass;

            // Row 1: badge + short feedback on same line
            var topRow = document.createElement('div');
            topRow.className = 'flex items-center gap-2 justify-center flex-wrap';
            var badge = document.createElement('span');
            badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider ' +
                (isPass ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400');
            badge.textContent = isPass ? 'Pass' : 'Retry';
            var hint = document.createElement('span');
            hint.className = 'text-xs ' + (isPass ? 'text-green-400/70' : 'text-red-400/70');
            hint.textContent = data.feedback || '';
            topRow.appendChild(badge);
            topRow.appendChild(hint);
            scoreDisplay.appendChild(topRow);

            // Row 2: correct answer — DB answer takes priority, then Gemini's, then fallback
            var correctAnswer = targetAH || data.correct || (currentMode === 'pronunciation' ? targetQ : targetQ);
            if (correctAnswer) {
                var correctEl = document.createElement('p');
                correctEl.className = 'text-base mt-2 font-semibold ' + (isPass ? 'text-green-300' : 'text-white');
                correctEl.textContent = correctAnswer;
                scoreDisplay.appendChild(correctEl);
            }

            resultCard.classList.add(isPass ? 'result-pass' : 'result-fail');

            // Always show playback button so user can hear themselves
            if (lastRecordingBlob) {
                document.getElementById('playbackBtn').classList.remove('hidden');
            } else {
                showPlaybackWhenReady = true;
            }

            // Repeat correct answer on fail
            if (!isPass && repeatOnFail && correctAnswer) {
                setTimeout(function() {
                    var msg = new SpeechSynthesisUtterance(correctAnswer);
                    msg.lang = 'hu-HU';
                    msg.rate = 0.8;
                    window.speechSynthesis.speak(msg);
                }, 1500);
            }

            if (isPass && autoAdvance) {
                var pauseRow = document.createElement('div');
                pauseRow.className = 'flex items-center justify-center gap-2 mt-2';
                var countdown = document.createElement('span');
                countdown.className = 'text-[10px] text-slate-400';
                countdown.textContent = 'Next in 3s...';
                var pauseBtn = document.createElement('button');
                pauseBtn.className = 'text-[10px] px-2 py-0.5 rounded border border-white/10 text-slate-300 hover:text-white hover:bg-white/5 font-semibold';
                pauseBtn.textContent = 'Pause';
                pauseBtn.onclick = function() {
                    clearTimeout(advanceTimeout);
                    clearInterval(countdownInterval);
                    pauseRow.remove();
                };
                pauseRow.appendChild(countdown);
                pauseRow.appendChild(pauseBtn);
                scoreDisplay.appendChild(pauseRow);
                var secsLeft = 2;
                var countdownInterval = setInterval(function() {
                    if (secsLeft <= 0) { clearInterval(countdownInterval); countdown.textContent = 'Moving on...'; return; }
                    countdown.textContent = 'Next in ' + secsLeft + 's...';
                    secsLeft--;
                }, 1000);
                advanceTimeout = setTimeout(function() { clearInterval(countdownInterval); nextQuestion(); }, 3000);
            }
            if (!questionAttempted) {
                questionAttempted = true;
                updateSession(data.pass);
                recordSRS(targetQ, data.pass);
            }
            lucide.createIcons();
        })
        .catch(function() {
            scoreDisplay.textContent = '';
            var errBadge = document.createElement('span');
            errBadge.className = 'inline-flex items-center gap-1.5 bg-amber-500/20 text-amber-400 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider';
            errBadge.textContent = 'Error';
            scoreDisplay.appendChild(errBadge);
        });
};

recognition.onend = function() {
    clearTimeout(recTimeout);
    isListening = false;
    isPractice  = false;
    cleanupAudio();
    indicator.className = 'status-dot dot-off';
    var rbReset = document.getElementById('recordBtn');
    rbReset.classList.remove('mic-active', 'bg-red-600', 'hover:bg-red-500', 'glow-red');
    rbReset.classList.add('bg-green-600', 'hover:bg-green-500', 'glow-green');
    document.getElementById('recordLabel').textContent = 'Mic';

    setRecordIcon('mic');
};

function toggleMic() {
    if (!isListening) {
        indicator.className = 'status-dot dot-warmup';
        try { recognition.start(); } catch(e) { console.log('rec start error:', e); }
    } else {
        clearTimeout(recTimeout);
        isListening = false;
        recognition.stop();
    }
}

// ── Translation (persistent toggle) ───────────────────────────────────
function toggleTranslation() {
    translateOn = !translateOn;
    localStorage.setItem('hugTranslate', translateOn ? '1' : '0');
    applyTranslateState();
    if (translateOn) fetchTranslation();
}

function applyTranslateState() {
    var btn = document.getElementById('translateBtn');
    var el = document.getElementById('inlineTranslation');
    if (translateOn) {
        btn.classList.add('text-blue-400');
        btn.classList.remove('text-slate-500', 'text-slate-200');
    } else {
        btn.classList.remove('text-blue-400');
        btn.classList.add('text-slate-200');
        el.classList.add('hidden');
    }
}

function fetchTranslation() {
    if (!translateOn) return;
    var el = document.getElementById('inlineTranslation');
    el.textContent = 'Translating...';
    el.classList.remove('hidden');
    var fd = new FormData();
    fd.append('text', targetQ);
    fetch('translate.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) { el.textContent = data.translation || 'Error'; })
        .catch(function() { el.textContent = 'Translation error'; });
}

// ── Phonetic hint (persistent toggle) ─────────────────────────────────
function togglePhonetic() {
    phoneticOn = !phoneticOn;
    localStorage.setItem('hugPhonetic', phoneticOn ? '1' : '0');
    applyPhoneticState();
    if (phoneticOn) fetchPhonetic();
}

function applyPhoneticState() {
    var btn = document.getElementById('phoneticBtn');
    var el = document.getElementById('phoneticHint');
    if (phoneticOn) {
        btn.classList.add('text-amber-400');
        btn.classList.remove('text-slate-500', 'text-slate-200');
    } else {
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-slate-200');
        el.classList.add('hidden');
    }
}

function fetchPhonetic() {
    if (!phoneticOn) return;
    var el = document.getElementById('phoneticHint');
    el.textContent = 'Loading phonetics...';
    el.classList.remove('hidden');
    var fd = new FormData();
    fd.append('text', targetQ);
    fetch('phonetic.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) { el.textContent = data.phonetic || 'Error'; })
        .catch(function() { el.textContent = 'Error loading phonetics'; });
}

// ── SRS record ────────────────────────────────────────────────────────
function recordSRS(phrase, pass) {
    var fd = new FormData();
    fd.append('phrase', phrase);
    fd.append('pass',   pass ? '1' : '0');
    fd.append('who',    who);
    fetch('record.php', { method: 'POST', body: fd }).catch(function() {});
}

// ── Practice section ──────────────────────────────────────────────────
function speakPractice() {
    var text = document.getElementById('practiceInput').value.trim();
    if (!text) return;
    window.speechSynthesis.cancel();
    var msg = new SpeechSynthesisUtterance(text);
    msg.lang = 'hu-HU';
    msg.rate = 1.0;
    if (huVoice) msg.voice = huVoice;
    msg.onend = function() { isPractice = true; setTimeout(toggleMic, 350); };
    window.speechSynthesis.speak(msg);
}

function translatePractice() {
    var text = document.getElementById('practiceInput').value.trim();
    if (!text) return;
    var el = document.getElementById('practiceTranslation');
    el.textContent = 'Translating...';
    el.classList.remove('hidden');
    var fd = new FormData();
    fd.append('text', text);
    fetch('translate.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var result = data.translation || 'Error';
            el.textContent = '';
            el.appendChild(document.createTextNode(result + ' '));
            if (result && result !== 'Error') {
                var speakBtn = document.createElement('button');
                speakBtn.className = 'inline-flex items-center ml-1 text-indigo-400 hover:text-white transition-colors align-middle';
                speakBtn.title = 'Listen';
                speakBtn.textContent = '\u{1F50A}';
                speakBtn.onclick = function() {
                    window.speechSynthesis.cancel();
                    var msg = new SpeechSynthesisUtterance(result);
                    msg.lang = 'hu-HU';
                    msg.rate = 0.9;
                    if (huVoice) msg.voice = huVoice;
                    window.speechSynthesis.speak(msg);
                };
                el.appendChild(speakBtn);
            }
        })
        .catch(function() { el.textContent = 'Translation error'; });
}

function savePracticePhrase() {
    var input = document.getElementById('practiceInput').value.trim();
    var transEl = document.getElementById('practiceTranslation');
    var transText = transEl.textContent.trim();
    if (!input) return;

    // Determine which is Hungarian and which is English
    var hasHuChars = /[áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/.test(input);
    var questionHu = hasHuChars ? input : transText;
    var answerEn = hasHuChars ? transText : input;
    var answerHu = hasHuChars ? input : (transText || '');

    if (!questionHu) { alert('Type a Hungarian phrase first (or translate to get one)'); return; }

    var btn = document.getElementById('savePhraseBtn');
    btn.classList.add('opacity-50');

    var fd = new FormData();
    fd.append('question_hu', questionHu);
    fd.append('answer_en', answerEn.replace(/\s*🔊$/, ''));
    fd.append('answer_hu', answerHu);
    fd.append('category', 'Practice');

    fetch('?ajax=1&action=save_phrase', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            transEl.textContent = data.msg;
            transEl.classList.remove('hidden');
            btn.classList.remove('opacity-50');
        })
        .catch(function() { btn.classList.remove('opacity-50'); alert('Save failed'); });
}

// Live translation as you type (debounced)
var practiceDebounce;
document.getElementById('practiceInput').addEventListener('input', function() {
    clearTimeout(practiceDebounce);
    var text = this.value.trim();
    if (!text) {
        document.getElementById('practiceTranslation').classList.add('hidden');
        return;
    }
    practiceDebounce = setTimeout(function() { translatePractice(); }, 600);
});

document.getElementById('practiceInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.shiftKey)) {
        e.preventDefault();
        speakPractice();
        translatePractice();
    }
});

// ── Phrase browser ────────────────────────────────────────────────────
function openBrowse() {
    document.getElementById('browseModal').classList.remove('hidden');
    document.getElementById('browseSearch').value = '';
    loadPhrases();
}

function closeBrowse() {
    document.getElementById('browseModal').classList.add('hidden');
}

function loadPhrases(search) {
    var url = '?who=' + who + '&ajax=1&action=phrases' + (search ? '&search=' + encodeURIComponent(search) : '');
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        renderPhrases(data);
    });
}

function searchPhrases() {
    var q = document.getElementById('browseSearch').value.trim();
    loadPhrases(q);
}

function renderPhrases(data) {
    var list = document.getElementById('browseList');
    document.getElementById('browseCount').textContent = data.length + ' phrases';
    list.textContent = '';
    if (!data.length) {
        var empty = document.createElement('p');
        empty.className = 'text-slate-500 text-sm text-center py-8';
        empty.textContent = 'No phrases found.';
        list.appendChild(empty);
        return;
    }
    data.forEach(function(p) {
        var mastery = p.pass_count >= 3 ? 'mastered' : p.pass_count >= 1 ? 'known' : p.fail_count > 0 ? 'learning' : 'new';
        var item = document.createElement('div');
        item.className = 'phrase-item';
        item.addEventListener('click', function() { jumpToPhrase(p.q, p.a); });

        var textDiv = document.createElement('div');
        textDiv.className = 'flex-1 min-w-0';
        var qLine = document.createElement('p');
        qLine.className = 'text-sm font-medium text-white truncate';
        qLine.textContent = p.q;
        var aLine = document.createElement('p');
        aLine.className = 'text-xs text-slate-500 truncate';
        aLine.textContent = p.a;
        textDiv.appendChild(qLine);
        textDiv.appendChild(aLine);

        var metaDiv = document.createElement('div');
        metaDiv.className = 'flex items-center gap-2 ml-3';
        var catSpan = document.createElement('span');
        catSpan.className = 'text-[10px] text-slate-600';
        catSpan.textContent = p.category;
        var dot = document.createElement('div');
        dot.className = 'w-2 h-2 rounded-full mastery-' + mastery;
        metaDiv.appendChild(catSpan);
        metaDiv.appendChild(dot);

        item.appendChild(textDiv);
        item.appendChild(metaDiv);
        list.appendChild(item);
    });
}

function jumpToPhrase(q, a) {
    targetQ = q;
    targetA = a;
    targetAH = '';
    questionAttempted = false;
    showPlaybackWhenReady = false;
    lastRecordingBlob = null;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}
    document.getElementById('questionText').textContent = q;
    document.getElementById('answerText').textContent   = a;
    document.getElementById('resultCard').classList.add('hidden');
    document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
    document.getElementById('matchScore').textContent   = '';
    document.getElementById('transcript').textContent   = '';
    document.getElementById('playbackBtn').classList.add('hidden');
    document.getElementById('revealDetails').removeAttribute('open');
    document.getElementById('practiceTranslation').classList.add('hidden');
    closeBrowse();
    if (listenMode) applyListenMode();
    if (translateOn) fetchTranslation(); else { document.getElementById('inlineTranslation').classList.add('hidden'); }
    if (phoneticOn) fetchPhonetic(); else { document.getElementById('phoneticHint').classList.add('hidden'); }
    speak(currentSpeed);
}

// ── Stats dashboard ───────────────────────────────────────────────────
function openStats() {
    document.getElementById('statsModal').classList.remove('hidden');
    var content = document.getElementById('statsContent');
    content.textContent = 'Loading...';
    fetch('?who=' + who + '&ajax=1&action=stats')
        .then(function(r) { return r.json(); })
        .then(function(data) { renderStats(data); });
}

function closeStats() {
    document.getElementById('statsModal').classList.add('hidden');
}

function renderStats(data) {
    var content = document.getElementById('statsContent');
    content.textContent = '';
    var pct = data.total > 0 ? Math.round((data.mastered / data.total) * 100) : 0;
    var studiedPct = data.total > 0 ? Math.round((data.studied / data.total) * 100) : 0;

    // Overview grid
    var grid = document.createElement('div');
    grid.className = 'grid grid-cols-2 gap-3';
    grid.appendChild(makeStatCard('Total Phrases', data.total));
    grid.appendChild(makeStatCard('Studied', data.studied + ' (' + studiedPct + '%)'));
    grid.appendChild(makeStatCard('Mastered', data.mastered + ' (' + pct + '%)'));
    grid.appendChild(makeStatCard('Due for Review', data.due));
    content.appendChild(grid);

    // Mastery bar
    var barSection = document.createElement('div');
    var barLabel = document.createElement('h3');
    barLabel.className = 'text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2';
    barLabel.textContent = 'Overall Mastery';
    barSection.appendChild(barLabel);
    var barTrack = document.createElement('div');
    barTrack.className = 'h-3 bg-surface-50 rounded-full overflow-hidden';
    var barFill = document.createElement('div');
    barFill.className = 'h-full bg-gradient-to-r from-green-500 to-emerald-400 rounded-full transition-all';
    barFill.style.width = pct + '%';
    barTrack.appendChild(barFill);
    barSection.appendChild(barTrack);
    var barCaption = document.createElement('p');
    barCaption.className = 'text-xs text-slate-500 mt-1';
    barCaption.textContent = pct + '% of phrases mastered';
    barSection.appendChild(barCaption);
    content.appendChild(barSection);

    // Weak phrases
    if (data.weak && data.weak.length) {
        var weakSection = document.createElement('div');
        var weakLabel = document.createElement('h3');
        weakLabel.className = 'text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2';
        weakLabel.textContent = 'Needs Practice';
        weakSection.appendChild(weakLabel);
        var weakList = document.createElement('div');
        weakList.className = 'space-y-1';
        data.weak.forEach(function(w) {
            var row = document.createElement('div');
            row.className = 'flex items-center justify-between p-2.5 rounded-lg bg-surface-50';
            var phrase = document.createElement('span');
            phrase.className = 'text-sm text-white truncate flex-1 mr-3';
            phrase.textContent = w.phrase;
            var fails = document.createElement('span');
            fails.className = 'text-xs text-red-400 whitespace-nowrap';
            fails.textContent = w.fail_count + ' fails';
            row.appendChild(phrase);
            row.appendChild(fails);
            weakList.appendChild(row);
        });
        weakSection.appendChild(weakList);
        content.appendChild(weakSection);
    }

    // Recent activity
    if (data.recent && data.recent.length) {
        var recentSection = document.createElement('div');
        var recentLabel = document.createElement('h3');
        recentLabel.className = 'text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2';
        recentLabel.textContent = 'Recent Activity';
        recentSection.appendChild(recentLabel);
        var recentList = document.createElement('div');
        recentList.className = 'space-y-1';
        data.recent.forEach(function(r) {
            var row = document.createElement('div');
            row.className = 'flex items-center justify-between p-2.5 rounded-lg bg-surface-50';
            var phrase = document.createElement('span');
            phrase.className = 'text-sm text-white truncate flex-1 mr-3';
            phrase.textContent = r.phrase;
            var date = document.createElement('span');
            date.className = 'text-[10px] text-slate-500';
            date.textContent = (r.last_seen || '').substring(0, 10);
            row.appendChild(phrase);
            row.appendChild(date);
            recentList.appendChild(row);
        });
        recentSection.appendChild(recentList);
        content.appendChild(recentSection);
    }
}

function makeStatCard(label, value) {
    var card = document.createElement('div');
    card.className = 'bg-surface-50 rounded-xl p-4';
    var labelEl = document.createElement('div');
    labelEl.className = 'text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-2';
    labelEl.textContent = label;
    var valueEl = document.createElement('div');
    valueEl.className = 'text-2xl font-bold text-white';
    valueEl.textContent = value;
    card.appendChild(labelEl);
    card.appendChild(valueEl);
    return card;
}

// ── Keyboard shortcuts ────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') return;
    if (e.key === ' ' && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        toggleMic();
    } else if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        nextQuestion();
    } else if (e.key === 'Escape') {
        if (isListening) { recognition.stop(); }
        closeBrowse();
        closeStats();
    } else if (e.key === 's' || e.key === 'S') {
        toggleSlow();
    } else if (e.key === 't' || e.key === 'T') {
        toggleTranslation();
    } else if (e.key === 'p' || e.key === 'P') {
        togglePhonetic();
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevQuestion();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextQuestion();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        speak(currentSpeed, false);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        var d = document.getElementById('revealDetails');
        d.open = !d.open;
    }
});

// ── Tab navigation ────────────────────────────────────────────────────
var currentView = 'home';

function showView(view) {
    currentView = view;
    var views = ['home', 'drills', 'grammar'];
    views.forEach(function(v) {
        var el = document.getElementById('view-' + v);
        if (el) el.classList.toggle('active', v === view);
    });
    // Show quickbar only on home
    document.getElementById('quickbar-home').classList.toggle('hidden', view !== 'home');
    // Lazy load
    if (view === 'home') { loadHomeStats(); loadDrillGroups(); }
    if (view === 'drills') loadDrillGroups();
    if (view === 'grammar') { loadGrammarPatterns(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    lucide.createIcons();
}

function goHome() {
    showView('home');
}

// Home screen data
var homeLoaded = false;
function loadHomeStats() {
    fetch('?who=' + who + '&ajax=1&action=home_stats')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            homeLoaded = true;
            document.getElementById('homeStreak').textContent = data.streak || 0;
            document.getElementById('homeStudied').textContent = data.studied || 0;
            document.getElementById('homeMastered').textContent = data.mastered || 0;

            // Due badge
            var badge = document.getElementById('homeDueBadge');
            if (data.due > 0) {
                badge.classList.remove('hidden');
                document.getElementById('homeDueCount').textContent = data.due + ' due';
            } else {
                badge.classList.add('hidden');
            }

            // Counts on drill/grammar buttons
            // These elements may exist depending on layout
            var gc = document.getElementById('homeGrammarCount');
            if (gc && data.grammar_count) gc.textContent = data.grammar_count + ' patterns';
            var dc = document.getElementById('homeDrillCount');
            var drillCount = (data.groups || []).filter(function(g) { return g.phrase_count > 0; }).length;
            if (dc && drillCount) dc.textContent = drillCount + ' groups';
            lucide.createIcons();
        })
        .catch(function() {});
}


// ── Drill system ──────────────────────────────────────────────────────
var drillPhrases = [];
var drillIdx = 0;
var drillPassCount = 0, drillFailCount = 0;
var drillGroupsLoaded = false;

function loadDrillGroups() {
    if (drillGroupsLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=drill_groups')
        .then(function(r) { return r.json(); })
        .then(function(groups) {
            drillGroupsLoaded = true;
            // Populate dropdown on home page
            var picker = document.getElementById('drillPicker');
            if (picker) {
                picker.innerHTML = '<option value="">Focused Drill...</option>';
                groups.forEach(function(g) {
                    if (g.phrase_count < 1) return;
                    var opt = document.createElement('option');
                    opt.value = g.name;
                    opt.textContent = g.name + ' (' + g.phrase_count + ')';
                    picker.appendChild(opt);
                });
            }
            // Populate drills view list
            var list = document.getElementById('drillGroupList');
            if (list) {
                list.textContent = '';
                var countEl = document.getElementById('drillGroupCount');
                if (countEl) countEl.textContent = groups.length + ' groups';
                groups.forEach(function(g) {
                    if (g.phrase_count < 1) return;
                    var card = document.createElement('div');
                    card.className = 'drill-card';
                    card.onclick = function() { startDrill(g.name); };
                    var top = document.createElement('div');
                    top.className = 'flex items-center justify-between';
                    var name = document.createElement('span');
                    name.className = 'text-sm font-semibold text-white';
                    name.textContent = g.name;
                    var count = document.createElement('span');
                    count.className = 'text-xs text-slate-500';
                    count.textContent = g.phrase_count;
                    top.appendChild(name);
                    top.appendChild(count);
                    if (g.description) {
                        var desc = document.createElement('p');
                        desc.className = 'text-[10px] text-slate-400 mt-0.5';
                        desc.textContent = g.description;
                        card.appendChild(top);
                        card.appendChild(desc);
                    } else {
                        card.appendChild(top);
                    }
                    list.appendChild(card);
                });
            }
            lucide.createIcons();
        });
}

function onDrillPick(name) {
    if (!name) return;
    // Go to home view if not already there
    if (currentView !== 'home') showView('home');
    startDrill(name);
    document.getElementById('drillPicker').value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Drill mode: loads phrases into the main player
var activeDrillName = '';

function startDrill(groupName) {
    fetch('?who=' + who + '&ajax=1&action=drill_phrases&tag=' + encodeURIComponent(groupName))
        .then(function(r) { return r.json(); })
        .then(function(phrases) {
            if (!phrases.length) { alert('No phrases found for "' + groupName + '". Try re-running import_notion.php.'); return; }
            drillPhrases = phrases;
            drillIdx = 0;
            activeDrillName = groupName;
            // Reset session counters for this drill
            sessionPass = sessionFail = sessionStreak = sessionBestStreak = sessionCount = 0;
            document.getElementById('sesPass').textContent = '0';
            document.getElementById('sesFail').textContent = '0';
            document.getElementById('sesStreak').textContent = '0';
            // Show drill banner
            showDrillBanner(groupName, phrases.length);
            // Load first phrase into main player
            loadDrillIntoPlayer();
        })
        .catch(function(err) { alert('Error loading drill: ' + err.message); });
}

function showDrillBanner(name, total) {
    var banner = document.getElementById('drillBanner');
    if (!banner) return;
    document.getElementById('drillBannerName').textContent = name;
    document.getElementById('drillBannerCount').textContent = total + ' phrases';
    banner.classList.remove('hidden');
}

function closeDrill() {
    drillPhrases = [];
    activeDrillName = '';
    drillIdx = 0;
    var banner = document.getElementById('drillBanner');
    if (banner) banner.classList.add('hidden');
    // Reset progress bar to session mode
    updateProgressBar();
}

function loadDrillIntoPlayer() {
    if (!drillPhrases.length) return;
    if (drillIdx >= drillPhrases.length) drillIdx = 0;
    var p = drillPhrases[drillIdx];
    targetQ = p.q;
    targetA = p.a;
    targetAH = p.a_hu || '';
    // Update the main player UI
    document.getElementById('questionText').textContent = p.q;
    document.getElementById('answerText').textContent = p.a_hu || p.a;
    document.getElementById('resultCard').classList.add('hidden');
    document.getElementById('resultCard').classList.remove('result-pass', 'result-fail');
    document.getElementById('matchScore').textContent = '';
    document.getElementById('transcript').textContent = '';
    document.getElementById('playbackBtn').classList.add('hidden');
    document.getElementById('categoryTag').textContent = activeDrillName;
    document.getElementById('revealDetails').removeAttribute('open');
    lastRecordingBlob = null;
    questionAttempted = false;
    // Update progress for drill
    var pct = drillPhrases.length > 0 ? Math.min(100, ((drillIdx) / drillPhrases.length) * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressLabel').textContent = (drillIdx + 1) + ' / ' + drillPhrases.length;
    if (listenMode) applyListenMode();
    if (translateOn) fetchTranslation(); else { document.getElementById('inlineTranslation').classList.add('hidden'); }
    if (phoneticOn) fetchPhonetic(); else { document.getElementById('phoneticHint').classList.add('hidden'); }
    speak(currentSpeed);
}

// ── Grammar patterns browser ──────────────────────────────────────────
var grammarLoaded = false;
var allGrammarPatterns = [];
var grammarActiveTag = '';

function loadGrammarPatterns() {
    if (grammarLoaded) return;
    fetch('?ajax=1&action=grammar_patterns')
        .then(function(r) { return r.json(); })
        .then(function(patterns) {
            grammarLoaded = true;
            allGrammarPatterns = patterns;
            document.getElementById('grammarCount').textContent = patterns.length + ' patterns';
            renderGrammarPatterns(patterns);
            buildGrammarTagFilter(patterns);
            lucide.createIcons();
        });
}

function renderGrammarPatterns(patterns) {
    var list = document.getElementById('grammarList');
    list.textContent = '';
    if (!patterns.length) {
        var empty = document.createElement('p');
        empty.className = 'text-slate-500 text-sm text-center py-4';
        empty.textContent = 'No patterns found.';
        list.appendChild(empty);
        return;
    }

    // Group patterns by their primary tag
    var groups = {};
    var ungrouped = [];
    patterns.forEach(function(p) {
        var primaryTag = (p.tags || '').split(',')[0].trim();
        if (!primaryTag) { ungrouped.push(p); return; }
        if (!groups[primaryTag]) groups[primaryTag] = [];
        groups[primaryTag].push(p);
    });

    var sortedTags = Object.keys(groups).sort();

    // If filtering by a single tag, show flat list (already scoped)
    if (grammarActiveTag) {
        renderGrammarFlat(list, patterns);
        return;
    }

    // Render grouped sections
    sortedTags.forEach(function(tag) {
        var section = document.createElement('div');
        section.className = 'glass rounded-2xl overflow-hidden';

        // Section header (clickable to expand)
        var sectionHeader = document.createElement('button');
        sectionHeader.className = 'w-full flex items-center justify-between px-4 py-3 hover:bg-white/[0.02] transition-all';
        var headerLeft = document.createElement('div');
        headerLeft.className = 'flex items-center gap-2.5';
        var tagBadge = document.createElement('span');
        tagBadge.className = 'tag-pill';
        tagBadge.textContent = tag;
        var countBadge = document.createElement('span');
        countBadge.className = 'text-[10px] text-slate-500 font-medium';
        countBadge.textContent = groups[tag].length + ' pattern' + (groups[tag].length !== 1 ? 's' : '');
        headerLeft.appendChild(tagBadge);
        headerLeft.appendChild(countBadge);
        var chevron = document.createElement('i');
        chevron.setAttribute('data-lucide', 'chevron-down');
        chevron.className = 'w-4 h-4 text-slate-500 transition-transform';
        sectionHeader.appendChild(headerLeft);
        sectionHeader.appendChild(chevron);
        section.appendChild(sectionHeader);

        // Section body (hidden)
        var body = document.createElement('div');
        body.className = 'hidden border-t border-white/5';

        groups[tag].forEach(function(p) {
            body.appendChild(buildPatternRow(p));
        });

        section.appendChild(body);

        // Toggle
        sectionHeader.onclick = function() {
            var isOpen = !body.classList.contains('hidden');
            body.classList.toggle('hidden');
            chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
        };

        list.appendChild(section);
    });

    // Ungrouped at bottom
    if (ungrouped.length) {
        var section = document.createElement('div');
        section.className = 'glass rounded-2xl overflow-hidden';
        var sectionHeader = document.createElement('button');
        sectionHeader.className = 'w-full flex items-center justify-between px-4 py-3 hover:bg-white/[0.02] transition-all';
        var headerLeft = document.createElement('div');
        headerLeft.className = 'flex items-center gap-2.5';
        var tagBadge = document.createElement('span');
        tagBadge.className = 'text-xs font-semibold text-slate-400';
        tagBadge.textContent = 'Other';
        var countBadge = document.createElement('span');
        countBadge.className = 'text-[10px] text-slate-500 font-medium';
        countBadge.textContent = ungrouped.length + ' pattern' + (ungrouped.length !== 1 ? 's' : '');
        headerLeft.appendChild(tagBadge);
        headerLeft.appendChild(countBadge);
        var chevron = document.createElement('i');
        chevron.setAttribute('data-lucide', 'chevron-down');
        chevron.className = 'w-4 h-4 text-slate-500 transition-transform';
        sectionHeader.appendChild(headerLeft);
        sectionHeader.appendChild(chevron);
        section.appendChild(sectionHeader);
        var body = document.createElement('div');
        body.className = 'hidden border-t border-white/5';
        ungrouped.forEach(function(p) { body.appendChild(buildPatternRow(p)); });
        section.appendChild(body);
        sectionHeader.onclick = function() {
            var isOpen = !body.classList.contains('hidden');
            body.classList.toggle('hidden');
            chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
        };
        list.appendChild(section);
    }

    lucide.createIcons();
}

// Flat list for filtered/search results
function renderGrammarFlat(list, patterns) {
    patterns.forEach(function(p) { list.appendChild(buildPatternRow(p)); });
    lucide.createIcons();
}

// Build a single pattern row (reusable)
function buildPatternRow(p) {
    var row = document.createElement('div');
    row.className = 'px-4 py-3 border-b border-white/[0.03] last:border-0 cursor-pointer hover:bg-white/[0.02] transition-all';

    // Top line: title + chevron
    var top = document.createElement('div');
    top.className = 'flex items-start justify-between gap-2';
    var titleWrap = document.createElement('div');
    titleWrap.className = 'flex-1';
    var title = document.createElement('h3');
    title.className = 'text-sm font-semibold text-white';
    title.textContent = p.pattern;
    titleWrap.appendChild(title);
    if (p.suffix_words) {
        var suffix = document.createElement('span');
        suffix.className = 'text-[10px] text-accent-light font-mono';
        suffix.textContent = p.suffix_words;
        titleWrap.appendChild(document.createTextNode(' '));
        titleWrap.appendChild(suffix);
    }
    var chevron = document.createElement('i');
    chevron.setAttribute('data-lucide', 'chevron-down');
    chevron.className = 'w-3.5 h-3.5 text-slate-600 flex-shrink-0 mt-1 transition-transform';
    top.appendChild(titleWrap);
    top.appendChild(chevron);
    row.appendChild(top);

    // Expandable detail
    var detail = document.createElement('div');
    detail.className = 'hidden mt-3 pt-3 border-t border-white/5 space-y-3';

    if (p.explanation) {
        var expl = document.createElement('p');
        expl.className = 'text-xs text-slate-400';
        expl.textContent = p.explanation;
        detail.appendChild(expl);
    }

    // Action buttons
    var actionRow = document.createElement('div');
    actionRow.className = 'flex gap-2';
    var listenBtn = document.createElement('button');
    listenBtn.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-50 text-xs font-semibold text-accent-light hover:bg-surface-200 transition-all';
    listenBtn.innerHTML = '<i data-lucide="volume-2" class="w-3.5 h-3.5"></i> Listen';
    listenBtn.onclick = function(e) {
        e.stopPropagation();
        window.speechSynthesis.cancel();
        var msg = new SpeechSynthesisUtterance(p.suffix_words || p.pattern);
        msg.lang = 'hu-HU'; msg.rate = 0.8;
        if (huVoice) msg.voice = huVoice;
        window.speechSynthesis.speak(msg);
    };
    actionRow.appendChild(listenBtn);

    var teachBtn = document.createElement('button');
    teachBtn.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent/20 text-xs font-semibold text-yellow-300 hover:bg-accent/30 transition-all border border-yellow-400/20';
    teachBtn.innerHTML = '<i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Teach Me';
    teachBtn.onclick = function(e) {
        e.stopPropagation();
        teachMe(p);
    };
    actionRow.appendChild(teachBtn);
    detail.appendChild(actionRow);

    row.appendChild(detail);

    // Toggle
    row.onclick = function() {
        var isOpen = !detail.classList.contains('hidden');
        detail.classList.toggle('hidden');
        chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    };

    return row;
}

function buildGrammarTagFilter(patterns) {
    var tagSet = {};
    patterns.forEach(function(p) {
        if (!p.tags) return;
        p.tags.split(',').forEach(function(t) {
            t = t.trim();
            if (t) tagSet[t] = (tagSet[t] || 0) + 1;
        });
    });
    var container = document.getElementById('grammarTagFilter');
    container.textContent = '';

    var allPill = document.createElement('span');
    allPill.className = 'tag-pill cursor-pointer' + (!grammarActiveTag ? ' tag-pill-active' : '');
    allPill.textContent = 'All';
    allPill.onclick = function() { filterGrammarByTag(''); };
    container.appendChild(allPill);

    Object.keys(tagSet).sort().forEach(function(tag) {
        var pill = document.createElement('span');
        pill.className = 'tag-pill cursor-pointer' + (grammarActiveTag === tag ? ' tag-pill-active' : '');
        pill.textContent = tag + ' (' + tagSet[tag] + ')';
        pill.onclick = function() { filterGrammarByTag(tag); };
        container.appendChild(pill);
    });
}

function filterGrammarByTag(tag) {
    grammarActiveTag = (grammarActiveTag === tag) ? '' : tag;
    var filtered = grammarActiveTag
        ? allGrammarPatterns.filter(function(p) { return p.tags && p.tags.indexOf(grammarActiveTag) !== -1; })
        : allGrammarPatterns;
    renderGrammarPatterns(filtered);
    buildGrammarTagFilter(allGrammarPatterns);
    document.getElementById('grammarCount').textContent = filtered.length + ' patterns';
}

var grammarDebounce;
var grammarSearchQuery = '';
function searchGrammar() {
    clearTimeout(grammarDebounce);
    grammarDebounce = setTimeout(function() {
        grammarSearchQuery = document.getElementById('grammarSearch').value.trim().toLowerCase();
        var filtered = allGrammarPatterns.filter(function(p) {
            if (!grammarSearchQuery) return true;
            return (p.pattern || '').toLowerCase().indexOf(grammarSearchQuery) !== -1 ||
                   (p.explanation || '').toLowerCase().indexOf(grammarSearchQuery) !== -1 ||
                   (p.suffix_words || '').toLowerCase().indexOf(grammarSearchQuery) !== -1;
        });
        if (grammarActiveTag) {
            filtered = filtered.filter(function(p) { return p.tags && p.tags.indexOf(grammarActiveTag) !== -1; });
        }
        // Flat list when searching, grouped when browsing
        if (grammarSearchQuery) {
            var list = document.getElementById('grammarList');
            list.textContent = '';
            renderGrammarFlat(list, filtered);
        } else {
            renderGrammarPatterns(filtered);
        }
        document.getElementById('grammarCount').textContent = filtered.length + ' patterns';
    }, 200);
}

// ── AI Teach Me ───────────────────────────────────────────────────────
function teachMe(pattern) {
    var panel = document.getElementById('lessonPanel');
    var content = document.getElementById('lessonContent');
    var titleSpan = document.getElementById('lessonTitle').querySelector('span');
    titleSpan.textContent = pattern.pattern;
    content.innerHTML = '<div class="flex flex-col items-center py-8 gap-3"><div class="w-8 h-8 border-2 border-accent-light border-t-transparent rounded-full animate-spin"></div><p class="text-slate-400 text-sm">Generating lesson...</p></div>';
    panel.classList.remove('hidden');
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    lucide.createIcons();

    var fd = new FormData();
    fd.append('pattern', pattern.pattern);
    fd.append('suffix_words', pattern.suffix_words || '');
    fd.append('explanation', pattern.explanation || '');

    fetch('?ajax=1&action=teach_me', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { content.innerHTML = '<p class="text-red-400 text-sm text-center py-4">' + data.error + '</p>'; return; }
            renderLesson(data, pattern);
        })
        .catch(function(err) {
            content.innerHTML = '<p class="text-red-400 text-sm text-center py-4">Failed to load lesson: ' + err.message + '</p>';
        });
}

function renderLesson(data, pattern) {
    var content = document.getElementById('lessonContent');
    var html = '';

    // Lesson explanation
    html += '<div class="bg-surface-50 rounded-xl p-4 border border-white/5">';
    html += '<p class="text-sm text-slate-200 leading-relaxed">' + escHtml(data.lesson) + '</p>';
    html += '</div>';

    // Tip
    if (data.tip) {
        html += '<div class="bg-yellow-400/5 rounded-xl p-4 border border-yellow-400/15 flex items-start gap-3">';
        html += '<i data-lucide="lightbulb" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5"></i>';
        html += '<p class="text-sm text-yellow-200">' + escHtml(data.tip) + '</p>';
        html += '</div>';
    }

    // Examples
    if (data.examples && data.examples.length) {
        html += '<div>';
        html += '<h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Examples</h3>';
        html += '<div class="space-y-2">';
        data.examples.forEach(function(ex, i) {
            var huText = ex.highlight ? ex.hu.replace(ex.highlight, '<span class="text-accent-light font-bold">' + escHtml(ex.highlight) + '</span>') : escHtml(ex.hu);
            html += '<div class="bg-surface-50 rounded-lg p-3 border border-white/5">';
            html += '<div class="flex items-center justify-between">';
            html += '<p class="text-sm text-white font-medium">' + huText + '</p>';
            html += '<button onclick="speakHu(\'' + escAttr(ex.hu) + '\')" class="p-1.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-accent-light transition-all flex-shrink-0"><i data-lucide="volume-2" class="w-4 h-4"></i></button>';
            html += '</div>';
            html += '<p class="text-xs text-slate-400 mt-1">' + escHtml(ex.en) + '</p>';
            html += '</div>';
        });
        html += '</div></div>';
    }

    // Quiz
    if (data.quiz && data.quiz.length) {
        html += '<div>';
        html += '<h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Quick Quiz</h3>';
        html += '<div class="space-y-3">';
        data.quiz.forEach(function(q, i) {
            html += '<div class="bg-surface-50 rounded-lg p-4 border border-white/5" id="quiz-' + i + '">';
            html += '<p class="text-sm text-white mb-2">' + escHtml(q.prompt) + '</p>';
            html += '<div class="flex items-center gap-2">';
            html += '<input type="text" class="flex-1 bg-surface-300 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 outline-none border border-white/5 focus:border-accent/40" placeholder="Your answer..." id="quiz-input-' + i + '" data-answer="' + escAttr(q.answer) + '" onkeydown="if(event.key===\'Enter\')checkQuiz(' + i + ')">';
            html += '<button onclick="checkQuiz(' + i + ')" class="px-3 py-2 rounded-lg bg-accent text-white text-xs font-semibold hover:bg-accent-dark transition-all">Check</button>';
            html += '</div>';
            html += '<p class="text-xs text-slate-500 mt-1.5 hidden" id="quiz-hint-' + i + '">' + escHtml(q.hint) + '</p>';
            html += '<p class="text-xs mt-1.5 hidden" id="quiz-result-' + i + '"></p>';
            html += '</div>';
        });
        html += '</div></div>';
    }

    content.innerHTML = html;
    lucide.createIcons();
}

function checkQuiz(idx) {
    var input = document.getElementById('quiz-input-' + idx);
    var result = document.getElementById('quiz-result-' + idx);
    var hint = document.getElementById('quiz-hint-' + idx);
    var answer = input.dataset.answer.toLowerCase().trim();
    var userAnswer = input.value.toLowerCase().trim();

    result.classList.remove('hidden');
    if (userAnswer === answer) {
        result.className = 'text-xs mt-1.5 text-green-400 font-semibold';
        result.textContent = 'Correct!';
        input.classList.add('border-green-500/40');
        input.classList.remove('border-white/5', 'border-red-500/40');
    } else {
        result.className = 'text-xs mt-1.5 text-red-400';
        result.textContent = 'Answer: ' + input.dataset.answer;
        input.classList.add('border-red-500/40');
        input.classList.remove('border-white/5', 'border-green-500/40');
        hint.classList.remove('hidden');
    }
}

function closeLesson() {
    document.getElementById('lessonPanel').classList.add('hidden');
}

function speakHu(text) {
    window.speechSynthesis.cancel();
    var msg = new SpeechSynthesisUtterance(text);
    msg.lang = 'hu-HU'; msg.rate = 0.8;
    if (huVoice) msg.voice = huVoice;
    window.speechSynthesis.speak(msg);
}

function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function escAttr(s) { return s.replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

// ── Skill proficiency display ─────────────────────────────────────────
function loadSkillProficiency() {
    fetch('?who=' + who + '&ajax=1&action=skill_proficiency')
        .then(function(r) { return r.json(); })
        .then(function(skills) {
            var list = document.getElementById('skillList');
            list.textContent = '';
            if (!skills.length) {
                var empty = document.createElement('p');
                empty.className = 'text-slate-500 text-sm text-center py-4';
                empty.textContent = 'Start practicing to build your skill profile.';
                list.appendChild(empty);
                return;
            }
            skills.forEach(function(s) {
                var total = (parseInt(s.pass_count) || 0) + (parseInt(s.fail_count) || 0);
                var pct = total > 0 ? Math.round((s.pass_count / total) * 100) : 0;
                var row = document.createElement('div');
                row.className = 'flex items-center gap-3 p-3 rounded-xl bg-surface-50';

                var nameEl = document.createElement('span');
                nameEl.className = 'text-sm text-white font-medium flex-1';
                nameEl.textContent = s.skill;

                var barWrap = document.createElement('div');
                barWrap.className = 'w-20 h-2 bg-surface-300 rounded-full overflow-hidden';
                var barFill = document.createElement('div');
                barFill.className = 'h-full rounded-full ' + (pct >= 80 ? 'bg-green-500' : pct >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                barFill.style.width = pct + '%';
                barWrap.appendChild(barFill);

                var pctEl = document.createElement('span');
                pctEl.className = 'text-xs text-slate-400 w-10 text-right';
                pctEl.textContent = pct + '%';

                row.appendChild(nameEl);
                row.appendChild(barWrap);
                row.appendChild(pctEl);
                list.appendChild(row);
            });
        });
}

// ── Init ──────────────────────────────────────────────────────────────
setMode(currentMode);
setCat(cat, true);
setSpeed(currentSpeed);
applyListenMode();
applyAutoAdvance();
applyTranslateState();
applyPhoneticState();
if (translateOn) fetchTranslation();
if (phoneticOn) fetchPhonetic();
updateProgressBar();
showView('home');
lucide.createIcons();
</script>
</body>
</html>
