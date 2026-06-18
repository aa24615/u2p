<?php

/**
 * zyan/u2p 图片抓取 SDK 示例。
 *
 * 运行方式（在项目根目录执行）：
 *   php docs/test.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Zyan\U2P\U2P;
use Zyan\U2P\Handlers\WeChatHandler;
use Zyan\U2P\Handlers\GenericHandler;

/* -----------------------------------------------------------------
 * 1. 最简用法：自动识别网站类型
 * -----------------------------------------------------------------
 * 输入任意网页链接，SDK 会按已注册的 Handler 依次匹配，
 * 命中公众号则走专项抓取，否则回退到通用抓取。
 */
$url = 'https://mp.weixin.qq.com/s/-iRttNYNtn4z4qGGQ_fbvw';

$u2p = new U2P();
$images = $u2p->get($url);

echo "1. 自动识别抓取（共 " . count($images) . " 张）\n";
foreach ($images as $i => $img) {
    echo "   " . ($i + 1) . ". " . $img . "\n";
}
echo "\n";


/* -----------------------------------------------------------------
 * 2. 公众号专项：含封面图
 * -----------------------------------------------------------------
 * 默认只返回正文图片；withCover() 会把 msg_cdn_url 封面置于首位。
 */
$handler = new WeChatHandler();
$imagesWithCover = $handler->withCover()->handle($url);

echo "2. 含封面图抓取（共 " . count($imagesWithCover) . " 张）\n";
echo "   封面: " . $imagesWithCover[0] . "\n";
echo "\n";


/* -----------------------------------------------------------------
 * 3. 通用网页抓取（任意网站兜底）
 * -----------------------------------------------------------------
 * 解析页面源码中的 <img>，优先取懒加载地址，
 * 自动解析相对路径，过滤 base64 等无效地址。
 */
// 通用抓取可能因网络/404 抛异常，示例中用 try/catch 包裹保证后续演示继续
$generic = new GenericHandler();
$anyImages = [];
try {
    $anyImages = $generic->handle('https://www.php.net/');
} catch (\Throwable $e) {
    echo "3. 通用网页抓取失败: " . $e->getMessage() . "\n\n";
    goto custom_demo;
}

echo "3. 通用网页抓取（共 " . count($anyImages) . " 张）\n";
foreach (array_slice($anyImages, 0, 5) as $i => $img) {
    echo "   " . ($i + 1) . ". " . $img . "\n";
}
if (count($anyImages) > 5) {
    echo "   ... 还有 " . (count($anyImages) - 5) . " 张\n";
}
echo "\n";

custom_demo:


/* -----------------------------------------------------------------
 * 4. 注册自定义 Handler（支持更多网站 / ajax 接口）
 * -----------------------------------------------------------------
 * 对于通过 ajax 接口返回图片的网站，实现 HandlerInterface，
 * 在 handle() 中抓接口 JSON 再解析即可，核心无需改动。
 */
$u2p->registerHandler(new class extends \Zyan\U2P\AbstractHandler {
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://www\.zhihu\.com/#i', $url);
    }

    public function handle(string $url): array
    {
        // 示例：实际应抓取页面 / 接口并解析
        return ['https://pic1.zhimg.com/v2-demo1.jpg'];
    }
});

$zhihuImages = $u2p->get('https://www.zhihu.com/question/123456');
echo "4. 自定义 Handler 抓取（共 " . count($zhihuImages) . " 张）\n";
foreach ($zhihuImages as $i => $img) {
    echo "   " . ($i + 1) . ". " . $img . "\n";
}
