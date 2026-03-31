<?php
// Google Translate TTS proxy — better Hungarian voice than browser speechSynthesis
// Returns audio/mpeg for the given Hungarian text
header('Access-Control-Allow-Origin: *');

$text = trim($_GET['q'] ?? '');
$lang = trim($_GET['lang'] ?? 'hu');
$speed = floatval($_GET['speed'] ?? 1.0);

if ($text === '') { http_response_code(400); echo 'No text'; exit; }
if (strlen($text) > 500) { http_response_code(400); echo 'Text too long'; exit; }

// Google Translate TTS endpoint
$url = 'https://translate.google.com/translate_tts?ie=UTF-8'
     . '&tl=' . urlencode($lang)
     . '&client=tw-ob'
     . '&q=' . urlencode($text)
     . '&ttsspeed=' . ($speed < 0.7 ? '0.3' : '1');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    CURLOPT_HTTPHEADER => ['Referer: https://translate.google.com/'],
]);
$audio = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$audio) {
    http_response_code(502);
    echo 'TTS fetch failed';
    exit;
}

header('Content-Type: audio/mpeg');
header('Cache-Control: public, max-age=86400');
echo $audio;
