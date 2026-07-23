<?php

declare(strict_types=1);

namespace Zyan\U2P\Handlers;

use Zyan\U2P\AbstractHandler;

/**
 * 豆包 AI 对话分享页图片抓取。
 *
 * 分享页图片通过 mergeLoaderData 注入页面脚本，正文图取 creation_block 中
 * image.image_ori_raw（无水印原图），而非带水印的预览地址。
 *
 * 解析逻辑参考 https://github.com/ihmily/doubao-nomark
 *
 * 用法：
 *   $handler = new DoubaoHandler();
 *   $images  = $handler->handle('https://www.doubao.com/thread/xxxx');
 */
class DoubaoHandler extends AbstractHandler
{
    /**
     * {@inheritdoc}
     */
    public function supports(string $url): bool
    {
        return (bool) preg_match('#https?://(?:www\.)?doubao\.com/thread/#i', $url);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(string $url): array
    {
        $html = $this->fetch($url);

        return $this->extractContentImages($html);
    }

    /**
     * 从豆包分享页 HTML 中提取无水印图片链接。
     *
     * @return string[]
     */
    public function extractContentImages(string $html): array
    {
        $payload = $this->extractPagePayload($html);
        if ($payload === null) {
            return [];
        }

        $images = [];
        foreach ($payload as $item) {
            if (is_array($item) && isset($item['data']['message_snapshot']['message_list'])) {
                $images = array_merge($images, $this->extractFromMessageList($item['data']['message_snapshot']['message_list']));
                continue;
            }

            if (!is_array($item) || empty($item[0]['routerDataFnArgs'][0])) {
                continue;
            }

            $router = json_decode((string) $item[0]['routerDataFnArgs'][0], true);
            if (!is_array($router) || !isset($router['data']['message_snapshot']['message_list'])) {
                continue;
            }

            $images = array_merge(
                $images,
                $this->extractFromMessageList($router['data']['message_snapshot']['message_list'])
            );
        }

        return array_values(array_unique(array_filter($images)));
    }

    /**
     * 从页面脚本中提取 mergeLoaderData / router-data 载荷。
     *
     * @return array<int,mixed>|null
     */
    protected function extractPagePayload(string $html): ?array
    {
        $patterns = [
            '/data-script-src="modern-run-router-data-fn"\s+data-fn-args="(.*?)"\s+nonce="/s',
            '/data-script-src="modern-run-window-fn"\s+data-fn-name="mergeLoaderData"\s+data-fn-args="(.*?)"\s+nonce="/s',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $html, $match)) {
                continue;
            }

            $json = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $payload = json_decode($json, true);
            if (is_array($payload)) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $messageList
     *
     * @return string[]
     */
    protected function extractFromMessageList(array $messageList): array
    {
        $images = [];

        foreach ($messageList as $message) {
            foreach ($message['content_block'] ?? [] as $block) {
                $content = $block['content_v2'] ?? $block['content'] ?? null;
                if ($content === null) {
                    continue;
                }

                $contentData = is_string($content) ? json_decode($content, true) : $content;
                if (!is_array($contentData)) {
                    continue;
                }

                foreach ($contentData['creation_block']['creations'] ?? [] as $creation) {
                    $url = $creation['image']['image_ori_raw']['url'] ?? '';
                    if ($url === '') {
                        continue;
                    }
                    $images[] = $this->cleanUrl(str_replace('&amp;', '&', $url));
                }
            }
        }

        return $images;
    }
}
