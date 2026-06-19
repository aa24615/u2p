<?php
$ch = curl_init('https://m.toutiao.com/i7652913480072167972/info/v2/');
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
file_put_contents('/workspace/_tt_full_api.json', $body);
$json = json_decode($body, true);
echo "json valid: " . ($json ? 'YES' : 'NO') . "\n";
if ($json) {
    echo "top keys: " . implode(',', array_keys($json)) . "\n";
    if (isset($json['data'])) {
        echo "data keys: " . implode(',', array_keys($json['data'])) . "\n";
    }
    // look for content
    $content = $json['data']['content'] ?? '';
    echo "content len: " . strlen($content) . "\n";
    echo "content has img: " . substr_count($content, '<img') . "\n";
    // extract image urls from content
    preg_match_all('/https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|webp|gif)[^\s"\'<>]*/i', $content, $m);
    echo "img urls found: " . count($m[0]) . "\n";
    foreach (array_slice($m[0], 0, 5) as $u) echo "  $u\n";
    // imageList
    if (isset($json['data']['imageList'])) {
        echo "imageList count: " . count($json['data']['imageList']) . "\n";
    }
}
