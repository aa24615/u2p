<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 泓盛拍卖拍品图片抓取。
 */
class HosaneHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\\.)?hosane\\.com/auction/detail/#i', $url);
    }

    public function handle(string $url): array
    {
        $html = $this->fetch($url);

        return $this->extractContentImages($html);
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        if (!preg_match_all(
            '#https://imageoss\\.hosane\\.com/upload/[^"\s<>]+\\.(?:jpg|jpeg|png|webp)(?:![^"\s<>]*)?#i',
            $html,
            $matches
        )) {
            return [];
        }

        $images = [];
        foreach ($matches[0] as $url) {
            $url = preg_replace('/!\\d+w$/', '', $url);
            $images[] = $this->cleanUrl($url);
        }

        return $this->uniqueUrls($images);
    }
}
