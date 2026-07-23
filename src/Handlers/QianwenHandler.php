<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 千问 AI 对话分享页图片抓取。
 *
 * 分享页为 SPA，图片数据需通过 share/info 接口获取；
 * 取 display_list[].image[0].url 作为无水印原图，排除 watermark_image / thumbnail。
 *
 * 解析逻辑参考 https://github.com/ihmily/doubao-nomark
 *
 * 用法：
 *   $handler = new QianwenHandler();
 *   $images  = $handler->handle('https://www.qianwen.com/share/chat/xxxx');
 */
class QianwenHandler extends AbstractHandler
{
    /**
     * 千问分享详情 API。
     */
    protected string $apiUrl = 'https://chat2-api.qianwen.com/api/v1/share/info';

    /**
     * API 请求头。
     */
    protected array $apiHeaders = [
        'Origin'          => 'https://www.qianwen.com',
        'Referer'         => 'https://www.qianwen.com/',
        'Accept'          => 'application/json, text/plain, */*',
        'Accept-Language' => 'zh-CN,zh;q=0.9',
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\.)?qianwen\.com/share/chat/#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $url): array
    {
        $shareId = $this->extractShareId($url);
        if ($shareId === '') {
            return [];
        }

        $body = $this->client->post($this->apiUrl, [
            'share_id' => $shareId,
            'biz_id'   => 'ai_qwen',
        ], $this->apiHeaders);

        return $this->extractContentImages($body);
    }

    /**
     * 从分享链接中提取 share_id。
     */
    public function extractShareId(string $url): string
    {
        if (preg_match('#/share/chat/([a-f0-9]+)#i', $url, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * 从 share/info API 响应 JSON 中提取无水印图片链接。
     *
     * @return string[]
     */
    public function extractContentImages(string $jsonBody): array
    {
        $data = json_decode($jsonBody, true);
        if (!is_array($data) || empty($data['data']['session']['record_list'])) {
            return [];
        }

        $images = [];
        foreach ($data['data']['session']['record_list'] as $record) {
            foreach ($record['response_messages'] ?? [] as $message) {
                if (($message['mime_type'] ?? '') !== 'multi_load/iframe') {
                    continue;
                }
                if (($message['status'] ?? '') !== 'complete') {
                    continue;
                }

                foreach ($message['meta_data']['multi_load'] ?? [] as $item) {
                    foreach ($item['content']['display_list'] ?? [] as $display) {
                        $url = $display['image'][0]['url'] ?? '';
                        if ($url === '') {
                            continue;
                        }
                        $images[] = $this->cleanUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($images)));
    }
}
