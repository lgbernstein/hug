<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$text = trim($_POST['text'] ?? '');
if ($text === '') { echo json_encode(['phonetic' => '']); exit; }

$env    = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['GEMINI_KEY'] ?? '';
if ($apiKey === '') { echo json_encode(['phonetic' => 'No API key configured']); exit; }

$prompt = 'You are a Hungarian pronunciation teacher helping an English-speaking beginner. '
        . 'Generate a simple phonetic guide for this Hungarian text: "' . $text . '". '
        . 'Write each word followed by its approximate English sound in parentheses. '
        . 'Example format: Jó (yoh) reggelt (REH-gelt) kívánok (KEE-vah-nok) '
        . 'Use capital letters for stressed syllables. Keep it very short. '
        . 'Reply with ONLY the phonetic guide, nothing else.';

$payload = json_encode([
    'contents'       => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 200]
]);

$ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 10
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    $err = $response ? json_decode($response, true) : null;
    $msg = $err['error']['message'] ?? ($curlErr ?: "HTTP $httpCode");
    echo json_encode(['phonetic' => 'API error: ' . $msg]);
    exit;
}

$data     = json_decode($response, true);
$phonetic = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Error';
echo json_encode(['phonetic' => trim($phonetic)]);
