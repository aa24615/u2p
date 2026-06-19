<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Symfony\Component\DomCrawler\Crawler;
use Zyan\U2P\AbstractHandler;

/**
 * 通用网页图片抓取（兜底处理器）。
 *
 * 适用于任意网页：解析页面源码中的 <img> 标签，
 * 优先取懒加载地址（data-src 等），自动解析相对路径，
 * 过滤 base64、空白等无效地址。
 *
 * 对于通过 ajax 接口返回图片的网站，建议编写对应的专项 Handler。
 */
class GenericHandler extends AbstractHandler
{
    /**
     * 需要排除的地址前缀 / 特征。
     */
    protected array $excludePatterns = [
        'data:image/',
        'about:blank',
        'javascript:',
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $url): array
    {
        $html = $this->fetch($url);

        return $this->extractImages($html, $url);
    }

    /**
     * 从任意 HTML 中提取图片链接。
     *
     * @param string $html     页面 HTML
     * @param string $baseUrl  页面地址，用于解析相对路径
     *
     * @return string[]
     */
    public function extractImages(string $html, string $baseUrl = ''): array
    {
        $crawler = $this->loadDom($html);

        $images = [];
        $crawler->filter('img')->each(function (Crawler $node) use (&$images, $baseUrl) {
            $src = $this->resolveSrc($node);
            if ($src === '') {
                return;
            }
            if ($this->isExcluded($src)) {
                return;
            }
            $src = $this->resolveRelative($src, $baseUrl);
            $src = $this->cleanUrl($src);
            if ($src !== '') {
                $images[] = $src;
            }
        });

        return array_values(array_unique($images));
    }

    protected function isExcluded(string $src): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (stripos($src, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将相对地址解析为绝对地址。
     */
    protected function resolveRelative(string $src, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        if ($baseUrl === '') {
            return $src;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        // 协议相对 //
        if (strpos($src, '//') === 0) {
            return $scheme . ':' . $src;
        }
        // 根路径绝对
        if (strpos($src, '/') === 0) {
            return $scheme . '://' . $host . $port . $src;
        }
        // 相对路径
        $baseDir = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';
        return $scheme . '://' . $host . $port . $baseDir . '/' . $src;
    }
}
