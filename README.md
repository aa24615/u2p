# zyan/u2p

> 网页图片抓取 SDK —— 输入网页链接，返回图片链接数组。

`u2p` = **U**RL **to** **P**ictures。一个轻量、零外部依赖、可扩展的 PHP 图片抓取库，内置微信公众号文章等专项抓取，支持 ajax 接口与网页源码两种图片加载方式。

## 特性

- **输入链接，输出图片数组** —— 极简的 `get($url)` API
- **专项攻克** —— 内置微信公众号文章抓取，精准提取正文图片、过滤头像/二维码/表情
- **通用兜底** —— 任意网页自动解析 `<img>`，支持懒加载、相对路径、编码识别
- **可扩展** —— 实现一个 `HandlerInterface` 即可支持新网站（含 ajax 接口型）
- **零外部依赖** —— 仅依赖 PHP 内置扩展（curl / dom / mbstring）
- **PHP 7.4+**，同时兼容 8.x

## 环境要求

- PHP >= 7.4
- ext-curl
- ext-dom
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
//     'https://mmbiz.qpic.cn/.../640?wx_fmt=png&from=appmsg',
//     'https://mmbiz.qpic.cn/.../640?wx_fmt=png&from=appmsg&...',
//     ...
// ]
```

SDK 会根据 URL 自动匹配处理器：公众号链接走专项抓取，其它链接回退到通用抓取。

## 使用示例

### 1. 自动识别网站类型

```php
$images = (new U2P())->get('https://mp.weixin.qq.com/s/xxxx');
```

### 2. 微信公众号文章 —— 含封面图

默认只返回正文图片，`withCover()` 会把封面图置于结果首位：

```php
use Zyan\U2P\Handlers\WeChatHandler;

$handler = new WeChatHandler();
$images  = $handler->withCover()->handle('https://mp.weixin.qq.com/s/xxxx');

echo '封面图: ' . $images[0];
```

### 3. 通用网页抓取

适用于任意网站，解析页面源码 `<img>`，优先取懒加载地址，自动解析相对路径，过滤 base64：

```php
use Zyan\U2P\Handlers\GenericHandler;

$generic = new GenericHandler();
$images  = $generic->handle('https://example.com/article');
```

### 4. 自定义处理器

为新的网站编写专项处理器（尤其适合 ajax 接口加载图片的网站）：

```php
use Zyan\U2P\AbstractHandler;

class ZhihuHandler extends AbstractHandler
{
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://www\.zhihu\.com/#i', $url);
    }

    public function handle(string $url): array
    {
        // 源码型：解析页面 HTML
        $html = $this->fetch($url);
        // ... 用 $this->loadDom($html) 解析

        // ajax 型：抓接口 JSON 再解析
        // $json = $this->fetch('https://www.zhihu.com/api/v4/...');

        return $images;
    }
}

$u2p = new U2P();
$u2p->registerHandler(new ZhihuHandler()); // 注册后优先匹配

$images = $u2p->get('https://www.zhihu.com/question/123456');
```

> 完整可运行示例见 [`docs/test.php`](docs/test.php)，执行 `php docs/test.php` 即可查看效果。

## API 文档

### `U2P` —— 入口管理器

| 方法 | 说明 |
| --- | --- |
| `get(string $url): string[]` | 抓取链接中的图片，返回图片链接数组 |
| `registerHandler(HandlerInterface $handler): self` | 注册专项处理器（先注册先匹配） |
| `setFallback(HandlerInterface $handler): self` | 设置兜底处理器 |
| `getHandlers(): HandlerInterface[]` | 获取已注册的处理器列表 |
| `static make(?HttpClient $client = null): self` | 快速创建实例 |

### `WeChatHandler` —— 公众号文章专项

| 方法 | 说明 |
| --- | --- |
| `handle(string $url): string[]` | 抓取正文图片 |
| `withCover(bool $withCover = true): self` | 是否将封面图置于结果首位 |
| `extractContentImages(string $html): string[]` | 从 HTML 提取正文图片（可独立使用） |
| `supports(string $url): bool` | 判断是否为公众号链接 |

**正文图片识别规则：**
- 懒加载地址取自 `data-src`（优先），回退 `data-original` / `data-lazy-src` / `src`
- 图床域名 `mmbiz.qpic.cn`，URL 含 `from=appmsg`
- class 含 `rich_pages` / `wxw-img` 视为正文
- 排除头像、二维码、表情等非正文图片

### `GenericHandler` —— 通用兜底

| 方法 | 说明 |
| --- | --- |
| `handle(string $url): string[]` | 抓取任意网页图片 |
| `extractImages(string $html, string $baseUrl = ''): string[]` | 从 HTML 提取图片（可独立使用） |

### `AbstractHandler` —— 处理器基类

自定义处理器继承此类，可直接使用以下能力：

| 方法 | 说明 |
| --- | --- |
| `fetch(string $url, array $headers = []): string` | 抓取 HTML 内容 |
| `loadDom(string $html): DOMDocument` | 加载 HTML 为 DOM（自动 UTF-8 转码） |
| `resolveSrc(DOMElement $img): string` | 解析 img 真实地址（懒加载优先） |
| `cleanUrl(string $url): string` | 清理地址（去锚点等） |
| `setClient(HttpClient $client): self` | 替换 HTTP 客户端 |

### `HttpClient` —— HTTP 客户端

| 方法 | 说明 |
| --- | --- |
| `get(string $url, array $headers = [], array $options = []): string` | GET 请求 |
| `setDefaultHeaders(array $headers): self` | 设置默认请求头 |
| `setDefaultOptions(array $options): self` | 设置默认 cURL 选项 |

## 进阶用法

### 自定义 HTTP 客户端（代理 / 超时 / Cookie）

```php
use Zyan\U2P\HttpClient;
use Zyan\U2P\U2P;

$client = new HttpClient(
    ['User-Agent' => 'MyBot/1.0', 'Cookie' => 'session=xxx'],
    [CURLOPT_TIMEOUT => 60, CURLOPT_PROXY => 'http://127.0.0.1:7890']
);

$u2p = new U2P($client);
$images = $u2p->get('https://mp.weixin.qq.com/s/xxxx');
```

### 单独使用解析方法（不发起请求）

已有 HTML 内容时，可跳过 HTTP 直接解析：

```php
use Zyan\U2P\Handlers\WeChatHandler;

$handler = new WeChatHandler();
// 从已有 HTML 提取，不抓取网络
$images = $handler->extractContentImages($htmlString);
```

## 架构

```
U2P（入口管理器）
  ├── 注册的专项 Handler（按顺序匹配）
  │     └── WeChatHandler   微信公众号文章
  └── Fallback Handler（兜底）
        └── GenericHandler   任意网页
```

所有 Handler 实现 `HandlerInterface`：

```php
interface HandlerInterface
{
    public function supports(string $url): bool; // 是否支持该链接
    public function handle(string $url): array;  // 返回图片链接数组
}
```

## 目录结构

```
src/
├── U2P.php                      # 入口管理器
├── HandlerInterface.php         # 处理器接口
├── AbstractHandler.php          # 处理器基类
├── HttpClient.php               # cURL 客户端
└── Handlers/
    ├── WeChatHandler.php        # 微信公众号文章专项
    └── GenericHandler.php       # 通用兜底
tests/
├── WeChatHandlerTest.php        # 单元测试
└── fixtures/wechat.html         # 测试用的公众号 HTML 样本
docs/
└── test.php                     # 可运行示例
```

## 测试

```bash
composer install
vendor/bin/phpunit
```

## 贡献

欢迎提交 Issue 和 PR！

新增网站专项处理器时，请：
1. 在 `src/Handlers/` 下创建 `XxxHandler.php`，继承 `AbstractHandler`
2. 实现 `supports()` 与 `handle()`
3. 在 `tests/` 添加测试用例与 fixture
4. 确保所有测试通过：`vendor/bin/phpunit`

## License

MIT License
