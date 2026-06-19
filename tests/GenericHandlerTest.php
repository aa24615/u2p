<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\GenericHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

/**
 * 测试用 HTTP 客户端：直接返回给定 HTML 字符串，不发起真实请求。
 */
class StringHttpClient extends HttpClient
{
    public string $html = '';

    public function get(string $url, array $headers = [], array $options = []): string
    {
        return $this->html;
    }
}

class GenericHandlerTest extends TestCase
{
    /* ================================================================
     * supports()
     * ================================================================ */

    public function testSupportsAnyUrl(): void
    {
        $handler = new GenericHandler();
        $this->assertTrue($handler->supports('https://example.com'));
        $this->assertTrue($handler->supports('http://test.local/index.html'));
        $this->assertTrue($handler->supports('ftp://files.example.com'));
    }

    /* ================================================================
     * 懒加载 src 优先级
     * ================================================================ */

    public function testDataSrcTakesPriorityOverSrc(): void
    {
        $html = '<img src="https://cdn.example.com/placeholder.gif" data-src="https://cdn.example.com/real.jpg">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/real.jpg'], $images);
    }

    public function testDataOriginalTakesPriorityOverSrc(): void
    {
        $html = '<img src="https://cdn.example.com/placeholder.gif" data-original="https://cdn.example.com/lazy.png">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/lazy.png'], $images);
    }

    public function testDataLazySrcTakesPriorityOverSrc(): void
    {
        $html = '<img src="https://cdn.example.com/placeholder.gif" data-lazy-src="https://cdn.example.com/lazy.webp">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/lazy.webp'], $images);
    }

    public function testDataSrcTakesPriorityOverDataOriginal(): void
    {
        // data-src > data-original > data-lazy-src > src
        $html = '<img src="placeholder" data-original="//cdn/o.jpg" data-src="https://cdn/s.jpg" data-lazy-src="//cdn/l.jpg">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn/s.jpg'], $images);
    }

    public function testFallsBackToSrcWhenNoLazyAttribute(): void
    {
        $html = '<img src="https://cdn.example.com/img.jpg">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/img.jpg'], $images);
    }

    /* ================================================================
     * 相对路径解析
     * ================================================================ */

    public function testResolvesRelativeUrls(): void
    {
        $html = <<<'HTML'
<html><body>
<img src="https://example.com/a.jpg">
<img data-src="//cdn.example.com/b.png">
<img data-original="/img/c.jpeg">
<img class="lazy" data-lazy-src="d.gif">
<img src="data:image/png;base64,xxxx">
<img src="about:blank">
<img src="">
</body></html>
HTML;
        $images = (new GenericHandler())->extractImages($html, 'https://example.com/news/index.html');

        $this->assertSame([
            'https://example.com/a.jpg',
            'https://cdn.example.com/b.png',
            'https://example.com/img/c.jpeg',
            'https://example.com/news/d.gif',
        ], $images);
    }

    public function testResolvesProtocolRelativeOnHttp(): void
    {
        $html = '<img src="//cdn.example.com/a.png">';
        $images = (new GenericHandler())->extractImages($html, 'http://example.com/page');

        $this->assertSame(['http://cdn.example.com/a.png'], $images);
    }

    public function testResolvesRootAbsoluteUrl(): void
    {
        $html = '<img src="/static/hero.jpg">';
        $images = (new GenericHandler())->extractImages($html, 'https://blog.example.com/post/1');

        $this->assertSame(['https://blog.example.com/static/hero.jpg'], $images);
    }

    public function testResolvesRelativeWithNonStandardPort(): void
    {
        $html = '<img src="img/photo.jpg">';
        $images = (new GenericHandler())->extractImages($html, 'https://example.com:8080/gallery/');

        $this->assertSame(['https://example.com:8080/gallery/img/photo.jpg'], $images);
    }

    public function testKeepsOriginalWhenNoBaseUrl(): void
    {
        $html = '<img src="/img/a.png"><img src="//cdn/b.png">';
        $images = (new GenericHandler())->extractImages($html, '');

        $this->assertSame(['/img/a.png', '//cdn/b.png'], $images);
    }

    /* ================================================================
     * 排除规则
     * ================================================================ */

    public function testExcludesDataUri(): void
    {
        $html = '<img src="data:image/png;base64,iVBORw0KGgo="><img src="https://cdn.example.com/real.jpg">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/real.jpg'], $images);
    }

    public function testExcludesAboutBlank(): void
    {
        $html = '<img src="about:blank"><img src="https://cdn.example.com/a.png">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/a.png'], $images);
    }

    public function testExcludesJavascriptPseudoUrl(): void
    {
        $html = '<img src="javascript:void(0)"><img src="https://cdn.example.com/b.png">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/b.png'], $images);
    }

    public function testExcludesEmptySrc(): void
    {
        $html = '<img src=""><img src="  "><img src="https://cdn.example.com/a.jpg">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/a.jpg'], $images);
    }

    /* ================================================================
     * 去重
     * ================================================================ */

    public function testDeduplicatesIdenticalUrls(): void
    {
        $html = <<<'HTML'
<img src="https://cdn.example.com/a.jpg">
<img data-src="https://cdn.example.com/a.jpg">
<img src="https://cdn.example.com/a.jpg">
<img src="https://cdn.example.com/b.jpg">
HTML;
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame([
            'https://cdn.example.com/a.jpg',
            'https://cdn.example.com/b.jpg',
        ], $images);
    }

    /* ================================================================
     * URL 清理（锚点、实体）
     * ================================================================ */

    public function testStripsFragmentAnchor(): void
    {
        $html = '<img src="https://cdn.example.com/img.jpg#fragment">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/img.jpg'], $images);
    }

    public function testDecodesHtmlEntitiesInUrl(): void
    {
        $html = '<img data-src="https://cdn.example.com/img.jpg?a=1&amp;b=2">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/img.jpg?a=1&b=2'], $images);
    }

    /* ================================================================
     * 编码处理
     * ================================================================ */

    public function testParsesGb2312Html(): void
    {
        // 验证 GB2312 页面能正确解析，不报错，URL 中不含中文则完全一致
        $html = '<meta charset="gb2312"><img src="https://cdn.example.com/photo.jpg" alt="中文描述">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/photo.jpg'], $images);
    }

    public function testGb2312HtmlWithEnglishUrl(): void
    {
        // 多个图片 + GB2312 页面
        $html = '<meta charset="gb2312"><img src="https://cdn.example.com/1.jpg"><img src="https://cdn.example.com/2.png">';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame(['https://cdn.example.com/1.jpg', 'https://cdn.example.com/2.png'], $images);
    }

    /* ================================================================
     * 无图片
     * ================================================================ */

    public function testReturnsEmptyArrayWhenNoImages(): void
    {
        $html = '<html><body><p>No images here</p></body></html>';
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame([], $images);
    }

    /* ================================================================
     * handle() 端到端（通过 mock HTTP）
     * ================================================================ */

    public function testHandleViaMockHttp(): void
    {
        $client = new StringHttpClient();
        $client->html = '<html><body><img src="https://cdn.example.com/a.jpg"><img data-src="https://cdn.example.com/b.png"></body></html>';
        $handler = new GenericHandler($client);

        $images = $handler->handle('https://example.com/page');

        $this->assertSame([
            'https://cdn.example.com/a.jpg',
            'https://cdn.example.com/b.png',
        ], $images);
    }

    public function testHandleWithRelativeUrls(): void
    {
        $client = new StringHttpClient();
        $client->html = '<html><body><img src="//cdn.example.com/banner.jpg"><img src="/img/logo.png"></body></html>';
        $handler = new GenericHandler($client);

        $images = $handler->handle('https://example.com/index.html');

        $this->assertSame([
            'https://cdn.example.com/banner.jpg',
            'https://example.com/img/logo.png',
        ], $images);
    }

    /* ================================================================
     * U2P 兜底调度
     * ================================================================ */

    public function testU2PFallsBackToGenericHandler(): void
    {
        $client = new StringHttpClient();
        $client->html = '<img src="https://cdn.example.com/img.jpg">';
        $u2p = new U2P($client);

        // 非公众号链接，应走 GenericHandler
        $images = $u2p->get('https://www.example.com/article');

        $this->assertSame(['https://cdn.example.com/img.jpg'], $images);
    }

    /* ================================================================
     * 混合场景
     * ================================================================ */

    public function testMixedValidAndInvalidUrls(): void
    {
        $html = <<<'HTML'
<img src="https://cdn.example.com/valid.jpg">
<img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%3E%3C/svg%3E">
<img data-src="//cdn.example.com/lazy.png">
<img src="about:blank">
<img src="">
<img data-original="https://cdn.example.com/original.webp">
<img src="javascript:void(0)" data-src="https://cdn.example.com/js-lazy.gif">
HTML;
        $images = (new GenericHandler())->extractImages($html, 'https://example.com/page');

        $this->assertSame([
            'https://cdn.example.com/valid.jpg',
            'https://cdn.example.com/lazy.png',
            'https://cdn.example.com/original.webp',
            'https://cdn.example.com/js-lazy.gif',
        ], $images);
    }

    public function testImgWithOnlySrcAttribute(): void
    {
        $html = <<<'HTML'
<img src="https://cdn.example.com/1.jpg" alt="photo">
<img src="https://cdn.example.com/2.jpg" width="800" height="600" title="banner">
<img src="https://cdn.example.com/3.png" class="thumbnail">
HTML;
        $images = (new GenericHandler())->extractImages($html);

        $this->assertSame([
            'https://cdn.example.com/1.jpg',
            'https://cdn.example.com/2.jpg',
            'https://cdn.example.com/3.png',
        ], $images);
    }
}
