<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyGenericException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * Dify通用异常单元测试
 *
 * 测试重点：继承的基类功能、静态工厂方法、异常消息和状态码
 * @internal
 */
#[CoversClass(DifyGenericException::class)]
class DifyGenericExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsBaseDifyApiException(): void
    {
        $exception = new DifyGenericException('Test message');

        $this->assertInstanceOf(DifyApiException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testConstructorWithDefaultParameters(): void
    {
        $exception = new DifyGenericException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Dify API 调用发生未知错误';
        $exception = new DifyGenericException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
    }

    public function testConstructorWithAllParameters(): void
    {
        $message = 'Dify API 调用失败';
        $httpStatusCode = 500;
        $responseBody = '{"error": "Internal Server Error", "details": "Service unavailable"}';
        $previousException = new \RuntimeException('Previous error');

        $exception = new DifyGenericException($message, $httpStatusCode, $responseBody, $previousException);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testCreateStaticFactoryMethodWithMinimalParameters(): void
    {
        $message = 'Generic Dify error occurred';
        $exception = DifyGenericException::create($message);

        $this->assertInstanceOf(DifyGenericException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertNull($exception->getPrevious());
    }

    public function testCreateStaticFactoryMethodWithAllParameters(): void
    {
        $message = 'Dify Console API 请求超时';
        $httpStatusCode = 408;
        $responseBody = '{"error": "Request Timeout", "code": 408}';
        $previousException = new \Exception('Connection timeout');

        $exception = DifyGenericException::create($message, $httpStatusCode, $responseBody, $previousException);

        $this->assertInstanceOf(DifyGenericException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    #[DataProvider('httpErrorScenarioProvider')]
    public function testHttpErrorScenarios(string $message, int $httpStatusCode, ?string $responseBody): void
    {
        $exception = DifyGenericException::create($message, $httpStatusCode, $responseBody);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
    }

    /**
     * @return array<string, array{message: string, httpStatusCode: int, responseBody: string|null}>
     */
    public static function httpErrorScenarioProvider(): array
    {
        return [
            'client_error_400' => [
                'message' => 'Bad Request - 请求参数无效',
                'httpStatusCode' => 400,
                'responseBody' => '{"error": "Invalid request parameters", "code": 400}',
            ],
            'unauthorized_401' => [
                'message' => 'Unauthorized - 认证失败',
                'httpStatusCode' => 401,
                'responseBody' => '{"error": "Authentication failed", "code": 401}',
            ],
            'forbidden_403' => [
                'message' => 'Forbidden - 访问被拒绝',
                'httpStatusCode' => 403,
                'responseBody' => '{"error": "Access denied", "code": 403}',
            ],
            'not_found_404' => [
                'message' => 'Not Found - 资源不存在',
                'httpStatusCode' => 404,
                'responseBody' => '{"error": "Resource not found", "code": 404}',
            ],
            'rate_limit_429' => [
                'message' => 'Too Many Requests - 请求频率过高',
                'httpStatusCode' => 429,
                'responseBody' => '{"error": "Rate limit exceeded", "code": 429, "retry_after": 60}',
            ],
            'server_error_500' => [
                'message' => 'Internal Server Error - 服务器内部错误',
                'httpStatusCode' => 500,
                'responseBody' => '{"error": "Internal server error", "code": 500}',
            ],
            'service_unavailable_503' => [
                'message' => 'Service Unavailable - 服务暂时不可用',
                'httpStatusCode' => 503,
                'responseBody' => '{"error": "Service temporarily unavailable", "code": 503}',
            ],
            'empty_response_body' => [
                'message' => 'Unknown error with empty response',
                'httpStatusCode' => 500,
                'responseBody' => null,
            ],
        ];
    }

    #[DataProvider('errorMessageProvider')]
    public function testErrorMessageHandling(string $message): void
    {
        $exception = DifyGenericException::create($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertInstanceOf(DifyGenericException::class, $exception);
    }

    /**
     * @return array<string, array{message: string}>
     */
    public static function errorMessageProvider(): array
    {
        return [
            'simple_message' => [
                'message' => 'Simple error',
            ],
            'chinese_message' => [
                'message' => 'Dify API 调用失败，请稍后重试',
            ],
            'detailed_message' => [
                'message' => 'Dify Console API call failed: Unable to connect to remote server. The request timed out after 30 seconds.',
            ],
            'json_error_message' => [
                'message' => 'API response contained invalid JSON: {"error": "malformed request"}',
            ],
            'empty_message' => [
                'message' => '',
            ],
            'special_characters' => [
                'message' => 'Error with special chars: <>&"\'',
            ],
            'multiline_message' => [
                'message' => "Multiline error message:\nLine 1: API connection failed\nLine 2: Retry attempts exhausted",
            ],
        ];
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('Database connection failed');
        $middlewareCause = new \Exception('Service layer error', 0, $rootCause);
        $difyException = DifyGenericException::create(
            'Dify API call failed due to underlying service error',
            500,
            '{"error": "Service error"}',
            $middlewareCause
        );

        $this->assertSame($middlewareCause, $difyException->getPrevious());
        $previous = $difyException->getPrevious();
        $this->assertNotNull($previous);
        $this->assertSame($rootCause, $previous->getPrevious());
    }

    public function testResponseBodyHandling(): void
    {
        $jsonResponseBody = '{"error": "validation_failed", "details": {"field": "name", "message": "required"}}';
        $exception = DifyGenericException::create('Validation failed', 400, $jsonResponseBody);

        $this->assertSame($jsonResponseBody, $exception->getResponseBody());

        // Test with null response body
        $exceptionWithNullBody = DifyGenericException::create('No response body', 500, null);
        $this->assertNull($exceptionWithNullBody->getResponseBody());
    }

    public function testExceptionIsSerializable(): void
    {
        $originalException = DifyGenericException::create(
            'Serialization test error',
            500,
            '{"error": "test"}',
            new \RuntimeException('Previous error')
        );

        $serialized = serialize($originalException);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DifyGenericException::class, $unserialized);
        $this->assertSame($originalException->getMessage(), $unserialized->getMessage());
        $this->assertSame($originalException->getCode(), $unserialized->getCode());
        $this->assertSame($originalException->getHttpStatusCode(), $unserialized->getHttpStatusCode());
        $this->assertSame($originalException->getResponseBody(), $unserialized->getResponseBody());
    }

    public function testInheritedMethodsFromBaseException(): void
    {
        $exception = DifyGenericException::create('Test error');

        // 异常的 getFile() 应该返回异常类的文件位置，而不是调用者的位置
        $expectedFile = '/home/admin/work/source/php-monorepo/packages/dify-console-api-bundle/src/Exception/DifyGenericException.php';
        $this->assertSame($expectedFile, $exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertIsArray($exception->getTrace());
        $this->assertIsString($exception->getTraceAsString());
        $this->assertIsString((string) $exception);
    }

    public function testMagicToStringMethod(): void
    {
        $exception = DifyGenericException::create('Dify generic error', 500, '{"error": "server error"}');
        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('Tourze\DifyConsoleApiBundle\Exception\DifyGenericException', $stringRepresentation);
        $this->assertStringContainsString('Dify generic error', $stringRepresentation);
        $this->assertStringContainsString(__FILE__, $stringRepresentation);
    }

    public function testCreateFactoryVersusDirectInstantiation(): void
    {
        $message = 'Test error message';
        $httpStatusCode = 422;
        $responseBody = '{"validation_errors": []}';
        $previousException = new \InvalidArgumentException('Invalid data');

        $createdViaFactory = DifyGenericException::create($message, $httpStatusCode, $responseBody, $previousException);
        $createdDirectly = new DifyGenericException($message, $httpStatusCode, $responseBody, $previousException);

        $this->assertEquals($createdViaFactory->getMessage(), $createdDirectly->getMessage());
        $this->assertEquals($createdViaFactory->getCode(), $createdDirectly->getCode());
        $this->assertEquals($createdViaFactory->getHttpStatusCode(), $createdDirectly->getHttpStatusCode());
        $this->assertEquals($createdViaFactory->getResponseBody(), $createdDirectly->getResponseBody());
        $this->assertEquals($createdViaFactory->getPrevious(), $createdDirectly->getPrevious());
    }

    protected function getExceptionClass(): string
    {
        return DifyGenericException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return DifyApiException::class;
    }
}
