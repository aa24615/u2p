<?php

declare(strict_types=1);

namespace Zyan\U2P;

/**
 * 图片抓取处理器接口。
 *
 * 每个具体网站（如微信公众号）实现一个 Handler，
 * 通过 supports() 判断是否处理该链接，handle() 返回图片链接数组。
 */
interface HandlerInterface
{
    /**
     * 判断当前处理器是否支持给定的链接。
     */
    public function supports(string $url): bool;

    /**
     * 抓取给定链接中的图片，返回图片链接数组。
     *
     * @return string[]
     */
    public function handle(string $url): array;
}
