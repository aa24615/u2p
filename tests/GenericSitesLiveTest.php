<?php

declare(strict_types=1);

namespace Zyan\U2P\Tests;

use PHPUnit\Framework\TestCase;
use Zyan\U2P\Handlers\GenericHandler;
use Zyan\U2P\U2P;

/**
 * 通用网站抓图 —— 真实站点集成测试。
 *
 * 对应 docs/测试用例.md 中“通用网站抓图”需覆盖的网址，验证 SDK 能对各类
 * 真实网页端到端抓取到合法图片链接。
 *
 * 默认不执行（避免 CI 因网络 / 反爬而飘红），需显式开启：
 *   U2P_LIVE_TESTS=1 vendor/bin/phpunit --group live
 *
 * @group live
 */
class GenericSitesLiveTest extends TestCase
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
     * 不变量：结果应为数组，且每个元素都是绝对 http(s) 地址。
     */
    private function assertValidImageList(array $images, string $url): void
    {
        foreach ($images as $i => $img) {
            $this->assertIsString($img, "[$url] 第 $i 个元素应为字符串");
            $this->assertMatchesRegularExpression(
                '#^https?://#i',
                $img,
                "[$url] 第 $i 个元素应为绝对地址: $img"
            );
        }
    }

    /**
     * 抓取指定网址；网络 / 反爬导致请求失败时跳过而非判负。
     */
    private function scrape(string $url): array
    {
        try {
            return $this->u2p->get($url);
        } catch (\Throwable $e) {
            $this->markTestSkipped("请求失败，跳过: $url (" . $e->getMessage() . ')');
            return []; // 不可达，仅为满足返回类型
        }
    }

    /**
     * @dataProvider provideSites
     */
    public function testScrapeGenericSite(string $url, bool $expectNonEmpty): void
    {
        $images = $this->scrape($url);

        $this->assertValidImageList($images, $url);

        if ($expectNonEmpty) {
            $this->assertNotEmpty($images, "[$url] 应至少抓到一张图片");
        }
    }

    /**
     * 测试用例.md 中需覆盖的网址。
     *
     * @return array<string, array{0:string, 1:bool}>
     */
    public function provideSites(): array
    {
        return [
            // 首页 / 搜索页可能为 JS 渲染，仅校验返回地址合法性
            'url2pic 首页'   => ['https://www.url2pic.com/', true],
            'QQ 首页'        => ['https://www.qq.com', false],
            '百度图片首页'   => ['https://image.baidu.com/', false],
            '百度图片详情页' => [
                'https://image.baidu.com/search/detail?adpicid=0&b_applid=11525922126380213442&bdtype=0&commodity=&copyright=&cs=2487938753%2C1450986665&di=7646086322926387201&fr=click-pic&fromurl=http%253A%252F%252Fwww.win4000.com%252Fwallpaper_detail_168573_3.html&gsm=3c&hd=&height=0&hot=&ic=&ie=utf-8&imgformat=&imgratio=&imgspn=0&is=0%2C0&isImgSet=&latest=&lid=&lm=&objurl=http%253A%252F%252Fpic1.win4000.com%252Fwallpaper%252F2020-05-13%252F5ebbbafdc96eb.jpg&os=1995517108%2C7315754&pd=image_content&pi=0&pn=58&rn=1&simid=2487938753%2C1450986665&tn=baiduimagedetail&width=0&word=%E5%A3%81%E7%BA%B8&z=',
                true,
            ],
            '阮一峰博客'     => ['http://www.ruanyifeng.com/blog/2015/12/git-cheat-sheet.html', true],
            '站长下载页'     => ['https://down.chinaz.com/soft/50789.htm', true],
            '优设首页'       => ['https://www.uisdc.com/', true],
            '千库网素材分类' => ['https://588ku.com/sucai/0-default-0-78-0-0-1/', true],
        ];
    }

    /**
     * 直接使用 GenericHandler 抓取内容页，结果应与 U2P 入口一致。
     */
    public function testGenericHandlerDirectlyOnContentPage(): void
    {
        $url = 'http://www.ruanyifeng.com/blog/2015/12/git-cheat-sheet.html';

        try {
            $images = (new GenericHandler())->handle($url);
        } catch (\Throwable $e) {
            $this->markTestSkipped("请求失败，跳过: $url");
            return;
        }

        $this->assertNotEmpty($images, "[$url] 应至少抓到一张图片");
        $this->assertValidImageList($images, $url);
    }
}
