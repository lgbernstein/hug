<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$text = trim($_POST['text'] ?? '');
if ($text === '') { echo json_encode(['translation' => '']); exit; }

$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['GEMINI_KEY'] ?? '';
if ($apiKey === '') { echo json_encode(['translation' => 'No API key configured']); exit; }

$prompt = 'Detect the language of this text. If it is Hungarian, translate it to English. If it is English, translate it to Hungarian. Reply with ONLY the translation, nothing else: "' . $text . '"';
$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
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
curl_close($ch);

if ($httpCode !== 200 || !$response) { echo json_encode(['translation' => 'API error']); exit; }

$data = json_decode($response, true);
$translation = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Error';
echo json_encode(['translation' => trim($translation)]);
