<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 得物商品详情图片抓取。
 *
 * 支持 dw4.co 短链（会跳转到 fast.dewu.com），从 SSR HTML 中提取轮播图。
 */
class DewuHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match(
            '#https?://(?:[\\w.-]+\\.)?(?:dw4\\.co|dewu\\.com|poizon\\.com)/#i',
            $url
        );
    }

    public function handle(string $url): array
    {
        $html = $this->fetch($url);
        $images = $this->extractContentImages($html);
        if ($images !== []) {
            return $images;
        }

        $fastUrl = $this->resolveFastProductUrl($url);
        if ($fastUrl !== null) {
            $html = $this->fetch($fastUrl);
            return $this->extractContentImages($html);
        }

        return [];
    }

    /**
     * m.dewu.com 路由页为 SPA 壳，需改抓 fast.dewu.com SSR 详情页。
     */
    protected function resolveFastProductUrl(string $url): ?string
    {
        $finalUrl = $this->client->resolveFinalUrl($url);

        if (preg_match('#https?://fast\\.dewu\\.com/page/productDetail#i', $finalUrl)) {
            return $finalUrl;
        }

        if (!preg_match('#https?://m\\.dewu\\.com/router/product/ProductDetail#i', $finalUrl)) {
            return null;
        }

        $parts = parse_url($finalUrl);
        $query = $parts['query'] ?? '';

        return 'https://fast.dewu.com/page/productDetail' . ($query !== '' ? '?' . $query : '');
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        $images = [];

        if (preg_match_all(
            '#https://webimg\\.dewucdn\\.com/(?:pro-img|stark/stark-web)/[^"\s<>]+#',
            $html,
            $matches
        )) {
            foreach ($matches[0] as $url) {
                $images[] = $this->cleanUrl($url);
            }
        }

        return $this->uniqueUrls($images);
    }
}
