<?php

declare(strict_types=1);

namespace Zyan\U2P;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;

/**
 * 基于 Guzzle 的 HTTP 客户端封装。
 *
 * 提供浏览器 UA、重定向、超时等默认配置，
 * 也可通过构造函数注入自定义 GuzzleClient（如测试时用 MockHandler）。
 */
class HttpClient
{
    /**
     * 默认请求头。
     */
    protected array $defaultHeaders = [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
    ];

    /**
     * Guzzle 客户端实例。
     */
    protected GuzzleClient $guzzle;

    public function __construct(?GuzzleClient $guzzle = null)
    {
        $this->guzzle = $guzzle ?? new GuzzleClient([
            'timeout'         => 30,
            'connect_timeout' => 15,
            'allow_redirects' => ['max' => 5],
            'verify'          => false,
            'http_errors'     => true,
        ]);
    }

    /**
     * 发送 GET 请求并返回响应体。
     *
     * @param array<string,string> $headers  额外请求头（与默认头合并）
     *
     * @throws \GuzzleHttp\Exception\GuzzleException 当请求失败或 HTTP 状态码 >= 400 时
     */
    public function get(string $url, array $headers = [], array $options = []): string
    {
        $response = $this->guzzle->get($url, [
            RequestOptions::HEADERS => array_merge($this->defaultHeaders, $headers),
        ]);

        return (string) $response->getBody();
    }

    /**
     * 设置默认请求头（与已有默认头合并）。
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * 获取底层 Guzzle 客户端，便于高级配置。
     */
    public function getGuzzle(): GuzzleClient
    {
        return $this->guzzle;
    }
}
