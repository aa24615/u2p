<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * Veer / ImageShop 图片详情抓取。
 *
 * veer.com 会跳转到 imageshop.com；通过 /ajax/image/view 接口获取预览图，
 * 优先返回无 Watermark 字段的 oss 地址。
 */
class VeerHandler extends AbstractHandler
{
    protected string $apiTemplate = 'https://www.imageshop.com/ajax/image/view?%s';

    public function supports(string $url): bool
    {
        return (bool) preg_match(
            '#https?://(?:www\\.)?(?:veer\\.com|imageshop\\.com)/photo/#i',
            $url
        );
    }

    public function handle(string $url): array
    {
        $photoId = $this->extractPhotoId($url);
        if ($photoId === '') {
            return [];
        }

        $html = $this->fetch($url);
        $uuid = $this->extractUuid($html);
        if ($uuid === '') {
            return [];
        }

        $query = http_build_query([
            'imageResId' => $photoId,
            'resId'      => $photoId,
            'anonyUid'   => $uuid,
            'favorid'    => '',
            'key'        => '',
        ]);

        $body = $this->fetch(sprintf($this->apiTemplate, $query), [
            'Referer' => 'https://www.imageshop.com/photo/' . $photoId . '.html',
            'Accept'  => 'application/json',
        ]);

        return $this->extractContentImages($body);
    }

    public function extractPhotoId(string $url): string
    {
        if (preg_match('#/photo/(\\d+)#i', $url, $match)) {
            return $match[1];
        }

        return '';
    }

    public function extractUuid(string $html): string
    {
        if (preg_match('/"uuid"\\s*:\\s*"([^"]+)"/', $html, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function extractContentImages(string $jsonBody): array
    {
        $payload = json_decode($jsonBody, true);
        if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
            return [];
        }

        $data = $payload['data'];
        $preferredKeys = [
            'ossMediumNoWatermark',
            'oss640NoWatermark',
            'oss400',
            'oss176',
            'previewUrl',
            'ossJpg',
        ];

        $images = [];
        foreach ($preferredKeys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $images[] = $this->normalizeAbsoluteUrl($data[$key]);
            }
        }

        foreach ($data as $key => $value) {
            if (!is_string($value) || stripos($key, 'oss') !== 0 || stripos($key, 'Watermark') !== false) {
                continue;
            }
            $images[] = $this->normalizeAbsoluteUrl($value);
        }

        return $this->uniqueUrls($images);
    }
}
