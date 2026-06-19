<?php

declare(strict_types=1);

namespace Zyan\U2P;

/**
 * 基于 cURL 的轻量 HTTP 客户端。
 *
 * 零外部依赖，自带浏览器 UA 与重定向、压缩处理，
 * 也可通过子类化（覆盖 get()）实现测试 mock。
 */
class HttpClient
{
    /**
     * 默认 cURL 选项。
     */
    protected array $defaultOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => '',
    ];

    /**
     * 默认请求头。
     */
    protected array $defaultHeaders = [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
    ];

    public function __construct(array $defaultHeaders = [], array $defaultOptions = [])
    {
        if (!empty($defaultHeaders)) {
            $this->defaultHeaders = array_merge($this->defaultHeaders, $defaultHeaders);
        }
        if (!empty($defaultOptions)) {
            $this->defaultOptions = $defaultOptions + $this->defaultOptions;
        }
    }

    /**
     * 发送 GET 请求并返回响应体。
     *
     * @param array<string,string> $headers  额外请求头
     * @param array<int,mixed>     $options  额外 cURL 选项
     *
     * @throws \RuntimeException 当请求失败或 HTTP 状态码 >= 400 时
     */
    public function get(string $url, array $headers = [], array $options = []): string
    {
        $ch = curl_init($url);

        $opts = $this->defaultOptions;

        $headerLines = [];
        foreach (array_merge($this->defaultHeaders, $headers) as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $opts[CURLOPT_HTTPHEADER] = $headerLines;

        foreach ($options as $key => $value) {
            $opts[$key] = $value;
        }

        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            throw new \RuntimeException('HTTP request failed with status ' . $code . ' for ' . $url);
        }

        return (string) $body;
    }

    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;
        return $this;
    }

    public function setDefaultOptions(array $options): self
    {
        $this->defaultOptions = $options + $this->defaultOptions;
        return $this;
    }
}
