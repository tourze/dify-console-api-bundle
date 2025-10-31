<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * Dify API 基础异常类
 *
 * 用于 Dify Console API 调用过程中发生的各种错误
 */
abstract class DifyApiException extends \Exception
{
    private int $httpStatusCode;

    private ?string $responseBody;

    public function __construct(
        string $message = '',
        int $httpStatusCode = 0,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
