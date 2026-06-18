<?php

declare(strict_types=1);

namespace Zyan\U2P;

use Symfony\Component\DomCrawler\Crawler;

/**
 * 处理器基类。
 *
 * 提供 HTTP 抓取（Guzzle）与 HTML 解析（Symfony DomCrawler）的通用能力，
 * 子类只需实现 supports() 与 handle()。
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
     * 仅返回 http(s) 或协议相对（//）地址。
     */
    protected function resolveSrc(Crawler $node): string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'src'] as $attr) {
            $val = trim($node->attr($attr) ?? '');
            if ($val === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $val) || strpos($val, '//') === 0) {
                return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
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
}
