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
    $parts[] = "SELECT question_hu AS q, answer_en AS a, $ahuCol AS a_hu, category FROM hungarian_prep";
}
$union = implode(' UNION ', $parts);

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

// SRS-weighted query
$srs_sql = "SELECT phrases.q, phrases.a, phrases.category
            FROM ($union) AS phrases
            LEFT JOIN study_history sh ON sh.phrase = phrases.q AND sh.who = '$who_safe'
            ORDER BY CASE WHEN sh.next_review IS NULL OR sh.next_review <= NOW() THEN 0 ELSE 1 END ASC, RAND()
            LIMIT 1";
$result = $conn->query($srs_sql);
if (!$result) {
    $result = $conn->query("SELECT q, a, category FROM ($union) AS phrases ORDER BY RAND() LIMIT 1");
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
<title>HUG COACH v5.0</title>
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
.pill-inactive { color: #cbd5e1; }
.pill-inactive:hover { color: #f1f5f9; background: rgba(255,255,255,0.05); }
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
        <button onclick="closeSummary()"
            class="w-full bg-accent hover:bg-accent-dark py-3 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2">
            Continue <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
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
                <span class="text-[10px] text-slate-500 ml-1.5">v5.0</span>
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

    <!-- SESSION PROGRESS -->
    <div class="flex items-center gap-3">
        <div class="flex-1 h-1.5 progress-track rounded-full overflow-hidden">
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
                    <i data-lucide="timer" class="w-3.5 h-3.5"></i> Auto
                </button>
                <button id="micToggle" onclick="toggleMic()" title="Mic on/off"
                    class="p-1.5 rounded-lg text-slate-200 hover:text-white hover:bg-white/5 transition-all">
                    <i id="micToggleIcon" data-lucide="mic" class="w-3.5 h-3.5"></i>
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
                <button id="translateBtn" onclick="toggleTranslation()" class="inline-flex items-center gap-2 text-slate-200 hover:text-blue-400 transition-colors">
                    <i data-lucide="languages" class="w-5 h-5"></i>
                    <span class="text-sm font-semibold">Translate</span>
                </button>
                <button id="phoneticBtn" onclick="togglePhonetic()" class="inline-flex items-center gap-2 text-slate-200 hover:text-amber-400 transition-colors">
                    <i data-lucide="spell-check" class="w-5 h-5"></i>
                    <span class="text-sm font-semibold">Phonetic</span>
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
                <button id="recordBtn" onclick="toggleMic()" title="Record [Space]" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-20 h-16 bg-red-600 hover:bg-red-500 text-white glow-red">
                    <i id="recordIcon" data-lucide="mic" class="w-6 h-6"></i>
                    <span id="recordLabel" class="text-[9px] font-semibold">Record</span>
                </button>
                <button onclick="nextQuestion()" title="Next [Enter]" class="ctrl-btn flex flex-col items-center justify-center gap-0.5 w-16 h-16 bg-accent hover:bg-accent-dark text-white">
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    <span class="text-[9px] font-semibold">Next</span>
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

        <!-- Practice Section -->
        <div class="px-5 pb-5 border-t border-white/5 pt-4">
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
                </div>
            </div>
            <p id="practiceTranslation" class="hidden text-slate-300 text-sm mt-3 italic px-1"></p>
        </div>
    </main>

    <!-- Keyboard Shortcuts (desktop) -->
    <div class="hidden md:flex items-center justify-center gap-4 text-[10px] text-slate-300 mt-2">
        <span><span class="kbd">Space</span> Record</span>
        <span><span class="kbd">Enter</span> Next</span>
        <span><span class="kbd">Esc</span> Stop</span>
        <span><span class="kbd">S</span> Slow</span>
        <span><span class="kbd">T</span> Translate</span>
        <span><span class="kbd">P</span> Phonetic</span>
    </div>
</div>

<!-- BOTTOM QUICK BAR -->
<nav class="quick-bar">
    <button onclick="openBrowse()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="book-open" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Browse</span>
    </button>
    <button onclick="openStats()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Stats</span>
    </button>
    <button onclick="speak(currentSpeed)" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-accent-light transition-all">
        <i data-lucide="volume-2" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Listen</span>
    </button>
    <button onclick="toggleMic()" class="flex flex-col items-center gap-1 p-2 text-slate-200 hover:text-red-400 transition-all">
        <i data-lucide="mic" class="w-5 h-5"></i>
        <span class="text-[10px] font-semibold">Record</span>
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
let autoAdvance    = localStorage.getItem('hugAutoAdvance') !== '0';
let translateOn    = localStorage.getItem('hugTranslate') === '1';
let phoneticOn     = localStorage.getItem('hugPhonetic') === '1';
let strictness     = parseInt(localStorage.getItem('hugStrict')) || 2;
let repeatOnFail   = localStorage.getItem('hugRepeatFail') === '1';

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

function closeSummary() {
    document.getElementById('summaryModal').classList.add('hidden');
    nextQuestion();
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

function speak(rate) {
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
        msg.onend = function() { setTimeout(toggleMic, 350); };
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
function setCat(c) {
    cat = c;
    localStorage.setItem('hugCat', c);
    ['all','prep','bios'].forEach(function(id) {
        var el = document.getElementById('cat-' + id);
        el.className = 'pill ' + (cat === id ? 'pill-active' : 'pill-inactive');
    });
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
        btn.classList.remove('text-slate-500');
    } else {
        q.classList.remove('listen-blur');
        q.title = '';
        q.onclick = null;
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-slate-500');
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
        btn.classList.remove('text-slate-500');
    } else {
        btn.classList.remove('text-accent-light');
        btn.classList.add('text-slate-500');
    }
}

// ── Mode toggle ───────────────────────────────────────────────────────
function setMode(mode) {
    currentMode = mode;
    localStorage.setItem('hugMode', mode);
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

    fetch('?who=' + who + '&cat=' + cat + '&ajax=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            targetQ  = data.q;
            targetA  = data.a;
            targetAH = data.a_hu || '';
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

// ── Speech recognition ────────────────────────────────────────────────
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
var recognition = new SpeechRecognition();
recognition.lang            = 'hu-HU';
recognition.interimResults  = false;
recognition.continuous      = true;
recognition.maxAlternatives = 1;

function setRecordIcon(iconName) {
    var icon = document.getElementById('recordIcon');
    icon.setAttribute('data-lucide', iconName);
    lucide.createIcons({ nodes: [icon] });
}
function setMicToggleIcon(iconName) {
    var icon = document.getElementById('micToggleIcon');
    icon.setAttribute('data-lucide', iconName);
    lucide.createIcons({ nodes: [icon] });
}

recognition.onstart = function() {
    isListening     = true;
    listenStartTime = Date.now();
    indicator.className = 'status-dot dot-live';
    document.getElementById('recordBtn').classList.add('mic-active');
    document.getElementById('recordLabel').textContent = 'Recording';

    setRecordIcon('headphones');
    setMicToggleIcon('mic-off');
    document.getElementById('micToggle').classList.add('text-red-400');
    document.getElementById('micToggle').classList.remove('text-slate-200');
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

    var result = event.results[event.results.length - 1][0].transcript.trim();
    isListening = false;
    indicator.className = 'status-dot dot-off';
    document.getElementById('recordBtn').classList.remove('mic-active');
    document.getElementById('recordLabel').textContent = 'Record';

    setRecordIcon('mic');
    setMicToggleIcon('mic');
    document.getElementById('micToggle').classList.remove('text-red-400');
    document.getElementById('micToggle').classList.add('text-slate-200');

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
                advanceTimeout = setTimeout(nextQuestion, 4000);
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
    document.getElementById('recordBtn').classList.remove('mic-active');
    document.getElementById('recordLabel').textContent = 'Record';

    setRecordIcon('mic');
    setMicToggleIcon('mic');
    document.getElementById('micToggle').classList.remove('text-red-400');
    document.getElementById('micToggle').classList.add('text-slate-200');
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
        btn.classList.remove('text-slate-500');
    } else {
        btn.classList.remove('text-blue-400');
        btn.classList.add('text-slate-500');
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
        btn.classList.remove('text-slate-500');
    } else {
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-slate-500');
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
        .then(function(data) { el.textContent = data.translation || 'Error'; })
        .catch(function() { el.textContent = 'Translation error'; });
}

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
    document.getElementById('questionText').textContent = q;
    document.getElementById('answerText').textContent   = a;
    document.getElementById('resultCard').classList.add('hidden');
    closeBrowse();
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
        showPhonetic();
    }
});

// ── Init ──────────────────────────────────────────────────────────────
setMode(currentMode);
setCat(cat);
setSpeed(currentSpeed);
applyListenMode();
applyAutoAdvance();
applyTranslateState();
applyPhoneticState();
if (translateOn) fetchTranslation();
if (phoneticOn) fetchPhonetic();
updateProgressBar();
lucide.createIcons();
</script>
</body>
</html>
