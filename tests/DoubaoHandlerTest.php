<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\DoubaoHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

/**
 * 返回本地 fixture 内容的测试用 HTTP 客户端。
 */
class DoubaoFixtureHttpClient extends HttpClient
{
    public string $fixture = '';

    public function get(string $url, array $headers = [], array $options = []): string
    {
        return file_get_contents($this->fixture);
    }
}

class DoubaoHandlerTest extends TestCase
{
    protected function getFixturePath(): string
    {
        return __DIR__ . '/fixtures/doubao.html';
    }

    protected function makeClient(string $fixture): DoubaoFixtureHttpClient
    {
        $client = new DoubaoFixtureHttpClient();
        $client->fixture = $fixture;

        return $client;
    }

    public function testSupportsDoubaoUrl(): void
    {
        $handler = new DoubaoHandler();

        $this->assertTrue($handler->supports('https://www.doubao.com/thread/xc3dd6c3f21568e159197430b50844550'));
        $this->assertTrue($handler->supports('https://doubao.com/thread/abc123'));
        $this->assertFalse($handler->supports('https://www.qianwen.com/share/chat/abc'));
    }

    public function testExtractContentImagesFromFixture(): void
    {
        $handler = new DoubaoHandler($this->makeClient($this->getFixturePath()));

        $images = $handler->handle('https://www.doubao.com/thread/test');

        $this->assertCount(2, $images);
        foreach ($images as $url) {
            $this->assertStringContainsString('byteimg.com', $url);
            $this->assertStringContainsString('rc_gen_image', $url);
        }
        $this->assertSame(
            'https://p3-flow-imagex-sign.byteimg.com/tos-cn-i-a9rns2rl98/rc_gen_image/test1.jpeg~tplv-a9rns2rl98-image.jpeg',
            $images[0]
        );
    }

    public function testU2PDispatchesToDoubaoHandler(): void
    {
        $u2p = new U2P($this->makeClient($this->getFixturePath()));

        $images = $u2p->get('https://www.doubao.com/thread/xc3dd6c3f21568e159197430b50844550');

        $this->assertCount(2, $images);
    }
}
