<?php
// 第二篇文章 7652651188260700699
$ch = curl_init('https://m.toutiao.com/i7652651188260700699/info/v2/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        'Referer: https://m.toutiao.com/',
    ],
]);
$body = curl_exec($ch);
curl_close($ch);
$json = json_decode($body, true);
echo "json valid: " . ($json ? 'YES' : 'NO') . "\n";
if (!$json) { echo "raw: " . substr($body,0,500) . "\n"; exit; }
$content = $json['data']['content'] ?? '';
echo "content len: " . strlen($content) . "\n";
echo "img tag count: " . substr_count($content, '<img') . "\n";
echo "toutiaoimg count: " . substr_count($content, 'toutiaoimg') . "\n";
echo "pgc-image count: " . substr_count($content, 'pgc-image') . "\n";
echo "=== poster_url ===\n";
echo ($json['data']['poster_url'] ?? 'none') . "\n";
echo "=== content first 3000 ===\n";
echo substr($content, 0, 3000) . "\n";
echo "=== all urls ===\n";
preg_match_all('/https?:\/\/[^\s"\'<>\\)]+/i', $content, $m);
foreach (array_slice($m[0], 0, 20) as $u) echo "  $u\n";
