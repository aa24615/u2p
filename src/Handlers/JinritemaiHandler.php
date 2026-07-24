<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 抖音商城（好货）商品详情图片抓取。
 *
 * 页面为 Gecko/PIA 壳，尝试从 HTML 内嵌数据与 CDN 链接中提取商品图。
 */
class JinritemaiHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:haohuo\\.)?jinritemai\\.com/#i', $url);
    }

    public function handle(string $url): array
    {
        $productId = $this->extractProductId($url);
        $targets = [$url];
        if ($productId !== '') {
            $targets[] = 'https://haohuo.jinritemai.com/views/product/item2?id=' . $productId;
            $targets[] = 'https://haohuo.jinritemai.com/ecommerce/trade/detail/index.html?id=' . $productId;
        }

        $images = [];
        foreach (array_unique($targets) as $target) {
            try {
                $html = $this->fetch($target, ['Referer' => 'https://haohuo.jinritemai.com/']);
                $images = array_merge($images, $this->extractContentImages($html));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $this->uniqueUrls($images);
    }

    public function extractProductId(string $url): string
    {
        if (preg_match('#[?&]id=(\\d+)#', $url, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        $images = [];

        if (preg_match_all(
            '#https?://[^"\s<>]*(?:douyinpic|ecom-shop-material|jinritemai|byteimg)[^"\s<>]*\\.(?:jpg|jpeg|png|webp)[^"\s<>]*#i',
            $html,
            $matches
        )) {
            foreach ($matches[0] as $url) {
                $images[] = $this->cleanUrl(str_replace('\\/', '/', $url));
            }
        }

        if (preg_match_all('#//[^"\s<>]*(?:douyinpic|ecom-shop-material)[^"\s<>]*\\.(?:jpg|jpeg|png|webp)[^"\s<>]*#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $images[] = $this->normalizeAbsoluteUrl($url);
            }
        }

        return $images;
    }
}
