<?php
$b = file_get_contents('/workspace/_tt_full_api.json');
$j = json_decode($b, true);
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
