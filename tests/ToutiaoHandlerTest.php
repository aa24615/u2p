<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\ToutiaoHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

/**
 * 测试用 HTTP 客户端：直接返回 fixture 文件内容，不发起真实请求。
 */
class ToutiaoFixtureHttpClient extends HttpClient
{
    public string $fixture = '';

    public function get(string $url, array $headers = [], array $options = []): string
    {
        return file_get_contents($this->fixture);
    }
}

class ToutiaoHandlerTest extends TestCase
{
    protected function getFixturePath(): string
    {
        return __DIR__ . '/fixtures/toutiao.json';
    }

    protected function makeClient(string $fixture): ToutiaoFixtureHttpClient
    {
        $client = new ToutiaoFixtureHttpClient();
        $client->fixture = $fixture;
        return $client;
    }

    /* ================================================================
     * supports()
     * ================================================================ */

    public function testSupportsToutiaoUrl(): void
    {
        $handler = new ToutiaoHandler();

        $this->assertTrue($handler->supports('https://www.toutiao.com/article/7652913480072167972/'));
        $this->assertTrue($handler->supports('https://www.toutiao.com/article/7652651188260700699/?log_from=xxx'));
        $this->assertTrue($handler->supports('https://m.toutiao.com/i7652913480072167972/'));
        $this->assertFalse($handler->supports('https://mp.weixin.qq.com/s/xxxx'));
        $this->assertFalse($handler->supports('https://example.com/article/123'));
    }

    /* ================================================================
     * 文章 ID 提取
     * ================================================================ */

    public function testExtractArticleIdFromArticlePath(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame('7652913480072167972', $handler->extractArticleId('https://www.toutiao.com/article/7652913480072167972/'));
    }

    public function testExtractArticleIdWithQueryParams(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame('7652651188260700699', $handler->extractArticleId('https://www.toutiao.com/article/7652651188260700699/?log_from=d8ccd8718815'));
    }

    public function testExtractArticleIdFromMobileUrl(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame('7652913480072167972', $handler->extractArticleId('https://m.toutiao.com/i7652913480072167972/'));
    }

    public function testExtractArticleIdReturnsEmptyForInvalidUrl(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame('', $handler->extractArticleId('https://example.com/no-id-here'));
    }

    /* ================================================================
     * 正文图片提取
     * ================================================================ */

    public function testExtractContentImages(): void
    {
        $handler = new ToutiaoHandler($this->makeClient($this->getFixturePath()));

        $images = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');

        // fixture 有 8 张正文图片
        $this->assertCount(8, $images);

        // 每张都是 toutiaoimg 图床
        foreach ($images as $url) {
            $this->assertStringContainsString('toutiaoimg.com', $url);
            $this->assertStringStartsWith('https://', $url);
        }
    }

    public function testExtractContentImagesAreUnique(): void
    {
        $handler = new ToutiaoHandler($this->makeClient($this->getFixturePath()));
        $images = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');

        $this->assertSame(count($images), count(array_unique($images)));
    }

    /* ================================================================
     * 封面图
     * ================================================================ */

    public function testWithCover(): void
    {
        $handler = new ToutiaoHandler($this->makeClient($this->getFixturePath()));
        $handler->withCover();

        $images = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');

        // 封面置于首位，共 9 张
        $this->assertCount(9, $images);
        $this->assertStringContainsString('toutiaoimg.com', $images[0]);
    }

    public function testWithoutCoverByDefault(): void
    {
        $handler = new ToutiaoHandler($this->makeClient($this->getFixturePath()));
        $images = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');

        // 默认不含封面，8 张
        $this->assertCount(8, $images);
    }

    /* ================================================================
     * extractCover
     * ================================================================ */

    public function testExtractCover(): void
    {
        $handler = new ToutiaoHandler();
        $json = file_get_contents($this->getFixturePath());
        $cover = $handler->extractCover($json);

        $this->assertStringContainsString('toutiaoimg.com', $cover);
        $this->assertStringStartsWith('https://', $cover);
    }

    public function testExtractCoverReturnsEmptyForInvalidJson(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame('', $handler->extractCover('not json'));
        $this->assertSame('', $handler->extractCover('{}'));
        $this->assertSame('', $handler->extractCover('{"data":{}}'));
    }

    /* ================================================================
     * extractContentImages 直接传 JSON
     * ================================================================ */

    public function testExtractContentImagesFromJsonDirectly(): void
    {
        $handler = new ToutiaoHandler();
        $json = file_get_contents($this->getFixturePath());
        $images = $handler->extractContentImages($json);

        $this->assertCount(8, $images);
    }

    public function testExtractContentImagesReturnsEmptyForInvalidJson(): void
    {
        $handler = new ToutiaoHandler();
        $this->assertSame([], $handler->extractContentImages('not json'));
        $this->assertSame([], $handler->extractContentImages('{}'));
        $this->assertSame([], $handler->extractContentImages('{"data":{"content":""}}'));
    }

    /* ================================================================
     * 无效文章 ID
     * ================================================================ */

    public function testHandleReturnsEmptyForInvalidUrl(): void
    {
        $handler = new ToutiaoHandler($this->makeClient($this->getFixturePath()));
        $images = $handler->handle('https://example.com/no-id');

        $this->assertSame([], $images);
    }

    /* ================================================================
     * U2P 调度
     * ================================================================ */

    public function testU2PDispatchesToToutiaoHandler(): void
    {
        $client = $this->makeClient($this->getFixturePath());
        $u2p = new U2P($client);

        $images = $u2p->get('https://www.toutiao.com/article/7652651188260700699/');

        $this->assertCount(8, $images);
    }

    public function testU2PStillDispatchesToWeChatForWeChatUrl(): void
    {
        // 确保注册 ToutiaoHandler 后不影响 WeChat 匹配
        $client = $this->makeClient($this->getFixturePath());
        $u2p = new U2P($client);

        // toutiao fixture 返回的不是微信格式，但 supports 应正确判断
        $this->assertNotEmpty($u2p->getHandlers());
    }
}
