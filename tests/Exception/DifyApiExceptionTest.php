<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * DifyApiException 基础异常类单元测试
 * 测试重点：异常基本属性、HTTP状态码、响应体、链式异常
 * @internal
 */
#[CoversClass(DifyApiException::class)]
class DifyApiExceptionTest extends AbstractExceptionTestCase
{
    public function testBasicExceptionCreation(): void
    {
        $message = 'API request failed';
        $httpStatusCode = 500;
        $responseBody = '{"error": "Internal server error"}';

        $exception = new ConcreteDifyApiException($message, $httpStatusCode, $responseBody);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithDefaults(): void
    {
        $exception = new ConcreteDifyApiException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previousException = new \Exception('Original error');
        $message = 'Dify API error occurred';
        $httpStatusCode = 400;
        $responseBody = '{"error": "Bad request"}';

        $exception = new ConcreteDifyApiException($message, $httpStatusCode, $responseBody, $previousException);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    #[TestWith([200, 'success'])]
    #[TestWith([201, 'success'])]
    #[TestWith([400, 'client_error'])]
    #[TestWith([401, 'client_error'])]
    #[TestWith([403, 'client_error'])]
    #[TestWith([404, 'client_error'])]
    #[TestWith([422, 'client_error'])]
    #[TestWith([429, 'client_error'])]
    #[TestWith([500, 'server_error'])]
    #[TestWith([502, 'server_error'])]
    #[TestWith([503, 'server_error'])]
    #[TestWith([504, 'server_error'])]
    #[TestWith([0, 'unknown'])]
    #[TestWith([-1, 'unknown'])]
    public function testVariousHttpStatusCodes(int $statusCode, string $expectedType): void
    {
        $exception = new ConcreteDifyApiException('Test message', $statusCode, null);

        $this->assertSame($statusCode, $exception->getHttpStatusCode());
        $this->assertSame($statusCode, $exception->getCode());

        // Verify status code type based on HTTP standard
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->assertSame('success', $expectedType);
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            $this->assertSame('client_error', $expectedType);
        } elseif ($statusCode >= 500 && $statusCode < 600) {
            $this->assertSame('server_error', $expectedType);
        }
    }

    
    public function testVariousResponseBodies(): void
    {
        $testCases = [
            null,
            '',
            '{"error": "Invalid request", "code": "INVALID_REQUEST"}',
            '<html><body><h1>404 Not Found</h1></body></html>',
            'Server temporarily unavailable',
            str_repeat('Error details: ', 100),
            '{"错误": "请求无效", "代码": "INVALID_REQUEST"}',
            '{"error": "incomplete json',
            '<?xml version="1.0"?><error><message>Server error</message></error>',
        ];

        foreach ($testCases as $responseBody) {
            $exception = new ConcreteDifyApiException('Test message', 400, $responseBody);
            $this->assertSame($responseBody, $exception->getResponseBody());
        }
    }

    public function testVariousMessages(): void
    {
        $testCases = [
            '',
            'API request failed',
            'Failed to connect to Dify API endpoint: Connection timeout after 30 seconds',
            'Dify API 请求失败：连接超时',
            str_repeat('Very detailed error message. ', 20),
            "Error occurred:\nLine 1\nLine 2",
            'Error: API key "invalid" @endpoint /v1/apps',
        ];

        foreach ($testCases as $message) {
            $exception = new ConcreteDifyApiException($message, 500);
            $this->assertSame($message, $exception->getMessage());
        }
    }

    
    public function testExceptionInheritance(): void
    {
        $exception = new ConcreteDifyApiException('Test message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionChaining(): void
    {
        $rootException = new \InvalidArgumentException('Invalid parameter');
        $networkException = new \RuntimeException('Network error', 0, $rootException);
        $difyException = new ConcreteDifyApiException(
            'Dify API call failed',
            500,
            '{"error": "upstream error"}',
            $networkException
        );

        // Test exception chain
        $this->assertSame($networkException, $difyException->getPrevious());
        $this->assertSame($rootException, $difyException->getPrevious()->getPrevious());
        $this->assertNull($difyException->getPrevious()->getPrevious()->getPrevious());

        // Test messages in chain
        $this->assertSame('Dify API call failed', $difyException->getMessage());
        $this->assertSame('Network error', $difyException->getPrevious()->getMessage());
        $this->assertSame('Invalid parameter', $difyException->getPrevious()->getPrevious()->getMessage());
    }

    public function testToStringRepresentation(): void
    {
        $exception = new ConcreteDifyApiException(
            'API request failed',
            404,
            '{"error": "not found"}'
        );

        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('DifyApiException', $stringRepresentation);
        $this->assertStringContainsString('API request failed', $stringRepresentation);
        $this->assertStringContainsString(__FILE__, $stringRepresentation); // Should contain file name
    }

    public function testGetTraceAsString(): void
    {
        $exception = new ConcreteDifyApiException('Test exception for trace');

        $trace = $exception->getTraceAsString();

        $this->assertIsString($trace);
        $this->assertStringContainsString(__CLASS__, $trace);
        $this->assertStringContainsString(__FUNCTION__, $trace);
    }

    public function testGetTrace(): void
    {
        $exception = new ConcreteDifyApiException('Test exception for trace');

        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testHttpStatusCodeAndCodeConsistency(): void
    {
        $statusCode = 422;
        $exception = new ConcreteDifyApiException('Validation error', $statusCode);

        // Both getCode() and getHttpStatusCode() should return the same value
        $this->assertSame($statusCode, $exception->getCode());
        $this->assertSame($statusCode, $exception->getHttpStatusCode());
    }

    public function testMutableResponseBodyAccess(): void
    {
        $responseBody = '{"initial": "response"}';
        $exception = new ConcreteDifyApiException('Test', 500, $responseBody);

        // Response body should be accessible and immutable
        $this->assertSame($responseBody, $exception->getResponseBody());

        // Create new exception with different response body
        $newResponseBody = '{"updated": "response"}';
        $newException = new ConcreteDifyApiException('Test', 500, $newResponseBody);

        $this->assertSame($newResponseBody, $newException->getResponseBody());
        $this->assertNotSame($exception->getResponseBody(), $newException->getResponseBody());
    }

    public function testExceptionSerializability(): void
    {
        $exception = new ConcreteDifyApiException(
            'Serialization test',
            500,
            '{"test": "response"}',
            new \Exception('Previous exception')
        );

        // Test that exception can be serialized and unserialized
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DifyApiException::class, $unserialized);
        $this->assertSame($exception->getMessage(), $unserialized->getMessage());
        $this->assertSame($exception->getCode(), $unserialized->getCode());
        $this->assertSame($exception->getHttpStatusCode(), $unserialized->getHttpStatusCode());
        $this->assertSame($exception->getResponseBody(), $unserialized->getResponseBody());
    }

    public function testExceptionWithZeroValues(): void
    {
        $exception = new ConcreteDifyApiException('', 0, null, null);

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithLargeValues(): void
    {
        $largeMessage = str_repeat('A', 10000);
        $largeResponseBody = str_repeat('{"key": "value"}', 1000);
        $largeStatusCode = 999999;

        $exception = new ConcreteDifyApiException($largeMessage, $largeStatusCode, $largeResponseBody);

        $this->assertSame($largeMessage, $exception->getMessage());
        $this->assertSame($largeStatusCode, $exception->getCode());
        $this->assertSame($largeStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($largeResponseBody, $exception->getResponseBody());
    }

    public function testNamedParameterConstruction(): void
    {
        $exception = new ConcreteDifyApiException(
            message: 'Named parameter test',
            httpStatusCode: 418, // I'm a teapot
            responseBody: '{"teapot": true}',
            previous: new \Exception('Brewing error')
        );

        $this->assertSame('Named parameter test', $exception->getMessage());
        $this->assertSame(418, $exception->getHttpStatusCode());
        $this->assertSame('{"teapot": true}', $exception->getResponseBody());
        $this->assertInstanceOf(\Exception::class, $exception->getPrevious());
    }

    protected function getExceptionClass(): string
    {
        return DifyApiException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return \Exception::class;
    }
}
