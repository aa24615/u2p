<?php

/**
 * 豆包 / 千问 AI 分享页抓图 —— 独立冒烟测试脚本。
 *
 * 对应 docs/ai图片抓取.md 中的测试链接，不依赖 PHPUnit，可直接运行：
 *   php ai_image_test.php
 *
 * 需要网络环境，所有请求失败均只告警、不中断其余用例。
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Zyan\U2P\U2P;

// docs/ai图片抓取.md 中的测试连接
$links = [
    '豆包 #1' => 'https://www.doubao.com/thread/xc3dd6c3f21568e159197430b50844550',
    '豆包 #2' => 'https://www.doubao.com/thread/x7ef46bda1e13861bbba48e892a828ed7',
    '千问'    => 'https://www.qianwen.com/share/chat/c1bf89c0db9a4a08806f518500f55602',
];

$u2p = new U2P();

$pass = 0;
$fail = 0;

echo "==== AI 图片抓取独立测试 ====\n\n";

foreach ($links as $name => $url) {
    echo "[{$name}] {$url}\n";

    try {
        $images = $u2p->get($url);
    } catch (\Throwable $e) {
        echo "  ✗ 请求异常: " . $e->getMessage() . "\n\n";
        $fail++;
        continue;
    }

    if (!is_array($images) || count($images) === 0) {
        echo "  ✗ 未抓到任何图片（返回 " . count($images ?? []) . " 张）\n\n";
        $fail++;
        continue;
    }

    echo "  ✓ 抓到 " . count($images) . " 张图片:\n";
    foreach ($images as $img) {
        echo "    - {$img}\n";
    }
    echo "\n";
    $pass++;
}

echo "==== 结果: {$pass} 通过 / {$fail} 失败 ====\n";
exit($fail > 0 ? 1 : 0);
