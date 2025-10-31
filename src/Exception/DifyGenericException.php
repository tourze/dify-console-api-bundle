<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * Dify 通用异常
 *
 * 当 Dify Console API 调用过程中发生未分类的错误时抛出此异常
 */
final class DifyGenericException extends DifyApiException
{
    public static function create(string $message, int $httpStatusCode = 0, ?string $responseBody = null, ?\Throwable $previous = null): self
    {
        return new self($message, $httpStatusCode, $responseBody, $previous);
    }
}
