<?php
$env = parse_ini_file('.env');
$conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
$who = isset($_GET['who']) ? $_GET['who'] : 'All';
$who = in_array($who, ['Maria', 'Larry', 'All']) ? $who : 'All';
$cat = $_GET['cat'] ?? 'all';
$cat = in_array($cat, ['all','prep','bios']) ? $cat : 'all';

$who_safe   = $conn->real_escape_string($who);
$bio_filter = ($who !== 'All')
    ? "WHERE subject_name = '$who_safe' AND fact_label_hu LIKE '%?'"
    : "WHERE fact_label_hu LIKE '%?'";

// ALL and PHRASES → hungarian_prep only (always Hungarian)
// PERSONAL        → user_bios personal facts (explicit opt-in)
$parts = [];
if ($cat === 'bios') {
    $parts[] = "SELECT fact_label_hu AS q, fact_value_hu AS a, category FROM user_bios $bio_filter";
} else {
    $parts[] = "SELECT question_hu AS q, answer_en AS a, category FROM hungarian_prep";
}
$union = implode(' UNION ', $parts);

// SRS-weighted query — falls back to simple RAND if study_history table doesn't exist yet
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
$targetQ = $row['q'] ?? 'No Data Found';
$targetA = $row['a'] ?? 'Sync n8n';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['q' => $targetQ, 'a' => $targetA, 'category' => $row['category'] ?? 'General']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HUG COACH v4.3</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { background-color: #050a14; color: white; font-family: sans-serif; }
.card-bg { background-color: #111a2e; border: 1px solid #1e293b; }
.status-dot { width: 18px; height: 18px; border-radius: 50%; display: inline-block; transition: 0.3s; }
.dot-off    { background-color: #334155; }
.dot-warmup { background-color: #eab308; box-shadow: 0 0 10px #eab308; }
.dot-live   { background-color: #ef4444; box-shadow: 0 0 15px #ef4444; }
#volBar  { width: 60px; height: 8px; background: #1e293b; border-radius: 4px; overflow: hidden; }
#volFill { height: 100%; width: 0%; background: #22c55e; border-radius: 4px; transition: width 0.05s; }
.listen-blur { filter: blur(14px); cursor: pointer; transition: filter 0.3s; }
</style>
</head>
<body class="p-6 flex flex-col items-center">

<!-- Session summary modal -->
<div id="summaryModal" class="hidden fixed inset-0 bg-black/85 flex items-center justify-center z-50">
    <div class="bg-[#111a2e] border border-slate-700 rounded-3xl p-10 text-center max-w-sm w-full mx-4 shadow-2xl">
        <div class="text-5xl mb-4">🎉</div>
        <h2 class="text-xl font-bold text-white mb-2">Session Complete!</h2>
        <p class="text-slate-500 text-xs uppercase tracking-widest mb-6">10 questions</p>
        <div class="flex justify-around mb-8">
            <div>
                <div id="summaryPass" class="text-4xl font-bold text-green-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Passed</div>
            </div>
            <div>
                <div id="summaryFail" class="text-4xl font-bold text-red-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Missed</div>
            </div>
            <div>
                <div id="summaryStreak" class="text-4xl font-bold text-yellow-400">0</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Best Streak</div>
            </div>
        </div>
        <button onclick="closeSummary()"
            class="bg-blue-600 hover:bg-blue-500 px-10 py-3 rounded-full text-sm font-black uppercase tracking-widest transition-all">
            Continue →
        </button>
    </div>
</div>

<!-- Header -->
<div class="w-full max-w-2xl flex justify-between items-center mb-8">
    <div class="flex items-center gap-2">
        <span class="text-xl">🇭🇺</span>
        <span class="font-bold tracking-widest uppercase text-blue-400">HUG COACH v4.3</span>
    </div>
    <div class="flex gap-2 bg-black/40 p-1 rounded-lg">
        <a href="?who=Maria" class="px-4 py-1 rounded text-xs font-bold <?php echo $who == 'Maria' ? 'bg-blue-600' : 'text-slate-500'; ?>">MARIA</a>
        <a href="?who=Larry" class="px-4 py-1 rounded text-xs font-bold <?php echo $who == 'Larry' ? 'bg-blue-600' : 'text-slate-500'; ?>">LARRY</a>
        <a href="?who=All"   class="px-4 py-1 rounded text-xs font-bold <?php echo $who == 'All'   ? 'bg-blue-600' : 'text-slate-500'; ?>">ALL</a>
    </div>
</div>

<div class="w-full max-w-2xl card-bg rounded-[2.5rem] p-10 shadow-2xl relative">

    <!-- Status dot + volume bar -->
    <div class="absolute top-6 right-10 flex items-center gap-2">
        <div id="readyIndicator" class="status-dot dot-off"></div>
        <div id="volBar"><div id="volFill"></div></div>
    </div>

    <div class="text-center mb-10">

        <!-- Mode toggle row -->
        <div class="flex justify-center items-center gap-2 mb-3">
            <button onclick="setMode('pronunciation')" id="btnPron"
                class="text-[10px] font-black uppercase tracking-[0.3em] px-4 py-1 rounded-full transition-all">
                PRONUNCIATION
            </button>
            <button onclick="setMode('interview')" id="btnInterview"
                class="text-[10px] font-black uppercase tracking-[0.3em] px-4 py-1 rounded-full transition-all">
                INTERVIEW
            </button>
            <div class="w-px h-4 bg-slate-700 mx-1"></div>
            <button id="listenModeBtn" onclick="toggleListenMode()"
                title="Hide question text until you answer"
                class="text-xl px-3 py-1 rounded-full transition-all text-slate-500 hover:text-white leading-none">
                👂
            </button>
        </div>

        <!-- Category filter row -->
        <div class="flex justify-center gap-2 mb-6">
            <button id="cat-all"  onclick="setCat('all')"
                class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full transition-all">ALL</button>
            <button id="cat-prep" onclick="setCat('prep')"
                class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full transition-all">PHRASES</button>
            <button id="cat-bios" onclick="setCat('bios')"
                class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full transition-all">PERSONAL</button>
        </div>

        <!-- Question -->
        <h1 id="questionText" class="text-5xl font-bold mb-4"><?php echo htmlspecialchars($targetQ); ?></h1>

        <!-- Translate + Phonetics -->
        <div class="flex justify-center gap-4 mb-2">
            <button onclick="toggleTranslation()" class="text-[11px] text-slate-500 hover:text-blue-400 uppercase tracking-widest">🌐 translate</button>
            <button onclick="showPhonetic()"       class="text-[11px] text-slate-500 hover:text-yellow-400 uppercase tracking-widest">🔤 phonetics</button>
        </div>
        <p id="inlineTranslation" class="hidden text-slate-400 text-base mt-1 italic"></p>
        <p id="phoneticHint"      class="hidden text-yellow-300/80 text-base mt-1 font-mono"></p>

        <!-- Listen button -->
        <button onclick="speak(1.0)" class="w-full bg-[#1e293b]/60 border border-slate-700/50 rounded-3xl p-12 flex flex-col items-center group hover:bg-[#26334d] transition-all mt-6 mb-2">
            <div class="text-5xl mb-4 group-active:scale-90 transition-transform">🔊</div>
            <span id="listenBtnLabel" class="text-[12px] font-black text-blue-400 uppercase tracking-[0.4em]">LISTEN & REPEAT</span>
        </button>
    </div>

    <!-- Result card -->
    <div id="resultCard" class="hidden bg-black/40 border border-slate-800 rounded-3xl p-8 mb-8 text-center">
        <div id="matchScore" class="mb-4"></div>
        <p id="transcript" class="text-2xl font-bold italic mb-4"></p>
        <button id="playbackBtn" onclick="playMyVoice()"
            class="hidden bg-slate-700 hover:bg-slate-600 px-6 py-2 rounded-full text-xs font-black uppercase tracking-widest mt-2">
            ▶ Hear Your Answer
        </button>
    </div>

    <!-- Nav buttons + session counter -->
    <div class="flex justify-center gap-6 mb-3">
        <button onclick="speak(0.6)"    class="w-24 h-20 bg-slate-800 rounded-2xl flex items-center justify-center hover:bg-slate-700 text-2xl">🐢</button>
        <button id="recordBtn" onclick="toggleMic()"
            class="w-40 h-20 bg-red-600 rounded-2xl flex items-center justify-center hover:bg-red-500 shadow-lg shadow-red-900/20">
            <span id="recordIcon" class="text-3xl text-white">🎤</span>
        </button>
        <button onclick="nextQuestion()" class="w-24 h-20 bg-blue-600 rounded-2xl flex items-center justify-center hover:bg-blue-500 text-2xl">➡️</button>
    </div>
    <!-- Session stats -->
    <div class="flex justify-center gap-6 text-xs text-slate-600 mb-8">
        <span>✓ <span id="sesPass">0</span></span>
        <span>✗ <span id="sesFail">0</span></span>
        <span>🔥 <span id="sesStreak">0</span></span>
    </div>

    <!-- Reveal answer -->
    <div class="text-center">
        <details id="revealDetails" class="group">
            <summary class="list-none cursor-pointer text-[10px] font-black text-slate-500 hover:text-white transition uppercase tracking-widest">
                👁️ REVEAL ANSWER
            </summary>
            <div class="mt-8 p-8 bg-blue-900/20 rounded-3xl border border-blue-900/30">
                <p id="answerText" class="text-2xl text-slate-300 italic"><?php echo htmlspecialchars($targetA); ?></p>
            </div>
        </details>
    </div>

    <!-- Free-form practice -->
    <div class="mt-8 border-t border-slate-800 pt-6">
        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Practice Any Phrase</p>
        <div class="flex gap-2">
            <textarea id="practiceInput" rows="2" placeholder="Type Hungarian or English here…"
                oninput="this.rows = Math.max(2, this.value.split('\n').length)"
                class="flex-1 bg-slate-800 rounded-xl px-4 py-2 text-white text-sm border border-slate-700 focus:outline-none focus:border-blue-500 resize-none"></textarea>
            <button onclick="speakPractice()"     class="bg-slate-700 hover:bg-slate-600 rounded-xl px-3 py-2 text-xl">🔊</button>
            <button onclick="translatePractice()" class="bg-slate-700 hover:bg-slate-600 rounded-xl px-3 py-2 text-xl">🌐</button>
        </div>
        <p id="practiceTranslation" class="hidden text-slate-300 text-base mt-3 italic"></p>
    </div>

</div>

<script>
let targetQ = <?php echo json_encode($targetQ); ?>;
let targetA = <?php echo json_encode($targetA); ?>;
const who   = '<?php echo $who; ?>';

let currentMode = localStorage.getItem('hugMode') || 'pronunciation';
// Reset any stale 'bios' default — user_bios data is English, not safe as default
if (localStorage.getItem('hugCat') === 'bios') localStorage.removeItem('hugCat');
let cat = localStorage.getItem('hugCat') || 'all';
let listenMode  = localStorage.getItem('hugListen') === '1';

const indicator = document.getElementById('readyIndicator');
let isListening    = false;
let recTimeout     = null;
let advanceTimeout = null;
let listenStartTime = 0;
let isPractice      = false;
let showPlaybackWhenReady = false;   // BUG FIX: mediaRecorder.onstop is async
let questionAttempted = false;       // count first attempt only toward SRS/session

// ── Session tracking ─────────────────────────────────────────────
let sessionPass = 0, sessionFail = 0, sessionStreak = 0, sessionBestStreak = 0, sessionCount = 0;
const SESSION_SIZE = 10;

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
}

function closeSummary() {
    document.getElementById('summaryModal').classList.add('hidden');
    nextQuestion();
}

// ── Audio ────────────────────────────────────────────────────────
let audioCtx = null, analyser = null, micStream = null, volTimer = null;
let mediaRecorder = null, audioChunks = [], lastRecordingBlob = null;
const volFill = document.getElementById('volFill');

// Voice Activity Detection (VAD)
const VAD_THRESHOLD = 8;    // volume units (0–100) — below this = silence
const VAD_SILENCE   = 1200; // ms of continuous silence after speech → auto-stop
let vadLastSpeech = 0;      // timestamp of last frame above threshold
let vadSpeaked    = false;  // true once user has actually spoken this session

function startVolume() {
    navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(stream => {
        micStream = stream;
        audioCtx  = new AudioContext();
        analyser  = audioCtx.createAnalyser();
        analyser.fftSize = 512;
        audioCtx.createMediaStreamSource(stream).connect(analyser);
        const data = new Uint8Array(analyser.frequencyBinCount);
        // Reset VAD state for this mic session
        vadSpeaked  = false;
        vadLastSpeech = Date.now();

        volTimer = setInterval(() => {
            analyser.getByteFrequencyData(data);
            const vol = Math.min(100, (data.reduce((a, b) => a + b) / data.length) * 5);
            volFill.style.width = vol + '%';

            // VAD: only active after echo-guard window
            if (!isListening || (Date.now() - listenStartTime) < 700) return;

            if (vol > VAD_THRESHOLD) {
                vadLastSpeech = Date.now();
                vadSpeaked    = true;
            } else if (vadSpeaked && (Date.now() - vadLastSpeech) > VAD_SILENCE) {
                // Silence detected after speech — stop recognition
                vadSpeaked = false;
                if (isListening) recognition.stop();
            }
        }, 50);
        audioChunks = [];
        try {
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
            mediaRecorder.onstop = () => {
                lastRecordingBlob = new Blob(audioChunks, { type: 'audio/webm' });
                // BUG FIX: show playback button now that the blob is ready
                if (showPlaybackWhenReady) {
                    showPlaybackWhenReady = false;
                    document.getElementById('playbackBtn').classList.remove('hidden');
                }
            };
            mediaRecorder.start();
        } catch(e) { console.log('MediaRecorder:', e); }
    }).catch(() => {});
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
    if (micStream) { micStream.getTracks().forEach(t => t.stop()); micStream = null; }
    if (audioCtx)  { audioCtx.close(); audioCtx = null; }
}

function playMyVoice() {
    if (!lastRecordingBlob) return;
    const url = URL.createObjectURL(lastRecordingBlob);
    new Audio(url).play();
}

// ── Voice synthesis ──────────────────────────────────────────────
let huVoice = null;
function loadVoices() {
    const voices = window.speechSynthesis.getVoices();
    huVoice = voices.find(v => v.lang === 'hu-HU') ||
              voices.find(v => v.lang.startsWith('hu')) || null;
}
window.speechSynthesis.onvoiceschanged = loadVoices;
loadVoices();

function speak(rate) {
    window.speechSynthesis.cancel();
    isListening = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}
    setTimeout(() => {
        const msg = new SpeechSynthesisUtterance(targetQ);
        msg.lang = 'hu-HU';
        msg.rate = rate;
        if (huVoice) msg.voice = huVoice;
        msg.onend = () => { setTimeout(toggleMic, 350); };
        window.speechSynthesis.speak(msg);
    }, 50);
}

// ── Category filter ──────────────────────────────────────────────
function setCat(c) {
    cat = c;
    localStorage.setItem('hugCat', c);
    ['all','prep','bios'].forEach(id => {
        document.getElementById('cat-' + id).className =
            'text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full transition-all ' +
            (cat === id ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:text-white');
    });
}

// ── Listen mode ──────────────────────────────────────────────────
function toggleListenMode() {
    listenMode = !listenMode;
    localStorage.setItem('hugListen', listenMode ? '1' : '0');
    applyListenMode();
}

function applyListenMode() {
    const q   = document.getElementById('questionText');
    const btn = document.getElementById('listenModeBtn');
    if (listenMode) {
        q.classList.add('listen-blur');
        q.title = 'Click to reveal';
        q.onclick = revealQuestion;
        btn.textContent = '👁️';
        btn.classList.add('text-yellow-400');
        btn.classList.remove('text-slate-500');
    } else {
        q.classList.remove('listen-blur');
        q.title = '';
        q.onclick = null;
        btn.textContent = '👂';
        btn.classList.remove('text-yellow-400');
        btn.classList.add('text-slate-500');
    }
}

function revealQuestion() {
    const q = document.getElementById('questionText');
    q.classList.remove('listen-blur');
    q.onclick = null;
}

// ── Mode toggle ──────────────────────────────────────────────────
function setMode(mode) {
    currentMode = mode;
    localStorage.setItem('hugMode', mode);
    document.getElementById('btnPron').className =
        'text-[10px] font-black uppercase tracking-[0.3em] px-4 py-1 rounded-full transition-all ' +
        (mode === 'pronunciation' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:text-white');
    document.getElementById('btnInterview').className =
        'text-[10px] font-black uppercase tracking-[0.3em] px-4 py-1 rounded-full transition-all ' +
        (mode === 'interview' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:text-white');
    document.getElementById('listenBtnLabel').textContent =
        mode === 'pronunciation' ? 'LISTEN & REPEAT' : 'HEAR QUESTION';
}

// ── Next question ────────────────────────────────────────────────
function nextQuestion() {
    isListening       = false;
    questionAttempted = false;
    showPlaybackWhenReady = false;
    clearTimeout(recTimeout);
    clearTimeout(advanceTimeout);
    try { recognition.abort(); } catch(e) {}

    // BUG FIX: clear stale UI from previous question
    document.getElementById('inlineTranslation').classList.add('hidden');
    document.getElementById('inlineTranslation').textContent = '';
    document.getElementById('phoneticHint').classList.add('hidden');
    document.getElementById('phoneticHint').textContent = '';
    document.getElementById('practiceTranslation').classList.add('hidden');
    document.getElementById('revealDetails').removeAttribute('open');   // close REVEAL ANSWER

    fetch('?who=' + who + '&cat=' + cat + '&ajax=1')
        .then(r => r.json())
        .then(data => {
            targetQ = data.q;
            targetA = data.a;
            document.getElementById('questionText').textContent = data.q;
            document.getElementById('answerText').textContent   = data.a;
            document.getElementById('resultCard').classList.add('hidden');
            document.getElementById('matchScore').innerHTML     = '';
            document.getElementById('transcript').innerText     = '';
            document.getElementById('playbackBtn').classList.add('hidden');
            lastRecordingBlob = null;
            if (listenMode) applyListenMode();   // re-blur for new question
            speak(1.0);
        });
}

// ── Speech recognition ───────────────────────────────────────────
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const recognition = new SpeechRecognition();
recognition.lang            = 'hu-HU';
recognition.interimResults  = false;
recognition.continuous      = true;
recognition.maxAlternatives = 1;

recognition.onstart = () => {
    isListening     = true;
    listenStartTime = Date.now();
    indicator.className = 'status-dot dot-live';
    document.getElementById('recordIcon').innerText = '🎧';
    startVolume();
    recTimeout = setTimeout(() => {
        if (isListening) recognition.stop();
    }, 8000);
};

recognition.onresult = (event) => {
    if (Date.now() - listenStartTime < 700) return;
    if (!isListening) return;
    clearTimeout(recTimeout);
    recognition.stop();

    // BUG FIX: stop mediaRecorder NOW so onstop fires with current recording
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch(e) {}
    }
    stopVolume();

    const result = event.results[event.results.length - 1][0].transcript.trim();
    isListening = false;
    indicator.className = 'status-dot dot-off';
    document.getElementById('recordIcon').innerText = '🎤';

    // Practice flow — just show transcript, skip eval
    if (isPractice) {
        isPractice = false;
        const el = document.getElementById('practiceTranslation');
        el.textContent = '🎤 You said: "' + result + '"';
        el.classList.remove('hidden');
        return;
    }

    document.getElementById('resultCard').classList.remove('hidden');
    document.getElementById('transcript').innerText = `"${result}"`;
    document.getElementById('playbackBtn').classList.add('hidden');

    const scoreDisplay = document.getElementById('matchScore');
    scoreDisplay.innerHTML = '<span class="text-slate-400 text-xs">Evaluating…</span>';

    const fd = new FormData();
    fd.append('target',     targetQ);
    fd.append('transcript', result);
    fd.append('mode',       currentMode);
    fd.append('who',        who);
    fetch('eval.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.pass) {
                scoreDisplay.innerHTML =
                    '<span class="bg-green-500 px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">PASS</span>' +
                    '<p class="text-green-400 text-sm mt-2">' + data.feedback + '</p>';
                advanceTimeout = setTimeout(nextQuestion, 2500);
            } else {
                const correctLine = currentMode === 'pronunciation'
                    ? '<p class="text-slate-400 text-sm mt-3 pt-3 border-t border-slate-700">Say: <span class="text-white italic">' + targetQ + '</span></p>'
                    : '<p class="text-slate-400 text-sm mt-3 pt-3 border-t border-slate-700">Answer: <span class="text-white italic">' + targetA + '</span></p>';
                scoreDisplay.innerHTML =
                    '<span class="bg-red-500 px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">RETRY</span>' +
                    '<p class="text-red-400 text-lg mt-2">' + data.feedback + '</p>' +
                    correctLine;
                // BUG FIX: blob may not be ready yet; show button now or when onstop fires
                if (lastRecordingBlob) {
                    document.getElementById('playbackBtn').classList.remove('hidden');
                } else {
                    showPlaybackWhenReady = true;
                }
            }
            // Record first attempt only for SRS + session
            if (!questionAttempted) {
                questionAttempted = true;
                updateSession(data.pass);
                recordSRS(targetQ, data.pass);
            }
        })
        .catch(() => {
            scoreDisplay.innerHTML =
                '<span class="bg-yellow-500 px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">ERROR</span>';
        });
};

recognition.onend = () => {
    clearTimeout(recTimeout);
    isListening = false;
    isPractice  = false;     // BUG FIX: reset flag so it never leaks
    cleanupAudio();
    indicator.className = 'status-dot dot-off';
    document.getElementById('recordIcon').innerText = '🎤';
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

// ── Translation ──────────────────────────────────────────────────
function toggleTranslation() {
    const el = document.getElementById('inlineTranslation');
    if (!el.classList.contains('hidden')) { el.classList.add('hidden'); return; }
    el.textContent = 'Translating…';
    el.classList.remove('hidden');
    const fd = new FormData();
    fd.append('text', targetQ);
    fetch('translate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { el.textContent = data.translation || 'Error'; })
        .catch(() => { el.textContent = 'Translation error'; });
}

// ── Phonetic hint ────────────────────────────────────────────────
function showPhonetic() {
    const el = document.getElementById('phoneticHint');
    if (!el.classList.contains('hidden')) { el.classList.add('hidden'); return; }
    el.textContent = 'Loading phonetics…';
    el.classList.remove('hidden');
    const fd = new FormData();
    fd.append('text', targetQ);
    fetch('phonetic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { el.textContent = data.phonetic || 'Error'; })
        .catch(() => { el.textContent = 'Error loading phonetics'; });
}

// ── SRS record (fire-and-forget) ─────────────────────────────────
function recordSRS(phrase, pass) {
    const fd = new FormData();
    fd.append('phrase', phrase);
    fd.append('pass',   pass ? '1' : '0');
    fd.append('who',    who);
    fetch('record.php', { method: 'POST', body: fd }).catch(() => {});
}

// ── Practice section ─────────────────────────────────────────────
function speakPractice() {
    const text = document.getElementById('practiceInput').value.trim();
    if (!text) return;
    window.speechSynthesis.cancel();
    const msg = new SpeechSynthesisUtterance(text);
    msg.lang = 'hu-HU';
    msg.rate = 1.0;
    if (huVoice) msg.voice = huVoice;
    msg.onend = () => { isPractice = true; setTimeout(toggleMic, 350); };
    window.speechSynthesis.speak(msg);
}

function translatePractice() {
    const text = document.getElementById('practiceInput').value.trim();
    if (!text) return;
    const el = document.getElementById('practiceTranslation');
    el.textContent = 'Translating…';
    el.classList.remove('hidden');
    const fd = new FormData();
    fd.append('text', text);
    fetch('translate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { el.textContent = data.translation || 'Error'; })
        .catch(() => { el.textContent = 'Translation error'; });
}

document.getElementById('practiceInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && (e.ctrlKey || e.shiftKey)) {
        e.preventDefault();
        speakPractice();
        translatePractice();
    }
});

// ── Init ─────────────────────────────────────────────────────────
setMode(currentMode);
setCat(cat);
applyListenMode();
</script>
</body>
</html>
