<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 微信公众号文章图片抓取。
 *
 * 公众号文章正文图片采用懒加载：图片真实地址写在 <img data-src> 中，
 * 而非 src；图床域名为 mmbiz.qpic.cn 且带 from=appmsg 参数。
 * 图片画廊类文章则通过 window.picture_page_info_list 注入图片地址。
 * 头像、二维码、表情等非正文图片通过 class 与 URL 特征排除。
 *
 * 用法：
 *   $handler = new WeChatHandler();
 *   $images  = $handler->handle('https://mp.weixin.qq.com/s/xxxx');
 *   // 如需同时获取封面：
 *   $handler->withCover()->handle($url);
 */
class WeChatHandler extends AbstractHandler
{
    /**
     * 需要排除的非正文图片 class（头像 / 二维码 / 表情等）。
     */
    protected array $excludeClasses = [
        'wx_follow_avatar_pic',
        'jump_author_avatar',
        'qr_code_pc_img',
        'js_pc_qr_code_img',
        'js_pc_weapp_code_img',
        'js_qrcode_img',
        'jump_wx_qrcode_img',
        'icon_emotion_single',
        'we-emoji',
    ];

    /**
     * 是否将封面图（msg_cdn_url）置于结果最前。
     */
    protected bool $withCover = false;

    public function withCover(bool $withCover = true): self
    {
        $this->withCover = $withCover;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://mp\.weixin\.qq\.com/s#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $url): array
    {
        $html = $this->fetch($url);

        $images = $this->extractContentImages($html);

        if ($this->withCover) {
            $cover = $this->extractCover($html);
            if ($cover !== '') {
                array_unshift($images, $cover);
                $images = array_values(array_unique($images));
            }
        }

        return $images;
    }

    /**
     * 从公众号文章 HTML 中提取正文图片链接。
     *
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        $images = $this->extractDomImages($html);
        $picturePageImages = $this->extractPicturePageImages($html);

        return array_values(array_unique(array_merge($images, $picturePageImages)));
    }

    /**
     * 从正文 img 节点提取图片（传统图文文章）。
     *
     * @return string[]
     */
    protected function extractDomImages(string $html): array
    {
        $dom = $this->loadDom($html);
        $xpath = new \DOMXPath($dom);

        $images = [];
        $nodes = $xpath->query('//img');
        foreach ($nodes as $img) {
            /** @var \DOMElement $img */
            $src = $this->resolveSrc($img);
            if ($src === '') {
                continue;
            }
            if (!$this->isContentImage($img, $src)) {
                continue;
            }
            $src = $this->cleanUrl($src);
            if ($src !== '') {
                $images[] = $src;
            }
        }

        return $images;
    }

    /**
     * 从 picture_page_info_list 提取图片（图片画廊类文章）。
     *
     * 此类文章正文图片通过 JS 变量注入，页面中无 data-src img 节点。
     *
     * @return string[]
     */
    protected function extractPicturePageImages(string $html): array
    {
        if (!preg_match('/window\.picture_page_info_list\s*=\s*\[/s', $html, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $sub = substr($html, $match[0][1]);
        if (!preg_match('/\];/s', $sub, $end, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $block = substr($sub, 0, $end[0][1] + 2);
        if (!preg_match_all(
            "/width:\s*'[^']*'\s*\*\s*1,\s*height:\s*'[^']*'\s*\*\s*1,\s*cdn_url:\s*'([^']+)'/s",
            $block,
            $matches
        )) {
            return [];
        }

        $images = [];
        foreach ($matches[1] as $url) {
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = $this->cleanUrl($url);
            if ($url !== '' && stripos($url, 'mmbiz.qpic.cn') !== false) {
                $images[] = $url;
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * 判断某 img 是否为正文图片。
     *
     * 规则：
     *  - 排除头像 / 二维码 / 表情等 class；
     *  - 仅保留 mmbiz.qpic.cn 图床；
     *  - class 含 rich_pages / wxw-img，或 URL 含 from=appmsg 视为正文。
     */
    protected function isContentImage(\DOMElement $img, string $src): bool
    {
        $class = $img->getAttribute('class');

        foreach ($this->excludeClasses as $exclude) {
            if (stripos($class, $exclude) !== false) {
                return false;
            }
        }

        if (stripos($src, 'mmbiz.qpic.cn') === false) {
            return false;
        }

        if (stripos($class, 'rich_pages') !== false || stripos($class, 'wxw-img') !== false) {
            return true;
        }

        if (stripos($src, 'from=appmsg') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 从 HTML 中的 msg_cdn_url 变量提取封面图地址。
     */
    protected function extractCover(string $html): string
    {
        if (preg_match('/msg_cdn_url\s*=\s*["\']([^"\']+)["\']/', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
}
