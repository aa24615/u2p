<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Zyan\U2P\HttpClient;

$urls = [
    '1688' => 'https://detail.1688.com/offer/568516651500.html',
    'dw4_short' => 'https://dw4.co/t/A/1vrIi0NUy',
    'jinritemai' => 'https://haohuo.jinritemai.com/ecommerce/trade/detail/index.html?id=3831479699402522995',
    'jd' => 'https://item.jd.com/10174996096733.html',
    'taobao' => 'https://item.taobao.com/item.htm?id=1062941248301',
    'ebay' => 'https://www.ebay.com/itm/267149219732',
    'hosane' => 'https://www.hosane.com/auction/detail/p13090032',
    'veer' => 'https://www.veer.com/photo/107294206.html',
    'zcool' => 'https://www.zcool.com.cn/work/ZMjIxMzQ1MTI=.html',
];

function analyzeHtml(string $html): array
{
    $imgCount = 0;
    $imgSamples = [];
    if (preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
        $imgCount = count($m[0]);
        foreach (array_slice($m[0], 0, 5) as $tag) {
            if (preg_match('/\b(?:src|data-src|data-original)=["\']([^"\']+)["\']/i', $tag, $sm)) {
                $imgSamples[] = mb_substr($sm[1], 0, 120);
            }
        }
    }

    $ogImages = [];
    if (preg_match_all('/<meta[^>]+property=["\']og:image(?::[^"\']*)?["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $ogImages = array_merge($ogImages, $m[1]);
    }
    if (preg_match_all('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::[^"\']*)?["\']/i', $html, $m)) {
        $ogImages = array_merge($ogImages, $m[1]);
    }

    $jsonLdImages = [];
    if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
        foreach ($m[1] as $block) {
            if (preg_match('/"image"\s*:\s*"([^"]+)"/', $block, $im)) {
                $jsonLdImages[] = mb_substr($im[1], 0, 120);
            } elseif (preg_match('/"image"\s*:\s*\[\s*"([^"]+)"/', $block, $im)) {
                $jsonLdImages[] = mb_substr($im[1], 0, 120);
            }
        }
    }

    $scriptState = [];
    $patterns = [
        'window.__INITIAL_STATE__' => '/window\.__INITIAL_STATE__\s*=\s*/',
        'window.__NUXT__' => '/window\.__NUXT__\s*=\s*/',
        'window.g_initialData' => '/window\.g_initialData\s*=\s*/',
        'window.pageData' => '/window\.pageData\s*=\s*/',
        'window.rawData' => '/window\.rawData\s*=\s*/',
        '__NEXT_DATA__' => '/id=["\']__NEXT_DATA__["\']/',
        'TShop.Setup' => '/TShop\.Setup\s*\(/',
        'skuJson' => '/skuJson\s*[:=]/',
        'imageList' => '/["\']imageList["\']\s*:/',
        'gallery' => '/["\']gallery["\']\s*:/',
        'window._CONFIG' => '/window\._CONFIG\s*=/',
    ];
    foreach ($patterns as $name => $re) {
        if (preg_match($re, $html)) {
            $scriptState[] = $name;
        }
    }

    $apiPatterns = [];
    if (preg_match_all('/https?:\/\/[^"\'\s<>]{10,200}/i', $html, $mm)) {
        $candidates = [];
        foreach ($mm[0] as $u) {
            if (preg_match('/api|graphql|\.json|details|offer|item|product|auction|photo/i', $u)) {
                $candidates[] = $u;
            }
        }
        $apiPatterns['embedded_urls'] = array_values(array_unique(array_slice($candidates, 0, 12)));
    }
    if (preg_match_all('/["\'](\/api\/[^"\']{5,120})["\']/i', $html, $mm)) {
        $apiPatterns['quoted_api_paths'] = array_values(array_unique(array_slice($mm[1], 0, 12)));
    }

    $title = '';
    if (preg_match('/<title[^>]*>([^<]*)<\/title>/i', $html, $tm)) {
        $title = trim(html_entity_decode($tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $isLikelyChallenge = (bool) preg_match('/验证码|captcha|punish|x5sec|baxia|login\.taobao|passport|slide\.verify/i', $html);

    $hasImages = $imgCount > 0 || count($ogImages) > 0 || count($jsonLdImages) > 0
        || preg_match('/["\'](https?:\/\/[^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\']/i', $html);

    return [
        'body_length' => strlen($html),
        'title' => mb_substr($title, 0, 200),
        'has_image_signals' => $hasImages,
        'img_tag_count' => $imgCount,
        'img_samples' => $imgSamples,
        'og_image' => array_values(array_unique(array_slice($ogImages, 0, 5))),
        'json_ld_image' => array_values(array_unique($jsonLdImages)),
        'script_vars' => $scriptState,
        'api_patterns' => $apiPatterns,
        'likely_antibot' => $isLikelyChallenge,
    ];
}

function probe(string $label, string $url, HttpClient $client): array
{
    $guzzle = $client->getGuzzle();
    $defaultHeaders = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
    ];

    $result = [
        'label' => $label,
        'request_url' => $url,
        'final_url' => $url,
        'status' => null,
        'error' => null,
        'redirect_chain' => [],
        'analysis' => null,
    ];

    try {
        $response = $guzzle->request('GET', $url, [
            RequestOptions::HEADERS => $defaultHeaders,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
            ],
        ]);
        $result['status'] = $response->getStatusCode();
        $history = $response->getHeader('X-Guzzle-Redirect-History');
        $result['redirect_chain'] = $history;
        $final = $response->getHeader('X-Guzzle-Redirect-URL');
        $result['final_url'] = $final[0] ?? ($history ? end($history) : $url);
        $html = (string) $response->getBody();
        $result['analysis'] = analyzeHtml($html);
    } catch (RequestException $e) {
        $result['error'] = $e->getMessage();
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $result['status'] = $response->getStatusCode();
            $html = (string) $response->getBody();
            $result['analysis'] = analyzeHtml($html);
        }
    } catch (\Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}

$client = new HttpClient();
$all = [];

foreach ($urls as $label => $url) {
    $label = (string) $label;
    fwrite(STDERR, "Probing {$label}...\n");
    $all[$label] = probe($label, $url, $client);
    usleep(800000);
}

file_put_contents(__DIR__ . '/_probe_raw.json', json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "OK\n";
