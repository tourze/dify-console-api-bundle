<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyInstanceUnavailableException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * DifyInstanceUnavailableException 实例不可用异常类单元测试
 * 测试重点：异常基本属性、实例URL、原因代码、静态工厂方法
 * @internal
 */
#[CoversClass(DifyInstanceUnavailableException::class)]
class DifyInstanceUnavailableExceptionTest extends AbstractExceptionTestCase
{
    public function testBasicExceptionCreation(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $message = '实例不可用';
        $reasonCode = 'TEST_REASON';
        $httpStatusCode = 503;
        $responseBody = '{"error": "service unavailable"}';

        $exception = new DifyInstanceUnavailableException(
            $instanceUrl,
            $message,
            $reasonCode,
            $httpStatusCode,
            $responseBody
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($httpStatusCode, $exception->getCode());
        $this->assertSame($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame($reasonCode, $exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testDefaultConstructor(): void
    {
        $instanceUrl = 'https://dify.example.com';

        $exception = new DifyInstanceUnavailableException($instanceUrl);

        $this->assertSame('实例不可用', $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame(503, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertNull($exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $previousException = new \Exception('Network error');

        $exception = new DifyInstanceUnavailableException(
            $instanceUrl,
            '连接失败',
            'CONNECTION_FAILED',
            0,
            null,
            $previousException
        );

        $this->assertSame('连接失败', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONNECTION_FAILED', $exception->getReasonCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testConnectionFailedStaticMethod(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $previousException = new \Exception('Connection timeout');

        $exception = DifyInstanceUnavailableException::connectionFailed($instanceUrl, $previousException);

        $this->assertSame("无法连接到 Dify 实例: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONNECTION_FAILED', $exception->getReasonCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testConnectionFailedWithoutPreviousException(): void
    {
        $instanceUrl = 'https://api.dify.com';

        $exception = DifyInstanceUnavailableException::connectionFailed($instanceUrl);

        $this->assertSame("无法连接到 Dify 实例: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONNECTION_FAILED', $exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testServiceUnavailableStaticMethod(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $responseBody = '{"error": "Service temporarily unavailable"}';

        $exception = DifyInstanceUnavailableException::serviceUnavailable($instanceUrl, $responseBody);

        $this->assertSame("Dify 实例服务不可用: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame(503, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('SERVICE_UNAVAILABLE', $exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testServiceUnavailableWithoutResponseBody(): void
    {
        $instanceUrl = 'https://api.dify.com';

        $exception = DifyInstanceUnavailableException::serviceUnavailable($instanceUrl);

        $this->assertSame("Dify 实例服务不可用: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('SERVICE_UNAVAILABLE', $exception->getReasonCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testMaintenanceStaticMethod(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $responseBody = '{"message": "Scheduled maintenance in progress"}';

        $exception = DifyInstanceUnavailableException::maintenance($instanceUrl, $responseBody);

        $this->assertSame("Dify 实例正在维护中: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame(503, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('MAINTENANCE', $exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testMaintenanceWithoutResponseBody(): void
    {
        $instanceUrl = 'https://api.dify.com';

        $exception = DifyInstanceUnavailableException::maintenance($instanceUrl);

        $this->assertSame("Dify 实例正在维护中: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('MAINTENANCE', $exception->getReasonCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testConfigurationErrorStaticMethod(): void
    {
        $instanceUrl = 'https://dify.example.com';
        $reason = 'Invalid API endpoint configuration';

        $exception = DifyInstanceUnavailableException::configurationError($instanceUrl, $reason);

        $this->assertSame("Dify 实例配置错误: {$instanceUrl} - {$reason}", $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame(500, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONFIGURATION_ERROR', $exception->getReasonCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConfigurationErrorWithoutReason(): void
    {
        $instanceUrl = 'https://api.dify.com';

        $exception = DifyInstanceUnavailableException::configurationError($instanceUrl);

        $this->assertSame("Dify 实例配置错误: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONFIGURATION_ERROR', $exception->getReasonCode());
    }

    public function testConfigurationErrorWithEmptyReason(): void
    {
        $instanceUrl = 'https://api.dify.com';

        $exception = DifyInstanceUnavailableException::configurationError($instanceUrl, '');

        $this->assertSame("Dify 实例配置错误: {$instanceUrl}", $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
        $this->assertSame('CONFIGURATION_ERROR', $exception->getReasonCode());
    }

    #[TestWith(['https://dify.example.com'])]
    #[TestWith(['https://dify.example.com:8080'])]
    #[TestWith(['https://dify.example.com/api/v1'])]
    #[TestWith(['http://localhost:3000'])]
    #[TestWith(['https://192.168.1.100:8080'])]
    #[TestWith(['https://very-long-subdomain.dify.example.com'])]
    #[TestWith(['custom://dify.internal'])]
    public function testVariousInstanceUrls(string $instanceUrl): void
    {
        $exception = new DifyInstanceUnavailableException($instanceUrl);

        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
    }

    
    #[TestWith([null])]
    #[TestWith(['CONNECTION_FAILED'])]
    #[TestWith(['SERVICE_UNAVAILABLE'])]
    #[TestWith(['MAINTENANCE'])]
    #[TestWith(['CONFIGURATION_ERROR'])]
    #[TestWith(['TIMEOUT'])]
    #[TestWith(['AUTH_FAILED'])]
    #[TestWith(['RATE_LIMITED'])]
    #[TestWith(['CUSTOM_REASON_CODE'])]
    public function testVariousReasonCodes(?string $reasonCode): void
    {
        $instanceUrl = 'https://dify.example.com';
        $exception = new DifyInstanceUnavailableException($instanceUrl, '测试消息', $reasonCode);

        $this->assertSame($reasonCode, $exception->getReasonCode());
    }

    
    public function testExceptionInheritance(): void
    {
        $exception = new DifyInstanceUnavailableException('https://dify.example.com');

        $this->assertInstanceOf(DifyApiException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionChaining(): void
    {
        $rootException = new \RuntimeException('Network error');
        $networkException = new \Exception('Connection failed', 0, $rootException);
        $instanceUrl = 'https://dify.example.com';

        $exception = new DifyInstanceUnavailableException(
            $instanceUrl,
            'Instance unavailable due to network issues',
            'NETWORK_ERROR',
            503,
            null,
            $networkException
        );

        // Test exception chain
        $this->assertSame($networkException, $exception->getPrevious());
        $this->assertSame($rootException, $exception->getPrevious()->getPrevious());
        $this->assertNull($exception->getPrevious()->getPrevious()->getPrevious());

        // Test messages in chain
        $this->assertSame('Instance unavailable due to network issues', $exception->getMessage());
        $this->assertSame('Connection failed', $exception->getPrevious()->getMessage());
        $this->assertSame('Network error', $exception->getPrevious()->getPrevious()->getMessage());
    }

    public function testToStringRepresentation(): void
    {
        $exception = new DifyInstanceUnavailableException(
            'https://dify.example.com',
            'Instance not accessible',
            'NETWORK_ERROR',
            503
        );

        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('DifyInstanceUnavailableException', $stringRepresentation);
        $this->assertStringContainsString('Instance not accessible', $stringRepresentation);
        $this->assertStringContainsString(__FILE__, $stringRepresentation);
    }

    public function testExceptionSerialization(): void
    {
        $previousException = new \Exception('Previous error');
        $exception = new DifyInstanceUnavailableException(
            'https://dify.example.com',
            'Serialization test',
            'TEST_REASON',
            503,
            '{"error": "test"}',
            $previousException
        );

        // Test that exception can be serialized and unserialized
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DifyInstanceUnavailableException::class, $unserialized);
        $this->assertSame($exception->getMessage(), $unserialized->getMessage());
        $this->assertSame($exception->getCode(), $unserialized->getCode());
        $this->assertSame($exception->getHttpStatusCode(), $unserialized->getHttpStatusCode());
        $this->assertSame($exception->getResponseBody(), $unserialized->getResponseBody());
        $this->assertSame($exception->getInstanceUrl(), $unserialized->getInstanceUrl());
        $this->assertSame($exception->getReasonCode(), $unserialized->getReasonCode());
    }

    public function testStaticMethodsReturnCorrectType(): void
    {
        $instanceUrl = 'https://dify.example.com';

        $connectionFailed = DifyInstanceUnavailableException::connectionFailed($instanceUrl);
        $serviceUnavailable = DifyInstanceUnavailableException::serviceUnavailable($instanceUrl);
        $maintenance = DifyInstanceUnavailableException::maintenance($instanceUrl);
        $configError = DifyInstanceUnavailableException::configurationError($instanceUrl);

        $this->assertInstanceOf(DifyInstanceUnavailableException::class, $connectionFailed);
        $this->assertInstanceOf(DifyInstanceUnavailableException::class, $serviceUnavailable);
        $this->assertInstanceOf(DifyInstanceUnavailableException::class, $maintenance);
        $this->assertInstanceOf(DifyInstanceUnavailableException::class, $configError);
    }

    public function testStaticMethodsWithComplexUrls(): void
    {
        $complexUrl = 'https://subdomain.example.com:8080/api/v1/dify?param=value&other=123';

        $exception = DifyInstanceUnavailableException::connectionFailed($complexUrl);

        $this->assertSame($complexUrl, $exception->getInstanceUrl());
        $this->assertStringContainsString($complexUrl, $exception->getMessage());
    }

    public function testMessageFormattingWithSpecialCharacters(): void
    {
        $instanceUrl = 'https://测试.example.com';
        $reason = '配置包含特殊字符 @#$%^&*()';

        $exception = DifyInstanceUnavailableException::configurationError($instanceUrl, $reason);

        $expectedMessage = "Dify 实例配置错误: {$instanceUrl} - {$reason}";
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($instanceUrl, $exception->getInstanceUrl());
    }

    public function testReasonCodeConstants(): void
    {
        $instanceUrl = 'https://dify.example.com';

        $connectionFailed = DifyInstanceUnavailableException::connectionFailed($instanceUrl);
        $serviceUnavailable = DifyInstanceUnavailableException::serviceUnavailable($instanceUrl);
        $maintenance = DifyInstanceUnavailableException::maintenance($instanceUrl);
        $configError = DifyInstanceUnavailableException::configurationError($instanceUrl);

        $this->assertSame('CONNECTION_FAILED', $connectionFailed->getReasonCode());
        $this->assertSame('SERVICE_UNAVAILABLE', $serviceUnavailable->getReasonCode());
        $this->assertSame('MAINTENANCE', $maintenance->getReasonCode());
        $this->assertSame('CONFIGURATION_ERROR', $configError->getReasonCode());
    }

    protected function getExceptionClass(): string
    {
        return DifyInstanceUnavailableException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return DifyApiException::class;
    }
}
