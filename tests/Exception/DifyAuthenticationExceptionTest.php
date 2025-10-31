<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyAuthenticationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * DifyAuthenticationException 认证异常单元测试
 * 测试重点：静态工厂方法、认证相关错误场景、继承关系
 * @internal
 */
#[CoversClass(DifyAuthenticationException::class)]
class DifyAuthenticationExceptionTest extends AbstractExceptionTestCase
{
    public function testInheritsFromDifyApiException(): void
    {
        $exception = new DifyAuthenticationException('Test message');

        $this->assertInstanceOf(DifyApiException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testLoginFailedFactory(): void
    {
        $responseBody = '{"error": "Invalid credentials", "message": "用户名或密码错误"}';

        $exception = DifyAuthenticationException::loginFailed($responseBody);

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify 登录失败', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
    }

    public function testLoginFailedFactoryWithoutResponseBody(): void
    {
        $exception = DifyAuthenticationException::loginFailed();

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify 登录失败', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testLoginFailedFactoryWithEmptyResponseBody(): void
    {
        $exception = DifyAuthenticationException::loginFailed('');

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify 登录失败', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testTokenExpiredFactory(): void
    {
        $exception = DifyAuthenticationException::tokenExpired();

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify token 已过期', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
    }

    public function testTokenInvalidFactory(): void
    {
        $responseBody = '{"error": "invalid_token", "error_description": "Token格式不正确"}';

        $exception = DifyAuthenticationException::tokenInvalid($responseBody);

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify token 无效', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
    }

    public function testTokenInvalidFactoryWithoutResponseBody(): void
    {
        $exception = DifyAuthenticationException::tokenInvalid();

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('Dify token 无效', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testInsufficientPermissionsFactory(): void
    {
        $exception = DifyAuthenticationException::insufficientPermissions();

        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
        $this->assertSame('权限不足', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
        $this->assertSame(403, $exception->getHttpStatusCode());
        $this->assertNull($exception->getResponseBody());
    }

    public function testManualConstruction(): void
    {
        $message = 'Custom authentication error';
        $statusCode = 401;
        $responseBody = '{"custom": "response"}';
        $previousException = new \Exception('Original error');

        $exception = new DifyAuthenticationException($message, $statusCode, $responseBody, $previousException);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($statusCode, $exception->getCode());
        $this->assertSame($statusCode, $exception->getHttpStatusCode());
        $this->assertSame($responseBody, $exception->getResponseBody());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testLoginFailedWithDetailedResponseBody(): void
    {
        $detailedResponse = json_encode([
            'error' => 'authentication_failed',
            'error_description' => '用户名或密码不正确',
            'details' => [
                'attempt_number' => 3,
                'remaining_attempts' => 2,
                'lockout_time' => 300,
            ],
            'timestamp' => '2024-01-15T10:30:00Z',
        ]);

        $this->assertIsString($detailedResponse);
        $exception = DifyAuthenticationException::loginFailed($detailedResponse);

        $this->assertSame('Dify 登录失败', $exception->getMessage());
        $this->assertSame(401, $exception->getHttpStatusCode());
        $this->assertSame($detailedResponse, $exception->getResponseBody());

        // Verify response body can be decoded
        $responseBody = $exception->getResponseBody();
        $this->assertIsString($responseBody);
        $decodedResponse = json_decode($responseBody, true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayHasKey('error', $decodedResponse);
        $this->assertArrayHasKey('details', $decodedResponse);
        $this->assertIsArray($decodedResponse['details']);
        $this->assertSame(3, $decodedResponse['details']['attempt_number']);
    }

    public function testTokenInvalidWithMalformedJson(): void
    {
        $malformedJson = '{"error": "invalid_token", "description": "Token has expired}'; // Missing closing quote

        $exception = DifyAuthenticationException::tokenInvalid($malformedJson);

        $this->assertSame('Dify token 无效', $exception->getMessage());
        $this->assertSame($malformedJson, $exception->getResponseBody());

        // Even with malformed JSON, the exception should still work
        $this->assertInstanceOf(DifyAuthenticationException::class, $exception);
    }

    public function testAllFactoryMethodsReturnCorrectStatusCodes(): void
    {
        $loginFailed = DifyAuthenticationException::loginFailed();
        $tokenExpired = DifyAuthenticationException::tokenExpired();
        $tokenInvalid = DifyAuthenticationException::tokenInvalid();
        $insufficientPermissions = DifyAuthenticationException::insufficientPermissions();

        // Login failures should be 401 Unauthorized
        $this->assertSame(401, $loginFailed->getHttpStatusCode());
        $this->assertSame(401, $tokenExpired->getHttpStatusCode());
        $this->assertSame(401, $tokenInvalid->getHttpStatusCode());

        // Permission issues should be 403 Forbidden
        $this->assertSame(403, $insufficientPermissions->getHttpStatusCode());
    }

    public function testFactoryMethodsReturnCorrectMessages(): void
    {
        $loginFailed = DifyAuthenticationException::loginFailed();
        $tokenExpired = DifyAuthenticationException::tokenExpired();
        $tokenInvalid = DifyAuthenticationException::tokenInvalid();
        $insufficientPermissions = DifyAuthenticationException::insufficientPermissions();

        $this->assertSame('Dify 登录失败', $loginFailed->getMessage());
        $this->assertSame('Dify token 已过期', $tokenExpired->getMessage());
        $this->assertSame('Dify token 无效', $tokenInvalid->getMessage());
        $this->assertSame('权限不足', $insufficientPermissions->getMessage());
    }

    public function testFactoryMethodsAreStatic(): void
    {
        $reflection = new \ReflectionClass(DifyAuthenticationException::class);

        $loginFailedMethod = $reflection->getMethod('loginFailed');
        $tokenExpiredMethod = $reflection->getMethod('tokenExpired');
        $tokenInvalidMethod = $reflection->getMethod('tokenInvalid');
        $insufficientPermissionsMethod = $reflection->getMethod('insufficientPermissions');

        $this->assertTrue($loginFailedMethod->isStatic());
        $this->assertTrue($tokenExpiredMethod->isStatic());
        $this->assertTrue($tokenInvalidMethod->isStatic());
        $this->assertTrue($insufficientPermissionsMethod->isStatic());
    }

    public function testFactoryMethodsReturnNewInstances(): void
    {
        $exception1 = DifyAuthenticationException::loginFailed();
        $exception2 = DifyAuthenticationException::loginFailed();

        $this->assertNotSame($exception1, $exception2);
        $this->assertEquals($exception1->getMessage(), $exception2->getMessage());
        $this->assertEquals($exception1->getCode(), $exception2->getCode());
    }

    public function testExceptionToStringContainsAuthenticationInfo(): void
    {
        $exception = DifyAuthenticationException::tokenExpired();
        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('DifyAuthenticationException', $stringRepresentation);
        $this->assertStringContainsString('token 已过期', $stringRepresentation);
    }

    public function testExceptionCanBeUsedInTryCatch(): void
    {
        $caughtException = null;

        try {
            throw DifyAuthenticationException::loginFailed('{"error": "test"}');
        } catch (DifyAuthenticationException $e) {
            $caughtException = $e;
        }

        $this->assertInstanceOf(DifyAuthenticationException::class, $caughtException);
        $this->assertSame('Dify 登录失败', $caughtException->getMessage());
        $this->assertSame('{"error": "test"}', $caughtException->getResponseBody());
    }

    public function testExceptionCanBeCaughtAsParentClass(): void
    {
        $caughtAsParent = null;
        $caughtAsBase = null;

        try {
            throw DifyAuthenticationException::insufficientPermissions();
        } catch (DifyApiException $e) {
            $caughtAsParent = $e;
        }

        try {
            throw DifyAuthenticationException::tokenInvalid();
        } catch (\Exception $e) {
            $caughtAsBase = $e;
        }

        $this->assertInstanceOf(DifyAuthenticationException::class, $caughtAsParent);
        $this->assertInstanceOf(DifyApiException::class, $caughtAsParent);

        $this->assertInstanceOf(DifyAuthenticationException::class, $caughtAsBase);
        $this->assertInstanceOf(\Exception::class, $caughtAsBase);
    }

    public function testResponseBodyWithUnicodeContent(): void
    {
        $unicodeResponse = '{"错误": "认证失败", "消息": "用户名或密码错误", "建议": "请检查您的凭据"}';

        $exception = DifyAuthenticationException::loginFailed($unicodeResponse);

        $this->assertSame($unicodeResponse, $exception->getResponseBody());

        // Verify unicode content is preserved
        $decoded = json_decode($exception->getResponseBody(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('错误', $decoded);
        $this->assertSame('认证失败', $decoded['错误']);
    }

    public function testResponseBodyWithLargeContent(): void
    {
        $largeResponse = json_encode([
            'error' => 'authentication_failed',
            'large_data' => str_repeat('A', 10000),
            'details' => array_fill(0, 1000, 'error_detail'),
        ]);

        $this->assertIsString($largeResponse);
        $exception = DifyAuthenticationException::tokenInvalid($largeResponse);

        $this->assertSame($largeResponse, $exception->getResponseBody());
        $this->assertGreaterThan(10000, strlen($exception->getResponseBody()));
    }

    public function testAuthenticationScenarios(): void
    {
        // Scenario 1: Login with wrong password
        $wrongPasswordException = DifyAuthenticationException::loginFailed(
            '{"error": "invalid_credentials", "field": "password"}'
        );
        $this->assertSame('Dify 登录失败', $wrongPasswordException->getMessage());
        $responseBody = $wrongPasswordException->getResponseBody();
        $this->assertIsString($responseBody);
        $this->assertStringContainsString('password', $responseBody);

        // Scenario 2: Expired session token
        $expiredTokenException = DifyAuthenticationException::tokenExpired();
        $this->assertSame('Dify token 已过期', $expiredTokenException->getMessage());

        // Scenario 3: Accessing admin-only resource
        $insufficientPermException = DifyAuthenticationException::insufficientPermissions();
        $this->assertSame('权限不足', $insufficientPermException->getMessage());
        $this->assertSame(403, $insufficientPermException->getHttpStatusCode());

        // Scenario 4: Malformed token
        $invalidTokenException = DifyAuthenticationException::tokenInvalid(
            '{"error": "malformed_token", "description": "JWT signature invalid"}'
        );
        $this->assertSame('Dify token 无效', $invalidTokenException->getMessage());
        $responseBody = $invalidTokenException->getResponseBody();
        $this->assertIsString($responseBody);
        $this->assertStringContainsString('JWT', $responseBody);
    }

    protected function getExceptionClass(): string
    {
        return DifyAuthenticationException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return DifyApiException::class;
    }
}
