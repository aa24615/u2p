<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\Ali1688Handler;
use Zyan\U2P\Handlers\DewuHandler;
use Zyan\U2P\Handlers\EbayHandler;
use Zyan\U2P\Handlers\HosaneHandler;
use Zyan\U2P\Handlers\JdHandler;
use Zyan\U2P\Handlers\JinritemaiHandler;
use Zyan\U2P\Handlers\TaobaoHandler;
use Zyan\U2P\Handlers\VeerHandler;
use Zyan\U2P\Handlers\ZcoolHandler;
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

class EcommerceFixtureHttpClient extends HttpClient
{
    /** @var array<string,string> */
    public array $fixtures = [];

    public function get(string $url, array $headers = [], array $options = []): string
    {
        foreach ($this->fixtures as $pattern => $content) {
            if (strpos($url, $pattern) !== false) {
                return file_get_contents($content);
            }
        }

        throw new \RuntimeException('No fixture for URL: ' . $url);
    }
}

class EcommerceHandlerTest extends TestCase
{
    public function testHosaneHandler(): void
    {
        $handler = new HosaneHandler();
        $images = $handler->extractContentImages(file_get_contents(__DIR__ . '/fixtures/hosane.html'));

        $this->assertCount(2, $images);
        $this->assertSame('https://imageoss.hosane.com/upload/picP13091/big/7731.jpg', $images[0]);
    }

    public function testDewuHandler(): void
    {
        $handler = new DewuHandler();
        $images = $handler->extractContentImages(file_get_contents(__DIR__ . '/fixtures/dewu.html'));

        $this->assertCount(2, $images);
        $this->assertStringContainsString('webimg.dewucdn.com/pro-img/', $images[0]);
    }

    public function testJdHandler(): void
    {
        $handler = new JdHandler();
        $images = $handler->extractContentImages(file_get_contents(__DIR__ . '/fixtures/jd_graphext.html'));

        $this->assertCount(2, $images);
        $this->assertStringContainsString('imgzone/jfs/', $images[0]);
        $this->assertStringContainsString('sku/jfs/', $images[1]);
    }

    public function testAli1688Handler(): void
    {
        $handler = new Ali1688Handler();
        $images = $handler->extractContentImages(file_get_contents(__DIR__ . '/fixtures/ali1688.html'));

        $this->assertGreaterThanOrEqual(1, count($images));
        $this->assertStringContainsString('alicdn.com', $images[0]);
    }

    public function testZcoolHandler(): void
    {
        $handler = new ZcoolHandler();
        $images = $handler->extractContentImages(file_get_contents(__DIR__ . '/fixtures/zcool.html'));

        $this->assertGreaterThanOrEqual(2, count($images));
        $this->assertStringContainsString('img.zcool.cn/community/', $images[0]);
    }

    public function testVeerHandler(): void
    {
        $client = new EcommerceFixtureHttpClient();
        $client->fixtures = [
            'veer.com/photo/' => __DIR__ . '/fixtures/veer.html',
            'imageshop.com/photo/' => __DIR__ . '/fixtures/veer.html',
            '/ajax/image/view' => __DIR__ . '/fixtures/veer_api.json',
        ];

        $handler = new VeerHandler($client);
        $images = $handler->handle('https://www.veer.com/photo/107294206.html');

        $this->assertCount(1, $images);
        $this->assertSame(
            'https://veer00.cfp.cn/creative/vcg/veer/612/veer-107294206.jpg',
            $images[0]
        );
        $this->assertStringNotContainsString('water', $images[0]);
    }

    public function testEbayHandlerExtractsOgImage(): void
    {
        $handler = new EbayHandler();
        $html = '<html><head><meta property="og:image" content="https://i.ebayimg.com/images/g/abc/s-l1600.jpg"></head></html>';
        $images = $handler->extractContentImages($html);

        $this->assertSame(['https://i.ebayimg.com/images/g/abc/s-l1600.jpg'], $images);
    }

    public function testU2PDispatchesToHosaneHandler(): void
    {
        $client = new EcommerceFixtureHttpClient();
        $client->fixtures = [
            'hosane.com' => __DIR__ . '/fixtures/hosane.html',
        ];

        $images = (new U2P($client))->get('https://www.hosane.com/auction/detail/p13090032');
        $this->assertCount(2, $images);
    }

    public function testSupportsPatterns(): void
    {
        $this->assertTrue((new Ali1688Handler())->supports('https://detail.1688.com/offer/568516651500.html'));
        $this->assertTrue((new DewuHandler())->supports('https://dw4.co/t/A/1vrIi0NUy'));
        $this->assertTrue((new JdHandler())->supports('https://item.jd.com/10174996096733.html'));
        $this->assertTrue((new TaobaoHandler())->supports('https://item.taobao.com/item.htm?id=1'));
        $this->assertTrue((new JinritemaiHandler())->supports('https://haohuo.jinritemai.com/ecommerce/trade/detail/index.html?id=1'));
        $this->assertTrue((new EbayHandler())->supports('https://www.ebay.com/itm/267149219732'));
        $this->assertTrue((new VeerHandler())->supports('https://www.veer.com/photo/107294206.html'));
        $this->assertTrue((new ZcoolHandler())->supports('https://www.zcool.com.cn/work/ZMjIxMzQ1MTI=.html'));
    }
}
