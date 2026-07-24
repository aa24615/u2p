<?php

declare(strict_types=1);

namespace Zyan\U2P;

use Symfony\Component\DomCrawler\Crawler;

/**
 * 处理器基类。
 *
 * 提供 HTTP 抓取（Guzzle）与 HTML 解析（Symfony DomCrawler）的通用能力，
 * 子类只需实现 supports() 与 handle()。
 *
 * DOM 解析风格参考 QueryList：通过 CSS 选择器筛选节点，再提取属性。
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected ?HttpClient $client = null;

    public function __construct(?HttpClient $client = null)
    {
        $this->client = $client ?: new HttpClient();
    }

    public function setClient(HttpClient $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * 抓取指定链接的 HTML 内容。
     */
    protected function fetch(string $url, array $headers = []): string
    {
        return $this->client->get($url, $headers);
    }

    /**
     * 将 HTML 加载为 Symfony DomCrawler（自动处理编码为 UTF-8）。
     */
    protected function loadDom(string $html): Crawler
    {
        return new Crawler($this->ensureUtf8($html));
    }

    /**
     * 根据 meta charset 将非 UTF-8 内容转换为 UTF-8。
     */
    protected function ensureUtf8(string $html): string
    {
        if (preg_match('/charset=["\']?\s*([\w-]+)/i', $html, $m)) {
            $charset = strtoupper(trim($m[1]));
            if ($charset !== '' && $charset !== 'UTF-8') {
                $enc = $charset === 'GB2312' ? 'GBK' : $charset;
                $converted = @mb_convert_encoding($html, 'UTF-8', $enc);
                if ($converted !== false) {
                    $html = str_ireplace($m[0], 'charset="UTF-8"', $converted);
                }
            }
        }

        return $html;
    }

    /**
     * 解析单个 img 节点的真实图片地址。
     *
     * 依次尝试 data-src、data-original、data-lazy-src、src（懒加载优先），
     * 返回解码后的地址（可能是绝对 URL、协议相对、根路径、相对路径）。
     * 空白、data:、about:、javascript: 等伪协议由子类 isExcluded() 负责过滤。
     */
    protected function resolveSrc(Crawler $node): string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'src'] as $attr) {
            $val = trim($node->attr($attr) ?? '');
            if ($val === '') {
                continue;
            }
            return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * 清理图片地址：去除锚点等无用部分。
     */
    protected function cleanUrl(string $url): string
    {
        $url = preg_replace('/#.*$/', '', $url);
        return trim($url);
    }

    /**
     * 将协议相对或相对地址转为绝对 http(s) 地址。
     */
    protected function normalizeAbsoluteUrl(string $url, string $baseUrl = ''): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $this->cleanUrl($url);
        }
        if (strpos($url, '//') === 0) {
            return $this->cleanUrl('https:' . $url);
        }
        if ($baseUrl === '') {
            return $this->cleanUrl($url);
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        if (strpos($url, '/') === 0) {
            return $this->cleanUrl($scheme . '://' . $host . $port . $url);
        }

        $baseDir = '';
        if (isset($parts['path'])) {
            $path = $parts['path'];
            $baseDir = substr($path, -1) === '/'
                ? rtrim($path, '/')
                : rtrim(dirname($path), '/');
        }

        return $this->cleanUrl($scheme . '://' . $host . $port . $baseDir . '/' . $url);
    }

    /**
     * @param string[] $urls
     *
     * @return string[]
     */
    protected function uniqueUrls(array $urls): array
    {
        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return string[]
     */
    protected function extractOgImages(string $html): array
    {
        $images = [];
        if (preg_match_all('/<meta[^>]+property=["\']og:image(?::[^"\']*)?["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }
        if (preg_match_all('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::[^"\']*)?["\']/i', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        return $this->uniqueUrls(array_map([$this, 'normalizeAbsoluteUrl'], $images));
    }

    /**
     * @return string[]
     */
    protected function extractJsonLdImages(string $html): array
    {
        $images = [];
        if (!preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (preg_match('/"image"\s*:\s*"([^"]+)"/', $block, $match)) {
                $images[] = $match[1];
                continue;
            }
            if (preg_match('/"image"\s*:\s*\[\s*"([^"]+)"/', $block, $match)) {
                $images[] = $match[1];
            }
        }

        return $this->uniqueUrls(array_map([$this, 'normalizeAbsoluteUrl'], $images));
    }

    /**
     * 解析页面中的 __NEXT_DATA__ JSON。
     */
    protected function extractNextData(string $html): ?array
    {
        if (!preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/is', $html, $match)) {
            return null;
        }

        $data = json_decode(trim($match[1]), true);

        return is_array($data) ? $data : null;
    }
}
