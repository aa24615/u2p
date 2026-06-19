# zyan/u2p

网页图片抓取 SDK —— 输入网页链接，返回图片链接数组。

内置微信公众号文章等专项抓取，支持懒加载、ajax 接口等多种图片加载方式，可扩展自定义网站处理器。

## 环境要求

- PHP >= 7.4
- ext-json
- ext-mbstring

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
| 封面图 | 可选（`withCover()` 从 `msg_cdn_url` 变量提取） |

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

## 自定义处理器

实现 `HandlerInterface` 接口并注册即可支持更多网站。对于通过 ajax 接口返回图片的网站，在 `handle()` 中抓取接口并解析 JSON 即可。

```php
use Zyan\U2P\AbstractHandler;
use Zyan\U2P\U2P;

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
        $crawler->filter('.article img')->each(function ($node) use (&$images) {
            $src = $node->attr('src');
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

## 技术栈

- [Guzzle](https://github.com/guzzle/guzzle) — HTTP 客户端
- [Symfony DomCrawler](https://github.com/symfony/dom-crawler) — HTML 解析

## License

MIT
