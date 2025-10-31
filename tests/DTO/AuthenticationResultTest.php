<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\AuthenticationResult;

/**
 * AuthenticationResult DTO 单元测试
 * 测试重点：readonly类的不可变性、构造函数参数、数据完整性
 * @internal
 */
#[CoversClass(AuthenticationResult::class)]
class AuthenticationResultTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $success = true;
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
        $expiresTime = new \DateTimeImmutable('+1 hour');
        $errorMessage = null;

        $result = new AuthenticationResult($success, $token, $expiresTime, $errorMessage);

        $this->assertSame($success, $result->success);
        $this->assertSame($token, $result->token);
        $this->assertSame($expiresTime, $result->expiresTime);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testSuccessfulAuthenticationResult(): void
    {
        $token = 'valid_jwt_token_here';
        $expiresTime = new \DateTimeImmutable('+2 hours');

        $result = new AuthenticationResult(
            success: true,
            token: $token,
            expiresTime: $expiresTime,
            errorMessage: null
        );

        $this->assertTrue($result->success);
        $this->assertSame($token, $result->token);
        $this->assertSame($expiresTime, $result->expiresTime);
        $this->assertNull($result->errorMessage);
    }

    public function testFailedAuthenticationResult(): void
    {
        $errorMessage = 'Invalid credentials provided';

        $result = new AuthenticationResult(
            success: false,
            token: null,
            expiresTime: null,
            errorMessage: $errorMessage
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->token);
        $this->assertNull($result->expiresTime);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    #[TestWith([true, 'jwt_token_12345', new \DateTimeImmutable('+1 hour'), null], 'successful_login')]
    #[TestWith([false, null, null, 'Invalid email or password'], 'failed_login_invalid_credentials')]
    #[TestWith([false, null, null, 'Account has been disabled'], 'failed_login_account_disabled')]
    #[TestWith([false, null, null, 'Too many login attempts. Please try again later.'], 'failed_login_too_many_attempts')]
    #[TestWith([true, 'short_lived_token', new \DateTimeImmutable('+5 minutes'), null], 'successful_login_with_short_expiry')]
    #[TestWith([true, 'long_lived_token', new \DateTimeImmutable('+7 days'), null], 'successful_login_with_long_expiry')]
    #[TestWith([false, null, null, ''], 'failed_login_with_empty_message')]
    #[TestWith([true, 'token_with_warning', new \DateTimeImmutable('+1 hour'), 'Login successful but password will expire soon'], 'edge_case_successful_with_error_message')]
    public function testVariousAuthenticationScenarios(
        bool $success,
        ?string $token,
        ?\DateTimeImmutable $expiresTime,
        ?string $errorMessage,
    ): void {
        $result = new AuthenticationResult($success, $token, $expiresTime, $errorMessage);

        $this->assertSame($success, $result->success);
        $this->assertSame($token, $result->token);
        $this->assertSame($expiresTime, $result->expiresTime);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testTokenCanBeNullWhenSuccessIsFalse(): void
    {
        $result = new AuthenticationResult(false, null, null, 'Authentication failed');

        $this->assertFalse($result->success);
        $this->assertNull($result->token);
        $this->assertNull($result->expiresTime);
        $this->assertSame('Authentication failed', $result->errorMessage);
    }

    public function testExpiresAtCanBeNullWhenSuccessIsFalse(): void
    {
        $result = new AuthenticationResult(false, null, null, 'Invalid token');

        $this->assertFalse($result->success);
        $this->assertNull($result->token);
        $this->assertNull($result->expiresTime);
        $this->assertSame('Invalid token', $result->errorMessage);
    }

    public function testErrorMessageCanBeNullWhenSuccessIsTrue(): void
    {
        $token = 'valid_token';
        $expiresTime = new \DateTimeImmutable('+1 hour');

        $result = new AuthenticationResult(true, $token, $expiresTime, null);

        $this->assertTrue($result->success);
        $this->assertSame($token, $result->token);
        $this->assertSame($expiresTime, $result->expiresTime);
        $this->assertNull($result->errorMessage);
    }

    public function testNamedParametersConstructor(): void
    {
        $result = new AuthenticationResult(
            success: false,
            errorMessage: 'Network timeout',
            token: null,
            expiresTime: null
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->token);
        $this->assertNull($result->expiresTime);
        $this->assertSame('Network timeout', $result->errorMessage);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $result = new AuthenticationResult(true, 'token', new \DateTimeImmutable(), null);

        // Readonly properties cannot be modified after construction
        // This test ensures the class is properly defined as readonly
        $this->assertTrue($result->success);
        $this->assertSame('token', $result->token);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->expiresTime);
        $this->assertNull($result->errorMessage);

        // The following would cause fatal errors if attempted:
        // $result->success = false;
        // $result->token = 'new_token';
        // $result->expiresAt = new \DateTimeImmutable();
        // $result->errorMessage = 'new_error';
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AuthenticationResult::class);

        $this->assertTrue($reflection->isFinal(), 'AuthenticationResult should be final');
        $this->assertTrue($reflection->isReadOnly(), 'AuthenticationResult should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(AuthenticationResult::class);
        $properties = $reflection->getProperties();

        $this->assertCount(4, $properties, 'Should have exactly 4 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('success', $propertyNames);
        $this->assertContains('token', $propertyNames);
        $this->assertContains('expiresTime', $propertyNames);
        $this->assertContains('errorMessage', $propertyNames);
    }

    public function testValidTokenFormats(): void
    {
        $jwtToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $result = new AuthenticationResult(true, $jwtToken, new \DateTimeImmutable(), null);

        $this->assertSame($jwtToken, $result->token);

        // Test various token formats
        $tokens = [
            'simple_token_123',
            'bearer_abc123def456',
            'session_' . str_repeat('a', 100),
            '',
        ];

        foreach ($tokens as $token) {
            $result = new AuthenticationResult(true, $token, new \DateTimeImmutable(), null);
            $this->assertSame($token, $result->token);
        }
    }

    public function testVariousErrorMessages(): void
    {
        $errorMessages = [
            'Username or password is incorrect',
            'Account locked due to multiple failed login attempts',
            'Your session has expired. Please log in again.',
            'Two-factor authentication required',
            'Account not verified. Please check your email.',
            'Service temporarily unavailable. Please try again later.',
            '', // Empty error message
            'Error code: 401 - Unauthorized access',
            'Network connection failed: timeout after 30 seconds',
            str_repeat('Long error message ', 50), // Very long error message
        ];

        foreach ($errorMessages as $errorMessage) {
            $result = new AuthenticationResult(false, null, null, $errorMessage);
            $this->assertSame($errorMessage, $result->errorMessage);
        }
    }

    public function testExpiredTokenScenario(): void
    {
        $pastTime = new \DateTimeImmutable('-1 hour');
        $result = new AuthenticationResult(true, 'expired_token', $pastTime, null);

        $this->assertTrue($result->success);
        $this->assertSame('expired_token', $result->token);
        $this->assertSame($pastTime, $result->expiresTime);
        $this->assertNull($result->errorMessage);

        // Verify the token is actually in the past
        $this->assertLessThan(new \DateTimeImmutable(), $result->expiresTime);
    }

    public function testFutureTokenExpiryScenario(): void
    {
        $futureTime = new \DateTimeImmutable('+1 year');
        $result = new AuthenticationResult(true, 'long_lived_token', $futureTime, null);

        $this->assertTrue($result->success);
        $this->assertSame('long_lived_token', $result->token);
        $this->assertSame($futureTime, $result->expiresTime);
        $this->assertNull($result->errorMessage);

        // Verify the token expires in the future
        $this->assertGreaterThan(new \DateTimeImmutable(), $result->expiresTime);
    }
}
