<?php
session_start();
$env = parse_ini_file('.env');
$ADMIN_PASS = $env['ADMIN_PASS'] ?? 'hug2026';

// Auth
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

$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($conn->connect_error) { die('DB connection failed'); }

// Ensure schema columns exist
if (empty($_SESSION['hug_schema_ok'])) {
    $colCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'answer_hu'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE hungarian_prep ADD COLUMN answer_hu TEXT DEFAULT NULL AFTER answer_en");
    }
    $whoCheck = $conn->query("SHOW COLUMNS FROM hungarian_prep LIKE 'who'");
    if ($whoCheck && $whoCheck->num_rows === 0) {
        $conn->query("ALTER TABLE hungarian_prep ADD COLUMN `who` VARCHAR(10) DEFAULT 'All' AFTER category");
    }
    // Ensure api_log table exists
    $conn->query("CREATE TABLE IF NOT EXISTS api_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(100) NOT NULL,
        model VARCHAR(100),
        tokens_in INT DEFAULT 0,
        tokens_out INT DEFAULT 0,
        cost_usd DECIMAL(10,6) DEFAULT 0,
        who VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_endpoint (endpoint)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ensure app_connections table exists
    $conn->query("CREATE TABLE IF NOT EXISTS app_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        invite_code VARCHAR(20),
        status ENUM('active','invited','disabled') DEFAULT 'invited',
        strictness TINYINT DEFAULT 2,
        speed ENUM('slow','normal','fast') DEFAULT 'normal',
        mode_pref ENUM('pronunciation','interview','both') DEFAULT 'both',
        last_active DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Seed default connections
    $cnt = $conn->query("SELECT COUNT(*) AS c FROM app_connections")->fetch_assoc()['c'];
    if ((int)$cnt === 0) {
        $conn->query("INSERT INTO app_connections (name, status, strictness) VALUES ('Maria','active',2),('Larry','active',2)");
    }
    $_SESSION['hug_schema_ok'] = true;
}

$tab = $_GET['tab'] ?? 'progress';
$message = '';

// ─── POST Actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Phrases actions
    if ($action === 'preview') {
        $raw = trim($_POST['tsv'] ?? '');
        $format = $_POST['format'] ?? '3col';
        $defaultCat = trim($_POST['default_cat'] ?? 'General');
        $preview = [];
        if ($raw) {
            $lines = array_filter(explode("\n", $raw), 'trim');
            foreach ($lines as $line) {
                $cols = array_map('trim', explode("\t", $line));
                if (count($cols) < 2) continue;
                $row = ['question_hu' => $cols[0], 'answer_en' => '', 'answer_hu' => '', 'category' => $defaultCat, 'who' => 'All'];
                if ($format === '2col_hu') { $row['answer_hu'] = $cols[1]; }
                elseif ($format === '2col') { $row['answer_en'] = $cols[1]; }
                elseif ($format === '3col') { $row['answer_hu'] = $cols[1] ?? ''; $row['answer_en'] = $cols[2] ?? ''; }
                elseif ($format === '4col') { $row['answer_hu'] = $cols[1] ?? ''; $row['answer_en'] = $cols[2] ?? ''; $row['category'] = $cols[3] ?? $defaultCat; }
                elseif ($format === '5col') { $row['answer_hu'] = $cols[1] ?? ''; $row['answer_en'] = $cols[2] ?? ''; $row['category'] = $cols[3] ?? $defaultCat; $row['who'] = in_array($cols[4] ?? '', ['Maria','Larry','All']) ? $cols[4] : 'All'; }
                $preview[] = $row;
            }
        }
    }

    if ($action === 'import') {
        $rows = json_decode($_POST['rows_json'], true);
        $imported = 0; $skipped = 0;
        if ($rows) {
            $stmt = $conn->prepare("INSERT INTO hungarian_prep (question_hu, answer_hu, answer_en, category, `who`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer_hu=VALUES(answer_hu), answer_en=VALUES(answer_en), category=VALUES(category), `who`=VALUES(`who`)");
            foreach ($rows as $r) {
                $q = trim($r['question_hu'] ?? ''); if (!$q) { $skipped++; continue; }
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
        $tab = 'phrases';
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $conn->query("DELETE FROM hungarian_prep WHERE id = " . (int)$_POST['id']);
        $message = "Deleted phrase #" . (int)$_POST['id'] . ".";
        $tab = 'phrases';
    }

    if ($action === 'delete_bulk') {
        $ids = json_decode($_POST['delete_ids'] ?? '[]', true);
        if ($ids) {
            $inList = implode(',', array_map('intval', $ids));
            $conn->query("DELETE FROM hungarian_prep WHERE id IN ($inList)");
            $message = "Deleted " . $conn->affected_rows . " phrases.";
        }
        $tab = 'phrases';
    }

    if ($action === 'update_all' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE hungarian_prep SET question_hu=?, answer_hu=?, answer_en=?, category=?, `who`=? WHERE id=?");
        $q = trim($_POST['question_hu'] ?? '');
        $ah = trim($_POST['answer_hu'] ?? '') ?: null;
        $ae = trim($_POST['answer_en'] ?? '');
        $cat = trim($_POST['category'] ?? 'General');
        $pw = in_array(trim($_POST['phrase_who'] ?? ''), ['All','Maria','Larry']) ? trim($_POST['phrase_who']) : 'All';
        $stmt->bind_param('sssssi', $q, $ah, $ae, $cat, $pw, $id);
        $stmt->execute(); $stmt->close();
        $message = "Updated phrase #$id.";
        $tab = 'phrases';
    }

    // Bio actions
    if ($action === 'bio_add') {
        $sn = in_array(trim($_POST['subject_name'] ?? ''), ['Maria','Larry','Tev','Hannah']) ? trim($_POST['subject_name']) : 'Maria';
        $fl = trim($_POST['fact_label_hu'] ?? '');
        $fv = trim($_POST['fact_value_hu'] ?? '');
        if ($fl && $fv) {
            $stmt = $conn->prepare("INSERT INTO user_bios (subject_name, fact_label_hu, fact_value_hu) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $sn, $fl, $fv);
            $stmt->execute(); $stmt->close();
            $message = "Added bio fact for $sn.";
        } else { $message = "All bio fields are required."; }
        $tab = 'users';
    }

    if ($action === 'bio_update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sn = in_array(trim($_POST['subject_name'] ?? ''), ['Maria','Larry','Tev','Hannah']) ? trim($_POST['subject_name']) : 'Maria';
        $fl = trim($_POST['fact_label_hu'] ?? '');
        $fv = trim($_POST['fact_value_hu'] ?? '');
        if ($fl && $fv) {
            $stmt = $conn->prepare("UPDATE user_bios SET subject_name=?, fact_label_hu=?, fact_value_hu=? WHERE id=?");
            $stmt->bind_param('sssi', $sn, $fl, $fv, $id);
            $stmt->execute(); $stmt->close();
            $message = "Updated bio #$id.";
        }
        $tab = 'users';
    }

    if ($action === 'bio_delete' && isset($_POST['id'])) {
        $conn->query("DELETE FROM user_bios WHERE id = " . (int)$_POST['id']);
        $message = "Deleted bio #" . (int)$_POST['id'] . ".";
        $tab = 'users';
    }

    if ($action === 'bio_delete_bulk') {
        $ids = json_decode($_POST['delete_ids'] ?? '[]', true);
        if ($ids) {
            $inList = implode(',', array_map('intval', $ids));
            $conn->query("DELETE FROM user_bios WHERE id IN ($inList)");
            $message = "Deleted " . $conn->affected_rows . " bio facts.";
        }
        $tab = 'users';
    }

    // Grammar actions
    if ($action === 'grammar_add') {
        $stmt = $conn->prepare("INSERT INTO grammar_patterns (pattern, suffix_words, explanation, part_of_speech, tags) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE suffix_words=VALUES(suffix_words), explanation=VALUES(explanation)");
        $p = trim($_POST['pattern'] ?? '');
        $sw = trim($_POST['suffix_words'] ?? '');
        $ex = trim($_POST['explanation'] ?? '');
        $pos = trim($_POST['part_of_speech'] ?? '');
        $tg = trim($_POST['tags'] ?? '');
        $stmt->bind_param('sssss', $p, $sw, $ex, $pos, $tg);
        $stmt->execute(); $stmt->close();
        $message = "Added grammar pattern.";
        $tab = 'grammar';
    }

    if ($action === 'grammar_update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE grammar_patterns SET pattern=?, suffix_words=?, explanation=?, part_of_speech=?, tags=? WHERE id=?");
        $p = trim($_POST['pattern'] ?? '');
        $sw = trim($_POST['suffix_words'] ?? '');
        $ex = trim($_POST['explanation'] ?? '');
        $pos = trim($_POST['part_of_speech'] ?? '');
        $tg = trim($_POST['tags'] ?? '');
        $stmt->bind_param('sssssi', $p, $sw, $ex, $pos, $tg, $id);
        $stmt->execute(); $stmt->close();
        $message = "Updated grammar pattern #$id.";
        $tab = 'grammar';
    }

    if ($action === 'grammar_delete' && isset($_POST['id'])) {
        $conn->query("DELETE FROM grammar_patterns WHERE id = " . (int)$_POST['id']);
        $message = "Deleted grammar pattern.";
        $tab = 'grammar';
    }

    // Resource actions
    if ($action === 'resource_add') {
        $stmt = $conn->prepare("INSERT INTO learning_resources (category, name, url, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
        $rc = trim($_POST['res_category'] ?? '');
        $rn = trim($_POST['res_name'] ?? '');
        $ru = trim($_POST['res_url'] ?? '');
        $ri = trim($_POST['res_icon'] ?? '') ?: '🔗';
        $rs = (int)($_POST['res_sort'] ?? 0);
        $stmt->bind_param('ssssi', $rc, $rn, $ru, $ri, $rs);
        $stmt->execute(); $stmt->close();
        $message = "Added resource.";
        $tab = 'resources';
    }

    if ($action === 'resource_update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE learning_resources SET category=?, name=?, url=?, icon=?, sort_order=? WHERE id=?");
        $rc = trim($_POST['res_category'] ?? '');
        $rn = trim($_POST['res_name'] ?? '');
        $ru = trim($_POST['res_url'] ?? '');
        $ri = trim($_POST['res_icon'] ?? '') ?: '🔗';
        $rs = (int)($_POST['res_sort'] ?? 0);
        $stmt->bind_param('ssssii', $rc, $rn, $ru, $ri, $rs, $id);
        $stmt->execute(); $stmt->close();
        $message = "Updated resource #$id.";
        $tab = 'resources';
    }

    if ($action === 'resource_delete' && isset($_POST['id'])) {
        $conn->query("DELETE FROM learning_resources WHERE id = " . (int)$_POST['id']);
        $message = "Deleted resource.";
        $tab = 'resources';
    }

    // Progress actions
    if ($action === 'reset_history') {
        $resetWho = trim($_POST['reset_who'] ?? '');
        if ($resetWho === '__all__') {
            $conn->query("DELETE FROM study_history");
            $message = "Cleared ALL study history.";
        } elseif ($resetWho) {
            $rw = $conn->real_escape_string($resetWho);
            $conn->query("DELETE FROM study_history WHERE who='$rw'");
            $message = "Cleared study history for $resetWho.";
        }
        $tab = 'progress';
    }

    // Connections actions
    if ($action === 'conn_add') {
        $cn = trim($_POST['conn_name'] ?? '');
        $code = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        if ($cn) {
            $stmt = $conn->prepare("INSERT INTO app_connections (name, invite_code, status) VALUES (?, ?, 'invited') ON DUPLICATE KEY UPDATE invite_code=VALUES(invite_code)");
            $stmt->bind_param('ss', $cn, $code);
            $stmt->execute(); $stmt->close();
            $message = "Invited $cn (code: $code).";
        }
        $tab = 'connections';
    }

    if ($action === 'conn_update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE app_connections SET strictness=?, speed=?, mode_pref=?, status=? WHERE id=?");
        $cs = (int)($_POST['conn_strictness'] ?? 2);
        $csp = in_array($_POST['conn_speed'] ?? '', ['slow','normal','fast']) ? $_POST['conn_speed'] : 'normal';
        $cm = in_array($_POST['conn_mode'] ?? '', ['pronunciation','interview','both']) ? $_POST['conn_mode'] : 'both';
        $cst = in_array($_POST['conn_status'] ?? '', ['active','invited','disabled']) ? $_POST['conn_status'] : 'active';
        $stmt->bind_param('isssi', $cs, $csp, $cm, $cst, $id);
        $stmt->execute(); $stmt->close();
        $message = "Updated connection.";
        $tab = 'connections';
    }

    if ($action === 'conn_delete' && isset($_POST['id'])) {
        $conn->query("DELETE FROM app_connections WHERE id = " . (int)$_POST['id']);
        $message = "Deleted connection.";
        $tab = 'connections';
    }

    // Migrate HU action
    if ($action === 'migrate_hu') {
        $ids = json_decode($_POST['migrate_ids'] ?? '[]', true);
        if ($ids) {
            $migrated = 0;
            $stmt = $conn->prepare("UPDATE hungarian_prep SET answer_hu = answer_en, answer_en = '' WHERE id = ? AND (answer_hu IS NULL OR answer_hu = '')");
            foreach ($ids as $mid) { $stmt->bind_param('i', $mid); $stmt->execute(); if ($stmt->affected_rows > 0) $migrated++; }
            $stmt->close();
            $message = "Migrated $migrated rows: moved answer_en -> answer_hu.";
        }
        $tab = 'phrases';
    }
}

// ─── AJAX Endpoints ───
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $ajaxAction = $_GET['action'] ?? '';

    if (in_array($ajaxAction, ['ai_answer', 'ai_question'])) {
        $question_hu = trim($_POST['question_hu'] ?? '');
        $answer_en = trim($_POST['answer_en'] ?? '');
        $answer_hu = trim($_POST['answer_hu'] ?? '');
        $apiKey = $env['GEMINI_KEY'] ?? '';
        if (!$apiKey) { echo json_encode(['error' => 'No Gemini API key']); exit; }
        if ($ajaxAction === 'ai_answer') {
            $prompt = "You are a Hungarian language expert.\nThe interviewer asks: \"$question_hu\"\n" . ($answer_en ? "English meaning: \"$answer_en\"\n" : "") . "Generate a formal Hungarian answer (1-2 sentences). Reply with ONLY the Hungarian text.";
        } else {
            $context = $answer_hu ?: $answer_en;
            $prompt = "You are a Hungarian language expert.\nThe answer is: \"$context\"\n" . ($answer_en ? "English: \"$answer_en\"\n" : "") . "Generate the formal Hungarian interview question. Reply with ONLY the question text.";
        }
        $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 256]]);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15]);
        $response = curl_exec($ch); curl_close($ch);
        $data = json_decode($response, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        echo json_encode(['result' => trim($text, '"\'')]);
        exit;
    }

    if ($ajaxAction === 'ai_group') {
        $topic = trim($_POST['topic'] ?? '');
        if (!$topic) { echo json_encode(['error' => 'No topic']); exit; }
        $apiKey = $env['GEMINI_KEY'] ?? '';
        if (!$apiKey) { echo json_encode(['error' => 'No API key']); exit; }
        $prompt = "You are a Hungarian language expert creating study materials for a naturalization interview.\n\nTopic: \"$topic\"\n\nGenerate 8-15 related Hungarian phrases. Reply ONLY with JSON array: [{\"question_hu\":\"...\",\"answer_hu\":\"...\",\"answer_en\":\"...\"}]";
        $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 2048]]);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 25]);
        $response = curl_exec($ch); curl_close($ch);
        $data = json_decode($response, true);
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/i', '', trim($content));
        $items = json_decode(trim($content), true);
        echo json_encode(is_array($items) ? ['items' => $items] : ['error' => 'Invalid AI response']);
        exit;
    }

    if ($ajaxAction === 'progress_data') {
        $who = $conn->real_escape_string($_GET['who'] ?? 'All');
        $whoClause = $who !== 'All' ? " WHERE who='$who'" : '';
        $whoAnd = $who !== 'All' ? " AND who='$who'" : '';

        $totalPhrases = (int)($conn->query("SELECT COUNT(*) AS c FROM hungarian_prep")->fetch_assoc()['c'] ?? 0);
        $totalGrammar = (int)($conn->query("SELECT COUNT(*) AS c FROM grammar_patterns")->fetch_assoc()['c'] ?? 0);
        $studied = (int)($conn->query("SELECT COUNT(*) AS c FROM study_history$whoClause")->fetch_assoc()['c'] ?? 0);
        $mastered = (int)($conn->query("SELECT COUNT(*) AS c FROM study_history WHERE pass_count >= 3$whoAnd")->fetch_assoc()['c'] ?? 0);
        $due = (int)($conn->query("SELECT COUNT(*) AS c FROM study_history WHERE next_review <= NOW()$whoAnd")->fetch_assoc()['c'] ?? 0);
        $totalPass = (int)($conn->query("SELECT SUM(pass_count) AS c FROM study_history$whoClause")->fetch_assoc()['c'] ?? 0);
        $totalFail = (int)($conn->query("SELECT SUM(fail_count) AS c FROM study_history$whoClause")->fetch_assoc()['c'] ?? 0);
        $passRate = ($totalPass + $totalFail) > 0 ? round($totalPass / ($totalPass + $totalFail) * 100) : 0;

        $recent = [];
        $r = $conn->query("SELECT phrase, who, item_type, pass_count, fail_count, last_seen, next_review FROM study_history$whoClause ORDER BY last_seen DESC LIMIT 50");
        if ($r) { while ($row = $r->fetch_assoc()) $recent[] = $row; }

        $daily = [];
        $r = $conn->query("SELECT DATE(last_seen) AS day, COUNT(*) AS cnt, SUM(CASE WHEN pass_count > 0 THEN 1 ELSE 0 END) AS passed FROM study_history WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY)$whoAnd GROUP BY DATE(last_seen) ORDER BY day");
        if ($r) { while ($row = $r->fetch_assoc()) $daily[] = $row; }

        $weak = [];
        $r = $conn->query("SELECT phrase, fail_count, pass_count FROM study_history WHERE fail_count > 0$whoAnd ORDER BY fail_count DESC LIMIT 10");
        if ($r) { while ($row = $r->fetch_assoc()) $weak[] = $row; }

        $perUser = [];
        $r = $conn->query("SELECT who, COUNT(*) AS items, SUM(pass_count) AS passes, SUM(fail_count) AS fails, MAX(last_seen) AS last_active FROM study_history GROUP BY who ORDER BY who");
        if ($r) { while ($row = $r->fetch_assoc()) $perUser[] = $row; }

        echo json_encode(['totalPhrases'=>$totalPhrases,'totalGrammar'=>$totalGrammar,'studied'=>$studied,'mastered'=>$mastered,'due'=>$due,'passRate'=>$passRate,'totalPass'=>$totalPass,'totalFail'=>$totalFail,'recent'=>$recent,'daily'=>$daily,'weak'=>$weak,'perUser'=>$perUser]);
        exit;
    }

    if ($ajaxAction === 'activity_log') {
        $who = $conn->real_escape_string($_GET['who'] ?? '');
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));
        $whoClause = $who ? " WHERE who='$who'" : '';
        $rows = [];
        $r = $conn->query("SELECT phrase, who, item_type, pass_count, fail_count, last_seen, next_review FROM study_history$whoClause ORDER BY last_seen DESC LIMIT $limit");
        if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
        $logs = [];
        $r2 = $conn->query("SELECT * FROM study_log" . ($who ? " WHERE who='$who'" : '') . " ORDER BY completed_at DESC LIMIT $limit");
        if ($r2) { while ($row = $r2->fetch_assoc()) $logs[] = $row; }
        echo json_encode(['history'=>$rows, 'sessions'=>$logs]);
        exit;
    }

    if ($ajaxAction === 'usage_stats') {
        $daily = [];
        $r = $conn->query("SELECT DATE(created_at) AS day, endpoint, COUNT(*) AS calls, SUM(tokens_in) AS tin, SUM(tokens_out) AS tout, SUM(cost_usd) AS cost FROM api_log GROUP BY DATE(created_at), endpoint ORDER BY day DESC LIMIT 90");
        if ($r) { while ($row = $r->fetch_assoc()) $daily[] = $row; }
        $totals = [];
        $r = $conn->query("SELECT endpoint, COUNT(*) AS calls, SUM(tokens_in) AS tin, SUM(tokens_out) AS tout, SUM(cost_usd) AS cost FROM api_log GROUP BY endpoint");
        if ($r) { while ($row = $r->fetch_assoc()) $totals[] = $row; }
        $recent = [];
        $r = $conn->query("SELECT * FROM api_log ORDER BY created_at DESC LIMIT 50");
        if ($r) { while ($row = $r->fetch_assoc()) $recent[] = $row; }
        echo json_encode(['daily'=>$daily, 'totals'=>$totals, 'recent'=>$recent]);
        exit;
    }

    echo json_encode(['error' => 'unknown action']);
    exit;
}

// ─── Data Queries ───
$totalCount = (int)($conn->query("SELECT COUNT(*) AS c FROM hungarian_prep")->fetch_assoc()['c'] ?? 0);
$grammarCount = (int)($conn->query("SELECT COUNT(*) AS c FROM grammar_patterns")->fetch_assoc()['c'] ?? 0);
$bioCount = (int)($conn->query("SELECT COUNT(*) AS c FROM user_bios")->fetch_assoc()['c'] ?? 0);
$resourceCount = (int)($conn->query("SELECT COUNT(*) AS c FROM learning_resources")->fetch_assoc()['c'] ?? 0);
$studyCount = (int)($conn->query("SELECT COUNT(*) AS c FROM study_history")->fetch_assoc()['c'] ?? 0);

$tabs = [
    'progress' => ['label' => 'Progress', 'count' => $studyCount],
    'users' => ['label' => 'Users', 'count' => $bioCount],
    'phrases' => ['label' => 'Phrases', 'count' => $totalCount],
    'grammar' => ['label' => 'Grammar', 'count' => $grammarCount],
    'resources' => ['label' => 'Resources', 'count' => $resourceCount],
    'connections' => ['label' => 'Connections', 'count' => null],
    'activity' => ['label' => 'Activity', 'count' => null],
    'usage' => ['label' => 'Usage & Costs', 'count' => null],
    'settings' => ['label' => 'Settings', 'count' => null],
];
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
.tab-btn { transition: all 0.2s; }
.tab-btn.active { background: rgba(99,102,241,0.15); color: #818cf8; border-color: #818cf8; }
.tab-btn:not(.active):hover { background: rgba(255,255,255,0.03); }
.card { background: #111a2e; border: 1px solid rgba(255,255,255,0.05); border-radius: 1rem; padding: 1.5rem; }
.stat-card { background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.05)); border: 1px solid rgba(99,102,241,0.15); border-radius: 0.75rem; padding: 1rem; }
.input { width: 100%; background: #0c1222; border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: white; outline: none; }
.input:focus { border-color: #6366f1; }
.btn { padding: 0.4rem 1rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; transition: all 0.2s; cursor: pointer; border: none; }
.btn-indigo { background: #4f46e5; color: white; } .btn-indigo:hover { background: #4338ca; }
.btn-green { background: #16a34a; color: white; } .btn-green:hover { background: #15803d; }
.btn-red { background: #dc2626; color: white; } .btn-red:hover { background: #b91c1c; }
.btn-slate { background: #334155; color: #e2e8f0; } .btn-slate:hover { background: #475569; }
.btn-violet { background: #7c3aed; color: white; } .btn-violet:hover { background: #6d28d9; }
table { width: 100%; font-size: 0.75rem; }
th { text-transform: uppercase; letter-spacing: 0.05em; font-size: 10px; color: #64748b; padding: 0.5rem; text-align: left; }
td { padding: 0.5rem; border-top: 1px solid rgba(255,255,255,0.03); }
.bar { height: 6px; border-radius: 3px; background: #1e293b; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s; }
.chart-bar { min-width: 8px; border-radius: 3px 3px 0 0; transition: height 0.3s; }
</style>
</head>
<body class="min-h-screen">
<div class="max-w-6xl mx-auto p-4 md:p-6 space-y-4">

<!-- Header -->
<div class="flex items-center justify-between flex-wrap gap-2">
    <div class="flex items-center gap-3">
        <span class="text-xl">&#x1f1ed;&#x1f1fa;</span>
        <div>
            <h1 class="text-lg font-bold text-white">HUG COACH Admin</h1>
            <p class="text-[10px] text-slate-500"><?php echo $totalCount; ?> phrases &middot; <?php echo $grammarCount; ?> grammar &middot; <?php echo $studyCount; ?> history records</p>
        </div>
    </div>
    <div class="flex items-center gap-3 text-xs">
        <a href="index.php" class="text-indigo-400 hover:text-indigo-300 font-semibold">&larr; App</a>
        <a href="?logout=1" class="text-slate-500 hover:text-red-400 font-semibold">Logout</a>
    </div>
</div>

<?php if ($message): ?>
<div class="bg-green-500/10 border border-green-500/20 rounded-xl px-4 py-3 text-sm text-green-400"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="flex flex-wrap gap-1.5">
<?php foreach ($tabs as $key => $t): ?>
    <a href="?tab=<?php echo $key; ?>" class="tab-btn text-xs font-semibold px-3 py-1.5 rounded-lg border border-transparent text-slate-400 <?php echo $tab === $key ? 'active' : ''; ?>">
        <?php echo $t['label']; ?><?php if ($t['count'] !== null): ?> <span class="text-[10px] opacity-60">(<?php echo $t['count']; ?>)</span><?php endif; ?>
    </a>
<?php endforeach; ?>
</div>

<!-- ═══════════════ PROGRESS TAB ═══════════════ -->
<?php if ($tab === 'progress'): ?>
<div id="progressTab">
    <div class="flex gap-2 mb-4">
        <select id="progressWho" class="input" style="width:auto" onchange="loadProgress()">
            <option value="All">All Users</option>
            <option value="Maria">Maria</option>
            <option value="Larry">Larry</option>
        </select>
        <form method="POST" onsubmit="return confirm('This will permanently delete study history. Continue?')" class="ml-auto flex gap-2">
            <input type="hidden" name="action" value="reset_history">
            <select name="reset_who" class="input" style="width:auto;font-size:11px">
                <option value="">-- Reset who? --</option>
                <option value="Maria">Maria</option>
                <option value="Larry">Larry</option>
                <option value="__all__">ALL USERS</option>
            </select>
            <button type="submit" class="btn btn-red">Reset History</button>
        </form>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="stat-card"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Items Studied</div><div class="text-2xl font-bold text-white" id="sSt">-</div></div>
        <div class="stat-card"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Mastered (3+)</div><div class="text-2xl font-bold text-green-400" id="sMa">-</div></div>
        <div class="stat-card"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Due for Review</div><div class="text-2xl font-bold text-yellow-400" id="sDu">-</div></div>
        <div class="stat-card"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Pass Rate</div><div class="text-2xl font-bold text-indigo-400" id="sPR">-</div></div>
    </div>

    <!-- Progress bar -->
    <div class="card mb-4">
        <div class="flex justify-between text-xs text-slate-400 mb-1"><span>Overall Coverage</span><span id="covPct">-</span></div>
        <div class="bar"><div class="bar-fill bg-indigo-500" id="covBar" style="width:0%"></div></div>
    </div>

    <!-- Per-user breakdown -->
    <div class="card mb-4" id="perUserCard" style="display:none">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Per-User Breakdown</h3>
        <table><thead><tr><th>User</th><th>Items</th><th>Pass</th><th>Fail</th><th>Rate</th><th>Last Active</th></tr></thead>
        <tbody id="perUserBody"></tbody></table>
    </div>

    <!-- 30-day chart -->
    <div class="card mb-4">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Last 30 Days Activity</h3>
        <div class="flex items-end gap-0.5 h-32" id="dailyChart"></div>
        <div class="flex justify-between text-[10px] text-slate-600 mt-1" id="dailyLabels"></div>
    </div>

    <!-- Weak items -->
    <div class="card mb-4" id="weakCard" style="display:none">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Most Struggled Items</h3>
        <table><thead><tr><th>Phrase</th><th>Fails</th><th>Passes</th></tr></thead>
        <tbody id="weakBody"></tbody></table>
    </div>

    <!-- Recent attempts -->
    <div class="card">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Recent Study History <span class="text-slate-500 font-normal">(last 50)</span></h3>
        <div class="overflow-x-auto">
            <table><thead><tr><th>Phrase</th><th>Who</th><th>Type</th><th>P</th><th>F</th><th>Last Seen</th><th>Next Review</th></tr></thead>
            <tbody id="recentBody"></tbody></table>
        </div>
    </div>
</div>

<!-- ═══════════════ USERS TAB ═══════════════ -->
<?php elseif ($tab === 'users'):
    $bios = $conn->query("SELECT * FROM user_bios ORDER BY subject_name, fact_label_hu");
    $people = ['Maria','Larry','Tev','Hannah'];
?>
<div class="card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">User Profiles <span class="text-slate-500 font-normal">(<?php echo $bioCount; ?>)</span></h2>
        <button onclick="openBioAdd()" class="btn btn-green">+ Add Fact</button>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <input type="text" id="bioSearch" placeholder="Search bios..." class="input" style="max-width:300px" oninput="filterBios()">
        <select id="bioWhoFilter" class="input" style="width:auto" onchange="filterBios()">
            <option value="">All People</option>
            <?php foreach ($people as $p): ?><option value="<?php echo $p; ?>"><?php echo $p; ?></option><?php endforeach; ?>
        </select>
    </div>

    <div id="bioBulkBar" class="hidden mb-4 flex items-center gap-3 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
        <span id="bioBulkCount" class="text-xs text-red-400 font-semibold"></span>
        <button onclick="bioBulkDelete()" class="btn btn-red">Delete Selected</button>
        <button onclick="bioClearSelection()" class="text-xs text-slate-400 hover:text-white cursor-pointer">Clear</button>
    </div>

    <div class="overflow-x-auto">
        <table>
            <thead><tr>
                <th class="w-6"><input type="checkbox" id="bioSelectAll" onchange="document.querySelectorAll('.bio-cb').forEach(function(c){c.checked=this.checked}.bind(this));updateBioBulkBar()" class="accent-red-500"></th>
                <th>#</th><th>Person</th><th>Label (EN)</th><th>Value (HU)</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if ($bios): while ($b = $bios->fetch_assoc()): ?>
            <tr class="bio-row hover:bg-white/[0.02]" data-id="<?php echo $b['id']; ?>" data-sn="<?php echo htmlspecialchars($b['subject_name'], ENT_QUOTES); ?>" data-fl="<?php echo htmlspecialchars($b['fact_label_hu'], ENT_QUOTES); ?>" data-fv="<?php echo htmlspecialchars($b['fact_value_hu'], ENT_QUOTES); ?>">
                <td><input type="checkbox" class="bio-cb accent-red-500" value="<?php echo $b['id']; ?>" onchange="updateBioBulkBar()"></td>
                <td class="text-slate-600"><?php echo $b['id']; ?></td>
                <td class="text-indigo-400 font-medium"><?php echo htmlspecialchars($b['subject_name']); ?></td>
                <td class="text-slate-300"><?php echo htmlspecialchars($b['fact_label_hu']); ?></td>
                <td class="text-green-400"><?php echo htmlspecialchars($b['fact_value_hu']); ?></td>
                <td class="whitespace-nowrap space-x-1">
                    <button onclick="editBio(<?php echo $b['id']; ?>)" class="btn btn-indigo">Edit</button>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="bio_delete">
                        <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="btn btn-red" style="opacity:0.6">Del</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ PHRASES TAB ═══════════════ -->
<?php elseif ($tab === 'phrases'):
    $catFilter = $conn->real_escape_string(trim($_GET['cat'] ?? ''));
    $sql = "SELECT * FROM hungarian_prep";
    if ($catFilter) $sql .= " WHERE category = '$catFilter'";
    $sql .= " ORDER BY category, question_hu";
    $phrases = $conn->query($sql);
    $cats = $conn->query("SELECT DISTINCT category FROM hungarian_prep ORDER BY category");
    $catList = []; if ($cats) { while ($c = $cats->fetch_assoc()) $catList[] = $c['category']; }
?>

<!-- Import Section -->
<div class="card mb-4">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-3">Import Phrases</h2>
    <form method="POST" action="?tab=phrases">
        <input type="hidden" name="action" value="preview">
        <div class="flex flex-wrap gap-3 mb-3 text-xs text-slate-400">
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="format" value="2col_hu" checked class="accent-indigo-500"> 2-col: Q HU | A HU</label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="format" value="2col" class="accent-indigo-500"> 2-col: HU | EN</label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="format" value="3col" class="accent-indigo-500"> 3-col: HU | A HU | EN</label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="format" value="4col" class="accent-indigo-500"> 4-col: + Category</label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="format" value="5col" class="accent-indigo-500"> 5-col: + Who</label>
        </div>
        <div class="flex gap-3 mb-3">
            <div class="flex-1"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Default Category</label>
                <input type="text" name="default_cat" value="General" class="input mt-1"></div>
        </div>
        <textarea name="tsv" rows="5" placeholder="Paste TSV data here..." class="input font-mono resize-y mb-3"><?php echo htmlspecialchars($_POST['tsv'] ?? ''); ?></textarea>
        <button type="submit" class="btn btn-indigo">Preview Import</button>
    </form>

    <?php if (!empty($preview)): ?>
    <div class="mt-4 border-t border-white/5 pt-4">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Preview (<?php echo count($preview); ?> rows)</h3>
        <div class="overflow-x-auto">
            <table><thead><tr><th>Question (HU)</th><th>Answer (HU)</th><th>English</th><th>Category</th><th>Who</th></tr></thead>
            <tbody>
            <?php foreach ($preview as $p): ?>
            <tr><td class="text-white"><?php echo htmlspecialchars($p['question_hu']); ?></td><td class="text-green-400"><?php echo htmlspecialchars($p['answer_hu']); ?></td><td class="text-slate-400"><?php echo htmlspecialchars($p['answer_en']); ?></td><td class="text-slate-500"><?php echo htmlspecialchars($p['category']); ?></td><td class="text-slate-500"><?php echo htmlspecialchars($p['who']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <form method="POST" action="?tab=phrases" class="mt-3">
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="rows_json" value="<?php echo htmlspecialchars(json_encode($preview)); ?>">
            <button type="submit" class="btn btn-green">Confirm Import (<?php echo count($preview); ?>)</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- AI Group Generator -->
<div class="card mb-4" style="border-color: rgba(139,92,246,0.1)">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-2">AI Group Generator</h2>
    <p class="text-xs text-slate-500 mb-3">Generate verb conjugations, vocabulary groups, etc.</p>
    <div class="flex gap-2 mb-3">
        <input type="text" id="groupTopic" placeholder='e.g. "family vocabulary"' class="input flex-1">
        <input type="text" id="groupCategory" placeholder="Category" class="input" style="width:140px">
        <button onclick="generateGroup()" id="groupGenBtn" class="btn btn-violet">Generate</button>
    </div>
    <div id="groupStatus" class="text-xs text-slate-500 hidden mb-3"></div>
    <div id="groupPreview" class="hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Preview <span id="groupCount" class="text-violet-400"></span></h3>
            <div class="flex gap-2">
                <button onclick="saveGroup()" class="btn btn-green">Save All</button>
                <button onclick="clearGroup()" class="btn btn-slate">Clear</button>
            </div>
        </div>
        <table><thead><tr>
            <th class="w-6"><input type="checkbox" id="groupSelectAll" checked onchange="document.querySelectorAll('.group-cb').forEach(function(c){c.checked=this.checked}.bind(this))" class="accent-violet-500"></th>
            <th>Question (HU)</th><th>Answer (HU)</th><th>English</th>
        </tr></thead><tbody id="groupBody"></tbody></table>
    </div>
</div>

<!-- Manage Phrases -->
<div class="card">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Manage Phrases</h2>
    <div class="flex flex-wrap gap-2 mb-4">
        <input type="text" id="liveSearch" placeholder="Search..." class="input" style="max-width:300px" oninput="clearTimeout(window._ft);window._ft=setTimeout(filterTable,150)">
        <select id="whoSelect" class="input" style="width:auto" onchange="filterTable()">
            <option value="">All People</option><option value="All">General</option><option value="Maria">Maria</option><option value="Larry">Larry</option>
        </select>
        <select id="catSelect" class="input" style="width:auto" onchange="filterTable()">
            <option value="">All Categories</option>
            <?php foreach ($catList as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
        </select>
    </div>

    <div id="bulkBar" class="hidden mb-4 flex items-center gap-3 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
        <span id="bulkCount" class="text-xs text-red-400 font-semibold"></span>
        <button onclick="bulkDelete()" class="btn btn-red">Delete Selected</button>
        <button onclick="clearSelection()" class="text-xs text-slate-400 hover:text-white cursor-pointer">Clear</button>
    </div>

    <div class="overflow-x-auto">
        <table>
            <thead><tr>
                <th class="w-6"><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.row-cb').forEach(function(c){c.checked=this.checked}.bind(this));updateBulkBar()" class="accent-red-500"></th>
                <th>#</th><th>Question (HU)</th><th>Answer (HU)</th><th>English</th><th>Category</th><th>Who</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if ($phrases): while ($p = $phrases->fetch_assoc()): ?>
            <tr class="phrase-row hover:bg-white/[0.02]" id="row-<?php echo $p['id']; ?>"
                data-id="<?php echo $p['id']; ?>"
                data-q="<?php echo htmlspecialchars($p['question_hu'], ENT_QUOTES); ?>"
                data-ah="<?php echo htmlspecialchars($p['answer_hu'] ?? '', ENT_QUOTES); ?>"
                data-ae="<?php echo htmlspecialchars($p['answer_en'] ?? '', ENT_QUOTES); ?>"
                data-cat="<?php echo htmlspecialchars($p['category'], ENT_QUOTES); ?>"
                data-who="<?php echo htmlspecialchars($p['who'] ?? 'All', ENT_QUOTES); ?>">
                <td><input type="checkbox" class="row-cb accent-red-500" value="<?php echo $p['id']; ?>" onchange="updateBulkBar()"></td>
                <td class="text-slate-600"><?php echo $p['id']; ?></td>
                <td class="text-white font-medium"><?php echo htmlspecialchars($p['question_hu']); ?></td>
                <td class="<?php echo $p['answer_hu'] ? 'text-green-400' : 'text-yellow-500/50 italic'; ?>"><?php echo $p['answer_hu'] ? htmlspecialchars($p['answer_hu']) : '(missing)'; ?></td>
                <td class="text-slate-400"><?php echo htmlspecialchars($p['answer_en'] ?? ''); ?></td>
                <td><a href="?tab=phrases&cat=<?php echo urlencode($p['category']); ?>" class="text-slate-500 hover:text-indigo-400 underline decoration-dotted"><?php echo htmlspecialchars($p['category']); ?></a></td>
                <td class="text-slate-500"><?php echo htmlspecialchars($p['who'] ?? 'All'); ?></td>
                <td class="whitespace-nowrap space-x-1">
                    <button onclick="editRow(<?php echo $p['id']; ?>)" class="btn btn-indigo">Edit</button>
                    <button onclick="aiGenerate(<?php echo $p['id']; ?>,'ai_answer',event)" class="btn btn-violet" title="AI Answer">AI</button>
                    <form method="POST" action="?tab=phrases" class="inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <button type="submit" class="btn btn-red" style="opacity:0.6">Del</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ GRAMMAR TAB ═══════════════ -->
<?php elseif ($tab === 'grammar'):
    $gPatterns = $conn->query("SELECT * FROM grammar_patterns ORDER BY pattern");
    $drillGroups = $conn->query("SELECT * FROM drill_groups ORDER BY name");
?>
<div class="card mb-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Grammar Patterns <span class="text-slate-500 font-normal">(<?php echo $grammarCount; ?>)</span></h2>
        <button onclick="openGrammarAdd()" class="btn btn-green">+ Add Pattern</button>
    </div>
    <input type="text" id="grammarSearch" placeholder="Search patterns..." class="input mb-4" style="max-width:300px" oninput="filterGrammar()">
    <div class="overflow-x-auto">
        <table>
            <thead><tr><th>#</th><th>Pattern</th><th>Suffix/Words</th><th>Explanation</th><th>POS</th><th>Tags</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($gPatterns): while ($g = $gPatterns->fetch_assoc()): ?>
            <tr class="grammar-row hover:bg-white/[0.02]"
                data-id="<?php echo $g['id']; ?>"
                data-pattern="<?php echo htmlspecialchars($g['pattern'], ENT_QUOTES); ?>"
                data-sw="<?php echo htmlspecialchars($g['suffix_words'] ?? '', ENT_QUOTES); ?>"
                data-ex="<?php echo htmlspecialchars($g['explanation'] ?? '', ENT_QUOTES); ?>"
                data-pos="<?php echo htmlspecialchars($g['part_of_speech'] ?? '', ENT_QUOTES); ?>"
                data-tags="<?php echo htmlspecialchars($g['tags'] ?? '', ENT_QUOTES); ?>">
                <td class="text-slate-600"><?php echo $g['id']; ?></td>
                <td class="text-white font-medium"><?php echo htmlspecialchars($g['pattern']); ?></td>
                <td class="text-green-400 text-[11px]"><?php echo htmlspecialchars(mb_substr($g['suffix_words'] ?? '', 0, 60)); ?></td>
                <td class="text-slate-400 text-[11px]" style="max-width:200px"><?php echo htmlspecialchars(mb_substr($g['explanation'] ?? '', 0, 80)); ?></td>
                <td class="text-indigo-400"><?php echo htmlspecialchars($g['part_of_speech'] ?? ''); ?></td>
                <td class="text-slate-500 text-[10px]"><?php echo htmlspecialchars(mb_substr($g['tags'] ?? '', 0, 40)); ?></td>
                <td class="whitespace-nowrap space-x-1">
                    <button onclick="editGrammar(<?php echo $g['id']; ?>)" class="btn btn-indigo">Edit</button>
                    <form method="POST" action="?tab=grammar" class="inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="grammar_delete">
                        <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                        <button type="submit" class="btn btn-red" style="opacity:0.6">Del</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($drillGroups && $drillGroups->num_rows > 0): ?>
<div class="card">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Drill Groups / Flashcard Decks</h2>
    <div class="overflow-x-auto">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Tag Match</th><th>Source</th></tr></thead>
            <tbody>
            <?php while ($dg = $drillGroups->fetch_assoc()): ?>
            <tr class="hover:bg-white/[0.02]">
                <td class="text-slate-600"><?php echo $dg['id']; ?></td>
                <td class="text-white font-medium"><?php echo htmlspecialchars($dg['name']); ?></td>
                <td class="text-slate-400 text-[11px]"><?php echo htmlspecialchars($dg['description'] ?? ''); ?></td>
                <td class="text-violet-400 text-[11px]"><?php echo htmlspecialchars($dg['tag_match'] ?? ''); ?></td>
                <td class="text-slate-500"><?php echo htmlspecialchars($dg['source']); ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════ RESOURCES TAB ═══════════════ -->
<?php elseif ($tab === 'resources'):
    $resources = $conn->query("SELECT * FROM learning_resources ORDER BY sort_order, category, name");
?>
<div class="card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Learning Resources <span class="text-slate-500 font-normal">(<?php echo $resourceCount; ?>)</span></h2>
        <button onclick="openResourceAdd()" class="btn btn-green">+ Add Resource</button>
    </div>
    <div class="overflow-x-auto">
        <table>
            <thead><tr><th>#</th><th>Icon</th><th>Category</th><th>Name</th><th>URL</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($resources): while ($r = $resources->fetch_assoc()): ?>
            <tr class="resource-row hover:bg-white/[0.02]"
                data-id="<?php echo $r['id']; ?>"
                data-cat="<?php echo htmlspecialchars($r['category'], ENT_QUOTES); ?>"
                data-name="<?php echo htmlspecialchars($r['name'], ENT_QUOTES); ?>"
                data-url="<?php echo htmlspecialchars($r['url'], ENT_QUOTES); ?>"
                data-icon="<?php echo htmlspecialchars($r['icon'], ENT_QUOTES); ?>"
                data-sort="<?php echo $r['sort_order']; ?>">
                <td class="text-slate-600"><?php echo $r['id']; ?></td>
                <td class="text-lg"><?php echo $r['icon']; ?></td>
                <td class="text-indigo-400"><?php echo htmlspecialchars($r['category']); ?></td>
                <td class="text-white font-medium"><?php echo htmlspecialchars($r['name']); ?></td>
                <td class="text-slate-400 text-[11px]" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($r['url']); ?></td>
                <td class="text-slate-500"><?php echo $r['sort_order']; ?></td>
                <td class="whitespace-nowrap space-x-1">
                    <button onclick="editResource(<?php echo $r['id']; ?>)" class="btn btn-indigo">Edit</button>
                    <form method="POST" action="?tab=resources" class="inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="resource_delete">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="btn btn-red" style="opacity:0.6">Del</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ CONNECTIONS TAB ═══════════════ -->
<?php elseif ($tab === 'connections'):
    $conns = $conn->query("SELECT * FROM app_connections ORDER BY name");
?>
<div class="card mb-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">App Connections</h2>
        <form method="POST" action="?tab=connections" class="flex gap-2">
            <input type="hidden" name="action" value="conn_add">
            <input type="text" name="conn_name" placeholder="New user name" class="input" style="width:150px">
            <button type="submit" class="btn btn-green">+ Invite</button>
        </form>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
    <?php if ($conns): while ($c = $conns->fetch_assoc()):
        $statusColor = ['active'=>'text-green-400','invited'=>'text-yellow-400','disabled'=>'text-slate-500'][$c['status']] ?? 'text-slate-500';
        $la = $conn->query("SELECT MAX(last_seen) AS ls FROM study_history WHERE who='" . $conn->real_escape_string($c['name']) . "'");
        $lastActive = $la ? ($la->fetch_assoc()['ls'] ?? null) : null;
        $itemCount = (int)($conn->query("SELECT COUNT(*) AS c FROM study_history WHERE who='" . $conn->real_escape_string($c['name']) . "'")->fetch_assoc()['c'] ?? 0);
    ?>
    <div class="bg-[#0c1222] border border-white/5 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-500/20 flex items-center justify-center text-sm font-bold text-indigo-400"><?php echo mb_substr($c['name'], 0, 1); ?></div>
                <div>
                    <div class="text-sm font-bold text-white"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="text-[10px] <?php echo $statusColor; ?> uppercase tracking-wider"><?php echo $c['status']; ?></div>
                </div>
            </div>
            <?php if ($c['invite_code']): ?><span class="text-[10px] text-slate-500 font-mono bg-black/30 px-2 py-0.5 rounded"><?php echo $c['invite_code']; ?></span><?php endif; ?>
        </div>
        <div class="grid grid-cols-2 gap-2 text-[11px] mb-3">
            <div><span class="text-slate-500">Items studied:</span> <span class="text-white"><?php echo $itemCount; ?></span></div>
            <div><span class="text-slate-500">Last active:</span> <span class="text-white"><?php echo $lastActive ? date('M j, g:ia', strtotime($lastActive)) : 'Never'; ?></span></div>
            <div><span class="text-slate-500">Strictness:</span> <span class="text-white"><?php echo $c['strictness']; ?>/5</span></div>
            <div><span class="text-slate-500">Mode:</span> <span class="text-white"><?php echo $c['mode_pref']; ?></span></div>
        </div>
        <form method="POST" action="?tab=connections" class="flex flex-wrap gap-2 items-end">
            <input type="hidden" name="action" value="conn_update">
            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
            <div><label class="text-[10px] text-slate-500">Strictness</label>
                <select name="conn_strictness" class="input" style="width:60px;font-size:11px"><?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>" <?php echo $c['strictness']==$i?'selected':''; ?>><?php echo $i; ?></option><?php endfor; ?></select></div>
            <div><label class="text-[10px] text-slate-500">Speed</label>
                <select name="conn_speed" class="input" style="width:80px;font-size:11px"><?php foreach(['slow','normal','fast'] as $s): ?><option value="<?php echo $s; ?>" <?php echo $c['speed']===$s?'selected':''; ?>><?php echo $s; ?></option><?php endforeach; ?></select></div>
            <div><label class="text-[10px] text-slate-500">Mode</label>
                <select name="conn_mode" class="input" style="width:100px;font-size:11px"><?php foreach(['pronunciation','interview','both'] as $m): ?><option value="<?php echo $m; ?>" <?php echo $c['mode_pref']===$m?'selected':''; ?>><?php echo $m; ?></option><?php endforeach; ?></select></div>
            <div><label class="text-[10px] text-slate-500">Status</label>
                <select name="conn_status" class="input" style="width:80px;font-size:11px"><?php foreach(['active','invited','disabled'] as $st): ?><option value="<?php echo $st; ?>" <?php echo $c['status']===$st?'selected':''; ?>><?php echo $st; ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="btn btn-indigo">Save</button>
        </form>
        <form method="POST" action="?tab=connections" class="mt-2" onsubmit="return confirm('Delete connection?')">
            <input type="hidden" name="action" value="conn_delete">
            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
            <button type="submit" class="text-[10px] text-slate-600 hover:text-red-400">Remove</button>
        </form>
    </div>
    <?php endwhile; endif; ?>
    </div>
</div>

<!-- ═══════════════ ACTIVITY TAB ═══════════════ -->
<?php elseif ($tab === 'activity'): ?>
<div class="card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Activity Log</h2>
        <div class="flex gap-2">
            <select id="actWho" class="input" style="width:auto" onchange="loadActivity()">
                <option value="">All Users</option><option value="Maria">Maria</option><option value="Larry">Larry</option>
            </select>
            <button onclick="exportActivity()" class="btn btn-slate">Export CSV</button>
        </div>
    </div>
    <div id="actSessions" class="mb-4"></div>
    <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Study History</h3>
    <div class="overflow-x-auto">
        <table><thead><tr><th>Phrase</th><th>Who</th><th>Type</th><th>Pass</th><th>Fail</th><th>Last Seen</th><th>Next Review</th></tr></thead>
        <tbody id="actBody"></tbody></table>
    </div>
</div>

<!-- ═══════════════ USAGE & COSTS TAB ═══════════════ -->
<?php elseif ($tab === 'usage'): ?>
<div class="card mb-4">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">API Usage & Costs</h2>
    <p class="text-xs text-slate-500 mb-4">API calls are logged to the <code class="text-indigo-400">api_log</code> table. Instrument eval.php, translate.php, etc. to populate.</p>

    <div id="usageTotals" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>

    <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Daily Breakdown</h3>
    <div class="overflow-x-auto">
        <table><thead><tr><th>Date</th><th>Endpoint</th><th>Calls</th><th>Tokens In</th><th>Tokens Out</th><th>Cost ($)</th></tr></thead>
        <tbody id="usageDaily"></tbody></table>
    </div>

    <h3 class="text-xs font-bold text-white uppercase tracking-wider mt-6 mb-3">Recent API Calls</h3>
    <div class="overflow-x-auto">
        <table><thead><tr><th>Time</th><th>Endpoint</th><th>Model</th><th>Tokens In</th><th>Tokens Out</th><th>Cost</th><th>Who</th></tr></thead>
        <tbody id="usageRecent"></tbody></table>
    </div>
</div>

<!-- ═══════════════ SETTINGS TAB ═══════════════ -->
<?php elseif ($tab === 'settings'): ?>
<div class="card mb-4">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">App Settings</h2>
    <div class="space-y-4">
        <div>
            <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Gemini API Key</label>
            <div class="flex gap-2 mt-1">
                <input type="password" value="<?php echo htmlspecialchars($env['GEMINI_KEY'] ?? ''); ?>" class="input font-mono" readonly id="geminiKey">
                <button onclick="var e=document.getElementById('geminiKey');e.type=e.type==='password'?'text':'password'" class="btn btn-slate">Show</button>
            </div>
        </div>
        <div>
            <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Admin Password</label>
            <div class="flex gap-2 mt-1">
                <input type="password" value="<?php echo htmlspecialchars($ADMIN_PASS); ?>" class="input font-mono" readonly id="adminPass">
                <button onclick="var e=document.getElementById('adminPass');e.type=e.type==='password'?'text':'password'" class="btn btn-slate">Show</button>
            </div>
        </div>
        <div>
            <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Database</label>
            <div class="text-sm text-slate-300 mt-1"><?php echo htmlspecialchars($env['DB_HOST'] ?? ''); ?> / <?php echo htmlspecialchars($env['DB_NAME'] ?? ''); ?></div>
        </div>
        <?php if (isset($env['ELEVENLABS_KEY'])): ?>
        <div>
            <label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">ElevenLabs API Key</label>
            <div class="flex gap-2 mt-1">
                <input type="password" value="<?php echo htmlspecialchars($env['ELEVENLABS_KEY']); ?>" class="input font-mono" readonly id="elevenKey">
                <button onclick="var e=document.getElementById('elevenKey');e.type=e.type==='password'?'text':'password'" class="btn btn-slate">Show</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Database Tables</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php
    $tables = ['hungarian_prep','user_bios','study_history','grammar_patterns','drill_groups','knowledge_cards','learning_resources','skill_proficiency','study_log','api_log','app_connections'];
    foreach ($tables as $tbl) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM $tbl");
        $cnt = $r ? (int)$r->fetch_assoc()['c'] : 0;
        echo "<div class='bg-[#0c1222] rounded-lg p-3'><div class='text-[10px] text-slate-500 uppercase tracking-wider'>$tbl</div><div class='text-lg font-bold text-white'>$cnt</div></div>";
    }
    ?>
    </div>
</div>

<div class="card">
    <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4">App Info</h2>
    <div class="space-y-2 text-xs">
        <div class="flex justify-between"><span class="text-slate-500">Version</span><span class="text-white">v8.0 (Study Command Center)</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Hosting</span><span class="text-white">HostRocket (shared)</span></div>
        <div class="flex justify-between"><span class="text-slate-500">PHP Version</span><span class="text-white"><?php echo phpversion(); ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Gemini Model</span><span class="text-white">gemini-2.5-flash-lite</span></div>
    </div>
</div>

<?php endif; ?>

</div><!-- /max-w-6xl -->

<!-- ═══════════════ MODALS ═══════════════ -->

<!-- Edit Phrase Modal -->
<div id="editModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeModal('editModal')">
    <div class="card w-full max-w-xl mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Edit Phrase <span id="editId" class="text-indigo-400"></span></h3>
        <form method="POST" action="?tab=phrases" id="editForm">
            <input type="hidden" name="action" value="update_all">
            <input type="hidden" name="id" id="editIdInput">
            <div class="space-y-3">
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Question (HU)</label>
                    <div class="flex gap-2 mt-1"><input type="text" name="question_hu" id="editQ" class="input flex-1">
                    <button type="button" onclick="aiFromModal('ai_question')" class="btn btn-violet">AI Q</button></div></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Answer (HU)</label>
                    <div class="flex gap-2 mt-1"><input type="text" name="answer_hu" id="editAH" class="input flex-1" style="color:#4ade80">
                    <button type="button" onclick="aiFromModal('ai_answer')" class="btn btn-violet">AI Ans</button></div></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">English</label>
                    <input type="text" name="answer_en" id="editAE" class="input mt-1"></div>
                <div class="flex gap-3">
                    <div class="flex-1"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Category</label>
                        <input type="text" name="category" id="editCat" class="input mt-1"></div>
                    <div style="width:100px"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Who</label>
                        <select name="phrase_who" id="editWho" class="input mt-1"><option value="All">All</option><option value="Maria">Maria</option><option value="Larry">Larry</option></select></div>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-green">Save</button>
                <button type="button" onclick="closeModal('editModal')" class="btn btn-slate">Cancel</button>
            </div>
        </form>
        <div id="aiStatus" class="text-xs text-slate-500 hidden"></div>
    </div>
</div>

<!-- Bio Modal -->
<div id="bioModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeModal('bioModal')">
    <div class="card w-full max-w-md mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider" id="bioModalTitle">Add Bio Fact</h3>
        <form method="POST" action="?tab=users" id="bioForm">
            <input type="hidden" name="action" id="bioAction" value="bio_add">
            <input type="hidden" name="id" id="bioIdInput">
            <div class="space-y-3">
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Person</label>
                    <select name="subject_name" id="bioSN" class="input mt-1"><option value="Maria">Maria</option><option value="Larry">Larry</option><option value="Tev">Tev</option><option value="Hannah">Hannah</option></select></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Label (EN)</label>
                    <input type="text" name="fact_label_hu" id="bioFL" placeholder="e.g. Birthday, City" class="input mt-1"></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Value (HU)</label>
                    <input type="text" name="fact_value_hu" id="bioFV" placeholder="Hungarian value" class="input mt-1" style="color:#4ade80"></div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeModal('bioModal')" class="btn btn-slate">Cancel</button>
                <button type="submit" class="btn btn-green">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Grammar Modal -->
<div id="grammarModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeModal('grammarModal')">
    <div class="card w-full max-w-xl mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider" id="grammarModalTitle">Add Grammar Pattern</h3>
        <form method="POST" action="?tab=grammar" id="grammarForm">
            <input type="hidden" name="action" id="grammarAction" value="grammar_add">
            <input type="hidden" name="id" id="grammarIdInput">
            <div class="space-y-3">
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Pattern</label>
                    <input type="text" name="pattern" id="gPattern" class="input mt-1" placeholder="e.g. -ban/-ben"></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Suffix Words / Examples</label>
                    <textarea name="suffix_words" id="gSuffix" class="input mt-1" rows="2" placeholder="hazban, iskolaban..."></textarea></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Explanation</label>
                    <textarea name="explanation" id="gExplain" class="input mt-1" rows="2" placeholder="Inessive case (in/inside)"></textarea></div>
                <div class="flex gap-3">
                    <div class="flex-1"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Part of Speech</label>
                        <input type="text" name="part_of_speech" id="gPOS" class="input mt-1" placeholder="suffix, verb, etc."></div>
                    <div class="flex-1"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Tags</label>
                        <input type="text" name="tags" id="gTags" class="input mt-1" placeholder="case,location"></div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeModal('grammarModal')" class="btn btn-slate">Cancel</button>
                <button type="submit" class="btn btn-green">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Resource Modal -->
<div id="resourceModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden" onclick="if(event.target===this)closeModal('resourceModal')">
    <div class="card w-full max-w-md mx-4 space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider" id="resourceModalTitle">Add Resource</h3>
        <form method="POST" action="?tab=resources" id="resourceForm">
            <input type="hidden" name="action" id="resourceAction" value="resource_add">
            <input type="hidden" name="id" id="resourceIdInput">
            <div class="space-y-3">
                <div class="flex gap-3">
                    <div class="flex-1"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Category</label>
                        <input type="text" name="res_category" id="resCat" class="input mt-1" placeholder="Listening, Vocabulary..."></div>
                    <div style="width:60px"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Icon</label>
                        <input type="text" name="res_icon" id="resIcon" class="input mt-1" value="🔗"></div>
                    <div style="width:60px"><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Sort</label>
                        <input type="number" name="res_sort" id="resSort" class="input mt-1" value="0"></div>
                </div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Name</label>
                    <input type="text" name="res_name" id="resName" class="input mt-1"></div>
                <div><label class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">URL</label>
                    <input type="text" name="res_url" id="resURL" class="input mt-1"></div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeModal('resourceModal')" class="btn btn-slate">Cancel</button>
                <button type="submit" class="btn btn-green">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Utilities ──
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') ['editModal','bioModal','grammarModal','resourceModal'].forEach(closeModal);
});

// ── Phrase functions ──
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

function aiGenerate(id, action, evt) {
    var row = document.getElementById('row-' + id);
    var btn = evt.target;
    var origText = btn.textContent;
    btn.textContent = '...'; btn.disabled = true;
    var fd = new FormData();
    fd.append('question_hu', row.dataset.q);
    fd.append('answer_en', row.dataset.ae);
    fd.append('answer_hu', row.dataset.ah);
    fetch('admin.php?ajax=1&action=' + action, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            editRow(id);
            if (action === 'ai_answer') document.getElementById('editAH').value = data.result;
            else document.getElementById('editQ').value = data.result;
            var s = document.getElementById('aiStatus'); s.textContent = 'AI generated — review and save.'; s.classList.remove('hidden');
        })
        .catch(function() { alert('AI request failed'); })
        .finally(function() { btn.textContent = origText; btn.disabled = false; });
}

function aiFromModal(action) {
    var s = document.getElementById('aiStatus'); s.textContent = 'Generating...'; s.classList.remove('hidden');
    var fd = new FormData();
    fd.append('question_hu', document.getElementById('editQ').value);
    fd.append('answer_en', document.getElementById('editAE').value);
    fd.append('answer_hu', document.getElementById('editAH').value);
    fetch('admin.php?ajax=1&action=' + action, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { s.textContent = data.error; return; }
            if (action === 'ai_answer') document.getElementById('editAH').value = data.result;
            else document.getElementById('editQ').value = data.result;
            s.textContent = 'AI generated — review and save.';
        })
        .catch(function() { s.textContent = 'Failed'; });
}

function filterTable() {
    var term = document.getElementById('liveSearch').value.toLowerCase();
    var cat = document.getElementById('catSelect').value;
    var who = document.getElementById('whoSelect').value;
    document.querySelectorAll('.phrase-row').forEach(function(row) {
        var text = (row.dataset.q + ' ' + row.dataset.ah + ' ' + row.dataset.ae).toLowerCase();
        row.style.display = (!term || text.indexOf(term) !== -1) && (!cat || row.dataset.cat === cat) && (!who || row.dataset.who === who) ? '' : 'none';
    });
}

function updateBulkBar() {
    var c = document.querySelectorAll('.row-cb:checked').length;
    var bar = document.getElementById('bulkBar');
    if (c > 0) { bar.classList.remove('hidden'); document.getElementById('bulkCount').textContent = c + ' selected'; }
    else { bar.classList.add('hidden'); }
}
function clearSelection() {
    document.querySelectorAll('.row-cb').forEach(function(c) { c.checked = false; });
    if (document.getElementById('selectAll')) document.getElementById('selectAll').checked = false;
    updateBulkBar();
}
function bulkDelete() {
    var ids = []; document.querySelectorAll('.row-cb:checked').forEach(function(c) { ids.push(parseInt(c.value)); });
    if (!ids.length || !confirm('Delete ' + ids.length + ' phrases?')) return;
    var form = document.createElement('form'); form.method = 'POST'; form.action = '?tab=phrases';
    form.innerHTML = '<input type="hidden" name="action" value="delete_bulk"><input type="hidden" name="delete_ids" value=\'' + JSON.stringify(ids) + '\'>';
    document.body.appendChild(form); form.submit();
}

// ── AI Group Generator ──
var generatedItems = [];
function generateGroup() {
    var topic = document.getElementById('groupTopic').value.trim();
    if (!topic) return;
    var btn = document.getElementById('groupGenBtn');
    var status = document.getElementById('groupStatus');
    btn.textContent = '...'; btn.disabled = true;
    status.textContent = 'Generating...'; status.classList.remove('hidden');
    var catInput = document.getElementById('groupCategory');
    if (!catInput.value.trim()) catInput.value = topic;
    var fd = new FormData(); fd.append('topic', topic);
    fetch('admin.php?ajax=1&action=ai_group', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { status.textContent = data.error; return; }
            generatedItems = data.items;
            var body = document.getElementById('groupBody'); body.innerHTML = '';
            generatedItems.forEach(function(item, i) {
                body.innerHTML += '<tr><td><input type="checkbox" class="group-cb accent-violet-500" data-idx="'+i+'" checked></td><td class="text-white">'+(item.question_hu||'')+'</td><td class="text-green-400">'+(item.answer_hu||'')+'</td><td class="text-slate-400">'+(item.answer_en||'')+'</td></tr>';
            });
            document.getElementById('groupCount').textContent = '(' + data.items.length + ')';
            document.getElementById('groupPreview').classList.remove('hidden');
            status.textContent = data.items.length + ' items generated.';
        })
        .catch(function() { status.textContent = 'Failed'; })
        .finally(function() { btn.textContent = 'Generate'; btn.disabled = false; });
}
function saveGroup() {
    var cat = document.getElementById('groupCategory').value.trim() || 'Practice';
    var sel = []; document.querySelectorAll('.group-cb:checked').forEach(function(cb) { sel.push(generatedItems[parseInt(cb.dataset.idx)]); });
    if (!sel.length) return;
    var rows = sel.map(function(item) { return {question_hu:item.question_hu,answer_hu:item.answer_hu||'',answer_en:item.answer_en||'',category:cat,who:'All'}; });
    var form = document.createElement('form'); form.method = 'POST'; form.action = '?tab=phrases';
    form.innerHTML = '<input type="hidden" name="action" value="import"><input type="hidden" name="rows_json" value=\'' + JSON.stringify(rows).replace(/'/g,'&#39;') + '\'>';
    document.body.appendChild(form); form.submit();
}
function clearGroup() { generatedItems = []; document.getElementById('groupPreview').classList.add('hidden'); document.getElementById('groupStatus').classList.add('hidden'); }

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
    var row = document.querySelector('.bio-row[data-id="'+id+'"]');
    document.getElementById('bioAction').value = 'bio_update';
    document.getElementById('bioIdInput').value = id;
    document.getElementById('bioModalTitle').textContent = 'Edit Bio #' + id;
    document.getElementById('bioSN').value = row.dataset.sn;
    document.getElementById('bioFL').value = row.dataset.fl;
    document.getElementById('bioFV').value = row.dataset.fv;
    document.getElementById('bioModal').classList.remove('hidden');
}
function filterBios() {
    var term = document.getElementById('bioSearch').value.toLowerCase();
    var who = document.getElementById('bioWhoFilter').value;
    document.querySelectorAll('.bio-row').forEach(function(r) {
        var text = (r.dataset.sn + ' ' + r.dataset.fl + ' ' + r.dataset.fv).toLowerCase();
        r.style.display = (!term || text.indexOf(term) !== -1) && (!who || r.dataset.sn === who) ? '' : 'none';
    });
}
function updateBioBulkBar() {
    var c = document.querySelectorAll('.bio-cb:checked').length;
    var bar = document.getElementById('bioBulkBar');
    if (c > 0) { bar.classList.remove('hidden'); document.getElementById('bioBulkCount').textContent = c + ' selected'; }
    else { bar.classList.add('hidden'); }
}
function bioClearSelection() { document.querySelectorAll('.bio-cb').forEach(function(c){c.checked=false}); updateBioBulkBar(); }
function bioBulkDelete() {
    var ids = []; document.querySelectorAll('.bio-cb:checked').forEach(function(c){ids.push(parseInt(c.value))});
    if (!ids.length || !confirm('Delete ' + ids.length + ' bio facts?')) return;
    var form = document.createElement('form'); form.method = 'POST'; form.action = '?tab=users';
    form.innerHTML = '<input type="hidden" name="action" value="bio_delete_bulk"><input type="hidden" name="delete_ids" value=\'' + JSON.stringify(ids) + '\'>';
    document.body.appendChild(form); form.submit();
}

// ── Grammar functions ──
function openGrammarAdd() {
    document.getElementById('grammarAction').value = 'grammar_add';
    document.getElementById('grammarIdInput').value = '';
    document.getElementById('grammarModalTitle').textContent = 'Add Grammar Pattern';
    ['gPattern','gSuffix','gExplain','gPOS','gTags'].forEach(function(id){document.getElementById(id).value='';});
    document.getElementById('grammarModal').classList.remove('hidden');
}
function editGrammar(id) {
    var row = document.querySelector('.grammar-row[data-id="'+id+'"]');
    document.getElementById('grammarAction').value = 'grammar_update';
    document.getElementById('grammarIdInput').value = id;
    document.getElementById('grammarModalTitle').textContent = 'Edit Grammar #' + id;
    document.getElementById('gPattern').value = row.dataset.pattern;
    document.getElementById('gSuffix').value = row.dataset.sw;
    document.getElementById('gExplain').value = row.dataset.ex;
    document.getElementById('gPOS').value = row.dataset.pos;
    document.getElementById('gTags').value = row.dataset.tags;
    document.getElementById('grammarModal').classList.remove('hidden');
}
function filterGrammar() {
    var term = document.getElementById('grammarSearch').value.toLowerCase();
    document.querySelectorAll('.grammar-row').forEach(function(r) {
        var text = (r.dataset.pattern + ' ' + r.dataset.sw + ' ' + r.dataset.ex + ' ' + r.dataset.tags).toLowerCase();
        r.style.display = (!term || text.indexOf(term) !== -1) ? '' : 'none';
    });
}

// ── Resource functions ──
function openResourceAdd() {
    document.getElementById('resourceAction').value = 'resource_add';
    document.getElementById('resourceIdInput').value = '';
    document.getElementById('resourceModalTitle').textContent = 'Add Resource';
    document.getElementById('resCat').value = '';
    document.getElementById('resName').value = '';
    document.getElementById('resURL').value = '';
    document.getElementById('resIcon').value = '🔗';
    document.getElementById('resSort').value = '0';
    document.getElementById('resourceModal').classList.remove('hidden');
}
function editResource(id) {
    var row = document.querySelector('.resource-row[data-id="'+id+'"]');
    document.getElementById('resourceAction').value = 'resource_update';
    document.getElementById('resourceIdInput').value = id;
    document.getElementById('resourceModalTitle').textContent = 'Edit Resource #' + id;
    document.getElementById('resCat').value = row.dataset.cat;
    document.getElementById('resName').value = row.dataset.name;
    document.getElementById('resURL').value = row.dataset.url;
    document.getElementById('resIcon').value = row.dataset.icon;
    document.getElementById('resSort').value = row.dataset.sort;
    document.getElementById('resourceModal').classList.remove('hidden');
}

// ── Progress tab ──
function loadProgress() {
    var who = document.getElementById('progressWho') ? document.getElementById('progressWho').value : 'All';
    fetch('admin.php?ajax=1&action=progress_data&who=' + who)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            document.getElementById('sSt').textContent = d.studied;
            document.getElementById('sMa').textContent = d.mastered;
            document.getElementById('sDu').textContent = d.due;
            document.getElementById('sPR').textContent = d.passRate + '%';
            var total = d.totalPhrases + d.totalGrammar;
            var pct = total > 0 ? Math.round(d.studied / total * 100) : 0;
            document.getElementById('covPct').textContent = d.studied + '/' + total + ' (' + pct + '%)';
            document.getElementById('covBar').style.width = pct + '%';

            if (d.perUser.length > 0) {
                document.getElementById('perUserCard').style.display = '';
                var html = '';
                d.perUser.forEach(function(u) {
                    var rate = (parseInt(u.passes||0) + parseInt(u.fails||0)) > 0 ? Math.round(parseInt(u.passes||0) / (parseInt(u.passes||0) + parseInt(u.fails||0)) * 100) : 0;
                    html += '<tr><td class="text-indigo-400 font-medium">' + u.who + '</td><td class="text-white">' + u.items + '</td><td class="text-green-400">' + (u.passes||0) + '</td><td class="text-red-400">' + (u.fails||0) + '</td><td class="text-white">' + rate + '%</td><td class="text-slate-500">' + (u.last_active ? new Date(u.last_active).toLocaleDateString() : '-') + '</td></tr>';
                });
                document.getElementById('perUserBody').innerHTML = html;
            }

            var chart = document.getElementById('dailyChart');
            if (d.daily.length > 0) {
                var maxCnt = Math.max.apply(null, d.daily.map(function(x){return parseInt(x.cnt)}));
                chart.innerHTML = '';
                d.daily.forEach(function(day) {
                    var h = maxCnt > 0 ? Math.max(4, parseInt(day.cnt) / maxCnt * 120) : 4;
                    chart.innerHTML += '<div class="chart-bar flex-1 bg-indigo-500/60" style="height:' + h + 'px" title="' + day.day + ': ' + day.cnt + ' items"></div>';
                });
            } else {
                chart.innerHTML = '<div class="text-xs text-slate-500 w-full text-center py-8">No activity in last 30 days</div>';
            }

            if (d.weak.length > 0) {
                document.getElementById('weakCard').style.display = '';
                var html = '';
                d.weak.forEach(function(w) {
                    html += '<tr><td class="text-white">' + w.phrase + '</td><td class="text-red-400">' + w.fail_count + '</td><td class="text-green-400">' + w.pass_count + '</td></tr>';
                });
                document.getElementById('weakBody').innerHTML = html;
            }

            var rHtml = '';
            d.recent.forEach(function(r) {
                rHtml += '<tr><td class="text-white" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + r.phrase + '</td><td class="text-indigo-400">' + r.who + '</td><td class="text-slate-500">' + (r.item_type||'phrase') + '</td><td class="text-green-400">' + r.pass_count + '</td><td class="text-red-400">' + r.fail_count + '</td><td class="text-slate-400">' + (r.last_seen||'-') + '</td><td class="text-slate-500">' + (r.next_review||'-') + '</td></tr>';
            });
            document.getElementById('recentBody').innerHTML = rHtml || '<tr><td colspan="7" class="text-slate-500 text-center py-4">No study history</td></tr>';
        });
}

// ── Activity tab ──
function loadActivity() {
    var who = document.getElementById('actWho') ? document.getElementById('actWho').value : '';
    fetch('admin.php?ajax=1&action=activity_log&who=' + who + '&limit=100')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var sHtml = '';
            if (d.sessions && d.sessions.length > 0) {
                sHtml = '<h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3">Study Sessions</h3><div class="overflow-x-auto mb-4"><table><thead><tr><th>Who</th><th>Block</th><th>Title</th><th>Duration</th><th>Items</th><th>Passed</th><th>Completed</th></tr></thead><tbody>';
                d.sessions.forEach(function(s) {
                    sHtml += '<tr><td class="text-indigo-400">' + s.who + '</td><td class="text-white">' + s.block_type + '</td><td class="text-slate-300">' + (s.block_title||'') + '</td><td class="text-white">' + s.duration_min + 'm</td><td class="text-white">' + s.items_completed + '</td><td class="text-green-400">' + s.items_passed + '</td><td class="text-slate-500">' + s.completed_at + '</td></tr>';
                });
                sHtml += '</tbody></table></div>';
            }
            document.getElementById('actSessions').innerHTML = sHtml;

            var html = '';
            d.history.forEach(function(r) {
                html += '<tr><td class="text-white" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + r.phrase + '</td><td class="text-indigo-400">' + r.who + '</td><td class="text-slate-500">' + (r.item_type||'phrase') + '</td><td class="text-green-400">' + r.pass_count + '</td><td class="text-red-400">' + r.fail_count + '</td><td class="text-slate-400">' + (r.last_seen||'-') + '</td><td class="text-slate-500">' + (r.next_review||'-') + '</td></tr>';
            });
            document.getElementById('actBody').innerHTML = html || '<tr><td colspan="7" class="text-slate-500 text-center py-4">No activity</td></tr>';
        });
}
function exportActivity() {
    var who = document.getElementById('actWho').value;
    fetch('admin.php?ajax=1&action=activity_log&who=' + who + '&limit=200')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var csv = 'Phrase,Who,Type,Pass,Fail,Last Seen,Next Review\n';
            d.history.forEach(function(r) {
                csv += '"' + (r.phrase||'').replace(/"/g,'""') + '","' + r.who + '","' + (r.item_type||'') + '",' + r.pass_count + ',' + r.fail_count + ',"' + (r.last_seen||'') + '","' + (r.next_review||'') + '"\n';
            });
            var blob = new Blob([csv], {type:'text/csv'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'hug-activity-' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
        });
}

// ── Usage tab ──
function loadUsage() {
    fetch('admin.php?ajax=1&action=usage_stats')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var tHtml = '';
            if (d.totals.length > 0) {
                d.totals.forEach(function(t) {
                    tHtml += '<div class="stat-card"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">' + t.endpoint + '</div><div class="text-lg font-bold text-white">' + t.calls + ' calls</div><div class="text-[11px] text-slate-400">$' + parseFloat(t.cost||0).toFixed(4) + '</div></div>';
                });
            } else {
                tHtml = '<div class="col-span-4 text-xs text-slate-500 text-center py-4">No API calls logged yet. Instrument eval.php / translate.php to log to api_log table.</div>';
            }
            document.getElementById('usageTotals').innerHTML = tHtml;

            var dHtml = '';
            d.daily.forEach(function(r) {
                dHtml += '<tr><td class="text-white">' + r.day + '</td><td class="text-indigo-400">' + r.endpoint + '</td><td class="text-white">' + r.calls + '</td><td class="text-slate-400">' + (r.tin||0) + '</td><td class="text-slate-400">' + (r.tout||0) + '</td><td class="text-green-400">$' + parseFloat(r.cost||0).toFixed(4) + '</td></tr>';
            });
            document.getElementById('usageDaily').innerHTML = dHtml || '<tr><td colspan="6" class="text-slate-500 text-center py-4">No data</td></tr>';

            var rHtml = '';
            d.recent.forEach(function(r) {
                rHtml += '<tr><td class="text-slate-400">' + r.created_at + '</td><td class="text-indigo-400">' + r.endpoint + '</td><td class="text-slate-500">' + (r.model||'-') + '</td><td class="text-white">' + r.tokens_in + '</td><td class="text-white">' + r.tokens_out + '</td><td class="text-green-400">$' + parseFloat(r.cost_usd||0).toFixed(4) + '</td><td class="text-slate-400">' + (r.who||'-') + '</td></tr>';
            });
            document.getElementById('usageRecent').innerHTML = rHtml || '<tr><td colspan="7" class="text-slate-500 text-center py-4">No data</td></tr>';
        });
}

// ── Auto-load data for active tab ──
var currentTab = '<?php echo $tab; ?>';
if (currentTab === 'progress') loadProgress();
if (currentTab === 'activity') loadActivity();
if (currentTab === 'usage') loadUsage();
</script>

</body>
</html>
<?php $conn->close(); ?>
