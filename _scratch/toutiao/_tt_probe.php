<?php
$apis = [
    'https://m.toutiao.com/i7652913480072167972/info/v2/',
    'https://m.toutiao.com/i7652913480072167972/info/',
    'https://www.toutiao.com/api/pc/article/v1/pcArticleDetail?article_id=7652913480072167972',
];
$out = [];
foreach ($apis as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'Referer: https://m.toutiao.com/',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $len = strlen((string)$body);
    $out[] = "$url\n  code=$code len=$len";
    $has = (strpos((string)$body, 'content') !== false) || (strpos((string)$body, 'imageList') !== false);
    if ($has) {
        $out[] = '  >>> HAS CONTENT <<<';
        file_put_contents('/workspace/_tt_api_sample.txt', "URL: $url\nCODE: $code\nLEN: $len\n\n" . substr((string)$body, 0, 3000));
    }
    if ($len > 0 && $len < 2000) {
        $out[] = '  body: ' . substr((string)$body, 0, 300);
    }
    $out[] = '';
}
file_put_contents('/workspace/_tt_api_result.txt', implode("\n", $out));
echo "done\n";
