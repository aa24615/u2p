<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 淘宝 / 天猫商品详情图片抓取。
 *
 * 尝试从 SSR HTML 中的 imageList 与 alicdn 图床提取；若触发风控则可能返回空。
 */
class TaobaoHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match(
            '#https?://(?:item\\.taobao\\.com|detail\\.tmall\\.com|pages\\.tmall\\.com|world\\.taobao\\.com)/#i',
            $url
        );
    }

    public function handle(string $url): array
    {
        $html = $this->fetch($url, ['Referer' => 'https://www.taobao.com/']);
        $images = $this->extractContentImages($html);

        if (preg_match('/desc:\\s*[\'"]([^\'"]+)[\'"]/', $html, $match)) {
            $descUrl = $this->normalizeAbsoluteUrl($match[1]);
            if ($descUrl !== '') {
                try {
                    $descHtml = $this->fetch($descUrl, ['Referer' => $url]);
                    $images = array_merge($images, $this->extractContentImages($descHtml));
                } catch (\Throwable $e) {
                    // 详情页可能不可用，忽略
                }
            }
        }

        return $this->uniqueUrls($images);
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        if (strlen($html) < 10000) {
            return [];
        }

        $images = [];

        if (preg_match('/imageList:\\s*(\\[[^\\]]+\\])/', $html, $match)) {
            if (preg_match_all('/"(\\/\\/[^"]+|https?:\\/\\/[^"]+)"/', $match[1], $urls)) {
                foreach ($urls[1] as $url) {
                    $images[] = $this->normalizeAbsoluteUrl($url);
                }
            }
        }

        if (preg_match_all('#https?://[^"\s<>]*alicdn[^"\s<>]*\\.(?:jpg|jpeg|png|webp)[^"\s<>]*#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $images[] = $this->normalizeAbsoluteUrl($url);
            }
        }

        if (preg_match_all('#//[^"\s<>]*alicdn[^"\s<>]*\\.(?:jpg|jpeg|png|webp)[^"\s<>]*#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $images[] = $this->normalizeAbsoluteUrl($url);
            }
        }

        return array_values(array_filter($images, function (string $url): bool {
            return stripos($url, 'alicdn.com') !== false
                && stripos($url, 'icon') === false
                && stripos($url, 'logo') === false;
        }));
    }
}
