<?php
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
$j = json_decode($body, true);
$mini = [
    'data' => [
        'content' => $j['data']['content'],
        'poster_url' => $j['data']['poster_url'],
    ],
];
file_put_contents(
    '/workspace/tests/fixtures/toutiao.json',
    json_encode($mini, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "saved size: " . filesize('/workspace/tests/fixtures/toutiao.json') . "\n";
// verify
$check = json_decode(file_get_contents('/workspace/tests/fixtures/toutiao.json'), true);
echo "content img count: " . substr_count($check['data']['content'], '<img') . "\n";
echo "poster: " . substr($check['data']['poster_url'], 0, 60) . "\n";
