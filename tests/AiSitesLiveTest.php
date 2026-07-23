<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\U2P;

/**
 * 豆包 / 千问 AI 分享页抓图 —— 真实站点集成测试。
 *
 * 对应 docs/ai图片抓取.md 中的测试链接。
 *
 * @group live
 */
class AiSitesLiveTest extends TestCase
{
    protected ?U2P $u2p = null;

    protected function setUp(): void
    {
        if (!getenv('U2P_LIVE_TESTS')) {
            $this->markTestSkipped(
                'Set U2P_LIVE_TESTS=1 to run live site tests (requires network).'
            );
        }

        $this->u2p = new U2P();
    }

    /**
     * @param string[] $images
     */
    protected function assertValidImages(array $images): void
    {
        $this->assertIsArray($images);
        $this->assertNotEmpty($images, 'Expected at least one image URL');

        foreach ($images as $url) {
            $this->assertMatchesRegularExpression('#^https?://#i', $url);
        }
    }

    public function testDoubaoThread1(): void
    {
        $images = $this->u2p->get('https://www.doubao.com/thread/xc3dd6c3f21568e159197430b50844550');
        $this->assertValidImages($images);
        $this->assertStringContainsString('byteimg.com', $images[0]);
    }

    public function testDoubaoThread2(): void
    {
        $images = $this->u2p->get('https://www.doubao.com/thread/x7ef46bda1e13861bbba48e892a828ed7');
        $this->assertValidImages($images);
    }

    public function testQianwenShareChat(): void
    {
        $images = $this->u2p->get('https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602');
        $this->assertValidImages($images);
        $this->assertStringContainsString('qianwen.com', $images[0]);
    }
}
