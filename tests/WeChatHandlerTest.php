<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\WeChatHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

/**
 * 测试用 HTTP 客户端：直接返回 fixture 文件内容，不发起真实请求。
 */
class FixtureHttpClient extends HttpClient
{
    public string $fixture = '';

    public function get(string $url, array $headers = [], array $options = []): string
    {
        return file_get_contents($this->fixture);
    }
}

class WeChatHandlerTest extends TestCase
{
    protected function getFixturePath(): string
    {
        return __DIR__ . '/fixtures/wechat.html';
    }

    protected function makeClient(string $fixture): FixtureHttpClient
    {
        $client = new FixtureHttpClient();
        $client->fixture = $fixture;
        return $client;
    }

    public function testSupportsWeChatUrl(): void
    {
        $handler = new WeChatHandler();

        $this->assertTrue($handler->supports('https://mp.weixin.qq.com/s/-iRttNYNtn4z4qGGQ_fbvw'));
        $this->assertTrue($handler->supports('http://mp.weixin.qq.com/s?__biz=MzI4&mid=123'));
        $this->assertFalse($handler->supports('https://example.com/article'));
    }

    public function testExtractContentImages(): void
    {
        $handler = new WeChatHandler($this->makeClient($this->getFixturePath()));

        $images = $handler->handle('https://mp.weixin.qq.com/s/test');

        $this->assertCount(5, $images);

        // 每张都是 mmbiz 图床且带 from=appmsg
        foreach ($images as $url) {
            $this->assertStringContainsString('mmbiz.qpic.cn', $url);
            $this->assertStringContainsString('from=appmsg', $url);
        }

        // HTML 实体应被解码（&amp; -> &）
        $this->assertStringNotContainsString('&amp;', $images[1]);

        // #imgIndex 锚点应被去除
        $this->assertStringNotContainsString('#imgIndex', $images[1]);
    }

    public function testExcludesAvatarAndQrCode(): void
    {
        $handler = new WeChatHandler($this->makeClient($this->getFixturePath()));
        $images = $handler->handle('https://mp.weixin.qq.com/s/test');

        foreach ($images as $url) {
            // 头像 / 封面变量地址走 /0? 路径，正文走 /640?
            $this->assertStringContainsString('/640?', $url);
        }
    }

    public function testWithCover(): void
    {
        $handler = new WeChatHandler($this->makeClient($this->getFixturePath()));
        $handler->withCover();

        $images = $handler->handle('https://mp.weixin.qq.com/s/test');

        // 封面置于首位
        $this->assertSame('https://mmbiz.qpic.cn/mmbiz_jpg/AAAA/cover/0?wx_fmt=jpeg', $images[0]);
        $this->assertCount(6, $images);
    }

    public function testU2PDispatchesToWeChatHandler(): void
    {
        $client = $this->makeClient($this->getFixturePath());
        $u2p = new U2P($client);

        $images = $u2p->get('https://mp.weixin.qq.com/s/-iRttNYNtn4z4qGGQ_fbvw');

        $this->assertCount(5, $images);
    }
}


