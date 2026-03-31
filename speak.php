<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$text = trim($_POST['text'] ?? '');
$speed = max(0.3, min(1.5, floatval($_POST['speed'] ?? 1.0)));

if ($text === '') { echo json_encode(['error'=>'No text']); exit; }

$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['ELEVENLABS_KEY'] ?? '';
if ($apiKey === '') { echo json_encode(['error'=>'No ElevenLabs key configured']); exit; }

// Voice ID — change in .env as ELEVENLABS_VOICE
$voiceId = $env['ELEVENLABS_VOICE'] ?? 'XB0fDUnXU5powFXDhCwa';

$url = 'https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($voiceId);

$payload = json_encode([
    'text' => $text,
    'model_id' => 'eleven_turbo_v2_5',
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.75,
        'style' => 0.0,
        'use_speaker_boost' => true
    ],
    'language_code' => 'hu'
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['error' => 'ElevenLabs error', 'status' => $httpCode]);
    exit;
}

// Return audio as base64 so JS can play it
echo json_encode(['audio' => base64_encode($response), 'format' => 'audio/mpeg']);
