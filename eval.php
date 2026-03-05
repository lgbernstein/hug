<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$target     = trim($_POST['target'] ?? '');
$transcript = trim($_POST['transcript'] ?? '');
$mode       = $_POST['mode'] ?? 'pronunciation';
$who        = in_array($_POST['who'] ?? '', ['Maria','Larry','All']) ? $_POST['who'] : 'All';

if ($target === '' || $transcript === '') {
    echo json_encode(['pass'=>false,'feedback'=>'Missing data.','pronunciation_poor'=>false]); exit;
}

$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['GEMINI_KEY'] ?? '';

if ($apiKey === '') {
    $pass = strtolower($transcript) === strtolower($target);
    echo json_encode(['pass'=>$pass,'feedback'=>$pass?'Correct!':'Try again.','pronunciation_poor'=>!$pass]); exit;
}

// Build bio context for interview mode
$bio_context = '';
if ($mode === 'interview' && $who !== 'All') {
    $conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
    $who_safe = $conn->real_escape_string($who);
    $res = $conn->query("SELECT fact_label_hu, fact_value_hu FROM user_bios WHERE subject_name = '$who_safe'");
    $facts = [];
    while ($r = $res->fetch_assoc()) {
        $facts[] = $r['fact_label_hu'] . ': ' . $r['fact_value_hu'];
    }
    $conn->close();
    if ($facts) $bio_context = 'Known facts about ' . $who . ': ' . implode('; ', $facts) . '. ';
}

// Build prompt
if ($mode === 'interview') {
    $prompt = 'You are simulating a Hungarian simplified naturalization (egyszerűsített honosítás) interview examiner. '
            . 'Since 2020 these interviews are a real B1-level conversational language check — not a formality, but not an academic grammar test either. '
            . 'Real examiners are strict on CONTENT and INTELLIGIBILITY, but forgiving on grammar mechanics. '
            . "\n\n"
            . 'PASS conditions (any of these):'
            . "\n- The answer communicates the correct meaning in intelligible Hungarian, even with grammar errors (wrong case endings, wrong conjugation, awkward word order are all fine if meaning is clear)"
            . "\n- Pronunciation is imperfect but the words can be understood"
            . "\n\n"
            . 'FAIL conditions (any of these):'
            . "\n- The answer is factually wrong or contradicts known facts"
            . "\n- The answer is completely off-topic or nonsensical"
            . "\n- The answer is in English or mostly English (giving up on Hungarian)"
            . "\n- The answer is so garbled it cannot be understood at all"
            . "\n- The answer is silence or just filler words with no real content"
            . "\n\n"
            . 'Question asked: "' . $target . '". '
            . 'Learner said: "' . $transcript . '". '
            . $bio_context
            . "\n\n"
            . 'Give ONE sentence of feedback in English. If they passed with grammar errors, briefly name the main error as coaching. If they failed, say specifically why. Keep it direct, not over-encouraging. '
            . 'Set pronunciation_poor:true only if the speech was genuinely hard to understand. '
            . 'Reply ONLY with valid JSON: {"pass":true/false,"feedback":"one sentence","pronunciation_poor":true/false}';
} else {
    $prompt = 'You are simulating a Hungarian simplified naturalization interview examiner evaluating pronunciation. '
            . 'The learner was asked to say: "' . $target . '". '
            . 'Speech recognition heard: "' . $transcript . '". '
            . "\n\n"
            . 'PASS: The words are recognisably the right Hungarian words — a Hungarian speaker would understand them. Minor accent or mispronunciation is fine. '
            . 'FAIL: Key words are missing, replaced with wrong words, or so mispronounced they would confuse a listener. '
            . "\n\n"
            . 'Give ONE sentence of feedback in English. If passed with errors, briefly name the main pronunciation issue as coaching. Keep it direct. '
            . 'Reply ONLY with valid JSON: {"pass":true/false,"feedback":"one sentence in English","pronunciation_poor":true/false}';
}

$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 2048]
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

if ($httpCode !== 200 || !$response) {
    $pass = strtolower($transcript) === strtolower($target);
    echo json_encode(['pass'=>$pass,'feedback'=>$pass?'Correct!':'Try again.','pronunciation_poor'=>!$pass]); exit;
}

$data = json_decode($response, true);
$content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$content = preg_replace('/^```json\s*/i', '', trim($content));
$content = preg_replace('/\s*```$/i',    '', trim($content));
$result  = json_decode(trim($content), true);

if (isset($result['pass']) && isset($result['feedback'])) {
    if (!isset($result['pronunciation_poor'])) $result['pronunciation_poor'] = false;
    echo json_encode($result);
} else {
    $pass = stripos($content, 'true') !== false;
    echo json_encode(['pass'=>$pass,'feedback'=>$pass?'Well done!':'Try again.','pronunciation_poor'=>!$pass]);
}
