<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$text = trim($_POST['text'] ?? '');
$speed = max(0.3, min(1.5, floatval($_POST['speed'] ?? 1.0)));
if ($text === '') { echo json_encode(['error'=>'No text']); exit; }

$env = parse_ini_file(__DIR__ . '/.env');

// Try ElevenLabs first if key is configured
$apiKey = $env['ELEVENLABS_KEY'] ?? '';
if ($apiKey !== '') {
    $voiceId = $env['ELEVENLABS_VOICE'] ?? 'XB0fDUnXU5powFXDhCwa';
    $ch = curl_init('https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($voiceId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'text' => $text, 'model_id' => 'eleven_turbo_v2_5',
            'voice_settings' => ['stability'=>0.5,'similarity_boost'=>0.75,'style'=>0.0,'use_speaker_boost'=>true],
            'language_code' => 'hu'
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'xi-api-key: ' . $apiKey],
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $response) {
        echo json_encode(['audio' => base64_encode($response), 'format' => 'audio/mpeg']);
        exit;
    }
}

// Google Translate TTS — free, good quality Hungarian
$gttsUrl = 'https://translate.google.com/translate_tts?ie=UTF-8'
    . '&tl=hu&client=tw-ob'
    . '&q=' . urlencode($text)
    . '&ttsspeed=' . ($speed < 0.7 ? '0.3' : '1');

$ch = curl_init($gttsUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    CURLOPT_HTTPHEADER => ['Referer: https://translate.google.com/'],
]);
$audio = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $audio && strlen($audio) > 100) {
    echo json_encode(['audio' => base64_encode($audio), 'format' => 'audio/mpeg']);
    exit;
}

echo json_encode(['error' => 'TTS unavailable']);
