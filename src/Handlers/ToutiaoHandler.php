<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Symfony\Component\DomCrawler\Crawler;
use Zyan\U2P\AbstractHandler;

/**
 * 今日头条文章图片抓取。
 *
 * 今日头条 PC 页面有反爬（需 __ac_signature 浏览器指纹），
 * 直接抓取 HTML 拿不到正文。故改走移动端 API：
 *   https://m.toutiao.com/i{articleId}/info/v2/
 * 返回 JSON，正文 HTML 在 data.content 中，图片为 <img src="...toutiaoimg.com...">；
 * 封面图在 data.poster_url。
 *
 * 用法：
 *   $handler = new ToutiaoHandler();
 *   $images  = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');
 *   // 含封面：
 *   $handler->withCover()->handle($url);
 */
class ToutiaoHandler extends AbstractHandler
{
    /**
     * 移动端文章详情 API 模板。
     */
    protected string $apiTemplate = 'https://m.toutiao.com/i%s/info/v2/';

    /**
     * 抓取 API 时使用的请求头。
     */
    protected array $apiHeaders = [
        'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        'Referer'         => 'https://m.toutiao.com/',
        'Accept'          => 'application/json, text/plain, */*',
        'Accept-Language' => 'zh-CN,zh;q=0.9',
    ];

    /**
     * 是否将封面图（poster_url）置于结果最前。
     */
    protected bool $withCover = false;

    public function withCover(bool $withCover = true): self
    {
        $this->withCover = $withCover;
        return $this;
    }

    public function setApiTemplate(string $template): self
    {
        $this->apiTemplate = $template;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\.|m\.)?toutiao\.com/(?:article|i\d|a\d)#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $url): array
    {
        $articleId = $this->extractArticleId($url);
        if ($articleId === '') {
            return [];
        }

        $apiUrl = sprintf($this->apiTemplate, $articleId);
        $body = $this->fetch($apiUrl, $this->apiHeaders);

        $images = $this->extractContentImages($body);

        if ($this->withCover) {
            $cover = $this->extractCover($body);
            if ($cover !== '') {
                array_unshift($images, $cover);
                $images = array_values(array_unique($images));
            }
        }

        return $images;
    }

    /**
     * 从文章链接中提取文章 ID。
     */
    public function extractArticleId(string $url): string
    {
        // /article/7652913480072167972/ 或 /i7652913480072167972/ 或 /a7652913480072167972/
        if (preg_match('#/(?:article/|i|a)(\d{10,})#i', $url, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * 从 API 返回的 JSON 中提取正文图片。
     *
     * @return string[]
     */
    public function extractContentImages(string $jsonBody): array
    {
        $data = json_decode($jsonBody, true);
        if (!is_array($data) || !isset($data['data']['content'])) {
            return [];
        }

        $content = (string) $data['data']['content'];
        $crawler = $this->loadDom($content);

        $images = [];
        $crawler->filter('img')->each(function (Crawler $node) use (&$images) {
            $src = $this->resolveSrc($node);
            if ($src === '') {
                return;
            }
            $src = $this->cleanUrl($src);
            if ($src !== '') {
                $images[] = $src;
            }
        });

        return array_values(array_unique($images));
    }

    /**
     * 从 API 返回的 JSON 中提取封面图（poster_url）。
     */
    public function extractCover(string $jsonBody): string
    {
        $data = json_decode($jsonBody, true);
        if (!is_array($data) || !isset($data['data']['poster_url'])) {
            return '';
        }

        $cover = trim((string) $data['data']['poster_url']);
        return $cover !== '' ? html_entity_decode($cover, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    }
}
