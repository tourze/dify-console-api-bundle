<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * Dify 实例不可用异常
 *
 * 当 Dify 实例无法访问或服务不可用时抛出此异常
 * 包括网络连接错误、服务维护、实例配置错误等情况
 */
class DifyInstanceUnavailableException extends DifyApiException
{
    private string $instanceUrl;

    private ?string $reasonCode;

    public function __construct(
        string $instanceUrl,
        string $message = '实例不可用',
        ?string $reasonCode = null,
        int $httpStatusCode = 503,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $responseBody, $previous);
        $this->instanceUrl = $instanceUrl;
        $this->reasonCode = $reasonCode;
    }

    public function getInstanceUrl(): string
    {
        return $this->instanceUrl;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public static function connectionFailed(string $instanceUrl, ?\Throwable $previous = null): self
    {
        return new self(
            $instanceUrl,
            "无法连接到 Dify 实例: {$instanceUrl}",
            'CONNECTION_FAILED',
            0,
            null,
            $previous
        );
    }

    public static function serviceUnavailable(string $instanceUrl, string $responseBody = ''): self
    {
        return new self(
            $instanceUrl,
            "Dify 实例服务不可用: {$instanceUrl}",
            'SERVICE_UNAVAILABLE',
            503,
            $responseBody
        );
    }

    public static function maintenance(string $instanceUrl, string $responseBody = ''): self
    {
        return new self(
            $instanceUrl,
            "Dify 实例正在维护中: {$instanceUrl}",
            'MAINTENANCE',
            503,
            $responseBody
        );
    }

    public static function configurationError(string $instanceUrl, string $reason = ''): self
    {
        $message = "Dify 实例配置错误: {$instanceUrl}";
        if ('' !== $reason) {
            $message .= " - {$reason}";
        }

        return new self(
            $instanceUrl,
            $message,
            'CONFIGURATION_ERROR',
            500
        );
    }

    public static function instanceUnavailable(string $responseBody = ''): self
    {
        return new self(
            '',
            'Dify 实例服务不可用',
            'SERVICE_ERROR',
            503,
            $responseBody
        );
    }
}
