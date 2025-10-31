<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyRateLimitException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * DifyRateLimitException 限流异常类单元测试
 * 测试重点：异常基本属性、重试时间、剩余请求数、重置时间戳、头部解析
 * @internal
 */
#[CoversClass(DifyRateLimitException::class)]
class DifyRateLimitExceptionTest extends AbstractExceptionTestCase
{
    public function testBasicExceptionCreation(): void
    {
        $message = 'API 请求限流';
        $retryAfterSeconds = 60;
        $remainingRequests = 0;
        $resetTimestamp = 1640995200; // 2022-01-01 00:00:00 UTC
        $responseBody = '{"error": "rate_limit_exceeded"}';

        $exception = new DifyRateLimitException(
            $message,
            $retryAfterSeconds,
            $remainingRequests,
            $resetTimestamp,
            $responseBody
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame(429, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($retryAfterSeconds, $exception->getRetryAfterSeconds());
        $this->assertSame($remainingRequests, $exception->getRemainingRequests());
        $this->assertSame($resetTimestamp, $exception->getResetTimestamp());
        $this->assertNull($exception->getPrevious());
    }

    public function testDefaultConstructor(): void
    {
        $exception = new DifyRateLimitException();

        $this->assertSame('API 请求限流', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame(429, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertNull($exception->getRetryAfterSeconds());
        $this->assertSame(0, $exception->getRemainingRequests());
        $this->assertSame(0, $exception->getResetTimestamp());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previousException = new \Exception('Network error');
        $message = '请求频率过高';
        $retryAfterSeconds = 30;

        $exception = new DifyRateLimitException(
            $message,
            $retryAfterSeconds,
            0,
            0,
            null,
            $previousException
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($retryAfterSeconds, $exception->getRetryAfterSeconds());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testGetResetTime(): void
    {
        $resetTimestamp = 1640995200; // 2022-01-01 00:00:00 UTC
        $exception = new DifyRateLimitException(
            'Test message',
            null,
            0,
            $resetTimestamp
        );

        $resetTime = $exception->getResetTime();

        $this->assertInstanceOf(\DateTimeImmutable::class, $resetTime);
        $this->assertSame('2022-01-01T00:00:00+00:00', $resetTime->format('c'));
    }

    public function testGetResetTimeWithZeroTimestamp(): void
    {
        $exception = new DifyRateLimitException(
            'Test message',
            null,
            0,
            0
        );

        $resetTime = $exception->getResetTime();

        $this->assertInstanceOf(\DateTimeImmutable::class, $resetTime);
        $this->assertSame('1970-01-01T00:00:00+00:00', $resetTime->format('c'));
    }

    public function testGetResetTimeWithInvalidTimestamp(): void
    {
        // Test with a very large timestamp that might cause createFromFormat to fail
        $exception = new DifyRateLimitException(
            'Test message',
            null,
            0,
            PHP_INT_MAX
        );

        $resetTime = $exception->getResetTime();

        $this->assertInstanceOf(\DateTimeImmutable::class, $resetTime);
        // For PHP_INT_MAX, createFromFormat actually succeeds and returns a far future date
        // So we test that it either returns the expected timestamp or current time as fallback
        $actualTimestamp = $resetTime->getTimestamp();
        $this->assertTrue(
            PHP_INT_MAX === $actualTimestamp || $actualTimestamp <= time() + 1,
            'Reset time should be either the expected large timestamp or current time as fallback'
        );
    }

    public function testFromHeadersWithCompleteHeaders(): void
    {
        $headers = [
            'Retry-After' => '120',
            'X-RateLimit-Remaining' => '5',
            'X-RateLimit-Reset' => '1640995200',
        ];
        $responseBody = '{"error": "too_many_requests"}';

        $exception = DifyRateLimitException::fromHeaders($headers, $responseBody);

        $this->assertSame('请求频率过高，请稍后重试', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame(120, $exception->getRetryAfterSeconds());
        $this->assertSame(5, $exception->getRemainingRequests());
        $this->assertSame(1640995200, $exception->getResetTimestamp());
    }

    public function testFromHeadersWithPartialHeaders(): void
    {
        $headers = [
            'Retry-After' => '60',
        ];

        $exception = DifyRateLimitException::fromHeaders($headers);

        $this->assertSame('请求频率过高，请稍后重试', $exception->getMessage());
        $this->assertSame(60, $exception->getRetryAfterSeconds());
        $this->assertSame(0, $exception->getRemainingRequests());
        $this->assertSame(0, $exception->getResetTimestamp());
    }

    public function testFromHeadersWithNoHeaders(): void
    {
        $headers = [];

        $exception = DifyRateLimitException::fromHeaders($headers);

        $this->assertSame('请求频率过高，请稍后重试', $exception->getMessage());
        $this->assertNull($exception->getRetryAfterSeconds());
        $this->assertSame(0, $exception->getRemainingRequests());
        $this->assertSame(0, $exception->getResetTimestamp());
    }

    /** @param array<string, string> $headers */
    #[TestWith([['retry-after' => '30'], 30])]
    #[TestWith([['RETRY-AFTER' => '45'], 45])]
    #[TestWith([['Retry-After' => '60'], 60])]
    #[TestWith([['x-ratelimit-remaining' => '10'], null])]
    #[TestWith([['X-RATELIMIT-REMAINING' => '5'], null])]
    #[TestWith([['X-RateLimit-Remaining' => '3'], null])]
    public function testFromHeadersCaseInsensitive(array $headers, ?int $expectedRetryAfter): void
    {
        $exception = DifyRateLimitException::fromHeaders($headers);

        $this->assertSame($expectedRetryAfter, $exception->getRetryAfterSeconds());
    }

    
    /** @param array<string, string> $headers */
    #[TestWith([['retry-after' => 'invalid'], null, 0])]
    #[TestWith([['x-ratelimit-remaining' => 'invalid'], null, 0])]
    #[TestWith([['x-ratelimit-reset' => 'invalid'], null, 0])]
    #[TestWith([['retry-after' => ''], null, 0])]
    #[TestWith([['x-ratelimit-remaining' => ''], null, 0])]
    #[TestWith([['retry-after' => '-1'], -1, 0])]
    #[TestWith([['x-ratelimit-remaining' => '-5'], null, -5])]
    #[TestWith([['retry-after' => '0', 'x-ratelimit-remaining' => '0'], 0, 0])]
    public function testFromHeadersWithInvalidValues(array $headers, ?int $expectedRetryAfter, int $expectedRemaining): void
    {
        $exception = DifyRateLimitException::fromHeaders($headers);

        $this->assertSame($expectedRetryAfter, $exception->getRetryAfterSeconds());
        $this->assertSame($expectedRemaining, $exception->getRemainingRequests());
    }

    
    #[TestWith([null])]
    #[TestWith([0])]
    #[TestWith([30])]
    #[TestWith([300])]
    #[TestWith([3600])]
    #[TestWith([86400])]
    #[TestWith([-1])]
    public function testVariousRetryAfterValues(?int $retryAfterSeconds): void
    {
        $exception = new DifyRateLimitException(
            'Test message',
            $retryAfterSeconds
        );

        $this->assertSame($retryAfterSeconds, $exception->getRetryAfterSeconds());
    }

    
    #[TestWith([0])]
    #[TestWith([5])]
    #[TestWith([100])]
    #[TestWith([9999])]
    #[TestWith([-1])]
    public function testVariousRemainingRequests(int $remainingRequests): void
    {
        $exception = new DifyRateLimitException(
            'Test message',
            null,
            $remainingRequests
        );

        $this->assertSame($remainingRequests, $exception->getRemainingRequests());
    }

    
    #[TestWith([0])]
    #[TestWith([1])]
    #[TestWith([946684800])]
    #[TestWith([1640995200])]
    #[TestWith([2000000000])]
    #[TestWith([-1])]
    public function testVariousResetTimestamps(int $resetTimestamp): void
    {
        $exception = new DifyRateLimitException(
            'Test message',
            null,
            0,
            $resetTimestamp
        );

        $this->assertSame($resetTimestamp, $exception->getResetTimestamp());
    }

    
    public function testExceptionInheritance(): void
    {
        $exception = new DifyRateLimitException();

        $this->assertInstanceOf(DifyApiException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionChaining(): void
    {
        $rootException = new \RuntimeException('HTTP client error');
        $networkException = new \Exception('Request failed', 0, $rootException);

        $exception = new DifyRateLimitException(
            'Rate limit exceeded',
            60,
            0,
            time() + 60,
            '{"error": "rate_limit"}',
            $networkException
        );

        // Test exception chain
        $this->assertSame($networkException, $exception->getPrevious());
        $this->assertSame($rootException, $exception->getPrevious()->getPrevious());
        $this->assertNull($exception->getPrevious()->getPrevious()->getPrevious());

        // Test messages in chain
        $this->assertSame('Rate limit exceeded', $exception->getMessage());
        $this->assertSame('Request failed', $exception->getPrevious()->getMessage());
        $this->assertSame('HTTP client error', $exception->getPrevious()->getPrevious()->getMessage());
    }

    public function testToStringRepresentation(): void
    {
        $exception = new DifyRateLimitException(
            'Rate limit test',
            30,
            5,
            time() + 300
        );

        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('DifyRateLimitException', $stringRepresentation);
        $this->assertStringContainsString('Rate limit test', $stringRepresentation);
        $this->assertStringContainsString(__FILE__, $stringRepresentation);
    }

    public function testExceptionSerialization(): void
    {
        $previousException = new \Exception('Previous error');
        $exception = new DifyRateLimitException(
            'Serialization test',
            120,
            10,
            1640995200,
            '{"error": "test"}',
            $previousException
        );

        // Test that exception can be serialized and unserialized
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DifyRateLimitException::class, $unserialized);
        $this->assertSame($exception->getMessage(), $unserialized->getMessage());
        $this->assertSame($exception->getCode(), $unserialized->getCode());
        $this->assertSame($exception->getHttpStatusCode(), $unserialized->getHttpStatusCode());
        $this->assertSame($exception->getResponseBody(), $unserialized->getResponseBody());
        $this->assertSame($exception->getRetryAfterSeconds(), $unserialized->getRetryAfterSeconds());
        $this->assertSame($exception->getRemainingRequests(), $unserialized->getRemainingRequests());
        $this->assertSame($exception->getResetTimestamp(), $unserialized->getResetTimestamp());
    }

    public function testFromHeadersReturnsCorrectType(): void
    {
        $headers = ['retry-after' => '60'];
        $exception = DifyRateLimitException::fromHeaders($headers);

        $this->assertInstanceOf(DifyRateLimitException::class, $exception);
    }

    public function testHttpStatusCodeIsAlways429(): void
    {
        // Test default constructor
        $exception1 = new DifyRateLimitException();
        $this->assertSame(429, $exception1->getCode());
        $this->assertSame(429, $exception1->getHttpStatusCode());

        // Test parameterized constructor
        $exception2 = new DifyRateLimitException('Custom message', 30, 5, time());
        $this->assertSame(429, $exception2->getCode());
        $this->assertSame(429, $exception2->getHttpStatusCode());

        // Test fromHeaders static method
        $exception3 = DifyRateLimitException::fromHeaders(['retry-after' => '45']);
        $this->assertSame(429, $exception3->getCode());
        $this->assertSame(429, $exception3->getHttpStatusCode());
    }

    public function testComplexHeaderParsing(): void
    {
        $headers = [
            'retry-after' => '300',
            'x-ratelimit-remaining' => '0',
            'x-ratelimit-reset' => '1640995500',
            'content-type' => 'application/json',
            'server' => 'nginx/1.18.0',
            'x-custom-header' => 'custom-value',
        ];
        $responseBody = '{"error": "rate_limit_exceeded", "message": "Too many requests"}';

        $exception = DifyRateLimitException::fromHeaders($headers, $responseBody);

        $this->assertSame(300, $exception->getRetryAfterSeconds());
        $this->assertSame(0, $exception->getRemainingRequests());
        $this->assertSame(1640995500, $exception->getResetTimestamp());
        $this->assertSame($responseBody, $exception->getResponseBody());
    }

    public function testResetTimeCalculation(): void
    {
        $now = time();
        $resetTimestamp = $now + 3600; // 1 hour from now

        $exception = new DifyRateLimitException(
            'Test message',
            null,
            0,
            $resetTimestamp
        );

        $resetTime = $exception->getResetTime();
        $expectedTime = \DateTimeImmutable::createFromFormat('U', (string) $resetTimestamp);

        $this->assertInstanceOf(\DateTimeImmutable::class, $resetTime);
        $this->assertInstanceOf(\DateTimeImmutable::class, $expectedTime);
        $this->assertSame($expectedTime->getTimestamp(), $resetTime->getTimestamp());
    }

    public function testNamedParameterConstruction(): void
    {
        $exception = new DifyRateLimitException(
            message: 'Named parameter test',
            retryAfterSeconds: 180,
            remainingRequests: 15,
            resetTimestamp: 1640995800,
            responseBody: '{"test": true}',
            previous: new \Exception('Test previous')
        );

        $this->assertSame('Named parameter test', $exception->getMessage());
        $this->assertSame(180, $exception->getRetryAfterSeconds());
        $this->assertSame(15, $exception->getRemainingRequests());
        $this->assertSame(1640995800, $exception->getResetTimestamp());
        $this->assertSame('{"test": true}', $exception->getResponseBody());
        $this->assertInstanceOf(\Exception::class, $exception->getPrevious());
    }

    protected function getExceptionClass(): string
    {
        return DifyRateLimitException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return DifyApiException::class;
    }
}
