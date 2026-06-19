<?php

declare(strict_types=1);

namespace Zyan\U2P;

/**
 * 处理器基类。
 *
 * 提供 HTTP 抓取与 HTML 解析（基于内置 DOMDocument）的通用能力，
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
     * 将 HTML 加载为 DOMDocument（自动处理编码为 UTF-8）。
     */
    protected function loadDom(string $html): \DOMDocument
    {
        $html = $this->ensureUtf8($html);

        // 确保存在 <meta charset="UTF-8">，让 DOM 正确解析中文
        if (stripos($html, '<meta') === false || stripos($html, 'charset') === false) {
            $html = '<meta charset="UTF-8">' . $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
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
    protected function resolveSrc(\DOMElement $img): string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'src'] as $attr) {
            $val = trim($img->getAttribute($attr));
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
}
