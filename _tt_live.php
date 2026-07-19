<?php
require __DIR__ . '/vendor/autoload.php';

use Zyan\U2P\U2P;
use Zyan\U2P\Handlers\ToutiaoHandler;

$u2p = new U2P();

echo "=== 文章1（纯文字新闻，只有封面） ===\n";
$url1 = 'https://www.toutiao.com/article/7652913480072167972/';
$img1 = $u2p->get($url1);
echo "正文图片: " . count($img1) . " 张\n";

$h = new ToutiaoHandler();
$img1c = $h->withCover()->handle($url1);
echo "含封面: " . count($img1c) . " 张\n";
if (!empty($img1c)) echo "封面: " . $img1c[0] . "\n";

echo "\n=== 文章2（图文，8张正文图） ===\n";
$url2 = 'https://www.toutiao.com/article/7652651188260700699/';
$img2 = $u2p->get($url2);
echo "正文图片: " . count($img2) . " 张\n";
foreach (array_slice($img2, 0, 3) as $i => $u) {
    echo "  " . ($i+1) . ". " . substr($u, 0, 80) . "...\n";
}
