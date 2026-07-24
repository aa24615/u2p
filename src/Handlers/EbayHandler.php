<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * eBay 商品详情图片抓取。
 *
 * 优先解析 og:image / JSON-LD；若页面可访问，再补充正文图片链接。
 */
class EbayHandler extends AbstractHandler
{
    protected array $requestHeaders = [
        'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language'           => 'en-US,en;q=0.9',
        'Sec-Fetch-Dest'            => 'document',
        'Sec-Fetch-Mode'            => 'navigate',
        'Sec-Fetch-Site'            => 'none',
        'Sec-Fetch-User'            => '?1',
        'Upgrade-Insecure-Requests' => '1',
    ];

    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\\.)?ebay\\.(?:com|[a-z]{2,3})/itm/#i', $url);
    }

    public function handle(string $url): array
    {
        $html = $this->fetch($url, $this->requestHeaders);

        return $this->extractContentImages($html);
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        $images = array_merge(
            $this->extractOgImages($html),
            $this->extractJsonLdImages($html)
        );

        if (preg_match_all(
            '#https://i\\.ebayimg\\.com/[^"\s<>]+\\.(?:jpg|jpeg|png|webp)[^"\s<>]*#i',
            $html,
            $matches
        )) {
            $images = array_merge($images, $matches[0]);
        }

        return $this->uniqueUrls($images);
    }
}
