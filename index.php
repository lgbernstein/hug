<?php
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
$who = isset($_GET['who']) ? $_GET['who'] : 'All';
$who = in_array($who, ['Maria', 'Larry', 'All']) ? $who : 'All';
$cat = $_GET['cat'] ?? 'all';
$cat = in_array($cat, ['all','prep','bios']) ? $cat : 'all';

// Columns added in v5/v6 migrations — always present
$hasAnswerHu = true;

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

// AJAX: save a user bio fact
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'save_bio') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }
    $subj = trim($_POST['subject_name'] ?? '');
    $label = trim($_POST['fact_label_hu'] ?? '');
    $value = trim($_POST['fact_value_hu'] ?? '');
    if (!$subj || !$label) { echo json_encode(['error'=>'subject_name and fact_label_hu required']); exit; }
    $stmt = $conn->prepare("INSERT INTO user_bios (subject_name, fact_label_hu, fact_value_hu) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE fact_value_hu = VALUES(fact_value_hu)");
    $stmt->bind_param('sss', $subj, $label, $value);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>true]);
    exit;
}

// AJAX: list all phrases for browser
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'phrases') {
    header('Content-Type: application/json');
    $search = $conn->real_escape_string(str_replace(['%','_'], ['\\%','\\_'], $_GET['search'] ?? ''));
    $tagFilter = $_GET['tag'] ?? '';
    $limitParam = (int)($_GET['limit'] ?? 0);
    $ahuBrowse = $hasAnswerHu ? "COALESCE(hp.answer_hu,'') AS a_hu," : "'' AS a_hu,";
    $sql = "SELECT hp.question_hu AS q, hp.answer_en AS a, $ahuBrowse hp.category,
            COALESCE(sh.pass_count, 0) AS pass_count, COALESCE(sh.fail_count, 0) AS fail_count, sh.next_review
            FROM hungarian_prep hp
            LEFT JOIN study_history sh ON sh.phrase = hp.question_hu AND sh.who = '$who_safe'";
    $wheres = [];
    if ($search) $wheres[] = "(hp.question_hu LIKE '%$search%' OR hp.answer_en LIKE '%$search%')";
    if ($tagFilter) {
        $tagWhere = buildTagWhere($tagFilter, $conn);
        $wheres[] = $tagWhere;
    }
    $whoFilter = ($who !== 'All') ? "(hp.`who` = 'All' OR hp.`who` = '$who_safe')" : '';
    if ($whoFilter) $wheres[] = $whoFilter;
    if ($wheres) $sql .= " WHERE " . implode(' AND ', $wheres);
    $sql .= " ORDER BY " . ($tagFilter ? "RAND()" : "hp.category, hp.question_hu");
    if ($limitParam > 0) $sql .= " LIMIT $limitParam";
    $result = $conn->query($sql);
    $rows = [];
    if ($result) { while ($r = $result->fetch_assoc()) {
        $r['pass_count'] = (int)$r['pass_count'];
        $r['fail_count'] = (int)$r['fail_count'];
        $rows[] = $r;
    }}
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
        $t = str_replace(['%', '_'], ['\\%', '\\_'], $conn->real_escape_string($t));
        $clauses[] = "tags LIKE '%$t%'";
    }
    return $clauses ? '(' . implode(' OR ', $clauses) . ')' : '0';
}

// AJAX: list drill groups
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'drill_groups') {
    header('Content-Type: application/json');
    $groups = [];
    $r = $conn->query("SELECT id, name, description, tag_match, source FROM drill_groups ORDER BY name");
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
    $tagMatch = $groupName;
    $lookup = $conn->query("SELECT tag_match FROM drill_groups WHERE name = '$groupName' LIMIT 1");
    if ($lookup && $row = $lookup->fetch_assoc()) {
        $tagMatch = $row['tag_match'] ?: $groupName;
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

// AJAX: scenario-based study data
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'scenarios') {
    header('Content-Type: application/json');
    $ahuCol = $hasAnswerHu ? "COALESCE(answer_hu,'')" : "''";
    $whoFilter = ($who !== 'All') ? " AND (`who` = 'All' OR `who` = '$who_safe')" : "";

    // Define scenarios with tag/category mappings
    $scenarioDefs = [
        ['id' => 'greeting', 'title' => 'Greeting & Small Talk', 'emoji' => '👋', 'desc' => 'Jó napot! Weather, nerves, travel', 'tags' => ['greeting','greetings','closing','interview'], 'cats' => ['interview'], 'tagRequired' => ['greeting','greetings','closing']],
        ['id' => 'who_are_you', 'title' => 'Who Are You?', 'emoji' => '🪪', 'desc' => 'Name, birthday, marriage, profession, education', 'tags' => ['personal-info','profession','education','family'], 'cats' => ['interview','personal'], 'tagRequired' => ['personal-info','profession','education']],
        ['id' => 'roots', 'title' => 'Hungarian Roots', 'emoji' => '🌳', 'desc' => 'Ancestry, Polena, Trianon, why citizenship', 'tags' => ['origin','motivation','history'], 'cats' => ['origin','motivation'], 'tagRequired' => ['origin','motivation']],
        ['id' => 'budapest', 'title' => 'Budapest Trip', 'emoji' => '🏛️', 'desc' => 'What you saw, liked, ate', 'tags' => ['budapest','food'], 'cats' => ['budapest','food'], 'tagRequired' => ['budapest','food']],
        ['id' => 'hungary_knowledge', 'title' => 'Hungary Knowledge', 'emoji' => '📚', 'desc' => 'History, geography, government, culture', 'tags' => ['facts','history','geography','government','culture'], 'cats' => ['prep'], 'tagRequired' => ['facts','history','geography','government']],
        ['id' => 'documents', 'title' => 'Documents & Closing', 'emoji' => '📋', 'desc' => 'Passport, application, goodbye', 'tags' => ['documents','closing','colors','time','flag'], 'cats' => ['interview'], 'tagRequired' => ['documents','colors','time','flag']],
        ['id' => 'daily_vocab', 'title' => 'Daily Vocabulary', 'emoji' => '💬', 'desc' => 'Core verbs, adjectives, places, food', 'tags' => ['tana-vocab','vocabulary','noun','verb','adjective','expression'], 'cats' => ['prep','vocab'], 'tagRequired' => ['tana-vocab','vocabulary']],
    ];

    $scenarios = [];
    foreach ($scenarioDefs as $s) {
        // Build WHERE clause: match tags OR categories
        $tagClauses = [];
        foreach ($s['tagRequired'] as $t) {
            $te = $conn->real_escape_string($t);
            $tagClauses[] = "tags LIKE '%$te%'";
        }
        $catClauses = [];
        foreach ($s['cats'] as $c) {
            $ce = $conn->real_escape_string($c);
            $catClauses[] = "category = '$ce'";
        }
        $where = '(' . implode(' OR ', array_merge($tagClauses, $catClauses)) . ')';

        // Count total phrases
        $total = 0;
        $r = $conn->query("SELECT COUNT(*) AS c FROM hungarian_prep WHERE $where $whoFilter");
        if ($r) $total = (int)($r->fetch_assoc()['c'] ?? 0);

        // Count mastered (pass_count >= 3)
        $mastered = 0;
        $r = $conn->query("SELECT COUNT(DISTINCT sh.phrase) AS c FROM study_history sh INNER JOIN hungarian_prep hp ON sh.phrase = hp.question_hu WHERE sh.who='$who_safe' AND sh.pass_count >= 3 AND $where");
        if ($r) $mastered = (int)($r->fetch_assoc()['c'] ?? 0);

        // Count due for review
        $due = 0;
        $r = $conn->query("SELECT COUNT(DISTINCT sh.phrase) AS c FROM study_history sh INNER JOIN hungarian_prep hp ON sh.phrase = hp.question_hu WHERE sh.who='$who_safe' AND sh.next_review <= NOW() AND $where");
        if ($r) $due = (int)($r->fetch_assoc()['c'] ?? 0);

        $scenarios[] = [
            'id' => $s['id'], 'title' => $s['title'], 'emoji' => $s['emoji'],
            'desc' => $s['desc'], 'total' => $total, 'mastered' => $mastered, 'due' => $due,
            'pct' => $total > 0 ? round(($mastered / $total) * 100) : 0
        ];
    }
    echo json_encode($scenarios);
    exit;
}

// AJAX: get phrases for a scenario
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'scenario_phrases') {
    header('Content-Type: application/json');
    $scenarioId = $conn->real_escape_string($_GET['scenario'] ?? '');
    $ahuCol = $hasAnswerHu ? "COALESCE(answer_hu,'')" : "''";
    $whoFilter = ($who !== 'All') ? " AND (`who` = 'All' OR `who` = '$who_safe')" : "";

    $tagMap = [
        'greeting' => ['greeting','greetings','closing'],
        'who_are_you' => ['personal-info','profession','education'],
        'roots' => ['origin','motivation'],
        'budapest' => ['budapest','food'],
        'hungary_knowledge' => ['facts','history','geography','government'],
        'documents' => ['documents','colors','time','flag'],
        'daily_vocab' => ['tana-vocab','vocabulary'],
    ];
    $catMap = [
        'greeting' => ['interview'],
        'who_are_you' => ['interview','personal'],
        'roots' => ['origin','motivation'],
        'budapest' => ['budapest','food'],
        'hungary_knowledge' => ['prep'],
        'documents' => ['interview'],
        'daily_vocab' => ['prep','vocab'],
    ];

    $tags = $tagMap[$scenarioId] ?? [];
    $cats = $catMap[$scenarioId] ?? [];
    $clauses = [];
    foreach ($tags as $t) { $te = $conn->real_escape_string($t); $clauses[] = "tags LIKE '%$te%'"; }
    foreach ($cats as $c) { $ce = $conn->real_escape_string($c); $clauses[] = "category = '$ce'"; }
    if (!$clauses) { echo json_encode([]); exit; }
    $where = '(' . implode(' OR ', $clauses) . ')';

    // Join with study_history to get mastery info, prioritize due/unseen items
    $sql = "SELECT hp.question_hu AS q, hp.answer_en AS a, $ahuCol AS a_hu, hp.category, hp.tags,
                   COALESCE(sh.pass_count, 0) AS pass_count, COALESCE(sh.fail_count, 0) AS fail_count,
                   sh.next_review,
                   CASE
                     WHEN sh.id IS NULL THEN 0
                     WHEN sh.next_review <= NOW() THEN 1
                     WHEN sh.pass_count >= 3 THEN 3
                     ELSE 2
                   END AS priority
            FROM hungarian_prep hp
            LEFT JOIN study_history sh ON sh.phrase = hp.question_hu AND sh.who = '$who_safe'
            WHERE $where $whoFilter
            ORDER BY priority ASC, RAND()";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// AJAX: must-nail phrases (essential interview questions)
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'must_nail') {
    header('Content-Type: application/json');
    $ahuCol = $hasAnswerHu ? "COALESCE(hp.answer_hu,'')" : "''";
    $whoFilter = ($who !== 'All') ? " AND (hp.`who` = 'All' OR hp.`who` = '$who_safe')" : "";
    $sql = "SELECT hp.question_hu AS q, hp.answer_en AS a, $ahuCol AS a_hu, hp.category, hp.tags,
                   COALESCE(sh.pass_count, 0) AS pass_count, COALESCE(sh.fail_count, 0) AS fail_count
            FROM hungarian_prep hp
            LEFT JOIN study_history sh ON sh.phrase = hp.question_hu AND sh.who = '$who_safe'
            WHERE hp.tags LIKE '%essential%' $whoFilter
            ORDER BY COALESCE(sh.pass_count, 0) ASC, COALESCE(sh.fail_count, 0) DESC";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// AJAX: smart recommendations (weak areas)
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'recommendations') {
    header('Content-Type: application/json');
    // Find grammar patterns with most failed related phrases
    $recs = [];
    // 1. Weakest grammar patterns
    $sql = "SELECT gp.id, gp.pattern, gp.explanation, gp.tags,
                   SUM(COALESCE(sh.fail_count,0)) AS total_fails,
                   COUNT(DISTINCT sh.phrase) AS studied
            FROM grammar_patterns gp
            LEFT JOIN hungarian_prep hp ON " . buildTagWhere('gp.tags', $conn) . "
            LEFT JOIN study_history sh ON sh.phrase = hp.question_hu AND sh.who = '$who_safe'
            GROUP BY gp.id
            ORDER BY total_fails DESC, studied ASC
            LIMIT 5";
    // Simplified: just get patterns with most failures or least studied
    $sql = "SELECT gp.id, gp.pattern, gp.explanation, gp.suffix_words, gp.part_of_speech, gp.tags
            FROM grammar_patterns gp
            LEFT JOIN study_history sh ON sh.item_type='grammar' AND sh.item_id=gp.id AND sh.who='$who_safe'
            WHERE sh.id IS NULL OR sh.pass_count < 3
            ORDER BY COALESCE(sh.fail_count,0) DESC, COALESCE(sh.pass_count,0) ASC, RAND()
            LIMIT 5";
    $r = $conn->query($sql);
    if ($r) { while ($row = $r->fetch_assoc()) $recs[] = $row; }
    echo json_encode($recs);
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
    $gq = $conn->query("SELECT id, name, description, tag_match FROM drill_groups ORDER BY name LIMIT 6");
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

// AJAX: grammar breakdown for a sentence
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'breakdown') {
    header('Content-Type: application/json');
    $sentence = trim($_POST['sentence'] ?? '');
    $english = trim($_POST['english'] ?? '');
    if (!$sentence) { echo json_encode(['error' => 'No sentence']); exit; }

    $sentenceEsc = addslashes($sentence);
    $englishEsc = addslashes($english);
    $prompt = "You are a Hungarian language tutor. Break down this Hungarian sentence word-by-word for an English speaker.\n\nSentence: **{$sentenceEsc}**" . ($english ? "\nEnglish meaning: {$englishEsc}" : "") . "\n\nFor EACH word or particle, explain:\n1. The base word and its meaning\n2. Any suffixes, cases, or conjugation endings and what they do\n3. Why this form is used here\n\nKeep explanations SHORT and practical. Use this JSON format:\n{\n  \"words\": [\n    {\"word\": \"the Hungarian word\", \"base\": \"dictionary form\", \"meaning\": \"English meaning\", \"suffixes\": \"explanation of endings/suffixes or none\", \"role\": \"its role in the sentence\"}\n  ],\n  \"structure\": \"One sentence explaining the overall sentence structure.\",\n  \"tip\": \"One practical tip for remembering this pattern.\"\n}\n\nBe concise. Focus on suffixes and grammar mechanics.";

    $apiKey = $env['GEMINI_KEY'];
    $geminiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=" . urlencode($apiKey);
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 2048]
    ]);
    $ch = curl_init($geminiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$resp) { echo json_encode(['error' => 'Gemini API error', 'http_code' => $httpCode]); exit; }
    $data = json_decode($resp, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $parsed = json_decode($text, true);
    if (!$parsed) { echo json_encode(['error' => 'Parse error', 'raw' => $text]); exit; }
    echo json_encode($parsed);
    exit;
}

// AJAX: list learning resources
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'resources') {
    header('Content-Type: application/json');
    $r = $conn->query("SELECT id, category, name, url, icon, sort_order FROM learning_resources ORDER BY sort_order, category, name");
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    echo json_encode($rows);
    exit;
}

// AJAX: list knowledge cards
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'knowledge_cards') {
    header('Content-Type: application/json');
    $kcCat = $conn->real_escape_string($_GET['kccat'] ?? '');
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $sql = "SELECT id, category, title_hu, title_en, content_hu, content_en, key_fact, tags FROM knowledge_cards WHERE 1=1";
    if ($kcCat) $sql .= " AND category = '$kcCat'";
    if ($search) $sql .= " AND (title_hu LIKE '%$search%' OR title_en LIKE '%$search%' OR content_hu LIKE '%$search%' OR content_en LIKE '%$search%')";
    $sql .= " ORDER BY category, title_hu";
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    // Merge family cards from user_bios if category is family or all
    if (!$kcCat || $kcCat === 'family') {
        $bioWho = ($who !== 'All') ? "WHERE subject_name = '$who_safe'" : "";
        $bios = $conn->query("SELECT subject_name, fact_label_hu, fact_value_hu FROM user_bios $bioWho");
        if ($bios) {
            while ($b = $bios->fetch_assoc()) {
                $rows[] = [
                    'id' => 'bio_' . md5($b['fact_label_hu'] . $b['subject_name']),
                    'category' => 'family',
                    'title_hu' => $b['fact_value_hu'],
                    'title_en' => $b['fact_label_hu'],
                    'content_hu' => $b['fact_value_hu'],
                    'content_en' => $b['fact_label_hu'] . ' (' . $b['subject_name'] . ')',
                    'key_fact' => $b['fact_value_hu'],
                    'tags' => 'family,personal'
                ];
            }
        }
    }
    echo json_encode($rows);
    exit;
}

// AJAX: save a knowledge card
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'save_knowledge') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }
    $kCat = trim($_POST['category'] ?? 'culture');
    $tHu = trim($_POST['title_hu'] ?? '');
    $tEn = trim($_POST['title_en'] ?? '');
    $cHu = trim($_POST['content_hu'] ?? '');
    $cEn = trim($_POST['content_en'] ?? '');
    $kf  = trim($_POST['key_fact'] ?? '');
    if (!$tHu) { echo json_encode(['error'=>'title_hu required']); exit; }
    $stmt = $conn->prepare("INSERT IGNORE INTO knowledge_cards (category, title_hu, title_en, content_hu, content_en, key_fact) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $kCat, $tHu, $tEn, $cHu, $cEn, $kf);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    echo json_encode(['ok'=>true, 'msg'=> $ok ? 'Saved!' : 'Already exists']);
    exit;
}

// AJAX: AI teach knowledge topic
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'knowledge_teach') {
    header('Content-Type: application/json');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $kcCategory = trim($_POST['category'] ?? '');
    if (!$title) { echo json_encode(['error' => 'No topic']); exit; }

    $prompt = "You are a Hungarian citizenship interview tutor helping an English speaker prepare for the simplified naturalization interview.

Teach this topic: **$title**
" . ($content ? "Context: $content\n" : "") . "Category: $kcCategory

Respond in JSON with this exact structure:
{
  \"lesson\": \"A clear explanation (3-4 sentences) of this topic and why it matters for the citizenship interview. Use simple English.\",
  \"key_facts\": [
    {\"hu\": \"Hungarian sentence or phrase\", \"en\": \"English translation\"},
    {\"hu\": \"...\", \"en\": \"...\"},
    {\"hu\": \"...\", \"en\": \"...\"}
  ],
  \"quiz\": [
    {\"prompt\": \"A question about this topic\", \"answer\": \"The correct answer\", \"hint\": \"A helpful hint\"},
    {\"prompt\": \"...\", \"answer\": \"...\", \"hint\": \"...\"},
    {\"prompt\": \"...\", \"answer\": \"...\", \"hint\": \"...\"}
  ],
  \"tip\": \"One practical tip or mnemonic to remember this topic.\"
}

Give exactly 3 key facts and 3 quiz questions. Key facts should be in Hungarian with English translation. Quiz questions can be in English or Hungarian.";

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

// AJAX: generate daily study plan
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'daily_plan') {
    header('Content-Type: application/json');
    $blocks = [];
    $blockNum = 0;

    // 1. Count SRS due items by type
    $duePhrases = 0; $dueGrammar = 0; $dueKnowledge = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND (item_type='phrase' OR item_type IS NULL) AND next_review <= NOW()");
    if ($r) $duePhrases = (int)($r->fetch_assoc()['c'] ?? 0);
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND item_type='grammar' AND next_review <= NOW()");
    if ($r) $dueGrammar = (int)($r->fetch_assoc()['c'] ?? 0);
    $r = $conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='$who_safe' AND item_type='knowledge' AND next_review <= NOW()");
    if ($r) $dueKnowledge = (int)($r->fetch_assoc()['c'] ?? 0);

    // 2. Check what was done today
    $todayMin = 0;
    $todayBlocks = [];
    $r = $conn->query("SELECT block_type, SUM(duration_min) AS mins FROM study_log WHERE who='$who_safe' AND DATE(completed_at) = CURDATE() GROUP BY block_type");
    if ($r) { while ($row = $r->fetch_assoc()) { $todayBlocks[$row['block_type']] = (int)$row['mins']; $todayMin += (int)$row['mins']; } }

    // 3. Get available grammar patterns not yet mastered
    $newGrammar = [];
    $r = $conn->query("SELECT gp.id, gp.pattern, gp.explanation, gp.suffix_words FROM grammar_patterns gp LEFT JOIN study_history sh ON sh.item_type='grammar' AND sh.item_id=gp.id AND sh.who='$who_safe' WHERE sh.id IS NULL OR sh.pass_count < 3 ORDER BY CASE WHEN sh.id IS NULL THEN 0 ELSE 1 END, RAND() LIMIT 3");
    if ($r) { while ($row = $r->fetch_assoc()) $newGrammar[] = $row; }

    // 4. Get knowledge categories with weak coverage
    $knowledgeCats = [];
    $r = $conn->query("SELECT category, COUNT(*) AS total FROM knowledge_cards GROUP BY category");
    if ($r) { while ($row = $r->fetch_assoc()) $knowledgeCats[$row['category']] = (int)$row['total']; }

    // 5. Get external resources for rotation
    $resources = [];
    $r = $conn->query("SELECT name, url, icon, category FROM learning_resources ORDER BY sort_order");
    if ($r) { while ($row = $r->fetch_assoc()) $resources[] = $row; }

    // 6. Build blocks — 20 min max, alternating active/passive
    $totalDue = $duePhrases + $dueGrammar + $dueKnowledge;

    // Block 1: SRS Review (active, always first)
    if ($totalDue > 0 && empty($todayBlocks['phrase_review'])) {
        $blocks[] = ['type' => 'in_app', 'block_type' => 'phrase_review', 'title' => 'Review Due Items', 'subtitle' => $totalDue . ' items due', 'duration' => min(20, max(10, $totalDue * 2)), 'icon' => 'rotate-ccw',
            'session' => ['mode' => 'review', 'limit' => min(10, $totalDue), 'tag' => 'essential']];
    }

    // Block 2: External — Pimsleur (passive listening)
    if (empty($todayBlocks['pimsleur'])) {
        $pim = array_values(array_filter($resources, function($r) { return $r['name'] === 'Pimsleur'; }));
        if ($pim) $blocks[] = ['type' => 'external', 'block_type' => 'pimsleur', 'title' => 'Pimsleur', 'subtitle' => 'Listening & speaking', 'duration' => 20, 'icon' => 'headphones', 'url' => $pim[0]['url'], 'emoji' => $pim[0]['icon']];
    }

    // Block 3: Grammar lesson (active)
    if (!empty($newGrammar) && empty($todayBlocks['grammar_lesson'])) {
        $g = $newGrammar[0];
        $blocks[] = ['type' => 'in_app', 'block_type' => 'grammar_lesson', 'title' => 'Grammar: ' . $g['pattern'], 'subtitle' => $g['explanation'] ? substr($g['explanation'], 0, 50) . '...' : 'Learn this pattern', 'duration' => 15, 'icon' => 'book-open',
            'session' => ['mode' => 'grammar', 'pattern_id' => (int)$g['id']]];
    }

    // Break
    if (count($blocks) >= 3) {
        $blocks[] = ['type' => 'break', 'block_type' => 'break', 'title' => 'Break', 'subtitle' => 'Stretch, water, move', 'duration' => 5, 'icon' => 'coffee'];
    }

    // Block 4: Interview sim (active — speaking)
    if (empty($todayBlocks['interview_sim'])) {
        $blocks[] = ['type' => 'in_app', 'block_type' => 'interview_sim', 'title' => 'Interview Practice', 'subtitle' => 'Answer personal questions', 'duration' => 15, 'icon' => 'message-square',
            'session' => ['mode' => 'interview', 'cat' => 'bios', 'limit' => 8]];
    }

    // Block 5: External — Quizlet (passive flashcards)
    if (empty($todayBlocks['quizlet'])) {
        $qz = array_values(array_filter($resources, function($r) { return $r['name'] === 'Quizlet'; }));
        if ($qz) $blocks[] = ['type' => 'external', 'block_type' => 'quizlet', 'title' => 'Quizlet', 'subtitle' => 'Vocabulary flashcards', 'duration' => 15, 'icon' => 'layers', 'url' => $qz[0]['url'], 'emoji' => $qz[0]['icon']];
    }

    // Block 6: Knowledge quiz (active)
    if (empty($todayBlocks['knowledge_review'])) {
        $weakCat = 'history';
        foreach ($knowledgeCats as $cat => $total) {
            if (!isset($todayBlocks['knowledge_' . $cat])) { $weakCat = $cat; break; }
        }
        $blocks[] = ['type' => 'in_app', 'block_type' => 'knowledge_review', 'title' => 'Knowledge: ' . ucfirst($weakCat), 'subtitle' => 'Quiz on ' . $weakCat . ' facts', 'duration' => 15, 'icon' => 'landmark',
            'session' => ['mode' => 'knowledge', 'category' => $weakCat, 'limit' => 6]];
    }

    // Break
    if (count($blocks) >= 6) {
        $blocks[] = ['type' => 'break', 'block_type' => 'break2', 'title' => 'Break', 'subtitle' => 'Rest your brain', 'duration' => 5, 'icon' => 'coffee'];
    }

    // Block 7: Phrase practice (active — pronunciation)
    if (empty($todayBlocks['phrase_practice'])) {
        $blocks[] = ['type' => 'in_app', 'block_type' => 'phrase_practice', 'title' => 'Phrase Practice', 'subtitle' => 'Mixed pronunciation', 'duration' => 20, 'icon' => 'mic',
            'session' => ['mode' => 'practice', 'cat' => 'all', 'limit' => 10]];
    }

    // Block 8: External — HungarianPod101 (passive listening)
    if (empty($todayBlocks['hungarianpod'])) {
        $hp = array_values(array_filter($resources, function($r) { return $r['name'] === 'HungarianPod101'; }));
        if ($hp) $blocks[] = ['type' => 'external', 'block_type' => 'hungarianpod', 'title' => 'HungarianPod101', 'subtitle' => 'Podcast lesson', 'duration' => 20, 'icon' => 'radio', 'url' => $hp[0]['url'], 'emoji' => $hp[0]['icon']];
    }

    // Block 9: Free practice (active)
    $blocks[] = ['type' => 'in_app', 'block_type' => 'free_practice', 'title' => 'Free Practice', 'subtitle' => 'Weak areas & explore', 'duration' => 20, 'icon' => 'target',
        'session' => ['mode' => 'practice', 'cat' => 'all', 'limit' => 10]];

    // Calculate streak
    $streak = 0;
    $r = $conn->query("SELECT DISTINCT DATE(completed_at) AS d FROM study_log WHERE who='$who_safe' ORDER BY d DESC LIMIT 60");
    if ($r) {
        $checkDate = new DateTime('today');
        while ($row = $r->fetch_assoc()) {
            $d = new DateTime($row['d']);
            if ($d->format('Y-m-d') === $checkDate->format('Y-m-d')) {
                $streak++;
                $checkDate->modify('-1 day');
            } else { break; }
        }
    }
    // Also count today if any study_history was updated today
    if ($streak === 0) {
        $r = $conn->query("SELECT 1 FROM study_history WHERE who='$who_safe' AND DATE(last_seen) = CURDATE() LIMIT 1");
        if ($r && $r->num_rows > 0) $streak = 1;
    }

    $totalPlanMin = 0;
    foreach ($blocks as $b) $totalPlanMin += $b['duration'];

    echo json_encode([
        'blocks' => $blocks,
        'streak' => $streak,
        'today_min' => $todayMin,
        'total_plan_min' => $totalPlanMin,
        'due' => ['phrases' => $duePhrases, 'grammar' => $dueGrammar, 'knowledge' => $dueKnowledge],
        'completed_blocks' => $todayBlocks
    ]);
    exit;
}

// AJAX: log a completed study block
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'log_block') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }
    $blockType = trim($_POST['block_type'] ?? '');
    $blockTitle = trim($_POST['block_title'] ?? '');
    $duration = max(0, (int)($_POST['duration_min'] ?? 0));
    $itemsCompleted = max(0, (int)($_POST['items_completed'] ?? 0));
    $itemsPassed = max(0, (int)($_POST['items_passed'] ?? 0));
    $startedAt = $_POST['started_at'] ?? date('Y-m-d H:i:s');
    if (!$blockType) { echo json_encode(['error'=>'block_type required']); exit; }
    $stmt = $conn->prepare("INSERT INTO study_log (who, block_type, block_title, duration_min, items_completed, items_passed, started_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssiiss', $who, $blockType, $blockTitle, $duration, $itemsCompleted, $itemsPassed, $startedAt);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

// AJAX: today's study log
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'today_log') {
    header('Content-Type: application/json');
    $r = $conn->query("SELECT block_type, block_title, duration_min, items_completed, items_passed, started_at, completed_at FROM study_log WHERE who='$who_safe' AND DATE(completed_at) = CURDATE() ORDER BY completed_at DESC");
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    $totalMin = 0;
    foreach ($rows as $row) $totalMin += (int)$row['duration_min'];
    echo json_encode(['log' => $rows, 'total_min' => $totalMin]);
    exit;
}

// Shuffle bypasses SRS, pure random
$shuffle = isset($_GET['shuffle']) && $_GET['shuffle'] === '1';
if ($shuffle) {
    $result = $conn->query("SELECT q, a, a_hu, category FROM ($union) AS phrases ORDER BY RAND() LIMIT 1");
} else {
    // SRS-weighted query — prioritize essential phrases, deprioritize skipped/advanced
    $srs_sql = "SELECT phrases.q, phrases.a, phrases.a_hu, phrases.category
                FROM ($union) AS phrases
                LEFT JOIN study_history sh ON sh.phrase = phrases.q AND sh.who = '$who_safe'
                LEFT JOIN hungarian_prep hp ON hp.question_hu = phrases.q
                ORDER BY
                    CASE WHEN sh.next_review IS NULL OR sh.next_review <= NOW() THEN 0 ELSE 1 END ASC,
                    CASE WHEN hp.tags LIKE '%essential%' THEN 0
                         WHEN hp.tags LIKE '%interview%' THEN 1
                         WHEN hp.tags LIKE '%tana-vocab%' THEN 2
                         ELSE 3 END ASC,
                    RAND()
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
<title>HUG COACH v8.0</title>
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
body { background: #0f172a; color: #e2e8f0; overflow-x: hidden; }
body.light { background: #f1f5f9; color: #1e293b; }
body.light .glass { background: #fff; border-color: #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
body.light .glass-strong { background: #fff; border-color: #cbd5e1; }
body.light .pill-inactive { color: #334155; border-color: #cbd5e1; }
body.light .pill-inactive:hover { color: #0f172a; background: #e2e8f0; }
body.light .grammar-card, body.light .drill-card { background: #fff; border-color: #e2e8f0; }
body.light .grammar-card:hover, body.light .drill-card:hover { border-color: #6366f1; }
body.light .phrase-item:hover { background: #f1f5f9; }
body.light .question-text { color: #0f172a; }
body.light .progress-track { background: #e2e8f0; }
body.light .result-pass { background: #f0fdf4; border-color: #86efac; }
body.light .result-fail { background: #fef2f2; border-color: #fca5a5; }
body.light [class*="bg-surface"] { background: #f1f5f9; }
body.light [class*="text-slate-3"] { color: #475569; }
body.light [class*="text-slate-4"] { color: #334155; }
body.light [class*="text-slate-5"] { color: #1e293b; }
body.light [class*="text-white"] { color: #0f172a; }
body.light [class*="border-white"] { border-color: #e2e8f0; }
body.light .quick-bar { background: #fff; border-color: #e2e8f0; }
body.light .modal-backdrop { background: rgba(0,0,0,0.4); }
body.light select option { background: white; color: #1e293b; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
.glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(99, 102, 241, 0.1); }
.glass-strong { background: rgba(30, 41, 59, 0.9); backdrop-filter: blur(30px); border: 1px solid rgba(99, 102, 241, 0.15); }
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
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
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
.quick-bar { display: flex; justify-content: space-around; align-items: center; padding: 6px 8px; background: rgba(17, 26, 46, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(99, 102, 241, 0.08); border-radius: 16px; }
@media (min-width: 768px) { .quick-bar { justify-content: center; gap: 4px; } }
.view-section { display: none; }
.view-section.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.view-section.active { animation: fadeIn 0.2s ease-out; }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.grammar-card { background: rgba(17, 26, 46, 0.6); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 12px; transition: all 0.2s; }
.grammar-card:hover { border-color: rgba(99, 102, 241, 0.15); background: rgba(17, 26, 46, 0.8); }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.drill-card { background: rgba(17, 26, 46, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 16px 20px; transition: all 0.2s; cursor: pointer; }
.drill-card:hover { border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.05); transform: translateY(-1px); }
.tag-pill { display: inline-flex; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; background: rgba(99, 102, 241, 0.1); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.15); }
.tag-pill-active { background: rgba(99, 102, 241, 0.35); border-color: rgba(99, 102, 241, 0.5); color: #fff; }
select option { background: #111a2e; color: #e2e8f0; }
</style>
</head>
<body class="min-h-screen flex flex-col items-center pb-6">

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
<div class="w-full px-4 md:px-8 pt-4 md:pt-8 space-y-4">

    <!-- HEADER -->
    <header class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-accent/20 flex items-center justify-center">
                <span class="text-sm">&#x1f1ed;&#x1f1fa;</span>
            </div>
            <div>
                <span class="text-sm font-bold tracking-wide text-white">HUG COACH</span>
                <span class="text-[10px] text-slate-500 ml-1.5">v8.0</span>
            </div>
        </div>
        <div class="flex items-center gap-1.5">
        <div id="headerSpeedBar" class="flex gap-px items-center"></div>
        <input id="headerStrictSlider" type="range" min="1" max="5" step="1" class="w-12 h-1 accent-accent cursor-pointer" title="Grading strictness">
        <span id="headerStrictLabel" class="text-[8px] font-bold text-accent-light w-10"></span>
        <button onclick="toggleTheme()" title="Toggle light/dark" class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition-all">
            <i data-lucide="sun" class="w-3.5 h-3.5" id="themeIcon"></i>
        </button>
        <a href="admin.php" title="Admin" class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition-all">
            <i data-lucide="settings" class="w-3.5 h-3.5"></i>
        </a>
        <div class="flex items-center gap-1 bg-surface-100 p-1 rounded-xl border border-white/5">
            <a href="?who=Maria&cat=<?php echo $cat; ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'Maria' ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">Maria</a>
            <a href="?who=Larry&cat=<?php echo $cat; ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'Larry' ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">Larry</a>
            <a href="?who=All&cat=<?php echo $cat; ?>"   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $who == 'All'   ? 'bg-accent text-white' : 'text-slate-500 hover:text-white'; ?>">All</a>
        </div>
        </div>
    </header>

    <!-- 3-TAB NAVIGATION -->
    <nav id="mainNav" class="quick-bar">
        <button onclick="showView('today')" id="nav-today" class="flex flex-col items-center gap-0.5 px-4 py-2 text-accent-light transition-all">
            <i data-lucide="sun" class="w-5 h-5"></i>
            <span class="text-[10px] font-semibold">Today</span>
        </button>
        <button onclick="showView('study')" id="nav-study" class="flex flex-col items-center gap-0.5 px-4 py-2 text-slate-500 hover:text-accent-light transition-all">
            <i data-lucide="book-open" class="w-5 h-5"></i>
            <span class="text-[10px] font-semibold">Study</span>
        </button>
        <button onclick="showView('progress')" id="nav-progress" class="flex flex-col items-center gap-0.5 px-4 py-2 text-slate-500 hover:text-accent-light transition-all">
            <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            <span class="text-[10px] font-semibold">Progress</span>
        </button>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: TODAY (command center) -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-today" class="view-section active space-y-4">

    <!-- Day header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-white">Today's Plan</h2>
            <p class="text-xs text-slate-400"><span id="planTotalTime">—</span> estimated</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="glass rounded-xl px-3 py-2 flex items-center gap-1.5">
                <i data-lucide="flame" class="w-4 h-4 text-amber-400"></i>
                <span id="planStreak" class="text-sm font-black text-amber-400">0</span>
            </div>
            <div class="glass rounded-xl px-3 py-2 flex items-center gap-1.5">
                <i data-lucide="clock" class="w-4 h-4 text-accent-light"></i>
                <span id="planTodayMin" class="text-sm font-bold text-accent-light">0m</span>
            </div>
        </div>
    </div>

    <!-- Day progress bar -->
    <div class="flex items-center gap-3">
        <div class="flex-1 h-2 progress-track rounded-full overflow-hidden">
            <div id="dayProgressFill" class="h-full progress-fill rounded-full" style="width: 0%"></div>
        </div>
        <span id="dayProgressLabel" class="text-[11px] text-slate-500 font-medium tabular-nums">0 of 0 completed</span>
    </div>

    <!-- Block grid -->
    <div id="planBlockList" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
        <div class="col-span-3 flex flex-col items-center py-8 gap-3">
            <div class="w-8 h-8 border-2 border-accent-light border-t-transparent rounded-full animate-spin"></div>
            <p class="text-slate-400 text-sm">Building your study plan...</p>
        </div>
    </div>

    <!-- Active session card (hidden until a block is started) -->
    <div id="sessionCard" class="hidden">
        <div class="glass rounded-3xl overflow-hidden glow-accent">
            <!-- Session header: compact — badge + blur + progress + close -->
            <div class="flex items-center justify-between px-4 py-1.5 border-b border-white/5">
                <span id="sessionBadge" class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-accent/20 text-accent-light">Review</span>
                <div class="flex items-center gap-2">
                    <button id="listenModeBtn" onclick="toggleListenMode()" title="Blur text" class="px-2 py-0.5 rounded-lg text-[9px] font-bold transition-all"></button>
                    <span id="sessionProgress" class="text-[10px] text-slate-500 font-medium tabular-nums"></span>
                    <button onclick="exitSession()" class="p-1 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
            <div id="sessionToolbar" class="hidden"></div>
            <div id="sessionSpeedBar" class="hidden"></div>
            <input id="strictSlider" type="hidden" value="3">
            <!-- Session progress -->
            <div class="px-5 pt-2">
                <div class="h-1.5 progress-track rounded-full overflow-hidden">
                    <div id="sessionProgressFill" class="h-full progress-fill rounded-full" style="width: 0%"></div>
                </div>
            </div>
            <!-- Session content (rendered dynamically) -->
            <div id="sessionContent" class="px-5 py-3 text-center flex flex-col items-center justify-center">
            </div>
            <!-- Session controls -->
            <div id="sessionControls" class="px-5 pb-5">
            </div>
        </div>
    </div>

    <!-- Session complete summary (hidden) -->
    <div id="sessionSummary" class="hidden">
        <div class="glass rounded-3xl overflow-hidden p-6 text-center glow-accent">
            <div class="w-14 h-14 rounded-full bg-green-500/20 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="check-circle" class="w-7 h-7 text-green-400"></i>
            </div>
            <h3 class="text-lg font-bold text-white mb-1">Block Complete!</h3>
            <p id="summarySubtitle" class="text-xs text-slate-400 mb-4"></p>
            <div class="flex justify-center gap-6 mb-4">
                <div><div id="summaryScore" class="text-2xl font-black text-green-400">0%</div><div class="text-[10px] text-slate-500 uppercase">Score</div></div>
                <div><div id="summaryItems" class="text-2xl font-black text-accent-light">0</div><div class="text-[10px] text-slate-500 uppercase">Items</div></div>
                <div><div id="summaryTime" class="text-2xl font-black text-amber-400">0m</div><div class="text-[10px] text-slate-500 uppercase">Time</div></div>
            </div>
            <button onclick="closeSessionSummary()" class="w-full py-3 bg-accent hover:bg-accent-dark rounded-xl text-sm font-bold text-white transition-all">
                Back to Plan
            </button>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="flex items-center gap-2">
        <button onclick="quickReview()" class="flex-1 flex items-center gap-2 p-3 rounded-xl bg-surface-100 border border-white/5 hover:border-accent/30 transition-all">
            <i data-lucide="zap" class="w-4 h-4 text-amber-400"></i>
            <div><span class="text-xs font-semibold text-white block">Quick Review</span><span class="text-[10px] text-slate-500">5 due phrases</span></div>
        </button>
        <button onclick="switchItUp()" class="flex-1 flex items-center gap-2 p-3 rounded-xl bg-surface-100 border border-white/5 hover:border-accent/30 transition-all">
            <i data-lucide="shuffle" class="w-4 h-4 text-accent-light"></i>
            <div><span class="text-xs font-semibold text-white block">Switch It Up</span><span class="text-[10px] text-slate-500">Shuffle today's plan</span></div>
        </button>
    </div>

    </div><!-- end view-today -->

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: STUDY (Scenario-based + Grammar + Resources) -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-study" class="view-section hidden space-y-4">

        <!-- Sub-nav -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1">
            <button onclick="showStudySub('scenarios')" id="studySub-scenarios" class="pill pill-active whitespace-nowrap">Scenarios</button>
            <button onclick="showStudySub('grammar')" id="studySub-grammar" class="pill pill-inactive whitespace-nowrap">Grammar</button>
            <button onclick="showStudySub('knowledge')" id="studySub-knowledge" class="pill pill-inactive whitespace-nowrap">Knowledge</button>
            <button onclick="showStudySub('resources')" id="studySub-resources" class="pill pill-inactive whitespace-nowrap">Resources</button>
            <button onclick="showStudySub('phrases')" id="studySub-phrases" class="pill pill-inactive whitespace-nowrap">Phrases</button>
        </div>

        <!-- ═══ Scenarios sub-view (NEW default) ═══ -->
        <div id="study-sub-scenarios">

        <!-- Must Nail section -->
        <div id="mustNailSection">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="text-red-400">★</span> Must Nail
                </h2>
                <button onclick="startMustNailQuiz()" class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-400 text-[11px] font-bold border border-red-500/20 hover:bg-red-500/25 transition-all">Quiz Me</button>
            </div>
            <p class="text-[11px] text-slate-500 mb-2">The ~15 questions they <em>will</em> ask. Drill to automaticity.</p>
            <div id="mustNailGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                <p class="col-span-full text-slate-500 text-sm text-center py-4">Loading...</p>
            </div>
        </div>

        <!-- Scenarios -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold text-white">Interview Scenarios</h2>
            </div>
            <div id="scenarioGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                <p class="col-span-full text-slate-500 text-sm text-center py-4">Loading scenarios...</p>
            </div>
        </div>

        <!-- Recommended Grammar (collapsed) -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i data-lucide="lightbulb" class="w-4 h-4 text-yellow-400"></i> Work On These
                </h2>
                <button onclick="toggleAllGrammar()" id="showAllGrammarBtn" class="text-[11px] text-slate-500 hover:text-white transition-colors">See all ▸</button>
            </div>
            <div id="recGrammarGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                <p class="col-span-full text-slate-500 text-sm text-center py-4">Loading...</p>
            </div>
        </div>

        <!-- All Grammar (hidden by default) -->
        <div id="allGrammarSection" class="hidden space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-white">All Grammar Patterns</h2>
                <span id="grammarCount" class="text-xs text-slate-500"></span>
            </div>
            <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
                <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                <input id="grammarSearch" type="text" placeholder="Search patterns..." oninput="searchGrammar()"
                    class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
            </div>
            <div id="grammarTagFilter" class="flex flex-wrap gap-1.5"></div>
            <div id="grammarList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2"></div>
        </div>

        <!-- AI Lesson Panel (shared) -->
        <div id="lessonPanel" class="hidden">
            <div class="glass rounded-2xl overflow-hidden border border-accent/20">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/5 bg-accent/5">
                    <h2 id="lessonTitle" class="text-base font-bold flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-yellow-400"></i> <span></span>
                    </h2>
                    <button onclick="closeLesson()" class="p-1.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div id="lessonContent" class="p-4 space-y-4">
                    <p class="text-slate-400 text-sm text-center py-8">Loading AI lesson...</p>
                </div>
            </div>
        </div>

        <!-- AI Knowledge Lesson Panel -->
        <div id="knowledgeLessonPanel" class="hidden">
            <div class="glass rounded-2xl overflow-hidden border border-accent/20">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/5 bg-accent/5">
                    <h2 id="knowledgeLessonTitle" class="text-base font-bold flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-yellow-400"></i> <span></span>
                    </h2>
                    <button onclick="closeKnowledgeLesson()" class="p-1.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div id="knowledgeLessonContent" class="p-4 space-y-4">
                    <p class="text-slate-400 text-sm text-center py-8">Loading...</p>
                </div>
            </div>
        </div>

        <!-- Scenario drill panel (quiz-first) -->
        <div id="scenarioDrillPanel" class="hidden">
            <div class="glass rounded-2xl overflow-hidden border border-accent/20">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
                    <h2 id="scenarioDrillTitle" class="text-base font-bold text-white flex items-center gap-2"><span></span></h2>
                    <div class="flex items-center gap-2">
                            <button onclick="toggleListenMode()" title="Blur text" class="px-2 py-1 rounded-lg text-[10px] font-bold transition-all"></button>
                        <span id="scenarioDrillProgress" class="text-[11px] text-slate-500 font-medium tabular-nums"></span>
                        <button onclick="closeScenarioDrill()" class="p-1.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <div class="px-4 pt-2">
                    <div class="h-1.5 progress-track rounded-full overflow-hidden">
                        <div id="scenarioDrillFill" class="h-full progress-fill rounded-full" style="width: 0%"></div>
                    </div>
                </div>
                <div id="scenarioDrillContent" class="p-5 min-h-[250px] flex flex-col items-center justify-center"></div>
                <div id="scenarioDrillControls" class="px-5 pb-5"></div>
            </div>
        </div>

        <!-- Resources (collapsed) -->
        <div>
            <button onclick="toggleResources()" class="flex items-center gap-2 text-sm font-bold text-slate-400 hover:text-white transition-colors mb-2">
                <i data-lucide="external-link" class="w-4 h-4"></i> External Resources <span id="resToggle" class="text-[10px]">▸</span>
            </button>
            <div id="resourcesCollapsed" class="hidden space-y-4">
                <div id="resourcesList" class="space-y-4">
                    <p class="text-slate-500 text-sm text-center py-4">Loading resources...</p>
                </div>
                <div class="glass rounded-2xl p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4 text-accent-light"></i>
                        <h3 class="text-sm font-bold text-white">Import from Google Sheets</h3>
                    </div>
                    <p class="text-xs text-slate-400">Paste a Google Sheets URL to import questions and answers into your phrase bank.</p>
                    <div class="flex gap-2">
                        <input id="sheetsUrl" type="text" placeholder="https://docs.google.com/spreadsheets/d/..."
                            class="flex-1 bg-surface-50 rounded-xl px-3 py-2 text-sm text-white placeholder-slate-500 outline-none border border-white/5 focus:border-accent/40">
                        <button onclick="fetchSheetPreview()" class="px-4 py-2 bg-accent hover:bg-accent-dark rounded-xl text-xs font-bold text-white transition-all">
                            Fetch
                        </button>
                    </div>
                    <div id="sheetsPreview" class="hidden space-y-3"></div>
                </div>
            </div>
        </div>

        </div><!-- end study-sub-scenarios -->

        <!-- ═══ Grammar sub-view ═══ -->
        <div id="study-sub-grammar" style="display:none">
            <div class="space-y-4">
                <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
                    <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                    <input id="grammarSearch2" type="text" placeholder="Search patterns..." oninput="searchGrammar()"
                        class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
                </div>
                <div id="grammarTagFilter2" class="flex flex-wrap gap-1.5"></div>
                <div id="grammarList2" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2"></div>
            </div>
        </div>

        <!-- ═══ Knowledge sub-view ═══ -->
        <div id="study-sub-knowledge" style="display:none">
            <div class="space-y-4">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button onclick="filterKnowledge('')" id="kc-all" class="pill pill-active">All</button>
                    <button onclick="filterKnowledge('history')" id="kc-history" class="pill pill-inactive">History</button>
                    <button onclick="filterKnowledge('geography')" id="kc-geography" class="pill pill-inactive">Geography</button>
                    <button onclick="filterKnowledge('family')" id="kc-family" class="pill pill-inactive">Family</button>
                    <button onclick="filterKnowledge('culture')" id="kc-culture" class="pill pill-inactive">Culture</button>
                </div>
                <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
                    <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                    <input id="knowledgeSearch" type="text" placeholder="Search knowledge cards..." oninput="searchKnowledge()"
                        class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="knowledgeQuizMode()" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-green-500/10 text-green-400 text-xs font-semibold border border-green-500/15 hover:bg-green-500/20 transition-all">
                        <i data-lucide="brain" class="w-3.5 h-3.5"></i> Quiz Me
                    </button>
                    <button onclick="addKnowledgeCard()" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-100 text-slate-300 text-xs font-semibold border border-white/10 hover:border-accent/30 transition-all">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Card
                    </button>
                    <span id="knowledgeCount" class="text-xs text-slate-500 ml-auto"></span>
                </div>
                <div id="knowledgeList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                    <p class="col-span-2 text-slate-500 text-sm text-center py-4">Loading...</p>
                </div>
                <div id="knowledgeQuizPanel" class="hidden">
                    <div class="glass rounded-2xl overflow-hidden border border-green-500/20">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-white/5 bg-green-500/5">
                            <h2 class="text-base font-bold flex items-center gap-2 text-green-400">
                                <i data-lucide="brain" class="w-4 h-4"></i> Knowledge Quiz
                            </h2>
                            <button onclick="closeKnowledgeQuiz()" class="p-1.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-all">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div id="knowledgeQuizContent" class="p-4 space-y-4"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Resources sub-view ═══ -->
        <div id="study-sub-resources" style="display:none">
            <div class="space-y-4">
                <div id="resourcesList2" class="space-y-4">
                    <p class="text-slate-500 text-sm text-center py-4">Loading resources...</p>
                </div>
                <div class="glass rounded-2xl p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4 text-accent-light"></i>
                        <h3 class="text-sm font-bold text-white">Import from Google Sheets</h3>
                    </div>
                    <p class="text-xs text-slate-400">Paste a Google Sheets URL to import questions and answers into your phrase bank.</p>
                    <div class="flex gap-2">
                        <input id="sheetsUrl2" type="text" placeholder="https://docs.google.com/spreadsheets/d/..."
                            class="flex-1 bg-surface-50 rounded-xl px-3 py-2 text-sm text-white placeholder-slate-500 outline-none border border-white/5 focus:border-accent/40">
                        <button onclick="fetchSheetPreview()" class="px-4 py-2 bg-accent hover:bg-accent-dark rounded-xl text-xs font-bold text-white transition-all">Fetch</button>
                    </div>
                    <div id="sheetsPreview2" class="hidden space-y-3"></div>
                </div>
            </div>
        </div>

        <!-- ═══ Phrases sub-view ═══ -->
        <div id="study-sub-phrases" style="display:none">
            <div class="space-y-3">
                <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
                    <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                    <input id="studyBrowseSearch" type="text" placeholder="Search phrases..." oninput="searchStudyPhrases()"
                        class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
                </div>
                <div id="studyBrowseList" class="space-y-1"></div>
                <div class="text-center"><span id="studyBrowseCount" class="text-xs text-slate-500"></span></div>
            </div>
        </div>

    </div><!-- end view-study -->

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- VIEW: PROGRESS -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div id="view-progress" class="view-section hidden space-y-4">

        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-5 h-5 text-accent-light"></i> Progress
            </h2>
        </div>

        <!-- Sub-nav -->
        <div class="flex items-center gap-1.5">
            <button onclick="showProgressSub('dashboard')" id="progSub-dashboard" class="pill pill-active">Dashboard</button>
            <button onclick="showProgressSub('phrases')" id="progSub-phrases" class="pill pill-inactive">Phrases</button>
        </div>

        <!-- Dashboard -->
        <div id="progress-sub-dashboard" class="space-y-4">
            <div id="progressDashboard">
                <p class="text-slate-500 text-sm text-center py-4">Loading stats...</p>
            </div>
        </div>

        <!-- Phrases browser (inline) -->
        <div id="progress-sub-phrases" style="display:none" class="space-y-3">
            <div class="flex items-center gap-2 bg-surface-50 rounded-xl px-3 py-2 border border-white/5">
                <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
                <input id="progressBrowseSearch" type="text" placeholder="Search phrases..." oninput="searchProgressPhrases()"
                    class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 outline-none">
            </div>
            <div id="progressBrowseList" class="space-y-1"></div>
            <div class="text-center">
                <span id="progressBrowseCount" class="text-xs text-slate-500"></span>
            </div>
        </div>

    </div><!-- end view-progress -->

</div>


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
let strictness     = parseInt(localStorage.getItem('hugStrict')) || 3;
let repeatOnFail   = localStorage.getItem('hugRepeatFail') === '1';

// Question history for prev/next navigation
var questionHistory = [{ q: targetQ, a: targetA, a_hu: targetAH }];
var historyIndex = 0;

// 1=Beginner, 2=Forgiving, 3=Interview standard, 4=Tough interviewer, 5=Exam board
var strictLabels = { 1: 'Beginner', 2: 'Forgiving', 3: 'Interview', 4: 'Tough', 5: 'Exam' };
var strictColors = { 1: 'text-green-400', 2: 'text-blue-400', 3: 'text-accent-light', 4: 'text-amber-400', 5: 'text-red-400' };

function initStrictSlider() {
    // Sync both header and hidden session sliders
    ['strictSlider', 'headerStrictSlider'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.value = strictness;
        el.oninput = function() {
            strictness = parseInt(this.value);
            localStorage.setItem('hugStrict', strictness);
            updateStrictLabel();
        };
    });
    updateStrictLabel();
}

function initSessionToolbar() {
    initStrictSlider();
    // Populate header speed bar
    var bar = document.getElementById('headerSpeedBar');
    if (!bar) return;
    bar.textContent = '';
    [0.5, 0.7, 0.8, 1.0].forEach(function(s) {
        var pill = document.createElement('button');
        pill.className = 'speed-btn px-1 py-0.5 rounded text-[8px] font-bold transition-all ' + (currentSpeed === s ? 'bg-amber-500 text-white' : 'text-slate-500 hover:text-white');
        pill.textContent = s === 1.0 ? '1x' : s.toFixed(1);
        pill.onclick = function() {
            setSpeed(s);
            bar.querySelectorAll('button').forEach(function(p) {
                var ps = parseFloat(p.textContent);
                p.className = 'speed-btn px-1 py-0.5 rounded text-[8px] font-bold transition-all ' + (ps === s ? 'bg-amber-500 text-white' : 'text-slate-500 hover:text-white');
            });
        };
        bar.appendChild(pill);
    });
    // Sync header strict slider
    var hs = document.getElementById('headerStrictSlider');
    if (hs) { hs.value = strictness; hs.oninput = function() { strictness = parseInt(this.value); localStorage.setItem('hugStrict', strictness); updateStrictLabel(); }; }
}
function updateStrictLabel() {
    var labels = {1:'Gentle',2:'Forgiving',3:'Interview',4:'Strict',5:'Harsh'};
    ['headerStrictLabel','strictLabel'].forEach(function(id) { var el = document.getElementById(id); if (el) el.textContent = labels[strictness] || ''; });
}

function toggleRepeatFail() {
    repeatOnFail = !repeatOnFail;
    localStorage.setItem('hugRepeatFail', repeatOnFail ? '1' : '0');
    var btn = document.getElementById('repeatFailBtn');
    if (!btn) return;
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

var indicator = document.getElementById('readyIndicator') || document.createElement('div');
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
    var pf = document.getElementById('progressFill');
    var pl = document.getElementById('progressLabel');
    if (!pf || !pl) return;
    const pct = Math.min(100, (sessionCount / SESSION_SIZE) * 100);
    pf.style.width = pct + '%';
    pl.textContent = sessionCount + ' / ' + SESSION_SIZE;
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
    var sp = document.getElementById('sesPass'); if (sp) sp.textContent = sessionPass;
    var sf = document.getElementById('sesFail'); if (sf) sf.textContent = sessionFail;
    var ss = document.getElementById('sesStreak'); if (ss) ss.textContent = sessionStreak;
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
    var sp2 = document.getElementById('sesPass'); if (sp2) sp2.textContent = '0';
    var sf2 = document.getElementById('sesFail'); if (sf2) sf2.textContent = '0';
    var ss2 = document.getElementById('sesStreak'); if (ss2) ss2.textContent = '0';
    updateProgressBar();
}

function closeSummary(keepGoing) {
    document.getElementById('summaryModal').classList.add('hidden');
    if (keepGoing) nextQuestion();
}

// ── Audio ─────────────────────────────────────────────────────────────
let audioCtx = null, analyser = null, micStream = null, volTimer = null;
let mediaRecorder = null, audioChunks = [], lastRecordingBlob = null;
var volFill = document.getElementById('volFill');

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
            if (!isListening || (Date.now() - listenStartTime) < 200) return;
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
    if (volFill) volFill.style.width = '0%';
}

function cleanupAudio() {
    clearInterval(volTimer);
    if (volFill) volFill.style.width = '0%';
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
// Ensure voices load on first user tap (Safari/Chrome requirement)
document.addEventListener('click', function initVoices() {
    loadVoices();
    // Warm up speechSynthesis with silent utterance
    var warmup = new SpeechSynthesisUtterance('');
    warmup.volume = 0;
    window.speechSynthesis.speak(warmup);
    document.removeEventListener('click', initVoices);
}, { once: true });

function speak(rate, autoRecord) {
    if (autoRecord === undefined) autoRecord = true;
    window.speechSynthesis.cancel();
    isListening = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}
    // Reset result card so re-listen triggers fresh eval
    var rc = document.getElementById('resultCard');
    if (rc) { rc.classList.add('hidden'); rc.classList.remove('result-pass', 'result-fail'); }
    var ms = document.getElementById('matchScore'); if (ms) ms.textContent = '';
    var tr = document.getElementById('transcript'); if (tr) tr.textContent = '';
    var pb = document.getElementById('playbackBtn'); if (pb) pb.classList.add('hidden');
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
    if (currentSpeed === 0.5) {
        setSpeed(1.0);
    } else {
        setSpeed(0.5);
    }
    speak(currentSpeed);
}

function setSpeed(speed) {
    currentSpeed = speed;
    localStorage.setItem('hugSpeed', speed);
    var slowBtn = document.getElementById('slowBtn');
    if (slowBtn) {
        if (speed === 0.5) {
            slowBtn.classList.remove('bg-surface-300', 'text-slate-200');
            slowBtn.classList.add('bg-amber-600', 'text-white');
        } else {
            slowBtn.classList.remove('bg-amber-600', 'text-white');
            slowBtn.classList.add('bg-surface-300', 'text-slate-200');
        }
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

// ── Theme toggle ─────────────────────────────────────────────────────
function toggleTheme() {
    var isLight = document.body.classList.toggle('light');
    localStorage.setItem('hugTheme', isLight ? 'light' : 'dark');
    var icon = document.getElementById('themeIcon');
    if (icon) icon.setAttribute('data-lucide', isLight ? 'moon' : 'sun');
    lucide.createIcons();
}
(function() {
    if (localStorage.getItem('hugTheme') === 'light') {
        document.body.classList.add('light');
        var icon = document.getElementById('themeIcon');
        if (icon) icon.setAttribute('data-lucide', 'moon');
    }
})();

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
    updateBlurButton();
}

function applyListenMode() {
    var q   = document.getElementById('questionText');
    if (!q) return;
    if (listenMode) {
        q.classList.add('listen-blur');
        q.title = 'Click to reveal';
        q.onclick = revealQuestion;
    } else {
        q.classList.remove('listen-blur');
        q.title = '';
        q.onclick = null;
    }
}

function updateBlurButton() {
    var btn = document.getElementById('listenModeBtn');
    if (!btn) return;
    btn.textContent = listenMode ? '👁 Blur ON' : '👁 Blur';
    if (listenMode) {
        btn.classList.add('bg-violet-600', 'text-white');
        btn.classList.remove('bg-surface-300', 'text-slate-200');
    } else {
        btn.classList.remove('bg-violet-600', 'text-white');
        btn.classList.add('bg-surface-300', 'text-slate-200');
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
    var bp = document.getElementById('btnPron'); if (bp) bp.className = 'pill ' + (mode === 'pronunciation' ? 'pill-active' : 'pill-inactive');
    var bi = document.getElementById('btnInterview'); if (bi) bi.className = 'pill ' + (mode === 'interview' ? 'pill-active' : 'pill-inactive');
    var lb = document.getElementById('listenBtnLabel'); if (lb) lb.textContent = mode === 'pronunciation' ? 'Listen & Repeat' : 'Hear Question';
}

// ── Next question ─────────────────────────────────────────────────────
// Safe DOM setter — no-op if element missing
function $set(id, prop, val) { var el = document.getElementById(id); if (el) { if (prop === 'text') el.textContent = val; else if (prop === 'hide') el.classList.add('hidden'); else if (prop === 'show') el.classList.remove('hidden'); else if (prop === 'removeClass') el.classList.remove(val); else if (prop === 'removeAttr') el.removeAttribute(val); } }

function nextQuestion() {
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}

    $set('practiceTranslation', 'hide');
    $set('revealDetails', 'removeAttr', 'open');

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
            questionHistory = questionHistory.slice(0, historyIndex + 1);
            questionHistory.push({ q: data.q, a: data.a, a_hu: data.a_hu || '', category: data.category || '' });
            historyIndex = questionHistory.length - 1;
            $set('questionText', 'text', data.q);
            $set('answerText', 'text', data.a_hu || data.a);
            $set('resultCard', 'hide'); $set('resultCard', 'removeClass', 'result-pass'); $set('resultCard', 'removeClass', 'result-fail');
            $set('matchScore', 'text', ''); $set('transcript', 'text', '');
            $set('playbackBtn', 'hide');
            $set('categoryTag', 'text', data.category || '');
            lastRecordingBlob = null;
            if (listenMode) applyListenMode();
            if (translateOn) fetchTranslation(); else { $set('inlineTranslation', 'hide'); $set('inlineTranslation', 'text', ''); }
            if (phoneticOn) fetchPhonetic(); else { $set('phoneticHint', 'hide'); $set('phoneticHint', 'text', ''); }
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
    $set('questionText', 'text', h.q);
    $set('answerText', 'text', h.a_hu || h.a);
    $set('resultCard', 'hide'); $set('resultCard', 'removeClass', 'result-pass'); $set('resultCard', 'removeClass', 'result-fail');
    $set('matchScore', 'text', ''); $set('transcript', 'text', '');
    $set('playbackBtn', 'hide');
    $set('categoryTag', 'text', h.category || '');
    $set('revealDetails', 'removeAttr', 'open');
    $set('practiceTranslation', 'hide');
    lastRecordingBlob = null;
    if (listenMode) applyListenMode();
    if (translateOn) fetchTranslation(); else { $set('inlineTranslation', 'hide'); $set('inlineTranslation', 'text', ''); }
    if (phoneticOn) fetchPhonetic(); else { $set('phoneticHint', 'hide'); $set('phoneticHint', 'text', ''); }
    speak(currentSpeed);
}

function shuffleQuestion() {
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}

    $set('practiceTranslation', 'hide');
    $set('revealDetails', 'removeAttr', 'open');

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
            $set('questionText', 'text', data.q);
            $set('answerText', 'text', data.a_hu || data.a);
            $set('resultCard', 'hide'); $set('resultCard', 'removeClass', 'result-pass'); $set('resultCard', 'removeClass', 'result-fail');
            $set('matchScore', 'text', ''); $set('transcript', 'text', '');
            $set('playbackBtn', 'hide');
            $set('categoryTag', 'text', data.category || '');
            lastRecordingBlob = null;
            if (listenMode) applyListenMode();
            if (translateOn) fetchTranslation(); else { $set('inlineTranslation', 'hide'); $set('inlineTranslation', 'text', ''); }
            if (phoneticOn) fetchPhonetic(); else { $set('phoneticHint', 'hide'); $set('phoneticHint', 'text', ''); }
            speak(currentSpeed);
        });
}

// ── Speech recognition ────────────────────────────────────────────────
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
if (!SpeechRecognition) {
    var rb = document.getElementById('recordBtn');
    if (rb) { rb.disabled = true; rb.title = 'Speech recognition not supported'; }
    var rl = document.getElementById('recordLabel');
    if (rl) rl.textContent = 'N/A';
}
var recognition = SpeechRecognition ? new SpeechRecognition() : { start:function(){}, stop:function(){}, abort:function(){}, onstart:null, onresult:null, onend:null, onerror:null };
recognition.lang            = 'hu-HU';
recognition.interimResults  = false;
recognition.continuous      = true;
recognition.maxAlternatives = 5;

function setRecordIcon(iconName) {
    var icon = document.getElementById('recordIcon');
    if (!icon) return;
    icon.setAttribute('data-lucide', iconName);
    lucide.createIcons({ nodes: [icon] });
}
recognition.onstart = function() {
    isListening     = true;
    listenStartTime = Date.now();
    indicator.className = 'status-dot dot-live';
    var rb = document.getElementById('recordBtn');
    if (rb) {
        rb.classList.add('mic-active');
        rb.classList.remove('bg-green-600', 'hover:bg-green-500', 'glow-green');
        rb.classList.add('bg-red-600', 'hover:bg-red-500', 'glow-red');
    }
    var rl = document.getElementById('recordLabel');
    if (rl) rl.textContent = 'Recording';

    try { setRecordIcon('headphones'); } catch(e) {}
    startVolume();
    recTimeout = setTimeout(function() {
        if (isListening) recognition.stop();
    }, 8000);
};

recognition.onresult = function(event) {
    if (Date.now() - listenStartTime < 200) return;
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
    if (rbReset) {
        rbReset.classList.remove('mic-active', 'bg-red-600', 'hover:bg-red-500', 'glow-red');
        rbReset.classList.add('bg-green-600', 'hover:bg-green-500', 'glow-green');
    }
    var rl = document.getElementById('recordLabel'); if (rl) rl.textContent = 'Mic';

    if (typeof setRecordIcon === 'function') setRecordIcon('mic');

    if (isPractice) {
        isPractice = false;
        var el = document.getElementById('practiceTranslation');
        if (el) { el.textContent = 'You said: "' + result + '"'; el.classList.remove('hidden'); }
        return;
    }

    var resultCard = document.getElementById('resultCard');
    if (!resultCard) {
        // No static result card — create one dynamically in the session content area
        var sessionContent = document.getElementById('sessionContent') || document.getElementById('scenarioDrillContent');
        if (sessionContent) {
            resultCard = document.createElement('div');
            resultCard.id = 'resultCard';
            resultCard.className = 'glass rounded-2xl p-4 mt-4 border';
            var transcriptEl = document.createElement('p');
            transcriptEl.id = 'transcript';
            transcriptEl.className = 'text-sm text-slate-300 italic mb-2';
            resultCard.appendChild(transcriptEl);
            var scoreEl = document.createElement('div');
            scoreEl.id = 'matchScore';
            scoreEl.className = 'text-center';
            resultCard.appendChild(scoreEl);
            var playBtn = document.createElement('button');
            playBtn.id = 'playbackBtn';
            playBtn.className = 'hidden mt-2 px-3 py-1.5 rounded-lg bg-surface-50 text-[11px] font-semibold text-slate-300 hover:text-white';
            playBtn.textContent = '🔊 Hear myself';
            playBtn.onclick = function() { playMyVoice(); };
            resultCard.appendChild(playBtn);
            sessionContent.appendChild(resultCard);
        } else {
            return; // nowhere to show results
        }
    }
    resultCard.classList.remove('hidden', 'result-pass', 'result-fail');
    var transcriptDisp = document.getElementById('transcript');
    if (transcriptDisp) transcriptDisp.textContent = '"' + result + '"';
    var pbtn = document.getElementById('playbackBtn');
    if (pbtn) pbtn.classList.add('hidden');

    var scoreDisplay = document.getElementById('matchScore');
    if (!scoreDisplay) return;
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
            var isPass = data.pass;
            var correctAnswer = targetAH || data.correct || targetQ;
            var fb = (data.feedback || '').split(/\.\s/)[0];
            if (fb.length > 80) fb = fb.substring(0, 77) + '...';

            // Replace content area with clean result panel at eye level
            var content = document.getElementById('sessionContent');
            var controls = document.getElementById('sessionControls');
            if (content && activeSession) {
                content.textContent = '';
                controls.textContent = '';

                // Pass/Fail badge — big and centered
                var badge = document.createElement('div');
                badge.className = 'text-center mb-3';
                var badgeSpan = document.createElement('span');
                badgeSpan.className = 'inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold uppercase tracking-wider ' +
                    (isPass ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400');
                badgeSpan.textContent = isPass ? '✓ Pass' : '✗ Try Again';
                badge.appendChild(badgeSpan);
                content.appendChild(badge);

                // The phrase
                var phrase = document.createElement('h1');
                phrase.className = 'question-text text-white mb-1 text-center';
                phrase.textContent = targetQ;
                content.appendChild(phrase);

                // Short feedback
                if (fb) {
                    var fbEl = document.createElement('p');
                    fbEl.className = 'text-xs text-center mb-3 ' + (isPass ? 'text-green-400/70' : 'text-red-400/70');
                    fbEl.textContent = fb;
                    content.appendChild(fbEl);
                }

                // What you said (small)
                var said = document.createElement('p');
                said.className = 'text-xs text-slate-500 text-center italic mb-4';
                said.textContent = 'You said: "' + result + '"';
                content.appendChild(said);

                // Action buttons — one row
                var btnRow = document.createElement('div');
                btnRow.className = 'flex items-center justify-center gap-3';

                var listenBtn = document.createElement('button');
                listenBtn.className = 'px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-surface-50 border border-white/10 text-slate-300 hover:text-white hover:border-accent/30';
                listenBtn.textContent = '🔊 Listen Again';
                listenBtn.onclick = function() { speak(currentSpeed, false); };
                btnRow.appendChild(listenBtn);

                if (lastRecordingBlob) {
                    var hearBtn = document.createElement('button');
                    hearBtn.className = 'px-4 py-2.5 rounded-xl text-sm font-bold transition-all bg-surface-50 border border-white/10 text-slate-300 hover:text-white hover:border-accent/30';
                    hearBtn.textContent = '🎤 Hear Myself';
                    hearBtn.onclick = playMyVoice;
                    btnRow.appendChild(hearBtn);
                }

                var nextBtn = document.createElement('button');
                nextBtn.className = 'px-6 py-2.5 rounded-xl text-sm font-bold text-white transition-all ' +
                    (isPass ? 'bg-accent hover:bg-accent-dark' : 'bg-red-500/80 hover:bg-red-500');
                nextBtn.textContent = isPass ? 'Next →' : '🎤 Retry';
                nextBtn.onclick = function() {
                    if (isPass) { sessionIdx++; renderSessionStep(); }
                    else { renderSessionStep(); }
                };
                btnRow.appendChild(nextBtn);
                controls.appendChild(btnRow);
            } else {
                // Non-session fallback — use existing resultCard
                scoreDisplay.textContent = '';
                var topRow = document.createElement('div');
                topRow.className = 'flex items-center gap-2 justify-center flex-wrap';
                var badgeLegacy = document.createElement('span');
                badgeLegacy.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider ' +
                    (isPass ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400');
                badgeLegacy.textContent = isPass ? 'Pass' : 'Retry';
                var hintLegacy = document.createElement('span');
                hintLegacy.className = 'text-xs ' + (isPass ? 'text-green-400/70' : 'text-red-400/70');
                hintLegacy.textContent = fb;
                topRow.appendChild(badgeLegacy);
                topRow.appendChild(hintLegacy);
                scoreDisplay.appendChild(topRow);
                if (correctAnswer) {
                    var correctEl = document.createElement('p');
                    correctEl.className = 'text-base mt-2 font-semibold text-white';
                    correctEl.textContent = correctAnswer;
                    scoreDisplay.appendChild(correctEl);
                }
                resultCard.classList.add(isPass ? 'result-pass' : 'result-fail');
                var playbackEl = document.getElementById('playbackBtn');
                if (lastRecordingBlob && playbackEl) playbackEl.classList.remove('hidden');
                else showPlaybackWhenReady = true;
            }

            // Repeat correct on fail
            if (!isPass && repeatOnFail && correctAnswer) {
                setTimeout(function() {
                    var msg = new SpeechSynthesisUtterance(correctAnswer);
                    msg.lang = 'hu-HU'; msg.rate = 0.8;
                    window.speechSynthesis.speak(msg);
                }, 1500);
            }

            // SRS tracking
            if (!questionAttempted) {
                questionAttempted = true;
                if (activeSession && sessionSteps.length > 0) {
                    sessionTotalCount++;
                    if (data.pass) sessionPassCount++;
                    recordSRSUnified(targetQ, 'phrase', null, data.pass);
                } else {
                    updateSession(data.pass);
                    recordSRS(targetQ, data.pass);
                }
            }
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
    if (rbReset) {
        rbReset.classList.remove('mic-active', 'bg-red-600', 'hover:bg-red-500', 'glow-red');
        rbReset.classList.add('bg-green-600', 'hover:bg-green-500', 'glow-green');
    }
    var rl = document.getElementById('recordLabel');
    if (rl) rl.textContent = 'Mic';
    try { setRecordIcon('mic'); } catch(e) {}
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
var practiceInputEl = document.getElementById('practiceInput');
if (practiceInputEl) {
    practiceInputEl.addEventListener('input', function() {
        clearTimeout(practiceDebounce);
        var text = this.value.trim();
        if (!text) {
            document.getElementById('practiceTranslation').classList.add('hidden');
            return;
        }
        practiceDebounce = setTimeout(function() { translatePractice(); }, 600);
    });
    practiceInputEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.shiftKey)) {
            e.preventDefault();
            speakPractice();
            translatePractice();
        }
    });
}

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

var allPhrasesData = [];
var phrasesShown = 0;
var PHRASES_PAGE = 50;

function renderPhrases(data) {
    allPhrasesData = data;
    phrasesShown = 0;
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
    showMorePhrases();
}

function showMorePhrases() {
    var list = document.getElementById('browseList');
    var oldBtn = document.getElementById('showMoreBtn');
    if (oldBtn) oldBtn.remove();
    var end = Math.min(phrasesShown + PHRASES_PAGE, allPhrasesData.length);
    for (var i = phrasesShown; i < end; i++) {
        list.appendChild(buildPhraseItem(allPhrasesData[i]));
    }
    phrasesShown = end;
    if (phrasesShown < allPhrasesData.length) {
        var btn = document.createElement('button');
        btn.id = 'showMoreBtn';
        btn.className = 'w-full py-3 mt-2 rounded-xl bg-surface-100 border border-white/5 text-xs font-semibold text-slate-400 hover:text-white hover:border-accent/30 transition-all';
        btn.textContent = 'Show more (' + (allPhrasesData.length - phrasesShown) + ' remaining)';
        btn.onclick = showMorePhrases;
        list.appendChild(btn);
    }
}

function buildPhraseItem(p) {
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
    return item;
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
var currentView = 'today';
var views = ['today', 'study', 'progress'];

function showView(view) {
    currentView = view;
    views.forEach(function(v) {
        var el = document.getElementById('view-' + v);
        if (el) el.classList.toggle('active', v === view);
        var nav = document.getElementById('nav-' + v);
        if (nav) {
            if (v === view) { nav.classList.add('text-accent-light'); nav.classList.remove('text-slate-500'); }
            else { nav.classList.remove('text-accent-light'); nav.classList.add('text-slate-500'); }
        }
    });
    if (view === 'today') loadDailyPlan();
    if (view === 'study') { loadScenarios(); loadMustNail(); loadRecommendedGrammar(); }
    if (view === 'progress') { loadProgressDashboard(); loadProgressPhrases(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    lucide.createIcons();
}

function goHome() { showView('today'); }

// ═══ SCENARIO-BASED STUDY ═══
var scenariosLoaded = false;
var mustNailLoaded = false;
var recGrammarLoaded = false;
var allGrammarVisible = false;

function loadScenarios() {
    if (scenariosLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=scenarios')
        .then(function(r) { return r.json(); })
        .then(function(data) { scenariosLoaded = true; renderScenarios(data); });
}

function renderScenarios(scenarios) {
    var grid = document.getElementById('scenarioGrid');
    grid.textContent = '';
    scenarios.forEach(function(s) {
        var tile = document.createElement('button');
        tile.className = 'grammar-card text-left flex flex-col gap-1.5 p-3 active:scale-95 cursor-pointer relative';

        var emoji = document.createElement('span');
        emoji.className = 'text-xl';
        emoji.textContent = s.emoji;
        tile.appendChild(emoji);

        var title = document.createElement('h3');
        title.className = 'text-[12px] font-bold text-white leading-snug';
        title.textContent = s.title;
        tile.appendChild(title);

        var desc = document.createElement('p');
        desc.className = 'text-[10px] text-slate-500 leading-snug line-clamp-2';
        desc.textContent = s.desc;
        tile.appendChild(desc);

        // Progress bar
        var barWrap = document.createElement('div');
        barWrap.className = 'mt-auto pt-2';
        var barTrack = document.createElement('div');
        barTrack.className = 'h-1.5 bg-surface-50 rounded-full overflow-hidden';
        var barFill = document.createElement('div');
        barFill.className = 'h-full rounded-full transition-all ' + (s.pct >= 80 ? 'bg-green-500' : s.pct >= 40 ? 'bg-amber-500' : 'bg-accent');
        barFill.style.width = s.pct + '%';
        barTrack.appendChild(barFill);
        barWrap.appendChild(barTrack);
        var stats = document.createElement('div');
        stats.className = 'flex items-center justify-between mt-1';
        var pctLabel = document.createElement('span');
        pctLabel.className = 'text-[9px] font-bold ' + (s.pct >= 80 ? 'text-green-400' : s.pct >= 40 ? 'text-amber-400' : 'text-accent-light');
        pctLabel.textContent = s.pct + '% mastered';
        var countLabel = document.createElement('span');
        countLabel.className = 'text-[9px] text-slate-600';
        countLabel.textContent = s.total + ' items';
        stats.appendChild(pctLabel);
        stats.appendChild(countLabel);
        barWrap.appendChild(stats);
        tile.appendChild(barWrap);

        // Due badge
        if (s.due > 0) {
            var dueBadge = document.createElement('span');
            dueBadge.className = 'absolute top-2 right-2 text-[9px] font-bold bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded-full';
            dueBadge.textContent = s.due + ' due';
            tile.appendChild(dueBadge);
        }

        (function(scenario) {
            tile.onclick = function() { startScenarioDrill(scenario); };
        })(s);
        grid.appendChild(tile);
    });
    lucide.createIcons();
}

// Must Nail
function loadMustNail() {
    if (mustNailLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=must_nail')
        .then(function(r) { return r.json(); })
        .then(function(data) { mustNailLoaded = true; renderMustNail(data); });
}

function renderMustNail(items) {
    var grid = document.getElementById('mustNailGrid');
    grid.textContent = '';
    if (!items.length) {
        var empty = document.createElement('p');
        empty.className = 'col-span-full text-slate-500 text-sm text-center py-4';
        empty.textContent = 'No essential phrases tagged yet.';
        grid.appendChild(empty);
        return;
    }
    items.forEach(function(p) {
        var mastered = p.pass_count >= 3;
        var failing = p.fail_count > 0 && p.pass_count < 3;
        var tile = document.createElement('button');
        tile.className = 'rounded-xl border p-2.5 text-left transition-all active:scale-95 flex flex-col gap-1 '
            + (mastered ? 'border-green-500/20 bg-green-500/5 opacity-60' : failing ? 'border-red-500/20 bg-red-500/5' : 'border-white/10 bg-surface-100 hover:border-accent/30');

        var q = document.createElement('span');
        q.className = 'text-[11px] font-bold leading-snug ' + (mastered ? 'text-green-400' : 'text-white');
        q.textContent = p.q;
        tile.appendChild(q);

        var a = document.createElement('span');
        a.className = 'text-[9px] text-slate-500 leading-snug line-clamp-1';
        a.textContent = p.a;
        tile.appendChild(a);

        // Status
        var status = document.createElement('span');
        status.className = 'text-[9px] font-bold mt-auto ' + (mastered ? 'text-green-500' : failing ? 'text-red-400' : 'text-slate-600');
        status.textContent = mastered ? '✓ Mastered' : failing ? p.fail_count + ' fails' : 'New';
        tile.appendChild(status);

        (function(phrase) {
            tile.onclick = function() { speakHu(phrase.q); };
        })(p);
        grid.appendChild(tile);
    });
}

function startMustNailQuiz() {
    fetch('?who=' + who + '&ajax=1&action=must_nail')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            // Filter to unmastered, shuffle
            var items = data.filter(function(p) { return p.pass_count < 3; });
            if (!items.length) items = data;
            items.sort(function() { return Math.random() - 0.5; });
            startQuizFirstDrill('★ Must Nail Quiz', items.slice(0, 10));
        });
}

// Recommended Grammar
function loadRecommendedGrammar() {
    if (recGrammarLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=recommendations')
        .then(function(r) { return r.json(); })
        .then(function(data) { recGrammarLoaded = true; renderRecGrammar(data); });
}

function renderRecGrammar(patterns) {
    var grid = document.getElementById('recGrammarGrid');
    grid.textContent = '';
    if (!patterns.length) {
        var empty = document.createElement('p');
        empty.className = 'col-span-full text-slate-500 text-sm text-center py-2';
        empty.textContent = 'All grammar mastered!';
        grid.appendChild(empty);
        return;
    }
    patterns.forEach(function(p) { grid.appendChild(buildPatternCard(p)); });
    lucide.createIcons();
}

function toggleAllGrammar() {
    allGrammarVisible = !allGrammarVisible;
    document.getElementById('allGrammarSection').classList.toggle('hidden', !allGrammarVisible);
    document.getElementById('showAllGrammarBtn').textContent = allGrammarVisible ? 'Hide ▾' : 'See all ▸';
    if (allGrammarVisible) loadGrammarPatterns();
}

function toggleResources() {
    var el = document.getElementById('resourcesCollapsed');
    var toggle = document.getElementById('resToggle');
    var isHidden = el.classList.toggle('hidden');
    toggle.textContent = isHidden ? '▸' : '▾';
    if (!isHidden) loadResources();
}

// Scenario Drill (quiz-first flow)
var scenarioDrillItems = [];
var scenarioDrillIdx = 0;
var scenarioDrillPass = 0;
var scenarioDrillTotal = 0;

function startScenarioDrill(scenario) {
    fetch('?who=' + who + '&ajax=1&action=scenario_phrases&scenario=' + scenario.id)
        .then(function(r) { return r.json(); })
        .then(function(phrases) {
            // Prioritize: unseen/due first, mastered last. Take up to 10.
            var items = phrases.slice(0, 10);
            startQuizFirstDrill(scenario.emoji + ' ' + scenario.title, items);
        });
}

function startQuizFirstDrill(title, items) {
    if (!items.length) { alert('No items for this scenario.'); return; }
    scenarioDrillItems = items;
    scenarioDrillIdx = 0;
    scenarioDrillPass = 0;
    scenarioDrillTotal = items.length;

    document.getElementById('scenarioDrillPanel').classList.remove('hidden');
    document.getElementById('scenarioDrillTitle').querySelector('span').textContent = title;

    // Hide other sections
    document.getElementById('mustNailSection').classList.add('hidden');
    document.getElementById('scenarioGrid').parentElement.classList.add('hidden');

    renderQuizStep();
    lucide.createIcons();
}

function closeScenarioDrill() {
    document.getElementById('scenarioDrillPanel').classList.add('hidden');
    document.getElementById('mustNailSection').classList.remove('hidden');
    document.getElementById('scenarioGrid').parentElement.classList.remove('hidden');
    // Refresh data
    scenariosLoaded = false; mustNailLoaded = false;
    loadScenarios(); loadMustNail();
}

function renderQuizStep() {
    if (scenarioDrillIdx >= scenarioDrillItems.length) {
        renderDrillSummary();
        return;
    }
    var item = scenarioDrillItems[scenarioDrillIdx];
    var content = document.getElementById('scenarioDrillContent');
    var controls = document.getElementById('scenarioDrillControls');
    content.textContent = '';
    controls.textContent = '';

    var pct = Math.round((scenarioDrillIdx / scenarioDrillTotal) * 100);
    document.getElementById('scenarioDrillFill').style.width = pct + '%';
    document.getElementById('scenarioDrillProgress').textContent = (scenarioDrillIdx + 1) + ' / ' + scenarioDrillTotal;

    // Speed selector bar
    var speedBar = document.createElement('div');
    speedBar.className = 'flex items-center gap-1 mb-4 justify-center';
    var speedLabel = document.createElement('span');
    speedLabel.className = 'text-[10px] text-slate-500 mr-1';
    speedLabel.textContent = 'Speed:';
    speedBar.appendChild(speedLabel);
    [0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0].forEach(function(s) {
        var pill = document.createElement('button');
        pill.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition-all ' + (currentSpeed === s ? 'bg-amber-500 text-white' : 'bg-surface-50 text-slate-400 hover:text-white');
        pill.textContent = s === 1.0 ? '1.0' : s.toFixed(1);
        pill.onclick = function(e) { e.stopPropagation(); setSpeed(s); updateSpeedBar(speedBar); };
        speedBar.appendChild(pill);
    });
    content.appendChild(speedBar);

    // Blur toggle
    var toolRow = document.createElement('div');
    toolRow.className = 'flex items-center gap-2 mb-4 justify-center';
    var blurBtn = document.createElement('button');
    blurBtn.className = 'px-3 py-1 rounded-lg text-[10px] font-bold transition-all ' + (listenMode ? 'bg-violet-600 text-white' : 'bg-surface-50 text-slate-400');
    blurBtn.textContent = listenMode ? '👁 Blur ON' : '👁 Blur OFF';
    blurBtn.onclick = function(e) {
        e.stopPropagation();
        listenMode = !listenMode;
        localStorage.setItem('hugListen', listenMode ? '1' : '0');
        renderQuizStep(); // re-render with new blur state
    };
    toolRow.appendChild(blurBtn);
    content.appendChild(toolRow);

    // Mode label — clear what we're asking
    var prompt = document.createElement('p');
    prompt.className = 'text-xs uppercase tracking-wider font-bold mb-3 ' + (item.a_hu ? 'text-pink-400' : 'text-blue-400');
    prompt.textContent = item.a_hu ? '💬 Answer in Hungarian' : '🎤 Say this phrase';
    content.appendChild(prompt);

    // Show English prompt (or Hungarian phrase for pronunciation)
    var mainText = document.createElement('h2');
    mainText.className = 'text-lg font-bold text-white mb-2';
    if (item.a_hu) {
        // Interview mode: show English, blur Hungarian answer
        mainText.textContent = item.a || '(translate)';
    } else {
        // Pronunciation mode: show Hungarian (blurred if listen mode)
        mainText.textContent = item.q;
        if (listenMode) mainText.classList.add('listen-blur');
        mainText.onclick = function() { mainText.classList.remove('listen-blur'); };
    }
    content.appendChild(mainText);

    // Listen button
    var listenBtn = document.createElement('button');
    listenBtn.className = 'mb-4 px-4 py-2 rounded-xl bg-surface-50 border border-white/10 text-sm text-slate-300 hover:text-white hover:bg-surface-200 transition-all';
    listenBtn.textContent = '🔊 Listen';
    listenBtn.onclick = function(e) { e.stopPropagation(); speakHu(item.q); };
    content.appendChild(listenBtn);

    // Hidden answer + breakdown
    var answerWrap = document.createElement('div');
    answerWrap.id = 'quizAnswer';
    answerWrap.className = 'hidden mt-4 space-y-3 w-full max-w-lg';

    var hunText = document.createElement('div');
    hunText.className = 'bg-accent/10 rounded-xl p-4 border border-accent/20 text-center';
    var hunLabel = document.createElement('p');
    hunLabel.className = 'text-xs text-accent-light/60 uppercase tracking-wider font-bold mb-1';
    hunLabel.textContent = item.a_hu ? 'Expected answer' : 'Correct pronunciation';
    hunText.appendChild(hunLabel);
    var hunPhrase = document.createElement('p');
    hunPhrase.className = 'text-base font-bold text-accent-light';
    hunPhrase.textContent = item.a_hu || item.q;
    hunText.appendChild(hunPhrase);
    if (item.a_hu && item.a_hu !== item.q) {
        var altLine = document.createElement('p');
        altLine.className = 'text-xs text-slate-400 mt-1';
        altLine.textContent = 'Question: ' + item.q;
        hunText.appendChild(altLine);
    }
    answerWrap.appendChild(hunText);

    // Breakdown button
    var breakdownBtn = document.createElement('button');
    breakdownBtn.className = 'w-full py-2 rounded-xl bg-yellow-400/10 border border-yellow-400/15 text-xs font-bold text-yellow-300 hover:bg-yellow-400/20 transition-all';
    breakdownBtn.textContent = '📖 Break it down — explain grammar & suffixes';
    breakdownBtn.onclick = function(e) {
        e.stopPropagation();
        breakdownBtn.textContent = '📖 Loading...';
        breakdownBtn.disabled = true;
        var fd = new FormData();
        fd.append('sentence', item.a_hu || item.q);
        fd.append('english', item.a || '');
        fetch('?ajax=1&action=breakdown', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                breakdownBtn.classList.add('hidden');
                renderBreakdown(data, answerWrap);
            })
            .catch(function() { breakdownBtn.textContent = '📖 Error — try again'; breakdownBtn.disabled = false; });
    };
    answerWrap.appendChild(breakdownBtn);

    content.appendChild(answerWrap);

    // Controls: Reveal button
    var revealBtn = document.createElement('button');
    revealBtn.className = 'w-full py-3 bg-accent hover:bg-accent-dark rounded-xl text-sm font-bold text-white transition-all';
    revealBtn.textContent = 'Show Answer';
    revealBtn.onclick = function() {
        document.getElementById('quizAnswer').classList.remove('hidden');
        revealBtn.classList.add('hidden');
        speakHu(item.a_hu || item.q);
        gradeRow.classList.remove('hidden');
    };
    controls.appendChild(revealBtn);

    // Grade buttons (hidden until reveal)
    var gradeRow = document.createElement('div');
    gradeRow.className = 'hidden flex gap-2 mt-2';

    var failBtn = document.createElement('button');
    failBtn.className = 'flex-1 py-3 bg-red-500/15 hover:bg-red-500/25 border border-red-500/20 rounded-xl text-sm font-bold text-red-400 transition-all';
    failBtn.textContent = 'Didn\'t Know';
    failBtn.onclick = function() { scoreQuizItem(item, false); };
    gradeRow.appendChild(failBtn);

    var passBtn = document.createElement('button');
    passBtn.className = 'flex-1 py-3 bg-green-500/15 hover:bg-green-500/25 border border-green-500/20 rounded-xl text-sm font-bold text-green-400 transition-all';
    passBtn.textContent = 'Got It!';
    passBtn.onclick = function() { scoreQuizItem(item, true); };
    gradeRow.appendChild(passBtn);

    controls.appendChild(gradeRow);
}

function updateSpeedBar(bar) {
    if (!bar) return;
    var pills = bar.querySelectorAll('button');
    pills.forEach(function(p) {
        var s = parseFloat(p.textContent);
        if (s === currentSpeed) { p.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition-all bg-amber-500 text-white'; }
        else { p.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition-all bg-surface-50 text-slate-400 hover:text-white'; }
    });
}

function renderBreakdown(data, container) {
    // Slide-out panel from the right
    var overlay = document.getElementById('breakdownOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'breakdownOverlay';
        overlay.className = 'fixed inset-0 z-50 flex justify-end';
        overlay.style.background = 'rgba(0,0,0,0.3)';
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.2s';
        overlay.style.pointerEvents = 'none';
        document.body.appendChild(overlay);

        var drawer = document.createElement('div');
        drawer.id = 'breakdownDrawer';
        drawer.className = 'w-80 max-w-[85vw] h-full overflow-y-auto p-5 space-y-3';
        drawer.style.background = document.body.classList.contains('light') ? '#fff' : '#0f172a';
        drawer.style.borderLeft = '1px solid rgba(99,102,241,0.15)';
        drawer.style.transform = 'translateX(100%)';
        drawer.style.transition = 'transform 0.25s ease-out';
        overlay.appendChild(drawer);

        // Tap backdrop to close
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closeBreakdownDrawer(); });
    }

    var drawer = document.getElementById('breakdownDrawer');
    drawer.style.background = document.body.classList.contains('light') ? '#fff' : '#0f172a';
    drawer.textContent = '';

    // Close button
    var closeBtn = document.createElement('button');
    closeBtn.className = 'mb-2 text-slate-400 hover:text-white text-xs font-bold';
    closeBtn.textContent = '✕ Close';
    closeBtn.onclick = closeBreakdownDrawer;
    drawer.appendChild(closeBtn);

    // Title
    var title = document.createElement('h3');
    title.className = 'text-sm font-bold text-yellow-400 mb-3';
    title.textContent = '📖 Grammar Breakdown';
    drawer.appendChild(title);

    if (data.words && data.words.length) {
        data.words.forEach(function(w) {
            var wordDiv = document.createElement('div');
            wordDiv.className = 'border-b border-white/5 pb-2.5 mb-2.5';
            var wordTitle = document.createElement('div');
            wordTitle.className = 'flex items-baseline gap-2 flex-wrap';
            var hu = document.createElement('span');
            hu.className = 'text-sm font-bold text-yellow-300';
            hu.textContent = w.word;
            wordTitle.appendChild(hu);
            if (w.base && w.base !== w.word) {
                var base = document.createElement('span');
                base.className = 'text-xs text-slate-400';
                base.textContent = '← ' + w.base;
                wordTitle.appendChild(base);
            }
            var meaning = document.createElement('span');
            meaning.className = 'text-xs text-slate-300';
            meaning.textContent = '"' + w.meaning + '"';
            wordTitle.appendChild(meaning);
            wordDiv.appendChild(wordTitle);
            if (w.suffixes && w.suffixes !== 'none') {
                var suf = document.createElement('p');
                suf.className = 'text-[11px] text-amber-200/80 mt-1';
                suf.textContent = w.suffixes;
                wordDiv.appendChild(suf);
            }
            if (w.role) {
                var role = document.createElement('span');
                role.className = 'inline-block mt-1 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/5 text-slate-500';
                role.textContent = w.role;
                wordDiv.appendChild(role);
            }
            drawer.appendChild(wordDiv);
        });
    }
    if (data.structure) {
        var struct = document.createElement('p');
        struct.className = 'text-xs text-slate-300 italic';
        struct.textContent = data.structure;
        drawer.appendChild(struct);
    }
    if (data.tip) {
        var tip = document.createElement('p');
        tip.className = 'text-xs text-yellow-200/70 mt-2';
        tip.textContent = '💡 ' + data.tip;
        drawer.appendChild(tip);
    }

    // Open the drawer
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'auto';
    requestAnimationFrame(function() { drawer.style.transform = 'translateX(0)'; });
}

function closeBreakdownDrawer() {
    var overlay = document.getElementById('breakdownOverlay');
    var drawer = document.getElementById('breakdownDrawer');
    if (!overlay) return;
    drawer.style.transform = 'translateX(100%)';
    overlay.style.opacity = '0';
    setTimeout(function() { overlay.style.pointerEvents = 'none'; }, 250);
}

function scoreQuizItem(item, passed) {
    if (passed) scenarioDrillPass++;
    // Record to SRS
    var fd = new FormData();
    fd.append('phrase', item.q);
    fd.append('pass', passed ? '1' : '0');
    fd.append('who', who);
    fetch('record.php', { method: 'POST', body: fd });

    scenarioDrillIdx++;
    renderQuizStep();
}

function renderDrillSummary() {
    var content = document.getElementById('scenarioDrillContent');
    var controls = document.getElementById('scenarioDrillControls');
    content.textContent = '';
    controls.textContent = '';
    document.getElementById('scenarioDrillFill').style.width = '100%';
    document.getElementById('scenarioDrillProgress').textContent = '';

    var pct = scenarioDrillTotal > 0 ? Math.round((scenarioDrillPass / scenarioDrillTotal) * 100) : 0;

    var icon = document.createElement('div');
    icon.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3 ' + (pct >= 70 ? 'bg-green-500/20' : 'bg-amber-500/20');
    icon.textContent = pct >= 70 ? '🎉' : '💪';
    icon.style.fontSize = '24px';
    content.appendChild(icon);

    var h = document.createElement('h3');
    h.className = 'text-lg font-bold text-white mb-1';
    h.textContent = pct >= 70 ? 'Great job!' : 'Keep practicing!';
    content.appendChild(h);

    var score = document.createElement('p');
    score.className = 'text-sm text-slate-400 mb-4';
    score.textContent = scenarioDrillPass + ' / ' + scenarioDrillTotal + ' correct (' + pct + '%)';
    content.appendChild(score);

    var closeBtn = document.createElement('button');
    closeBtn.className = 'w-full py-3 bg-accent hover:bg-accent-dark rounded-xl text-sm font-bold text-white transition-all';
    closeBtn.textContent = 'Back to Study';
    closeBtn.onclick = closeScenarioDrill;
    controls.appendChild(closeBtn);
}

// Study tab sub-nav
var studySub = 'scenarios';
function showStudySub(sub) {
    studySub = sub;
    ['scenarios', 'grammar', 'knowledge', 'resources', 'phrases'].forEach(function(s) {
        var el = document.getElementById('study-sub-' + s);
        if (el) el.style.display = s === sub ? 'block' : 'none';
        var btn = document.getElementById('studySub-' + s);
        if (btn) btn.className = 'pill ' + (s === sub ? 'pill-active' : 'pill-inactive');
    });
    if (sub === 'scenarios') { loadScenarios(); loadMustNail(); loadRecommendedGrammar(); }
    if (sub === 'grammar') loadGrammarPatterns();
    if (sub === 'knowledge') loadKnowledgeCards();
    if (sub === 'resources') loadResources();
    if (sub === 'phrases') loadStudyPhrases();
    lucide.createIcons();
}

// Progress sub-nav
var progressSub = 'dashboard';
function showProgressSub(sub) {
    progressSub = sub;
    document.getElementById('progress-sub-dashboard').style.display = sub === 'dashboard' ? 'block' : 'none';
    document.getElementById('progress-sub-phrases').style.display = sub === 'phrases' ? 'block' : 'none';
    document.getElementById('progSub-dashboard').className = 'pill ' + (sub === 'dashboard' ? 'pill-active' : 'pill-inactive');
    document.getElementById('progSub-phrases').className = 'pill ' + (sub === 'phrases' ? 'pill-active' : 'pill-inactive');
    if (sub === 'phrases') loadProgressPhrases();
}

// Study phrases sub-view
var studyPhrasesLoaded = false;
function loadStudyPhrases() {
    if (studyPhrasesLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=phrases')
        .then(function(r) { return r.json(); })
        .then(function(data) { studyPhrasesLoaded = true; renderStudyPhrases(data); });
}
function searchStudyPhrases() {
    var q = document.getElementById('studyBrowseSearch').value.trim();
    fetch('?who=' + who + '&ajax=1&action=phrases' + (q ? '&search=' + encodeURIComponent(q) : ''))
        .then(function(r) { return r.json(); })
        .then(function(data) { renderStudyPhrases(data); });
}
var studyPhrasesData = [];
var studyPhrasesShown = 0;

function renderStudyPhrases(data) {
    studyPhrasesData = data;
    studyPhrasesShown = 0;
    var list = document.getElementById('studyBrowseList');
    document.getElementById('studyBrowseCount').textContent = data.length + ' phrases';
    list.textContent = '';
    if (!data.length) {
        var empty = document.createElement('p');
        empty.className = 'text-slate-500 text-sm text-center py-8';
        empty.textContent = 'No phrases found.';
        list.appendChild(empty);
        return;
    }
    showMoreStudyPhrases();
}

function showMoreStudyPhrases() {
    var list = document.getElementById('studyBrowseList');
    var oldBtn = document.getElementById('showMoreStudyBtn');
    if (oldBtn) oldBtn.remove();
    var end = Math.min(studyPhrasesShown + PHRASES_PAGE, studyPhrasesData.length);
    for (var i = studyPhrasesShown; i < end; i++) {
        var p = studyPhrasesData[i];
        var mastery = p.pass_count >= 3 ? 'mastered' : p.pass_count >= 1 ? 'known' : p.fail_count > 0 ? 'learning' : 'new';
        var item = document.createElement('div');
        item.className = 'phrase-item';
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
        var dot = document.createElement('div');
        dot.className = 'w-2 h-2 rounded-full mastery-' + mastery + ' ml-3';
        item.appendChild(textDiv);
        item.appendChild(dot);
        list.appendChild(item);
    }
    studyPhrasesShown = end;
    if (studyPhrasesShown < studyPhrasesData.length) {
        var btn = document.createElement('button');
        btn.id = 'showMoreStudyBtn';
        btn.className = 'w-full py-3 mt-2 rounded-xl bg-surface-100 border border-white/5 text-xs font-semibold text-slate-400 hover:text-white hover:border-accent/30 transition-all';
        btn.textContent = 'Show more (' + (studyPhrasesData.length - studyPhrasesShown) + ' remaining)';
        btn.onclick = showMoreStudyPhrases;
        list.appendChild(btn);
    }
}

// ── Daily Plan Engine ────────────────────────────────────────────────
var dailyPlan = null;
var dailyPlanLoaded = false;
var activeSession = null;

function loadDailyPlan() {
    if (dailyPlanLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=daily_plan')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            dailyPlanLoaded = true;
            dailyPlan = data;
            renderDailyPlan(data);
            lucide.createIcons();
        })
        .catch(function() {
            document.getElementById('planBlockList').textContent = '';
            var p = document.createElement('p');
            p.className = 'text-slate-500 text-sm text-center py-4';
            p.textContent = 'Could not load plan. Run migrate_v8.php first.';
            document.getElementById('planBlockList').appendChild(p);
        });
}

function renderDailyPlan(data) {
    document.getElementById('planStreak').textContent = data.streak || 0;
    document.getElementById('planTodayMin').textContent = (data.today_min || 0) + 'm';
    var totalHrs = Math.round((data.total_plan_min || 0) / 60 * 10) / 10;
    document.getElementById('planTotalTime').textContent = totalHrs + ' hours';

    var completedTypes = data.completed_blocks || {};
    var completedCount = Object.keys(completedTypes).length;
    var totalBlocks = (data.blocks || []).length;
    var pct = totalBlocks > 0 ? Math.round((completedCount / totalBlocks) * 100) : 0;
    document.getElementById('dayProgressFill').style.width = pct + '%';
    document.getElementById('dayProgressLabel').textContent = completedCount + ' of ' + totalBlocks + ' completed';

    var list = document.getElementById('planBlockList');
    list.textContent = '';
    if (!data.blocks || !data.blocks.length) {
        var empty = document.createElement('p');
        empty.className = 'col-span-3 text-green-400 text-sm text-center py-8 font-semibold';
        empty.textContent = 'All done for today! Great work.';
        list.appendChild(empty);
        return;
    }

    var blockColors = {
        'phrase_review': 'border-blue-500/20 bg-blue-500/5',
        'grammar_lesson': 'border-purple-500/20 bg-purple-500/5',
        'interview_sim': 'border-pink-500/20 bg-pink-500/5',
        'knowledge_review': 'border-amber-500/20 bg-amber-500/5',
        'phrase_practice': 'border-green-500/20 bg-green-500/5',
        'free_practice': 'border-accent/20 bg-accent/5',
        'break': 'border-slate-500/20 bg-slate-500/5'
    };
    var blockBadgeColors = {
        'phrase_review': 'bg-blue-500/20 text-blue-400',
        'grammar_lesson': 'bg-purple-500/20 text-purple-400',
        'interview_sim': 'bg-pink-500/20 text-pink-400',
        'knowledge_review': 'bg-amber-500/20 text-amber-400',
        'phrase_practice': 'bg-green-500/20 text-green-400',
        'free_practice': 'bg-accent/20 text-accent-light',
        'break': 'bg-slate-500/20 text-slate-400'
    };

    data.blocks.forEach(function(block, idx) {
        var isDone = completedTypes[block.block_type];
        var tile = document.createElement('button');
        tile.className = 'rounded-xl border p-3 transition-all flex flex-col items-center gap-1.5 text-center min-h-[80px] justify-center active:scale-95 '
            + (isDone ? 'opacity-40 border-white/5 bg-surface-50' : (blockColors[block.block_type] || 'border-white/5 bg-surface-100 hover:border-accent/30'));

        // Icon / emoji
        var iconWrap = document.createElement('div');
        iconWrap.className = 'w-9 h-9 rounded-lg flex items-center justify-center ' + (blockBadgeColors[block.block_type] || 'bg-white/5 text-slate-400');
        if (block.emoji) {
            iconWrap.textContent = block.emoji;
            iconWrap.className = 'w-9 h-9 rounded-lg flex items-center justify-center text-lg ' + (blockBadgeColors[block.block_type] || 'bg-white/5');
        } else {
            var lucideIcon = document.createElement('i');
            lucideIcon.setAttribute('data-lucide', block.icon || 'circle');
            lucideIcon.className = 'w-4 h-4';
            iconWrap.appendChild(lucideIcon);
        }
        tile.appendChild(iconWrap);

        // Title
        var title = document.createElement('span');
        title.className = 'text-[11px] font-bold leading-tight ' + (isDone ? 'text-slate-500 line-through' : 'text-white');
        title.textContent = block.title;
        tile.appendChild(title);

        // Duration
        var dur = document.createElement('span');
        dur.className = 'text-[10px] text-slate-500';
        dur.textContent = isDone ? '✓' : block.duration + 'm';
        tile.appendChild(dur);

        // Click handler
        if (!isDone) {
            if (block.type === 'external') {
                (function(b) { tile.onclick = function() { openExternalBlock(b); }; })(block);
            } else if (block.type === 'break') {
                (function(b) { tile.onclick = function() { logBlock(b.block_type, b.title, b.duration, 0, 0); }; })(block);
            } else {
                (function(b, i) { tile.onclick = function() { startSessionBlock(b, i); }; })(block, idx);
            }
        }

        list.appendChild(tile);
    });
    lucide.createIcons();
}

function openExternalBlock(block) {
    window.open(block.url, '_blank');
    // Show a log confirmation after a delay
    setTimeout(function() {
        var min = prompt('How many minutes did you spend on ' + block.title + '?', block.duration);
        if (min !== null) {
            logBlock(block.block_type, block.title, parseInt(min) || block.duration, 0, 0);
        }
    }, 2000);
}

function logBlock(blockType, title, duration, completed, passed) {
    var fd = new FormData();
    fd.append('block_type', blockType);
    fd.append('block_title', title);
    fd.append('duration_min', duration);
    fd.append('items_completed', completed);
    fd.append('items_passed', passed);
    fd.append('started_at', new Date().toISOString().slice(0, 19).replace('T', ' '));
    fetch('?who=' + who + '&ajax=1&action=log_block', { method: 'POST', body: fd })
        .then(function() {
            dailyPlanLoaded = false;
            loadDailyPlan();
        });
}

// ── Session Engine ───────────────────────────────────────────────────
var sessionSteps = [];
var sessionIdx = 0;
var sessionStartTime = null;
var sessionPassCount = 0;
var sessionTotalCount = 0;
var sessionBlockInfo = null;

function startSessionBlock(block, blockIdx) {
    activeSession = true;
    sessionBlockInfo = block;
    sessionStartTime = new Date();
    sessionPassCount = 0;
    sessionTotalCount = 0;
    sessionIdx = 0;

    document.getElementById('planBlockList').classList.add('hidden');
    document.getElementById('sessionCard').classList.remove('hidden');
    document.getElementById('sessionSummary').classList.add('hidden');
    initSessionToolbar();
    updateBlurButton();

    var badge = document.getElementById('sessionBadge');
    badge.textContent = block.title;
    badge.className = 'text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ' +
        (block.block_type.indexOf('grammar') !== -1 ? 'bg-purple-500/20 text-purple-400' :
         block.block_type.indexOf('knowledge') !== -1 ? 'bg-amber-500/20 text-amber-400' :
         block.block_type.indexOf('interview') !== -1 ? 'bg-pink-500/20 text-pink-400' :
         'bg-accent/20 text-accent-light');

    // Show intro card
    var mode = block.session.mode;
    var introMessages = {
        'review': { emoji: '🎤', title: 'Pronunciation Practice', desc: 'Listen to each phrase, then repeat it aloud.' },
        'practice': { emoji: '🎤', title: 'Phrase Practice', desc: 'Listen and repeat. Focus on clear pronunciation.' },
        'interview': { emoji: '💬', title: 'Interview Practice', desc: 'Answer each question in Hungarian.' },
        'grammar': { emoji: '📖', title: 'Grammar Lesson', desc: 'Review this grammar pattern and examples.' },
        'knowledge': { emoji: '🧠', title: 'Knowledge Quiz', desc: 'Test your knowledge of Hungarian facts and culture.' }
    };
    var intro = introMessages[mode] || { emoji: '📚', title: block.title, desc: 'Get ready!' };
    var content = document.getElementById('sessionContent');
    var controls = document.getElementById('sessionControls');
    content.textContent = '';
    controls.textContent = '';
    var introEl = document.createElement('div');
    introEl.className = 'text-center';
    introEl.style.animation = 'fadeIn 0.3s ease-out';
    var emojiEl = document.createElement('div');
    emojiEl.className = 'text-4xl mb-3';
    emojiEl.textContent = intro.emoji;
    var titleEl = document.createElement('h2');
    titleEl.className = 'text-xl font-bold text-white mb-1';
    titleEl.textContent = intro.title;
    var descEl = document.createElement('p');
    descEl.className = 'text-sm text-slate-400';
    descEl.textContent = intro.desc;
    introEl.appendChild(emojiEl);
    introEl.appendChild(titleEl);
    introEl.appendChild(descEl);
    content.appendChild(introEl);

    // Fetch session content based on block mode
    var limit = block.session.limit || 10;

    if (mode === 'review' || mode === 'practice' || mode === 'interview') {
        var catParam = block.session.cat || 'all';
        var modeParam = mode === 'interview' ? 'interview' : 'pronunciation';
        var tagParam = block.session.tag || '';
        // Fetch multiple phrases
        var url = '?who=' + who + '&cat=' + catParam + '&ajax=1&action=phrases&limit=' + limit;
        if (tagParam) url += '&tag=' + encodeURIComponent(tagParam);
        fetch(url).then(function(r) { return r.json(); }).then(function(phrases) {
            sessionSteps = phrases.slice(0, limit).map(function(p) {
                return { type: 'audio', q: p.q, a: p.a, a_hu: p.a_hu || '', category: p.category, mode: modeParam };
            });
            if (!sessionSteps.length) {
                // Fallback to random
                sessionSteps = [{ type: 'audio', q: targetQ, a: targetA, a_hu: targetAH, category: 'General', mode: modeParam }];
            }
            setTimeout(renderSessionStep, 1800);
        });
    } else if (mode === 'grammar') {
        // Load grammar pattern + generate quiz
        var patternId = block.session.pattern_id;
        sessionSteps = [{ type: 'grammar_teach', pattern_id: patternId }];
        setTimeout(renderSessionStep, 1800);
    } else if (mode === 'knowledge') {
        var kcCategory = block.session.category || '';
        fetch('?who=' + who + '&ajax=1&action=knowledge_cards&kccat=' + kcCategory)
            .then(function(r) { return r.json(); })
            .then(function(cards) {
                var shuffled = cards.sort(function() { return Math.random() - 0.5; }).slice(0, limit);
                sessionSteps = shuffled.map(function(c) {
                    return { type: 'knowledge', title_hu: c.title_hu, title_en: c.title_en, content_hu: c.content_hu, content_en: c.content_en, key_fact: c.key_fact, category: c.category, id: c.id };
                });
                setTimeout(renderSessionStep, 1800);
            });
    }
}

function renderSessionStep() {
    if (sessionIdx >= sessionSteps.length) {
        showBlockSummary();
        return;
    }
    var step = sessionSteps[sessionIdx];
    var content = document.getElementById('sessionContent');
    var controls = document.getElementById('sessionControls');
    content.textContent = '';
    controls.textContent = '';

    var pct = sessionSteps.length > 0 ? Math.round((sessionIdx / sessionSteps.length) * 100) : 0;
    document.getElementById('sessionProgressFill').style.width = pct + '%';
    document.getElementById('sessionProgress').textContent = (sessionIdx + 1) + ' / ' + sessionSteps.length;

    if (step.type === 'audio') {
        renderAudioStep(step, content, controls);
    } else if (step.type === 'knowledge') {
        renderKnowledgeStep(step, content, controls);
    } else if (step.type === 'grammar_teach') {
        renderGrammarTeachStep(step, content, controls);
    }
    lucide.createIcons();
}

function renderAudioStep(step, content, controls) {
    // Mode label — make clear what's expected
    var modeLabel = document.createElement('div');
    var isPron = (step.mode || 'pronunciation') === 'pronunciation';
    modeLabel.className = 'mb-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold ' +
        (isPron ? 'bg-blue-500/15 text-blue-400' : 'bg-pink-500/15 text-pink-400');
    modeLabel.textContent = isPron ? '🎤 Repeat this phrase aloud' : '💬 Answer this question in Hungarian';
    content.appendChild(modeLabel);

    // Question text
    var q = document.createElement('h1');
    q.id = 'questionText';
    q.className = 'question-text text-white mb-2';
    q.textContent = step.q;
    if (listenMode) { q.classList.add('listen-blur'); q.onclick = function() { q.classList.remove('listen-blur'); }; }
    content.appendChild(q);

    // Translation (small)
    var trans = document.createElement('p');
    trans.className = 'text-blue-300/70 text-sm italic mb-4';
    trans.textContent = step.a;
    content.appendChild(trans);

    // Reveal answer (for interview mode)
    if (!isPron && step.a_hu) {
        var revealWrap = document.createElement('details');
        revealWrap.id = 'revealDetails';
        revealWrap.className = 'mb-4';
        var revealSummary = document.createElement('summary');
        revealSummary.className = 'text-xs text-slate-500 cursor-pointer hover:text-white transition-colors';
        revealSummary.textContent = 'Show expected answer';
        revealWrap.appendChild(revealSummary);
        var revealText = document.createElement('p');
        revealText.id = 'answerText';
        revealText.className = 'text-sm text-accent-light font-semibold mt-1';
        revealText.textContent = step.a_hu;
        revealWrap.appendChild(revealText);
        content.appendChild(revealWrap);
    }

    // Status indicators
    var statusRow = document.createElement('div');
    statusRow.className = 'flex items-center justify-center gap-2 mb-4';
    var readyDot = document.createElement('div');
    readyDot.id = 'readyIndicator';
    readyDot.className = 'status-dot dot-off';
    indicator = readyDot;
    var volTrack = document.createElement('div');
    volTrack.className = 'vol-track';
    var volFillEl = document.createElement('div');
    volFillEl.id = 'volFill';
    volFillEl.className = 'vol-fill';
    volFill = volFillEl;
    volTrack.appendChild(volFillEl);
    statusRow.appendChild(readyDot);
    statusRow.appendChild(volTrack);
    content.appendChild(statusRow);

    // Listen & Speak button (above result card so it's always visible)
    var listenBtn = document.createElement('button');
    // Compact button row: Listen & Repeat | Repeat | Next | Breakdown
    var btnRow = document.createElement('div');
    btnRow.className = 'flex items-center justify-center gap-2';

    listenBtn.className = 'flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold bg-accent text-white hover:bg-accent-dark transition-all active:scale-95';
    var speakerIcon = document.createElement('i');
    speakerIcon.setAttribute('data-lucide', 'volume-2');
    speakerIcon.className = 'w-3.5 h-3.5';
    listenBtn.appendChild(speakerIcon);
    var btnLabel = document.createElement('span');
    btnLabel.textContent = isPron ? 'Listen & Repeat' : 'Listen & Answer';
    listenBtn.appendChild(btnLabel);
    listenBtn.onclick = function() {
        targetQ = step.q;
        targetA = step.a;
        targetAH = step.a_hu || '';
        currentMode = step.mode || 'pronunciation';
        speak(currentSpeed);
    };
    btnRow.appendChild(listenBtn);

    var repeatBtn = document.createElement('button');
    repeatBtn.className = 'flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-bold bg-surface-50 border border-white/10 text-slate-300 hover:text-white transition-all active:scale-95';
    repeatBtn.textContent = '🔁';
    repeatBtn.title = 'Repeat (no recording)';
    repeatBtn.onclick = function() { speak(currentSpeed, false); };
    btnRow.appendChild(repeatBtn);

    var nextNavBtn = document.createElement('button');
    nextNavBtn.className = 'flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-bold bg-surface-50 border border-white/10 text-slate-300 hover:text-white transition-all active:scale-95';
    nextNavBtn.textContent = '→';
    nextNavBtn.title = 'Next phrase';
    nextNavBtn.onclick = function() {
        if (activeSession && sessionSteps.length > 0) {
            sessionIdx++;
            sessionTotalCount++;
            renderSessionStep();
        } else {
            nextQuestion();
        }
    };
    btnRow.appendChild(nextNavBtn);

    controls.appendChild(btnRow);

    var skipRow = document.createElement('div');
    skipRow.className = 'flex items-center justify-center mt-2';

    // Grammar breakdown toggle button
    var breakdownBtn = document.createElement('button');
    breakdownBtn.className = 'text-[11px] text-yellow-400/70 hover:text-yellow-300 transition-colors underline decoration-dotted underline-offset-2 ml-4';
    breakdownBtn.textContent = '📖 Break it down';
    var breakdownLoaded = false;
    breakdownBtn.onclick = function(e) {
        e.stopPropagation();
        var overlay = document.getElementById('breakdownOverlay');
        // If already loaded, just toggle
        if (breakdownLoaded) {
            if (overlay && overlay.style.pointerEvents === 'auto') { closeBreakdownDrawer(); }
            else { overlay.style.opacity = '1'; overlay.style.pointerEvents = 'auto'; document.getElementById('breakdownDrawer').style.transform = 'translateX(0)'; }
            return;
        }
        breakdownBtn.textContent = '📖 Loading...';
        var fd = new FormData();
        fd.append('sentence', step.a_hu || step.q);
        fd.append('english', step.a || '');
        fetch('?ajax=1&action=breakdown', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                breakdownBtn.textContent = '📖 Break it down';
                if (data.error) { breakdownBtn.textContent = '📖 Error — try again'; return; }
                breakdownLoaded = true;
                renderBreakdown(data, null);
            })
            .catch(function() { breakdownBtn.textContent = '📖 Error — try again'; });
    };
    skipRow.appendChild(breakdownBtn);

    controls.appendChild(skipRow);

    // Result card (hidden until eval completes — below controls so Listen & Repeat stays visible)
    var resultCard = document.createElement('div');
    resultCard.id = 'resultCard';
    resultCard.className = 'hidden glass rounded-2xl p-3 border mt-3';
    var transcriptEl = document.createElement('p');
    transcriptEl.id = 'transcript';
    transcriptEl.className = 'text-xs text-slate-400 italic mb-1 truncate';
    resultCard.appendChild(transcriptEl);
    var scoreEl = document.createElement('div');
    scoreEl.id = 'matchScore';
    scoreEl.className = 'text-center';
    resultCard.appendChild(scoreEl);
    var playBtn = document.createElement('button');
    playBtn.id = 'playbackBtn';
    playBtn.className = 'hidden mt-1 px-3 py-1 rounded-lg bg-surface-50 text-[11px] font-semibold text-slate-300 hover:text-white';
    playBtn.textContent = '🔊 Hear myself';
    playBtn.onclick = function() { playMyVoice(); };
    resultCard.appendChild(playBtn);
    controls.appendChild(resultCard);

    // Result area (filled after eval — session-aware)
    var resultArea = document.createElement('div');
    resultArea.id = 'sessionResultArea';
    resultArea.className = 'hidden mt-4 text-center';
    controls.appendChild(resultArea);

    // Set targets for the speech recognition system
    targetQ = step.q;
    targetA = step.a;
    targetAH = step.a_hu || '';
    currentMode = step.mode || 'pronunciation';
}

function renderKnowledgeStep(step, content, controls) {
    // Question
    var q = document.createElement('h1');
    q.className = 'text-xl font-bold text-white mb-2';
    q.textContent = step.title_en || step.title_hu;
    content.appendChild(q);

    var huTitle = document.createElement('p');
    huTitle.className = 'text-accent-light text-lg font-semibold mb-4';
    huTitle.textContent = step.title_hu;
    content.appendChild(huTitle);

    // Listen button
    var listenBtn = document.createElement('button');
    listenBtn.className = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-surface-300 text-slate-200 hover:text-white text-xs font-semibold mb-4';
    listenBtn.textContent = '▶ Listen';
    (function(text) { listenBtn.onclick = function() { speakHu(text); }; })(step.title_hu + '. ' + (step.content_hu || ''));
    content.appendChild(listenBtn);

    // Answer area
    var answerDiv = document.createElement('div');
    answerDiv.id = 'kStepAnswer';
    answerDiv.className = 'hidden bg-accent/5 rounded-xl p-4 border border-accent/10 mt-2 mb-2';
    if (step.content_en) {
        var ce = document.createElement('p');
        ce.className = 'text-sm text-slate-300 mb-1';
        ce.textContent = step.content_en;
        answerDiv.appendChild(ce);
    }
    var kf = document.createElement('p');
    kf.className = 'text-base font-bold text-accent-light';
    kf.textContent = step.key_fact || step.title_hu;
    answerDiv.appendChild(kf);
    content.appendChild(answerDiv);

    // Buttons
    var showBtn = document.createElement('button');
    showBtn.className = 'w-full py-3 bg-surface-300 hover:bg-surface-400 rounded-xl text-sm font-bold text-white transition-all mb-2';
    showBtn.textContent = 'Show Answer';
    showBtn.onclick = function() {
        answerDiv.classList.remove('hidden');
        showBtn.classList.add('hidden');
        actionRow.classList.remove('hidden');
    };
    controls.appendChild(showBtn);

    var actionRow = document.createElement('div');
    actionRow.className = 'hidden flex gap-2';
    var gotIt = document.createElement('button');
    gotIt.className = 'flex-1 py-3 bg-green-600 hover:bg-green-500 rounded-xl text-sm font-bold text-white transition-all';
    gotIt.textContent = 'Got It ✓';
    gotIt.onclick = function() {
        sessionPassCount++;
        sessionTotalCount++;
        recordSRSUnified(step.title_hu, 'knowledge', step.id, true);
        sessionIdx++;
        renderSessionStep();
    };
    var again = document.createElement('button');
    again.className = 'flex-1 py-3 bg-red-600/80 hover:bg-red-500 rounded-xl text-sm font-bold text-white transition-all';
    again.textContent = 'Again ✗';
    again.onclick = function() {
        sessionTotalCount++;
        recordSRSUnified(step.title_hu, 'knowledge', step.id, false);
        sessionIdx++;
        renderSessionStep();
    };
    actionRow.appendChild(gotIt);
    actionRow.appendChild(again);
    controls.appendChild(actionRow);
}

function renderGrammarTeachStep(step, content, controls) {
    content.textContent = '';
    var loading = document.createElement('div');
    loading.className = 'flex flex-col items-center py-8 gap-3';
    loading.innerHTML = '<div class="w-8 h-8 border-2 border-purple-400 border-t-transparent rounded-full animate-spin"></div><p class="text-slate-400 text-sm">Generating grammar lesson...</p>';
    content.appendChild(loading);

    // Fetch grammar pattern details and teach
    fetch('?ajax=1&action=grammar_patterns&search=')
        .then(function(r) { return r.json(); })
        .then(function(patterns) {
            var p = patterns.find(function(pt) { return pt.id == step.pattern_id; }) || patterns[0];
            if (!p) { content.textContent = 'No grammar patterns found.'; return; }
            var fd = new FormData();
            fd.append('pattern', p.pattern);
            fd.append('suffix_words', p.suffix_words || '');
            fd.append('explanation', p.explanation || '');
            return fetch('?ajax=1&action=teach_me', { method: 'POST', body: fd });
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { content.textContent = data.error; return; }
            content.textContent = '';
            // Render lesson content
            var lessonEl = document.createElement('div');
            lessonEl.className = 'text-left space-y-4 w-full';
            var expl = document.createElement('p');
            expl.className = 'text-sm text-slate-200 leading-relaxed';
            expl.textContent = data.lesson;
            lessonEl.appendChild(expl);
            if (data.tip) {
                var tip = document.createElement('p');
                tip.className = 'text-xs text-yellow-200 bg-yellow-400/5 rounded-lg p-3 border border-yellow-400/15';
                tip.textContent = '💡 ' + data.tip;
                lessonEl.appendChild(tip);
            }
            content.appendChild(lessonEl);

            // Done button (marks as complete)
            controls.textContent = '';
            var doneBtn = document.createElement('button');
            doneBtn.className = 'w-full py-3 bg-green-600 hover:bg-green-500 rounded-xl text-sm font-bold text-white transition-all';
            doneBtn.textContent = 'Got It — Next';
            doneBtn.onclick = function() {
                sessionPassCount++;
                sessionTotalCount++;
                recordSRSUnified(sessionBlockInfo.title, 'grammar', step.pattern_id, true);
                sessionIdx++;
                renderSessionStep();
            };
            controls.appendChild(doneBtn);
        })
        .catch(function(err) { content.textContent = 'Error loading lesson'; });
}

function recordSRSUnified(phrase, itemType, itemId, pass) {
    var fd = new FormData();
    fd.append('phrase', phrase);
    fd.append('pass', pass ? '1' : '0');
    fd.append('who', who);
    fd.append('item_type', itemType);
    if (itemId) fd.append('item_id', itemId);
    fetch('record.php', { method: 'POST', body: fd }).catch(function() {});
}

function showBlockSummary() {
    document.getElementById('sessionCard').classList.add('hidden');
    document.getElementById('sessionSummary').classList.remove('hidden');
    var elapsed = Math.round((new Date() - sessionStartTime) / 60000);
    var pct = sessionTotalCount > 0 ? Math.round((sessionPassCount / sessionTotalCount) * 100) : 100;
    document.getElementById('summaryScore').textContent = pct + '%';
    document.getElementById('summaryItems').textContent = sessionTotalCount;
    document.getElementById('summaryTime').textContent = elapsed + 'm';
    document.getElementById('summarySubtitle').textContent = sessionBlockInfo ? sessionBlockInfo.title : '';

    // Log the block
    if (sessionBlockInfo) {
        logBlock(sessionBlockInfo.block_type, sessionBlockInfo.title, elapsed || sessionBlockInfo.duration, sessionTotalCount, sessionPassCount);
    }
}

function closeSessionSummary() {
    activeSession = false;
    document.getElementById('sessionSummary').classList.add('hidden');
    document.getElementById('planBlockList').classList.remove('hidden');
    dailyPlanLoaded = false;
    loadDailyPlan();
}

function exitSession() {
    activeSession = false;
    document.getElementById('sessionCard').classList.add('hidden');
    document.getElementById('planBlockList').classList.remove('hidden');
    // Log partial progress
    if (sessionBlockInfo && sessionTotalCount > 0) {
        var elapsed = Math.round((new Date() - sessionStartTime) / 60000);
        logBlock(sessionBlockInfo.block_type, sessionBlockInfo.title, elapsed || 1, sessionTotalCount, sessionPassCount);
        dailyPlanLoaded = false;
        loadDailyPlan();
    }
}

function quickReview() {
    startSessionBlock({
        type: 'in_app', block_type: 'phrase_review', title: 'Quick Review', subtitle: '5 items',
        duration: 5, icon: 'zap', session: { mode: 'review', limit: 5 }
    }, -1);
}

function switchItUp() {
    var types = ['grammar_lesson', 'knowledge_review', 'interview_sim', 'phrase_practice'];
    var pick = types[Math.floor(Math.random() * types.length)];
    var blocks = {
        grammar_lesson: { type: 'in_app', block_type: 'grammar_lesson', title: 'Grammar Surprise', subtitle: 'Random pattern', duration: 15, icon: 'book-open', session: { mode: 'grammar', pattern_id: null } },
        knowledge_review: { type: 'in_app', block_type: 'knowledge_review', title: 'Knowledge Quiz', subtitle: 'Random category', duration: 15, icon: 'landmark', session: { mode: 'knowledge', category: '', limit: 5 } },
        interview_sim: { type: 'in_app', block_type: 'interview_sim', title: 'Interview Practice', subtitle: 'Personal questions', duration: 15, icon: 'message-square', session: { mode: 'interview', cat: 'bios', limit: 5 } },
        phrase_practice: { type: 'in_app', block_type: 'phrase_practice', title: 'Phrase Sprint', subtitle: 'Quick fire phrases', duration: 15, icon: 'mic', session: { mode: 'practice', cat: 'all', limit: 8 } }
    };
    startSessionBlock(blocks[pick], -1);
}

// Home screen data
var homeLoaded = false;
function loadHomeStats() {
    // Home stats are now shown via daily plan; this is kept for backward compat
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
    if (currentView !== 'practice') showView('practice');
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
    $set('questionText', 'text', p.q);
    $set('answerText', 'text', p.a_hu || p.a);
    $set('resultCard', 'hide'); $set('resultCard', 'removeClass', 'result-pass'); $set('resultCard', 'removeClass', 'result-fail');
    $set('matchScore', 'text', ''); $set('transcript', 'text', '');
    $set('playbackBtn', 'hide');
    $set('categoryTag', 'text', activeDrillName);
    $set('revealDetails', 'removeAttr', 'open');
    lastRecordingBlob = null;
    questionAttempted = false;
    var pf = document.getElementById('progressFill');
    var pl = document.getElementById('progressLabel');
    if (pf) { var pct = Math.min(100, ((drillIdx) / drillPhrases.length) * 100); pf.style.width = pct + '%'; }
    if (pl) pl.textContent = (drillIdx + 1) + ' / ' + drillPhrases.length;
    if (listenMode) applyListenMode();
    if (translateOn) fetchTranslation(); else { $set('inlineTranslation', 'hide'); }
    if (phoneticOn) fetchPhonetic(); else { $set('phoneticHint', 'hide'); }
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
    ['grammarList', 'grammarList2'].forEach(function(id) {
        var list = document.getElementById(id);
        if (!list) return;
        list.textContent = '';
        if (!patterns.length) {
            var empty = document.createElement('p');
            empty.className = 'col-span-2 text-slate-500 text-sm text-center py-4';
            empty.textContent = 'No grammar patterns yet. Import content to get started.';
            list.appendChild(empty);
            return;
        }
        patterns.forEach(function(p) { list.appendChild(buildPatternCard(p)); });
    });
    lucide.createIcons();
}

function buildPatternCard(p) {
    var tile = document.createElement('button');
    tile.className = 'grammar-card text-left flex flex-col gap-1 p-3 active:scale-95 cursor-pointer relative';

    // Speaker icon top-right
    var speaker = document.createElement('span');
    speaker.className = 'absolute top-2 right-2 text-slate-600 hover:text-accent-light transition-colors';
    speaker.textContent = '🔊';
    speaker.style.fontSize = '12px';
    speaker.onclick = function(e) { e.stopPropagation(); speakHu(p.suffix_words || p.pattern); };
    tile.appendChild(speaker);

    var title = document.createElement('h3');
    title.className = 'text-[11px] font-bold text-white leading-snug pr-5';
    title.textContent = p.pattern;
    tile.appendChild(title);

    if (p.part_of_speech && p.part_of_speech !== 'Other') {
        var posBadge = document.createElement('span');
        posBadge.className = 'text-[9px] font-bold uppercase tracking-wider text-slate-500';
        posBadge.textContent = p.part_of_speech;
        tile.appendChild(posBadge);
    }

    if (p.explanation) {
        var expl = document.createElement('p');
        expl.className = 'text-[10px] text-slate-500 leading-snug line-clamp-2';
        expl.textContent = p.explanation;
        tile.appendChild(expl);
    }

    tile.onclick = function() { teachMe(p); };
    return tile;
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
        renderGrammarPatterns(filtered);
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
            if (data.error) {
                content.textContent = '';
                var errP = document.createElement('p');
                errP.className = 'text-red-400 text-sm text-center py-4';
                errP.textContent = data.error;
                content.appendChild(errP);
                return;
            }
            renderLesson(data, pattern);
        })
        .catch(function(err) {
            content.textContent = '';
            var errP = document.createElement('p');
            errP.className = 'text-red-400 text-sm text-center py-4';
            errP.textContent = 'Failed to load lesson: ' + err.message;
            content.appendChild(errP);
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
            var huEscaped = escHtml(ex.hu);
            var huText = ex.highlight ? huEscaped.replace(escHtml(ex.highlight), '<span class="text-accent-light font-bold">' + escHtml(ex.highlight) + '</span>') : huEscaped;
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
    if (!text) return;
    // Chrome bug: speechSynthesis can get stuck. Cancel + resume.
    window.speechSynthesis.cancel();
    if (window.speechSynthesis.paused) window.speechSynthesis.resume();

    // Reload voices if not found yet
    if (!huVoice) loadVoices();

    var msg = new SpeechSynthesisUtterance(text);
    msg.lang = 'hu-HU'; msg.rate = 0.8;
    if (huVoice) msg.voice = huVoice;

    // Chrome workaround: short delay after cancel
    setTimeout(function() {
        window.speechSynthesis.speak(msg);
    }, 50);
}

function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function escAttr(s) { return s.replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

// ── Knowledge tab ────────────────────────────────────────────────────
var knowledgeLoaded = false;
var allKnowledgeCards = [];
var knowledgeActiveCat = '';

function loadKnowledgeCards() {
    if (knowledgeLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=knowledge_cards')
        .then(function(r) { return r.json(); })
        .then(function(cards) {
            knowledgeLoaded = true;
            allKnowledgeCards = cards;
            document.getElementById('knowledgeCount').textContent = cards.length + ' cards';
            renderKnowledgeCards(cards);
            lucide.createIcons();
        })
        .catch(function() {
            document.getElementById('knowledgeList').textContent = '';
            var p = document.createElement('p');
            p.className = 'text-slate-500 text-sm text-center py-4';
            p.textContent = 'Could not load knowledge cards. Check your connection and try again.';
            document.getElementById('knowledgeList').appendChild(p);
        });
}

function filterKnowledge(cat) {
    knowledgeActiveCat = (knowledgeActiveCat === cat) ? '' : cat;
    ['', 'history', 'geography', 'family', 'culture'].forEach(function(c) {
        var id = c ? 'kc-' + c : 'kc-all';
        var el = document.getElementById(id);
        var isActive = (c === knowledgeActiveCat) || (!knowledgeActiveCat && !c);
        el.className = 'pill ' + (isActive ? 'pill-active' : 'pill-inactive');
    });
    var filtered = knowledgeActiveCat ? allKnowledgeCards.filter(function(c) { return c.category === knowledgeActiveCat; }) : allKnowledgeCards;
    document.getElementById('knowledgeCount').textContent = filtered.length + ' cards';
    renderKnowledgeCards(filtered);
}

var knowledgeDebounce;
function searchKnowledge() {
    clearTimeout(knowledgeDebounce);
    knowledgeDebounce = setTimeout(function() {
        var q = document.getElementById('knowledgeSearch').value.trim().toLowerCase();
        var filtered = allKnowledgeCards.filter(function(c) {
            if (knowledgeActiveCat && c.category !== knowledgeActiveCat) return false;
            if (!q) return true;
            return (c.title_hu || '').toLowerCase().indexOf(q) !== -1 ||
                   (c.title_en || '').toLowerCase().indexOf(q) !== -1 ||
                   (c.content_en || '').toLowerCase().indexOf(q) !== -1;
        });
        document.getElementById('knowledgeCount').textContent = filtered.length + ' cards';
        renderKnowledgeCards(filtered);
    }, 200);
}

function renderKnowledgeCards(cards) {
    var list = document.getElementById('knowledgeList');
    list.textContent = '';
    if (!cards.length) {
        var empty = document.createElement('p');
        empty.className = 'text-slate-500 text-sm text-center py-4';
        empty.textContent = 'No knowledge cards found.';
        list.appendChild(empty);
        return;
    }
    var catColors = { history: 'border-amber-500/20 text-amber-400', geography: 'border-blue-500/20 text-blue-400', family: 'border-pink-500/20 text-pink-400', culture: 'border-purple-500/20 text-purple-400' };
    cards.forEach(function(c) {
        var tile = document.createElement('button');
        tile.className = 'grammar-card text-left flex flex-col gap-1 p-3 active:scale-95 cursor-pointer relative ' + (catColors[c.category] || '');

        // Speaker icon top-right
        var speaker = document.createElement('span');
        speaker.className = 'absolute top-2 right-2 text-slate-600 hover:text-accent-light transition-colors';
        speaker.textContent = '🔊';
        speaker.style.fontSize = '12px';
        (function(text) { speaker.onclick = function(e) { e.stopPropagation(); speakHu(text); }; })(c.title_hu + '. ' + (c.content_hu || ''));
        tile.appendChild(speaker);

        var title = document.createElement('h3');
        title.className = 'text-[11px] font-bold text-white leading-snug pr-5';
        title.textContent = c.title_hu;
        tile.appendChild(title);

        if (c.title_en) {
            var sub = document.createElement('span');
            sub.className = 'text-[10px] text-slate-500 leading-snug';
            sub.textContent = c.title_en;
            tile.appendChild(sub);
        }

        var badge = document.createElement('span');
        badge.className = 'text-[9px] font-bold uppercase tracking-wider text-slate-500 mt-auto';
        badge.textContent = c.category;
        tile.appendChild(badge);

        (function(kc) { tile.onclick = function() { knowledgeTeachMe(kc); }; })(c);
        list.appendChild(tile);
    });
    lucide.createIcons();
}

function knowledgeTeachMe(card) {
    var panel = document.getElementById('knowledgeLessonPanel');
    var content = document.getElementById('knowledgeLessonContent');
    document.getElementById('knowledgeLessonTitle').querySelector('span').textContent = card.title_hu;
    content.textContent = '';
    var spinner = document.createElement('div');
    spinner.className = 'flex flex-col items-center py-8 gap-3';
    var ring = document.createElement('div');
    ring.className = 'w-8 h-8 border-2 border-accent-light border-t-transparent rounded-full animate-spin';
    spinner.appendChild(ring);
    var msg = document.createElement('p');
    msg.className = 'text-slate-400 text-sm';
    msg.textContent = 'Generating study guide...';
    spinner.appendChild(msg);
    content.appendChild(spinner);
    panel.classList.remove('hidden');
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    var fd = new FormData();
    fd.append('title', card.title_hu + (card.title_en ? ' (' + card.title_en + ')' : ''));
    fd.append('content', card.content_hu || card.content_en || '');
    fd.append('category', card.category);

    fetch('?ajax=1&action=knowledge_teach', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                content.textContent = '';
                var err = document.createElement('p');
                err.className = 'text-red-400 text-sm text-center py-4';
                err.textContent = data.error;
                content.appendChild(err);
                return;
            }
            renderKnowledgeLesson(data);
        })
        .catch(function(err) {
            content.textContent = '';
            var errP = document.createElement('p');
            errP.className = 'text-red-400 text-sm text-center py-4';
            errP.textContent = 'Failed: ' + err.message;
            content.appendChild(errP);
        });
}

function renderKnowledgeLesson(data) {
    var content = document.getElementById('knowledgeLessonContent');
    var html = '';
    html += '<div class="bg-surface-50 rounded-xl p-4 border border-white/5"><p class="text-sm text-slate-200 leading-relaxed">' + escHtml(data.lesson) + '</p></div>';
    if (data.tip) {
        html += '<div class="bg-yellow-400/5 rounded-xl p-4 border border-yellow-400/15 flex items-start gap-3"><p class="text-sm text-yellow-200">' + escHtml(data.tip) + '</p></div>';
    }
    if (data.key_facts && data.key_facts.length) {
        html += '<div><h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Key Facts</h3><div class="space-y-2">';
        data.key_facts.forEach(function(f) {
            html += '<div class="bg-surface-50 rounded-lg p-3 border border-white/5"><p class="text-sm text-white font-medium">' + escHtml(f.hu) + '</p><p class="text-xs text-slate-400 mt-1">' + escHtml(f.en) + '</p></div>';
        });
        html += '</div></div>';
    }
    if (data.quiz && data.quiz.length) {
        html += '<div><h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Quick Quiz</h3><div class="space-y-3">';
        data.quiz.forEach(function(q, i) {
            html += '<div class="bg-surface-50 rounded-lg p-4 border border-white/5">';
            html += '<p class="text-sm text-white mb-2">' + escHtml(q.prompt) + '</p>';
            html += '<div class="flex items-center gap-2">';
            html += '<input type="text" class="flex-1 bg-surface-300 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 outline-none border border-white/5 focus:border-accent/40" placeholder="Your answer..." id="kquiz-input-' + i + '" data-answer="' + escAttr(q.answer) + '" onkeydown="if(event.key===\'Enter\')checkKQuiz(' + i + ')">';
            html += '<button onclick="checkKQuiz(' + i + ')" class="px-3 py-2 rounded-lg bg-accent text-white text-xs font-semibold hover:bg-accent-dark transition-all">Check</button></div>';
            html += '<p class="text-xs text-slate-500 mt-1.5 hidden" id="kquiz-hint-' + i + '">' + escHtml(q.hint) + '</p>';
            html += '<p class="text-xs mt-1.5 hidden" id="kquiz-result-' + i + '"></p></div>';
        });
        html += '</div></div>';
    }
    content.innerHTML = html;
    lucide.createIcons();
}

function checkKQuiz(idx) {
    var input = document.getElementById('kquiz-input-' + idx);
    var result = document.getElementById('kquiz-result-' + idx);
    var hint = document.getElementById('kquiz-hint-' + idx);
    result.classList.remove('hidden');
    if (input.value.toLowerCase().trim() === input.dataset.answer.toLowerCase().trim()) {
        result.className = 'text-xs mt-1.5 text-green-400 font-semibold';
        result.textContent = 'Correct!';
    } else {
        result.className = 'text-xs mt-1.5 text-red-400';
        result.textContent = 'Answer: ' + input.dataset.answer;
        hint.classList.remove('hidden');
    }
}

function closeKnowledgeLesson() { document.getElementById('knowledgeLessonPanel').classList.add('hidden'); }

function knowledgeQuizMode() {
    var cards = knowledgeActiveCat ? allKnowledgeCards.filter(function(c) { return c.category === knowledgeActiveCat; }) : allKnowledgeCards;
    if (cards.length < 3) { alert('Need at least 3 cards for a quiz.'); return; }
    var shuffled = cards.slice().sort(function() { return Math.random() - 0.5; }).slice(0, 5);
    var panel = document.getElementById('knowledgeQuizPanel');
    var content = document.getElementById('knowledgeQuizContent');
    content.textContent = '';
    panel.classList.remove('hidden');
    panel.scrollIntoView({ behavior: 'smooth' });
    shuffled.forEach(function(c, i) {
        var card = document.createElement('div');
        card.className = 'bg-surface-50 rounded-lg p-4 border border-white/5';
        var prompt = document.createElement('p');
        prompt.className = 'text-sm text-white mb-2 font-medium';
        prompt.textContent = c.title_en || ('Translate: ' + c.title_hu);
        card.appendChild(prompt);
        var row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'flex-1 bg-surface-300 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 outline-none border border-white/5 focus:border-accent/40';
        inp.placeholder = 'Answer in Hungarian...';
        inp.id = 'kqm-input-' + i;
        inp.dataset.answer = c.key_fact || c.title_hu;
        var btn = document.createElement('button');
        btn.className = 'px-3 py-2 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-500 transition-all';
        btn.textContent = 'Check';
        (function(idx) { btn.onclick = function() { checkKQM(idx); }; })(i);
        row.appendChild(inp);
        row.appendChild(btn);
        card.appendChild(row);
        var res = document.createElement('p');
        res.className = 'text-xs mt-1.5 hidden';
        res.id = 'kqm-result-' + i;
        card.appendChild(res);
        content.appendChild(card);
    });
}

function checkKQM(idx) {
    var input = document.getElementById('kqm-input-' + idx);
    var result = document.getElementById('kqm-result-' + idx);
    var answer = input.dataset.answer.toLowerCase().trim();
    var userAnswer = input.value.toLowerCase().trim();
    result.classList.remove('hidden');
    if (userAnswer === answer || answer.indexOf(userAnswer) !== -1) {
        result.className = 'text-xs mt-1.5 text-green-400 font-semibold';
        result.textContent = 'Correct!';
    } else {
        result.className = 'text-xs mt-1.5 text-red-400';
        result.textContent = 'Answer: ' + input.dataset.answer;
    }
}

function closeKnowledgeQuiz() { document.getElementById('knowledgeQuizPanel').classList.add('hidden'); }

function addKnowledgeCard() {
    var title = prompt('Title in Hungarian:');
    if (!title) return;
    var titleEn = prompt('Title in English (optional):') || '';
    var kcat = prompt('Category (history/geography/family/culture):') || 'culture';
    var fd = new FormData();
    fd.append('category', kcat);
    fd.append('title_hu', title);
    fd.append('title_en', titleEn);
    fetch('?ajax=1&action=save_knowledge', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            alert(data.msg || data.error);
            knowledgeLoaded = false;
            loadKnowledgeCards();
        });
}

// ── Resources tab ────────────────────────────────────────────────────
var resourcesLoaded = false;

function loadResources() {
    if (resourcesLoaded) return;
    fetch('?ajax=1&action=resources')
        .then(function(r) { return r.json(); })
        .then(function(resources) {
            resourcesLoaded = true;
            renderResources(resources);
            lucide.createIcons();
        })
        .catch(function() {
            ['resourcesList', 'resourcesList2'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.textContent = '';
                var p = document.createElement('p');
                p.className = 'text-slate-500 text-sm text-center py-4';
                p.textContent = 'Could not load resources.';
                el.appendChild(p);
            });
        });
}

function renderResources(resources) {
    var list = document.getElementById('resourcesList') || document.getElementById('resourcesList2');
    list.textContent = '';
    if (!resources.length) {
        var p = document.createElement('p');
        p.className = 'text-slate-500 text-sm text-center py-4';
        p.textContent = 'No resources yet. Run migrate_v7.php to seed them.';
        list.appendChild(p);
        return;
    }
    var groups = {};
    resources.forEach(function(r) {
        if (!groups[r.category]) groups[r.category] = [];
        groups[r.category].push(r);
    });
    Object.keys(groups).forEach(function(cat) {
        var section = document.createElement('div');
        section.className = 'space-y-2';
        var header = document.createElement('h3');
        header.className = 'text-xs font-bold text-slate-400 uppercase tracking-wider';
        header.textContent = cat;
        section.appendChild(header);

        var grid = document.createElement('div');
        grid.className = 'grid grid-cols-2 sm:grid-cols-3 gap-2';
        groups[cat].forEach(function(r) {
            var link = document.createElement('a');
            link.href = r.url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'flex items-center gap-2 p-3 rounded-xl bg-surface-100 border border-white/5 hover:border-accent/30 hover:bg-surface-200 transition-all';
            var icon = document.createElement('span');
            icon.className = 'text-lg';
            icon.textContent = r.icon || '🔗';
            var name = document.createElement('span');
            name.className = 'text-sm font-semibold text-white';
            name.textContent = r.name;
            link.appendChild(icon);
            link.appendChild(name);
            grid.appendChild(link);
        });
        section.appendChild(grid);
        list.appendChild(section);
    });
    // Mirror to second resources list if it exists
    var list2 = document.getElementById('resourcesList2');
    if (list2 && list2 !== list) {
        list2.textContent = '';
        list.childNodes.forEach(function(node) { list2.appendChild(node.cloneNode(true)); });
    }
}

// ── Google Sheets import (basic) ─────────────────────────────────────
function fetchSheetPreview() {
    var url = document.getElementById('sheetsUrl').value.trim();
    if (!url) return;
    var preview = document.getElementById('sheetsPreview');
    preview.classList.remove('hidden');
    preview.textContent = '';
    var loading = document.createElement('p');
    loading.className = 'text-slate-400 text-sm';
    loading.textContent = 'Fetching sheet...';
    preview.appendChild(loading);

    fetch('import_sheets.php?action=fetch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'url=' + encodeURIComponent(url)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.error) {
            preview.textContent = '';
            var err = document.createElement('p');
            err.className = 'text-red-400 text-sm';
            err.textContent = data.error;
            preview.appendChild(err);
            return;
        }
        renderSheetPreview(data, url);
    })
    .catch(function(err) {
        preview.textContent = '';
        var errP = document.createElement('p');
        errP.className = 'text-red-400 text-sm';
        errP.textContent = 'Error: ' + err.message;
        preview.appendChild(errP);
    });
}

function renderSheetPreview(data, url) {
    var preview = document.getElementById('sheetsPreview');
    preview.textContent = '';
    var info = document.createElement('p');
    info.className = 'text-green-400 text-xs font-semibold';
    info.textContent = data.total_rows + ' rows found';
    preview.appendChild(info);

    // Column mapping selects
    var mapGrid = document.createElement('div');
    mapGrid.className = 'grid grid-cols-2 gap-2';
    var headers = data.headers || [];
    ['question_hu', 'answer_hu', 'answer_en', 'category'].forEach(function(field) {
        var wrap = document.createElement('div');
        var label = document.createElement('label');
        label.className = 'text-[10px] text-slate-400 font-semibold uppercase';
        label.textContent = field;
        wrap.appendChild(label);
        var sel = document.createElement('select');
        sel.id = 'map-' + field;
        sel.className = 'w-full bg-surface-300 rounded-lg px-2 py-1.5 text-xs text-white border border-white/5';
        var skip = document.createElement('option');
        skip.value = '-1';
        skip.textContent = '-- skip --';
        sel.appendChild(skip);
        headers.forEach(function(h, i) {
            var opt = document.createElement('option');
            opt.value = i;
            opt.textContent = h;
            if (h.toLowerCase().indexOf(field.replace('_', ' ')) !== -1) opt.selected = true;
            sel.appendChild(opt);
        });
        wrap.appendChild(sel);
        mapGrid.appendChild(wrap);
    });
    preview.appendChild(mapGrid);

    var importBtn = document.createElement('button');
    importBtn.className = 'w-full py-2 bg-green-600 hover:bg-green-500 rounded-xl text-sm font-bold text-white transition-all mt-2';
    importBtn.textContent = 'Import ' + data.total_rows + ' rows';
    (function(u) { importBtn.onclick = function() { confirmSheetImport(u); }; })(url);
    preview.appendChild(importBtn);
}

function confirmSheetImport(url) {
    var preview = document.getElementById('sheetsPreview');
    var fields = {};
    ['question_hu', 'answer_hu', 'answer_en', 'category'].forEach(function(f) {
        var sel = document.getElementById('map-' + f);
        fields['col_' + f] = sel ? sel.value : '-1';
    });
    preview.textContent = '';
    var loading = document.createElement('p');
    loading.className = 'text-slate-400 text-sm';
    loading.textContent = 'Importing...';
    preview.appendChild(loading);
    var body = 'url=' + encodeURIComponent(url);
    Object.keys(fields).forEach(function(k) { body += '&' + k + '=' + fields[k]; });
    body += '&who=' + who;

    fetch('import_sheets.php?action=import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        preview.textContent = '';
        var msg = document.createElement('p');
        if (data.error) {
            msg.className = 'text-red-400 text-sm';
            msg.textContent = data.error;
        } else {
            msg.className = 'text-green-400 text-sm font-semibold';
            msg.textContent = 'Imported ' + data.imported + ' phrases (' + data.skipped + ' skipped, ' + data.duplicates + ' duplicates)';
        }
        preview.appendChild(msg);
    })
    .catch(function(err) {
        preview.textContent = '';
        var errP = document.createElement('p');
        errP.className = 'text-red-400 text-sm';
        errP.textContent = 'Error: ' + err.message;
        preview.appendChild(errP);
    });
}

// ── Progress tab (inline stats + phrases) ────────────────────────────
var progressDashboardLoaded = false;
var progressPhrasesLoaded = false;

function loadProgressDashboard() {
    if (progressDashboardLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=stats')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            progressDashboardLoaded = true;
            renderProgressDashboard(data);
        });
}

function renderProgressDashboard(data) {
    var container = document.getElementById('progressDashboard');
    container.textContent = '';
    var pct = data.total > 0 ? Math.round((data.mastered / data.total) * 100) : 0;
    var studiedPct = data.total > 0 ? Math.round((data.studied / data.total) * 100) : 0;

    var grid = document.createElement('div');
    grid.className = 'grid grid-cols-2 gap-3';
    grid.appendChild(makeStatCard('Total Phrases', data.total));
    grid.appendChild(makeStatCard('Studied', data.studied + ' (' + studiedPct + '%)'));
    grid.appendChild(makeStatCard('Mastered', data.mastered + ' (' + pct + '%)'));
    grid.appendChild(makeStatCard('Due for Review', data.due));
    container.appendChild(grid);

    // Mastery bar
    var barSection = document.createElement('div');
    barSection.className = 'mt-4';
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
    container.appendChild(barSection);

    // Weak phrases
    if (data.weak && data.weak.length) {
        var weakSection = document.createElement('div');
        weakSection.className = 'mt-4';
        var weakLabel = document.createElement('h3');
        weakLabel.className = 'text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2';
        weakLabel.textContent = 'Needs Practice';
        weakSection.appendChild(weakLabel);
        var weakGrid = document.createElement('div');
        weakGrid.className = 'flex flex-wrap gap-1.5';
        data.weak.forEach(function(w) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-500/10 border border-red-500/15 text-[11px] text-white cursor-pointer hover:bg-red-500/20 transition-all';
            chip.textContent = w.phrase;
            var badge = document.createElement('span');
            badge.className = 'text-[9px] text-red-400 font-bold';
            badge.textContent = w.fail_count;
            chip.appendChild(badge);
            weakGrid.appendChild(chip);
        });
        weakSection.appendChild(weakGrid);
        container.appendChild(weakSection);
    }

    // Recent activity
    if (data.recent && data.recent.length) {
        var recentSection = document.createElement('div');
        recentSection.className = 'mt-4';
        var recentLabel = document.createElement('h3');
        recentLabel.className = 'text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2';
        recentLabel.textContent = 'Recent Activity';
        recentSection.appendChild(recentLabel);
        var recentGrid = document.createElement('div');
        recentGrid.className = 'flex flex-wrap gap-1.5';
        data.recent.forEach(function(r) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-surface-50 border border-white/5 text-[11px] text-white';
            chip.textContent = r.phrase;
            var date = document.createElement('span');
            date.className = 'text-[9px] text-slate-500';
            date.textContent = (r.last_seen || '').substring(0, 10);
            chip.appendChild(date);
            recentGrid.appendChild(chip);
        });
        recentSection.appendChild(recentGrid);
        container.appendChild(recentSection);
    }
}

function loadProgressPhrases() {
    if (progressPhrasesLoaded) return;
    fetch('?who=' + who + '&ajax=1&action=phrases')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            progressPhrasesLoaded = true;
            renderProgressPhrases(data);
        });
}

function searchProgressPhrases() {
    var q = document.getElementById('progressBrowseSearch').value.trim();
    fetch('?who=' + who + '&ajax=1&action=phrases' + (q ? '&search=' + encodeURIComponent(q) : ''))
        .then(function(r) { return r.json(); })
        .then(function(data) { renderProgressPhrases(data); });
}

function renderProgressPhrases(data) {
    var list = document.getElementById('progressBrowseList');
    document.getElementById('progressBrowseCount').textContent = data.length + ' phrases';
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
        (function(q, a) {
            item.onclick = function() {
                showView('practice');
                setTimeout(function() { jumpToPhrase(q, a); }, 100);
            };
        })(p.q, p.a);
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

// ── Init ──────────────────────────────────────────────────────────────
// Guard old practice card inits — elements may not exist in v8 layout
try { setMode(currentMode); } catch(e) {}
try { setCat(cat, true); } catch(e) {}
try { setSpeed(currentSpeed); } catch(e) {}
try { applyListenMode(); } catch(e) {}
try { applyAutoAdvance(); } catch(e) {}
try { applyTranslateState(); } catch(e) {}
try { applyPhoneticState(); } catch(e) {}
showView('today');
lucide.createIcons();
</script>
</body>
</html>
