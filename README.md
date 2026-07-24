# zyan/u2p

网页图片抓取 SDK —— 输入网页链接，返回图片链接数组。

内置微信公众号文章、豆包 / 千问 AI 分享页、今日头条文章，以及京东、得物、1688、淘宝、泓盛拍卖、Veer、站酷等电商 / 专项站点抓取，支持懒加载、ajax 接口等多种图片加载方式，可扩展自定义网站处理器。

## 环境要求

- PHP >= 7.4
- ext-json
- ext-mbstring
- Guzzle（HTTP 客户端）
- Symfony DomCrawler + CssSelector（HTML 解析）

## 安装

```bash
composer require zyan/u2p
```

## 快速开始

```php
use Zyan\U2P\U2P;

$u2p = new U2P();

// 输入网页链接，返回图片链接数组
$images = $u2p->get('https://mp.weixin.qq.com/s/-iRttNYNtn4z4qGGQ_fbvw');

print_r($images);
// [
//     'https://mmbiz.qpic.cn/mmbiz_png/xxxx/640?wx_fmt=png&from=appmsg',
//     'https://mmbiz.qpic.cn/mmbiz_png/xxxx/640?wx_fmt=png&from=appmsg&tp=webp&wxfrom=5&wx_lazy=1',
//     ...
// ]
```

SDK 会根据 URL 自动匹配对应的处理器。未匹配到专项处理器时，回退到通用处理器。

## 内置处理器

### 微信公众号文章（WeChatHandler）

公众号文章的正文图片采用懒加载，真实地址写在 `<img data-src>` 中而非 `src`，图床域名为 `mmbiz.qpic.cn`。处理器会自动过滤头像、二维码、表情等非正文图片。

```php
use Zyan\U2P\Handlers\WeChatHandler;

$handler = new WeChatHandler();

// 仅正文图片
$images = $handler->handle('https://mp.weixin.qq.com/s/xxxx');

// 包含封面图（封面置于数组首位）
$images = $handler->withCover()->handle('https://mp.weixin.qq.com/s/xxxx');
```

**过滤规则：**

| 类型 | 处理方式 |
|------|----------|
| 正文图片 | 保留（`class` 含 `rich_pages`/`wxw-img`，或 URL 含 `from=appmsg`） |
| 头像 | 排除（`wx_follow_avatar_pic`、`jump_author_avatar`） |
| 二维码 | 排除（`qr_code_pc_img`、`js_qrcode_img` 等） |
| 表情 | 排除（`we-emoji`、`icon_emotion_single`） |
| 封面图 | 可选（`withCover()` 从 `msg_cdn_url` 或 `og:image` 提取） |
| 图片画廊 | 支持（从 `window.picture_page_info_list` 提取） |

图片画廊类文章（如 `https://mp.weixin.qq.com/s/oHLa0IhHZArCWb9d-rLKfQ`）不走正文 `<img>`，而是通过页面脚本注入 `picture_page_info_list`，处理器会自动识别并提取。

### 通用网页（GenericHandler）

适用于任意网页的兜底处理器，解析页面源码中的 `<img>` 标签，优先取懒加载地址（`data-src`、`data-original`、`data-lazy-src`），自动解析相对路径，过滤 base64 等无效地址。

```php
use Zyan\U2P\Handlers\GenericHandler;

$handler = new GenericHandler();
$images = $handler->handle('https://example.com/article');
```

也可以直接传入 HTML 进行解析：

```php
$images = $handler->extractImages($html, 'https://example.com/news/page');
```

### 豆包 AI 分享页（DoubaoHandler）

豆包 AI 对话分享页的图片通过 `mergeLoaderData` 注入页面脚本，正文使用 `image_ori_raw` 无水印原图（而非带水印的预览地址）。处理器从页面脚本中提取 JSON 载荷，解析 `creation_block` 中的图片。

```php
use Zyan\U2P\Handlers\DoubaoHandler;

$handler = new DoubaoHandler();

// 抓取豆包分享页中的无水印图片
$images = $handler->handle('https://www.doubao.com/thread/xxxx');
```

解析逻辑参考 [ihmily/doubao-nomark](https://github.com/ihmily/doubao-nomark)。

### 千问 AI 分享页（QianwenHandler）

千问分享页为 SPA，图片数据需通过 `share/info` 接口获取。处理器提取 `share_id` 后调用接口，取 `display_list[].image[].url` 作为无水印原图，自动排除水印图与缩略图。

```php
use Zyan\U2P\Handlers\QianwenHandler;

$handler = new QianwenHandler();

// 抓取千问分享页图片
$images = $handler->handle('https://www.qianwen.com/share/chat/xxxx');

// 也可单独提取 share_id
$id = $handler->extractShareId('https://www.qianwen.com/share/chat/xxxx');
```

### 今日头条文章（ToutiaoHandler）

今日头条 PC 页面有反爬（需浏览器指纹），直接抓取 HTML 拿不到正文，故改走移动端 API（`m.toutiao.com/i{id}/info/v2/`）。正文 HTML 在 `data.content`，图片为 `<img src="...toutiaoimg.com...">`，封面图在 `data.poster_url`。

```php
use Zyan\U2P\Handlers\ToutiaoHandler;

$handler = new ToutiaoHandler();

// 抓取头条文章图片
$images = $handler->handle('https://www.toutiao.com/article/7652651188260700699/');

// 将封面图（poster_url）置于结果首位
$images = $handler->withCover()->handle($url);
```

### 电商与专项站点

以下处理器对应 `docs/专项攻克.md` 中的目标站点，已默认注册到 `U2P` 入口。

| 处理器 | 支持域名 | 策略 | 稳定性 |
|--------|----------|------|--------|
| `JdHandler` | `item.jd.com` | 移动端 graphext 详情页，提取 `360buyimg.com` 商品图 | 稳定 |
| `DewuHandler` | `dw4.co`、`dewu.com`、`poizon.com` | SSR 详情页轮播图；`m.dewu.com` 壳页自动改抓 `fast.dewu.com` | 稳定 |
| `HosaneHandler` | `hosane.com/auction/detail/` | 从 HTML 提取 `imageoss.hosane.com` 拍品大图 | 稳定 |
| `VeerHandler` | `veer.com`、`imageshop.com` | 跳转后调 `/ajax/image/view` 接口，优先无水印 oss 地址 | 稳定 |
| `ZcoolHandler` | `zcool.com.cn/work/` | `__NEXT_DATA__` + `og:image`，过滤缩略图 | 稳定 |
| `Ali1688Handler` | `detail.1688.com`、`m.1688.com` | PC + 移动页，提取 alicdn / `fullPathImageURI` | 易触发风控 |
| `TaobaoHandler` | `item.taobao.com`、`detail.tmall.com` 等 | `imageList` + alicdn 图床 | 易触发风控 |
| `JinritemaiHandler` | `jinritemai.com` | 多 URL 尝试 + douyinpic CDN 正则 | 页面为 SPA 壳，成功率低 |
| `EbayHandler` | `ebay.com/itm/` | `og:image` / JSON-LD / `i.ebayimg.com` | 常返回 403 |

```php
use Zyan\U2P\U2P;

$u2p = new U2P();

// 京东商品详情图
$images = $u2p->get('https://item.jd.com/10174996096733.html');

// 得物短链
$images = $u2p->get('https://dw4.co/t/A/1vrIi0NUy');

// Veer 图片（会跳转到 imageshop.com）
$images = $u2p->get('https://www.veer.com/photo/107294206.html');
```

#### 京东（JdHandler）

PC 商品页为 SPA，正文图不在首屏 HTML 中。处理器改抓移动端 graphext 接口（`in.m.jd.com/product/graphext/{sku}.html`），提取 `imgzone`、`sku` 等路径下的详情图，自动过滤 `/imagetools/` 小图标，并支持协议相对地址（`//img30.360buyimg.com/...`）。

```php
use Zyan\U2P\Handlers\JdHandler;

$handler = new JdHandler();
$images = $handler->handle('https://item.jd.com/10174996096733.html');
$sku = $handler->extractSku($url);
```

#### 得物（DewuHandler）

支持 `dw4.co` 短链，跟随重定向到 `fast.dewu.com` SSR 详情页，从 HTML 中提取 `webimg.dewucdn.com/pro-img` 轮播图。若短链落到 `m.dewu.com` 的 SPA 壳页，会自动改抓对应的 `fast.dewu.com/page/productDetail` 地址。

```php
use Zyan\U2P\Handlers\DewuHandler;

$handler = new DewuHandler();
$images = $handler->handle('https://dw4.co/t/A/1vrIi0NUy');
```

#### 泓盛拍卖（HosaneHandler）

从拍品详情页 HTML 中提取 `imageoss.hosane.com/upload/*/big/*.jpg` 大图，并去掉 `!300w` 等缩略图后缀。

```php
use Zyan\U2P\Handlers\HosaneHandler;

$handler = new HosaneHandler();
$images = $handler->handle('https://www.hosane.com/auction/detail/p13090032');
```

#### Veer / ImageShop（VeerHandler）

`veer.com/photo/` 会 302 到 `imageshop.com`。处理器解析页面中的 `uuid`，再请求 `/ajax/image/view?imageResId=&anonyUid=` 接口，优先取无 `Watermark` 字段的 oss 预览地址。

```php
use Zyan\U2P\Handlers\VeerHandler;

$handler = new VeerHandler();
$images = $handler->handle('https://www.veer.com/photo/107294206.html');
```

#### 站酷（ZcoolHandler）

从 `__NEXT_DATA__` 与 `og:image` 中提取 `img.zcool.cn/community/` 正文图，过滤头像级小缩略图。

```php
use Zyan\U2P\Handlers\ZcoolHandler;

$handler = new ZcoolHandler();
$images = $handler->handle('https://www.zcool.com.cn/work/ZMjIxMzQ1MTI=.html');
```

#### 1688 / 淘宝 / 抖音商城 / eBay

上述站点反爬较强，处理器已实现基础提取逻辑，但在无 Cookie / 代理环境下可能返回空数组：

- **1688**：同时尝试 PC 页与 `m.1688.com` 移动页
- **淘宝 / 天猫**：解析 `imageList` 与 alicdn 图床链接
- **抖音商城（好货）**：尝试多个详情 URL 与 `douyinpic` CDN 正则
- **eBay**：使用英文浏览器请求头，解析 `og:image` 与 JSON-LD；直连常 403，建议配合代理使用

## 自定义处理器

实现 `HandlerInterface` 接口并注册即可支持更多网站。对于通过 ajax 接口返回图片的网站，在 `handle()` 中抓取接口并解析 JSON 即可。

```php
use Zyan\U2P\AbstractHandler;
use Zyan\U2P\U2P;
use Symfony\Component\DomCrawler\Crawler;

class MySiteHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://mysite\.com/#i', $url);
    }

    public function handle(string $url): array
    {
        $html = $this->fetch($url);
        $crawler = $this->loadDom($html);

        $images = [];
        $crawler->filter('.article img')->each(function (Crawler $node) use (&$images) {
            $src = $this->resolveSrc($node);
            if ($src) {
                $images[] = $src;
            }
        });

        return $images;
    }
}

$u2p = new U2P();
$u2p->registerHandler(new MySiteHandler());

$images = $u2p->get('https://mysite.com/post/123');
```

## 自定义 HTTP 客户端

如需自定义请求头、代理、超时等，可注入底层 Guzzle 客户端：

```php
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;
use GuzzleHttp\Client as GuzzleClient;

$guzzle = new GuzzleClient([
    'timeout' => 60,
    'proxy'   => 'http://127.0.0.1:7890',
    'headers' => ['Cookie' => 'session=xxx'],
]);

$client = new HttpClient($guzzle);
$u2p = new U2P($client);

$images = $u2p->get('https://mp.weixin.qq.com/s/xxxx');
```

## 运行测试

```bash
# 单元测试（使用 fixture，无需网络）
php vendor/bin/phpunit --exclude-group live

# 专项站点真实链接集成测试（需网络）
U2P_LIVE_TESTS=1 php vendor/bin/phpunit --group live
```

Live 测试用例来自 `docs/专项攻克.md` 与 `docs/ai图片抓取.md` 中的示例链接。1688、淘宝、抖音商城、eBay 等站点在 live 测试中允许返回空数组。

## API

### `U2P`

| 方法 | 说明 |
|------|------|
| `get(string $url): string[]` | 抓取指定链接中的图片，返回图片链接数组 |
| `registerHandler(HandlerInterface $handler): self` | 注册自定义处理器（优先匹配） |
| `setFallback(HandlerInterface $handler): self` | 设置兜底处理器 |
| `make(): U2P` | 快速创建实例 |

### `HandlerInterface`

| 方法 | 说明 |
|------|------|
| `supports(string $url): bool` | 判断是否支持该链接 |
| `handle(string $url): string[]` | 抓取图片，返回链接数组 |

### `WeChatHandler`

| 方法 | 说明 |
|------|------|
| `withCover(bool $withCover = true): self` | 是否包含封面图（置于首位） |
| `handle(string $url): string[]` | 抓取公众号文章图片 |
| `extractContentImages(string $html): string[]` | 从 HTML 中提取正文图片 |

### `GenericHandler`

| 方法 | 说明 |
|------|------|
| `handle(string $url): string[]` | 抓取任意网页图片 |
| `extractImages(string $html, string $baseUrl = ''): string[]` | 从 HTML 中提取图片 |

### `DoubaoHandler`

| 方法 | 说明 |
|------|------|
| `handle(string $url): string[]` | 抓取豆包 AI 分享页图片 |
| `extractContentImages(string $html): string[]` | 从页面 HTML 中提取无水印图片 |

### `QianwenHandler`

| 方法 | 说明 |
|------|------|
| `handle(string $url): string[]` | 抓取千问 AI 分享页图片 |
| `extractShareId(string $url): string` | 提取分享链接中的 `share_id` |
| `extractContentImages(string $jsonBody): string[]` | 从 API 响应 JSON 中提取无水印图片 |

### `ToutiaoHandler`

| 方法 | 说明 |
|------|------|
| `handle(string $url): string[]` | 抓取今日头条文章图片 |
| `withCover(bool $withCover = true): self` | 是否包含封面图（置于结果首位） |
| `extractArticleId(string $url): string` | 提取文章 ID |
| `extractContentImages(string $jsonBody): string[]` | 从 API 响应 JSON 中提取正文图片 |
| `extractCover(string $jsonBody): string` | 提取封面图（`poster_url`） |

### 电商与专项站点处理器

| 处理器 | 主要方法 | 说明 |
|--------|----------|------|
| `JdHandler` | `handle()`、`extractSku()`、`extractContentImages()` | 京东商品详情图 |
| `DewuHandler` | `handle()`、`extractContentImages()` | 得物商品轮播图 |
| `HosaneHandler` | `handle()`、`extractContentImages()` | 泓盛拍卖拍品大图 |
| `VeerHandler` | `handle()`、`extractPhotoId()`、`extractUuid()` | Veer / ImageShop 预览图 |
| `ZcoolHandler` | `handle()`、`extractContentImages()` | 站酷作品正文图 |
| `Ali1688Handler` | `handle()`、`extractOfferId()`、`extractContentImages()` | 1688 商品图 |
| `TaobaoHandler` | `handle()`、`extractContentImages()` | 淘宝 / 天猫商品图 |
| `JinritemaiHandler` | `handle()`、`extractProductId()` | 抖音商城商品图 |
| `EbayHandler` | `handle()`、`extractContentImages()` | eBay 商品图 |

### `HttpClient`

| 方法 | 说明 |
|------|------|
| `get(string $url, array $headers = [], array $options = []): string` | GET 请求，返回响应体 |
| `post(string $url, array $data = [], array $headers = [], array $options = []): string` | POST JSON 请求，返回响应体 |
| `resolveFinalUrl(string $url, array $headers = []): string` | 跟随重定向，返回最终 URL |
| `setDefaultHeaders(array $headers): self` | 设置默认请求头 |
| `getGuzzle(): GuzzleClient` | 获取底层 Guzzle 实例 |

## 技术栈

- [Guzzle](https://github.com/guzzle/guzzle) — HTTP 客户端
- [Symfony DomCrawler](https://github.com/symfony/dom-crawler) — HTML 解析
- [Symfony CssSelector](https://github.com/symfony/css-selector) — CSS 选择器

## License

MIT
