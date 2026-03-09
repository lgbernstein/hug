<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$target     = trim($_POST['target'] ?? '');
$transcript = trim($_POST['transcript'] ?? '');
$mode       = $_POST['mode'] ?? 'pronunciation';
$who        = in_array($_POST['who'] ?? '', ['Maria','Larry','All']) ? $_POST['who'] : 'All';
$strictness = max(1, min(5, (int)($_POST['strictness'] ?? 2)));
$alternatives = json_decode($_POST['alternatives'] ?? '[]', true);

// Strictness levels adjust grading criteria
$strictnessGuide = [
    1 => 'Be lenient. Pass if the learner uses the right key vocabulary words and communicates the general idea, even with grammar errors or wrong word order. Ignore case endings and conjugation. Only fail for completely wrong meaning, English-only responses, or silence.',
    2 => 'Be moderately lenient. Pass if the learner conveys the right meaning with mostly correct vocabulary. Ignore minor grammar issues like case endings and word order. Fail for missing key words, wrong meaning, or mostly English.',
    3 => 'Balanced grading. Pass if meaning is clear AND key vocabulary is correct. Note grammar issues in feedback but only fail for wrong meaning, missing key words, or unintelligible speech.',
    4 => 'Be strict. Require correct vocabulary, reasonable grammar, and intelligible pronunciation. Fail for significant grammar errors (wrong verb form, missing key case endings) even if meaning is guessable.',
    5 => 'Exam-level strictness simulating a real B1 naturalization interview. Require correct vocabulary, proper grammar, good sentence structure, and clear pronunciation. Only pass responses that would satisfy an actual examiner.',
];

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
    $facts = [];
    if (!$conn->connect_error) {
        $who_safe = $conn->real_escape_string($who);
        $res = $conn->query("SELECT fact_label_hu, fact_value_hu FROM user_bios WHERE subject_name = '$who_safe'");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $facts[] = $r['fact_label_hu'] . ': ' . $r['fact_value_hu'];
            }
        }
        $conn->close();
    }
    if ($facts) $bio_context = 'Known facts about ' . $who . ': ' . implode('; ', $facts) . '. ';
}

// Build prompt
if ($mode === 'interview') {
    $prompt = 'You are a friendly Hungarian language coach helping a learner prepare for the simplified naturalization (egyszerűsített honosítás) interview. '
            . 'Your job is to help them LEARN, not just grade them. Every response should teach something.' . "\n\n"
            . 'GRADING LEVEL (' . $strictness . '/5): ' . $strictnessGuide[$strictness] . "\n\n"
            . 'CONTEXT:'
            . "\n- These interviews are B1-level conversational checks — natural speech, not academic grammar"
            . "\n- In Hungarian, family name comes FIRST (Bernstein Lawrence, not Lawrence Bernstein)"
            . "\n- Some prompts are greetings/commands, not questions — a natural conversational reply is correct"
            . "\n\n"
            . 'PASS if the learner:'
            . "\n- Communicates the right idea in intelligible Hungarian, even with grammar errors"
            . "\n- Responds naturally to greetings/commands (e.g. \"köszönöm\" to \"Jöjjön be!\")"
            . "\n- Gets the meaning across even with wrong tense, case endings, or word order"
            . "\n\n"
            . 'FAIL only if the learner:'
            . "\n- Says something factually wrong (wrong name, wrong city, etc.)"
            . "\n- Responds in English or gives up"
            . "\n- Says something with zero connection to the prompt"
            . "\n- Is completely unintelligible"
            . "\n\n"
            . 'Prompt: "' . $target . '"' . "\n"
            . 'Learner said: "' . $transcript . '"' . "\n";

    if ($alternatives && count($alternatives) > 1) {
        $prompt .= 'Speech recognition alternatives (consider ALL of these — pick the one closest to a valid Hungarian answer): ' . implode(' | ', $alternatives) . "\n"
                . 'IMPORTANT: If ANY alternative is a reasonable Hungarian response, grade based on the best one, not the first one.' . "\n";
    }

    // If we have an expected Hungarian answer from the DB, use it as guidance (not strict)
    $expected_hu = trim($_POST['expected_hu'] ?? '');
    if ($expected_hu) {
        $prompt .= 'Expected answer (use as guidance, not strict match): "' . $expected_hu . '"' . "\n"
                . 'The learner does NOT need to match this exactly — any answer that conveys similar meaning is fine. '
                . 'But use this as the basis for the "correct" field in your response.' . "\n";
    }

    $prompt .= $bio_context
            . "\n\n"
            . 'FEEDBACK RULES:'
            . "\n- ALWAYS include \"correct\": the ideal Hungarian answer an interviewer would expect"
            . "\n- Be specific about WHAT was wrong. Name the exact word, suffix, or conjugation error."
            . "\n- Format: what you said → what it should be, plus a brief reason."
            . "\n- Examples: \"utazni → utaztam (past tense needed)\", \"autó → autóval (need -val suffix = by/with)\", \"Perfect answer!\""
            . "\n- Keep it to 1-2 short sentences max. Direct and helpful."
            . "\n- Do NOT repeat the full correct answer in feedback — it goes in the \"correct\" field"
            . "\n- If multiple errors, mention the most important one or two"
            . "\n\n"
            . 'Reply ONLY with valid JSON: {"pass":true/false,"feedback":"coaching feedback in English with Hungarian corrections","correct":"the ideal complete Hungarian answer","pronunciation_poor":true/false}';
} else {
    $prompt = 'You are simulating a Hungarian simplified naturalization interview examiner evaluating pronunciation. '
            . 'The learner was asked to say: "' . $target . '". '
            . 'Speech recognition heard: "' . $transcript . '". ';

    if ($alternatives && count($alternatives) > 1) {
        $prompt .= "\n" . 'Speech recognition alternatives (consider ALL — use the best match): ' . implode(' | ', $alternatives) . '. '
                . 'If ANY alternative closely matches the target, grade based on that one. ';
    }

    $prompt .= "\n\n"
            . 'GRADING LEVEL (' . $strictness . '/5): ' . $strictnessGuide[$strictness] . "\n\n"
            . 'PASS: The words are recognisably the right Hungarian words — a Hungarian speaker would understand them. Minor accent or mispronunciation is fine. '
            . 'FAIL: Key words are missing, replaced with wrong words, or so mispronounced they would confuse a listener. '
            . "\n\n"
            . 'Be specific about what was wrong. Name the exact word that was mispronounced or missing, and show the correction (e.g. "hívnak → hívják (á sound, not a)" or "missed üljön at the end"). 1-2 short sentences max. '
            . 'Reply ONLY with valid JSON: {"pass":true/false,"feedback":"short feedback","correct":"the exact target phrase","pronunciation_poor":true/false}';
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
