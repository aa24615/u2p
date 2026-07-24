<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\U2P;

/**
 * 专项攻克站点 —— 真实链接集成测试。
 *
 * 对应 docs/专项攻克.md 中的测试链接。
 *
 * @group live
 */
class EcommerceSitesLiveTest extends TestCase
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
    protected function assertValidImages(array $images, string $url, bool $expectNonEmpty = true): void
    {
        $this->assertIsArray($images, "[$url] 返回值应为数组");

        foreach ($images as $img) {
            $this->assertMatchesRegularExpression('#^https?://#i', $img, "[$url] 非法图片地址: $img");
        }

        if ($expectNonEmpty) {
            $this->assertNotEmpty($images, "[$url] 应至少抓到一张图片");
        }
    }

    /**
     * @return string[]
     */
    protected function scrape(string $url): array
    {
        try {
            return $this->u2p->get($url);
        } catch (\Throwable $e) {
            $this->markTestSkipped("请求失败，跳过: $url (" . $e->getMessage() . ')');
            return [];
        }
    }

    /**
     * @dataProvider provideSites
     */
    public function testScrapeSite(string $url, bool $expectNonEmpty): void
    {
        $images = $this->scrape($url);
        $this->assertValidImages($images, $url, $expectNonEmpty);
    }

    /**
     * @return array<string, array{0:string, 1:bool}>
     */
    public function provideSites(): array
    {
        return [
            '1688-1' => ['https://detail.1688.com/offer/568516651500.html', false],
            '1688-2' => ['https://detail.1688.com/offer/744620689996.html', false],
            '1688-3' => ['https://detail.1688.com/offer/710845637274.html', false],
            '1688-4' => ['https://detail.1688.com/offer/1043537040659.html', false],
            'dewu-1' => ['https://dw4.co/t/A/1vrIi0NUy', true],
            'dewu-2' => ['https://dw4.co/t/A/1vwZxFvGT', true],
            'dewu-3' => ['https://dw4.co/t/A/1vrIMRCR6', true],
            'jinritemai-1' => ['https://haohuo.jinritemai.com/ecommerce/trade/detail/index.html?id=3831479699402522995', false],
            'jd-1' => ['https://item.jd.com/10174996096733.html', true],
            'jd-2' => ['https://item.jd.com/10205022471158.html', true],
            'jd-3' => ['https://item.jd.com/10205022471158.html?pcdk=LBfDhhozbnsqGpb2I1X39wNmZzlekT59k1T4gUjB8PpSIjGTyEJz57TAe4GlDGdT.3z6a.aI3x', true],
            'jd-4' => ['https://item.jd.com/100062191889.html', true],
            'taobao-1' => ['https://item.taobao.com/item.htm?id=1062941248301&skuId=6107517629983', false],
            'ebay-1' => ['https://www.ebay.com/itm/267149219732', false],
            'ebay-2' => ['https://www.ebay.com/itm/136218929689', false],
            'ebay-3' => ['https://www.ebay.com/itm/166573198512', false],
            'hosane-1' => ['https://www.hosane.com/auction/detail/p13090032', true],
            'hosane-2' => ['https://www.hosane.com/auction/detail/p13090031', true],
            'hosane-3' => ['https://www.hosane.com/auction/detail/P13062287', true],
            'hosane-4' => ['https://www.hosane.com/auction/detail/P13061998', true],
            'hosane-5' => ['https://www.hosane.com/auction/detail/P13061624', true],
            'veer-1' => ['https://www.veer.com/photo/107294206.html', true],
            'veer-2' => ['https://www.veer.com/photo/300235253.html', true],
            'veer-3' => ['https://www.veer.com/photo/141008588.html', true],
            'veer-4' => ['https://www.veer.com/photo/312203595.html', true],
            'veer-5' => ['https://www.veer.com/photo/301775812.html', true],
            'zcool-1' => ['https://www.zcool.com.cn/work/ZMjIxMzQ1MTI=.html', true],
            'zcool-2' => ['https://www.zcool.com.cn/work/ZNjg4MTA4Njg=.html', true],
        ];
    }
}
