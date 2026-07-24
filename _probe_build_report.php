<?php

declare(strict_types=1);

$rawPath = __DIR__ . '/_probe_raw.json';
$outPath = __DIR__ . '/_probe_report.txt';

$data = json_decode(file_get_contents($rawPath), true, 512, JSON_THROW_ON_ERROR);

$recommendations = [
    '1688' => <<<'TXT'
推荐方案：单独 Handler + 反爬对策。
- 当前 HttpClient 裸 GET 得到 ~2.5KB 风控页（_____tmd_____/punish?x5secdata=），无 img/og/JSON-LD。
- 可行路径：(1) 带 Cookie / x5sec 等阿里系参数再抓 PC 详情；(2) 解析 HTML/脚本中的 offerId，调用移动端或开放接口（如 m.1688.com、h5api 类域名，需抓包确认）；(3) 降级用 og/meta 通常不可用。
- DOM：风控通过后可试 .detail-gallery / script 内 imageList、offerImgList 等字段（需实页验证）。
TXT,
    'dw4_short' => <<<'TXT'
推荐方案：GenericHandler 扩展或 DewuHandler。
- 短链 dw4.co → fast.dewu.com/page/productDetail?spuId=…（2 跳）。
- HTML 体积大，img 标签 20+，CDN 域 webimg.dewucdn.com；脚本暴露 app.dewu.com/api/v1/h5/*（如 buildPage/firstPageDelivery）。
- 优先：CSS 选择器抓详情区 img[data-src|src]；或 POST/GET H5 API 用 spuId/skuId 换图集 JSON（需 Referer/Cookie）。
- og:image 未出现，勿依赖。
TXT,
    'jinritemai' => <<<'TXT'
推荐方案：Douyin/精选联盟 Handler（与现有 Toutiao 系可共用配置思路）。
- 无重定向；~12KB 壳页面，likely_antibot，无 img。
- 页面为 PIA/Gecko 资源（lf3-ecom-toc.jinritemai.com），真实数据走端内 API / 渲染后 DOM。
- 从 URL 取 id= 商品 ID；需抓包 haohuo/ecom 接口或 __INITIAL_STATE__ 类变量（当前响应未含）。
- 可能需要 Cookie、msToken、a_bogus 等；纯 HttpClient 不足时需浏览器态或签名参数。
TXT,
    'jd' => <<<'TXT'
推荐方案：JdHandler + api.m.jd.com。
- HTTP 200 但为新版 PC SPA（retail-mall/pc_item_components），title 为京东通用页，仅 1 个占位 img。
- 脚本含 pageConfig、api.m.jd.com、window.externalSdk；商品图不在首屏 HTML。
- 提取：从 URL 解析 skuId（10174996096733），调用京东商品详情 API（如 pc_detail / wareBusiness 等，需 appid、client、uuid 等 query，参考公开抓包）。
- 图片域名通常为 img*.360buyimg.com，从 API JSON 的 imageList / wareImage 字段取。
TXT,
    'taobao' => <<<'TXT'
推荐方案：TaobaoHandler + 登录/风控绕过。
- ~5KB，无 img；嵌入 login.taobao.com redirect 与 _____tmd_____ 风控路径。
- 目标应为 tbpc pc-detail-ssr-2025，需 Cookie、x5sec 或登录后 SSR HTML。
- 数据常在 g_config / __ICE_APP__ / 详情 SSR 的 itemImages 或 h5api.m.taobao.com（sign 参数）。
- 裸 HttpClient 不可行；先解决 antibot 再 DomCrawler + JSON 解析。
TXT,
    'ebay' => <<<'TXT'
推荐方案：EbayHandler + 请求头/地区。
- HTTP 403，Error Page，无图片信号；典型 bot 拦截（可能需 Accept-Language、Cookie、或美国 IP）。
- 成功时 eBay 详情页常有 og:image、JSON-LD Product、#icImg 或 picture URL 于脚本。
- 建议：增强 UA + Sec-Fetch-* + 可选代理；或 eBay Browse API（OAuth）用 item id 267149219732。
TXT,
    'hosane' => <<<'TXT'
推荐方案：HosaneHandler（SSR Nuxt）。
- HTTP 200，~679KB，title 含拍品名；window.__NUXT__ 存在，HTML 含 imageUrl 字段。
- img 标签含 /images/logo.png 等站点资源；拍品图应在 __NUXT__ 的 data/state 或正文区大图选择器。
- 推荐：regex/JSON 抽取 window.__NUXT__=… 解析拍品 imageUrl / images 数组；辅以 DomCrawler 拍品详情区 img。
- 无典型 REST API 暴露在 HTML 中。
TXT,
    'veer' => <<<'TXT'
推荐方案：VeerHandler（域名已迁移）。
- 301/302：veer.com → www.imageshop.com/photo/107294206.html。
- 有 title 与 img（多为 UI 静态资源 res-veer.cfp.cn）；正文大图可能在 data-src 或下载按钮链接。
- 解析最终 URL 的 photo id；DomCrawler 找预览/下载链接；可能需登录才能拿原图（页面含 antibot 关键词需人工区分）。
- API：未见明确 /api/；以 HTML 链接 pattern /photo/{id} 为主。
TXT,
    'zcool' => <<<'TXT'
推荐方案：ZcoolHandler（Next.js SSR）。
- HTTP 200，~462KB；img 109+，og:image + JSON-LD image 一致（封面）；__NEXT_DATA__ 存在。
- 快速路径：meta og:image 或 application/ld+json 的 image（常为封面）；完整图集解析 __NEXT_DATA__ JSON 内 props.pageProps 作品图片列表。
- 嵌入式 api.zcool.com.cn / tubeapi 等多为交互接口；列表页图优先 img.zcool.cn/community/*。
- GenericHandler 可部分覆盖 og:image，专用 Handler 应用 __NEXT_DATA__ 取全图。
TXT,
];

$lines = [];
$lines[] = 'U2P URL Probe Report';
$lines[] = 'Generated: ' . date('c');
$lines[] = 'Tool: Zyan\U2P\HttpClient (Guzzle, allow_redirects max=10, track_redirects)';
$lines[] = str_repeat('=', 72);
$lines[] = '';

foreach ($data as $key => $row) {
    $a = $row['analysis'] ?? [];
    $lines[] = "## {$key}";
    $lines[] = 'Request:  ' . ($row['request_url'] ?? '');
    $lines[] = 'Final:    ' . ($row['final_url'] ?? '');
    $lines[] = 'Status:   ' . ($row['status'] ?? 'n/a');
    if (!empty($row['error'])) {
        $lines[] = 'Error:    ' . $row['error'];
    }
    if (!empty($row['redirect_chain'])) {
        $lines[] = 'Redirects (' . count($row['redirect_chain']) . '):';
        foreach ($row['redirect_chain'] as $i => $u) {
            $lines[] = '  ' . ($i + 1) . '. ' . $u;
        }
    }
    $lines[] = 'Body:     ' . ($a['body_length'] ?? 0) . ' bytes';
    $lines[] = 'Title:    ' . ($a['title'] ?? '');
    $lines[] = 'Images in HTML: ' . (!empty($a['has_image_signals']) ? 'yes' : 'no')
        . ' | img tags: ' . ($a['img_tag_count'] ?? 0)
        . ' | og:image: ' . count($a['og_image'] ?? [])
        . ' | JSON-LD image: ' . count($a['json_ld_image'] ?? []);
    if (!empty($a['img_samples'])) {
        $lines[] = 'img samples:';
        foreach ($a['img_samples'] as $s) {
            $lines[] = '  - ' . $s;
        }
    }
    if (!empty($a['og_image'])) {
        $lines[] = 'og:image:';
        foreach ($a['og_image'] as $s) {
            $lines[] = '  - ' . html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    if (!empty($a['json_ld_image'])) {
        $lines[] = 'JSON-LD image:';
        foreach ($a['json_ld_image'] as $s) {
            $lines[] = '  - ' . $s;
        }
    }
    if (!empty($a['script_vars'])) {
        $lines[] = 'Script/state hints: ' . implode(', ', $a['script_vars']);
    }
    if (!empty($a['api_patterns'])) {
        $lines[] = 'API / URL patterns:';
        foreach ($a['api_patterns'] as $kind => $samples) {
            $lines[] = "  [{$kind}]";
            foreach ($samples as $s) {
                $lines[] = '    - ' . $s;
            }
        }
    }
    $lines[] = 'Antibot/heuristic: ' . (!empty($a['likely_antibot']) ? 'likely' : 'not flagged');
    $lines[] = '';
    $lines[] = 'Recommended extraction:';
    $lines[] = trim($recommendations[$key] ?? '(no recommendation)');
    $lines[] = str_repeat('-', 72);
    $lines[] = '';
}

$lines[] = 'Artifacts: _probe_urls.php, _probe_raw.json, _probe_build_report.php';

file_put_contents($outPath, implode("\n", $lines) . "\n");
echo "Wrote {$outPath}\n";
