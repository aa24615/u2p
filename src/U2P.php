<?php

declare(strict_types=1);

namespace Zyan\U2P;

use Zyan\U2P\Handlers\GenericHandler;
use Zyan\U2P\Handlers\ToutiaoHandler;
use Zyan\U2P\Handlers\WeChatHandler;

/**
 * 网页图片抓取 SDK 入口。
 *
 * 根据 URL 自动匹配专项处理器（如微信公众号），
 * 未匹配时回退到通用处理器。可注册自定义处理器以支持更多网站。
 *
 * 用法：
 *   $u2p = new \Zyan\U2P\U2P();
 *   $images = $u2p->get('https://mp.weixin.qq.com/s/xxxx');
 *
 * 自定义处理器：
 *   $u2p->registerHandler(new MyHandler());
 */
class U2P
{
    /**
     * 已注册的专项处理器（先注册先匹配）。
     *
     * @var HandlerInterface[]
     */
    protected array $handlers = [];

    /**
     * 兜底处理器。
     */
    protected ?HandlerInterface $fallback = null;

    public function __construct(?HttpClient $client = null)
    {
        $client = $client ?: new HttpClient();

        $this->registerHandler(new WeChatHandler($client));
        $this->registerHandler(new ToutiaoHandler($client));
        $this->setFallback(new GenericHandler($client));
    }

    /**
     * 注册专项处理器（优先于已注册的处理器匹配）。
     *
     * @return $this
     */
    public function registerHandler(HandlerInterface $handler): self
    {
        array_unshift($this->handlers, $handler);
        return $this;
    }

    /**
     * @return HandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function setFallback(HandlerInterface $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function getFallback(): ?HandlerInterface
    {
        return $this->fallback;
    }

    /**
     * 抓取指定链接中的图片，返回图片链接数组。
     *
     * @return string[]
     *
     * @throws \RuntimeException 当没有匹配的处理器时
     */
    public function get(string $url): array
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($url)) {
                return $handler->handle($url);
            }
        }

        if ($this->fallback !== null) {
            return $this->fallback->handle($url);
        }

        throw new \RuntimeException('No suitable handler for URL: ' . $url);
    }

    /**
     * 快速创建实例。
     */
    public static function make(?HttpClient $client = null): self
    {
        return new self($client);
    }
}
