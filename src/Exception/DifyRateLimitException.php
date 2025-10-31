<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * Dify 限流异常
 *
 * 当 Dify Console API 请求超过限流阈值时抛出此异常
 * 包含重试建议时间和限流详情
 */
class DifyRateLimitException extends DifyApiException
{
    private ?int $retryAfterSeconds;

    private int $remainingRequests;

    private int $resetTimestamp;

    public function __construct(
        string $message = 'API 请求限流',
        ?int $retryAfterSeconds = null,
        int $remainingRequests = 0,
        int $resetTimestamp = 0,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $responseBody, $previous);
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->remainingRequests = $remainingRequests;
        $this->resetTimestamp = $resetTimestamp;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function getRemainingRequests(): int
    {
        return $this->remainingRequests;
    }

    public function getResetTimestamp(): int
    {
        return $this->resetTimestamp;
    }

    public function getResetTime(): \DateTimeImmutable
    {
        $result = \DateTimeImmutable::createFromFormat('U', (string) $this->resetTimestamp);

        return false !== $result ? $result : new \DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $headers
     */
    public static function fromHeaders(array $headers, string $responseBody = ''): self
    {
        $retryAfter = null;
        $remaining = 0;
        $reset = 0;

        // 解析 X-RateLimit-* 或 Retry-After 头
        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            if ('retry-after' === $name) {
                $retryAfter = is_numeric($value) ? (int) $value : null;
            } elseif ('x-ratelimit-remaining' === $name && is_numeric($value)) {
                $remaining = (int) $value;
            } elseif ('x-ratelimit-reset' === $name && is_numeric($value)) {
                $reset = (int) $value;
            }
        }

        return new self(
            '请求频率过高，请稍后重试',
            $retryAfter,
            $remaining,
            $reset,
            $responseBody
        );
    }

    public static function rateLimitExceeded(string $responseBody = ''): self
    {
        return new self(
            '请求频率过高，请稍后重试',
            null,
            0,
            0,
            $responseBody
        );
    }
}
