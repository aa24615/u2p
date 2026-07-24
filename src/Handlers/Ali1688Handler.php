<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 1688 商品详情图片抓取。
 *
 * PC 页常触发风控，故同时尝试移动端页面，并从 HTML / 内嵌 JSON 中提取 alicdn 图床地址。
 */
class Ali1688Handler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:detail|m)\.1688\.com/offer/#i', $url);
    }

    public function handle(string $url): array
    {
        $offerId = $this->extractOfferId($url);
        if ($offerId === '') {
            return [];
        }

        $headers = ['Referer' => 'https://detail.1688.com/offer/' . $offerId . '.html'];
        $pages = [
            $this->fetch($url, $headers),
            $this->fetch('https://m.1688.com/offer/' . $offerId . '.html', $headers),
            $this->fetch('https://detail.1688.com/offer/' . $offerId . '.html', $headers),
        ];

        $images = [];
        foreach ($pages as $html) {
            $images = array_merge($images, $this->extractContentImages($html));
        }

        return $this->uniqueUrls($images);
    }

    public function extractOfferId(string $url): string
    {
        if (preg_match('#/offer/(?:\\d+/)*?(\\d{8,})#i', $url, $match)) {
            return $match[1];
        }

        return '';
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

        if (preg_match_all('#https?://[^"\s<>]+?\\.(?:jpg|jpeg|png|webp)(?:\\?[^"\s<>]*)?#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                if (stripos($url, 'alicdn.com') !== false) {
                    $images[] = $this->normalizeAbsoluteUrl($url);
                }
            }
        }

        if (preg_match_all('#//[^"\s<>]+?\\.(?:jpg|jpeg|png|webp)(?:\\?[^"\s<>]*)?#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                if (stripos($url, 'alicdn.com') !== false) {
                    $images[] = $this->normalizeAbsoluteUrl($url);
                }
            }
        }

        if (preg_match_all('#fullPathImageURI["\']?\\s*[:=]\\s*["\']([^"\']+)["\']#i', $html, $matches)) {
            foreach ($matches[1] as $url) {
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
