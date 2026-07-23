<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\QianwenHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

/**
 * 返回本地 fixture 内容的测试用 HTTP 客户端（仅 POST）。
 */
class QianwenFixtureHttpClient extends HttpClient
{
    public string $fixture = '';

    public function post(string $url, array $data = [], array $headers = [], array $options = []): string
    {
        return file_get_contents($this->fixture);
    }
}

class QianwenHandlerTest extends TestCase
{
    protected function getFixturePath(): string
    {
        return __DIR__ . '/fixtures/qianwen.json';
    }

    protected function makeClient(string $fixture): QianwenFixtureHttpClient
    {
        $client = new QianwenFixtureHttpClient();
        $client->fixture = $fixture;

        return $client;
    }

    public function testSupportsQianwenUrl(): void
    {
        $handler = new QianwenHandler();

        $this->assertTrue($handler->supports('https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602'));
        $this->assertTrue($handler->supports('https://qianwen.com/share/chat/abc123'));
        $this->assertFalse($handler->supports('https://www.doubao.com/thread/abc'));
    }

    public function testExtractShareId(): void
    {
        $handler = new QianwenHandler();

        $this->assertSame(
            'c1bf89c0db9a4a08806f518500f55602',
            $handler->extractShareId('https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602')
        );
    }

    public function testExtractContentImagesFromFixture(): void
    {
        $handler = new QianwenHandler($this->makeClient($this->getFixturePath()));

        $images = $handler->handle('https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602');

        $this->assertGreaterThanOrEqual(4, count($images));
        foreach ($images as $url) {
            $this->assertStringContainsString('workspace-zb-cdn.qianwen.com', $url);
            $this->assertStringNotContainsString('webp.jpg', $url);
        }
    }

    public function testExcludesWatermarkImages(): void
    {
        $handler = new QianwenHandler();
        $images = $handler->extractContentImages(file_get_contents($this->getFixturePath()));

        $this->assertNotContains(
            'https://workspace-zb-cdn.qianwen.com/2b111c6a26ea468ab270f9f4966e6020%2Fo%2F1784738978268.png?auth_key=1787334360-0-0-79dc5a5dd083ae7696fca83903a625e1',
            $images
        );
    }

    public function testU2PDispatchesToQianwenHandler(): void
    {
        $u2p = new U2P($this->makeClient($this->getFixturePath()));

        $images = $u2p->get('https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602');

        $this->assertGreaterThanOrEqual(4, count($images));
    }
}
