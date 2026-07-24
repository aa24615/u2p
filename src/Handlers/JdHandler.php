<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 京东商品详情图片抓取。
 *
 * PC 页为 SPA，商品图走移动端 graphext 接口获取详情图。
 */
class JdHandler extends AbstractHandler
{
    protected string $graphextTemplate = 'https://in.m.jd.com/product/graphext/%s.html';

    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://item\\.jd\\.com/\\d+#i', $url);
    }

    public function handle(string $url): array
    {
        $sku = $this->extractSku($url);
        if ($sku === '') {
            return [];
        }

        $apiUrl = sprintf($this->graphextTemplate, $sku);
        $html = $this->fetch($apiUrl, [
            'Referer' => 'https://item.jd.com/' . $sku . '.html',
        ]);

        return $this->extractContentImages($html);
    }

    public function extractSku(string $url): string
    {
        if (preg_match('#item\\.jd\\.com/(\\d+)#i', $url, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        if (!preg_match_all('#(?:https:)?//img\d+\.360buyimg\.com/[^"\s<>]+#', $html, $matches)) {
            return [];
        }

        $images = [];
        foreach ($matches[0] as $url) {
            $url = preg_replace('/[);>\'"\s]+$/', '', $url);
            $url = $this->normalizeAbsoluteUrl($url);
            if (stripos($url, '/imagetools/') !== false) {
                continue;
            }
            if (!preg_match('/\\.(jpg|jpeg|png|webp|dpg)(?:\\?|$)/i', $url)) {
                continue;
            }
            $images[] = $this->cleanUrl($url);
        }

        return $this->uniqueUrls($images);
    }
}
