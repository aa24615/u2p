<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 站酷作品图片抓取。
 *
 * 从 __NEXT_DATA__ 与 og:image 中提取 img.zcool.cn/community 正文图，过滤头像级缩略图。
 */
class ZcoolHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\\.)?zcool\\.com\\.cn/work/#i', $url);
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
        $images = array_merge(
            $this->extractOgImages($html),
            $this->extractJsonLdImages($html)
        );

        $nextData = $this->extractNextData($html);
        if ($nextData !== null) {
            $json = json_encode($nextData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json) && preg_match_all(
                '#https://img\\.zcool\\.cn/community/[^"\s<>]+#',
                $json,
                $matches
            )) {
                foreach ($matches[0] as $url) {
                    $images[] = $this->normalizeAbsoluteUrl(str_replace('\\u0026', '&', $url));
                }
            }
        }

        $images = array_values(array_filter($images, function (string $url): bool {
            if (stripos($url, 'img.zcool.cn/community/') === false) {
                return false;
            }
            if (preg_match('/resize,m_fill,w_(?:1|16|32|48|64|80|160)\\b/', $url)) {
                return false;
            }

            return true;
        }));

        return $this->uniqueUrls($images);
    }
}
