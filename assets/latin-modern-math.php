<?php
declare(strict_types=1);

$faces = [
    'math' => [
        'url' => 'https://mirrors.ctan.org/fonts/lm-math/opentype/latinmodern-math.otf',
        'file' => 'latinmodern-math.otf',
    ],
    'regular' => [
        'url' => 'https://mirrors.ctan.org/fonts/lm/fonts/opentype/public/lm/lmroman10-regular.otf',
        'file' => 'lmroman10-regular.otf',
    ],
    'italic' => [
        'url' => 'https://mirrors.ctan.org/fonts/lm/fonts/opentype/public/lm/lmroman10-italic.otf',
        'file' => 'lmroman10-italic.otf',
    ],
    'bold' => [
        'url' => 'https://mirrors.ctan.org/fonts/lm/fonts/opentype/public/lm/lmroman10-bold.otf',
        'file' => 'lmroman10-bold.otf',
    ],
    'bolditalic' => [
        'url' => 'https://mirrors.ctan.org/fonts/lm/fonts/opentype/public/lm/lmroman10-bolditalic.otf',
        'file' => 'lmroman10-bolditalic.otf',
    ],
];

$face = isset($_GET['face']) && is_string($_GET['face']) ? $_GET['face'] : 'math';
if (!isset($faces[$face])) {
    http_response_code(404);
    exit;
}

$fontUrl = $faces[$face]['url'];
$cacheFile = __DIR__ . '/fonts/' . $faces[$face]['file'];

if (is_file($cacheFile)) {
    header('Content-Type: font/otf');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

header('Content-Type: font/otf');
header('Cache-Control: public, max-age=86400');

$fontData = false;
if (ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'follow_location' => 1,
            'user_agent' => 'Core Logic Natural Deduction Lab',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $fontData = @file_get_contents($fontUrl, false, $context);
}

if ($fontData === false && function_exists('curl_init')) {
    $curl = curl_init($fontUrl);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'Core Logic Natural Deduction Lab',
    ]);
    $fontData = curl_exec($curl);
    curl_close($curl);
}

if ($fontData === false || $fontData === '') {
    http_response_code(502);
    exit;
}

$cacheDir = dirname($cacheFile);
if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
    @file_put_contents($cacheFile, $fontData, LOCK_EX);
}

header('Content-Length: ' . strlen($fontData));
echo $fontData;
