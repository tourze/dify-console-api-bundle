<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Service\Helper\AuthenticationProcessor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AuthenticationProcessor::class)]
#[RunTestsInSeparateProcesses]
class AuthenticationProcessorTest extends AbstractIntegrationTestCase
{
    private AuthenticationProcessor $processor;

    protected function onSetUp(): void
    {
        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->processor = self::getService(AuthenticationProcessor::class);
    }

    public function testValidateLoginResponse(): void
    {
        /** @var ResponseInterface&MockObject $response */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['access_token' => 'test-token']);

        $result = $this->processor->validateLoginResponse($response, 'http://test.com');

        $this->assertSame(['access_token' => 'test-token'], $result);
    }

    public function testExtractAuthenticationData(): void
    {
        $data = ['access_token' => 'test-token', 'expires_in' => 3600];
        $result = $this->processor->extractAuthenticationData($data);

        $this->assertSame('test-token', $result['token']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['expiresAt']);
    }

    public function testEnsureValidToken(): void
    {
        /** @var DifyAccount&MockObject $account */
        $account = $this->createMock(DifyAccount::class);
        $account->method('getAccessToken')->willReturn(null);
        $account->method('isTokenExpired')->willReturn(true);

        $result = new \stdClass();
        $result->success = true;
        $result->token = 'new-token';
        $result->expiresTime = new \DateTimeImmutable();

        $refreshCallback = fn () => $result;

        $account->expects($this->once())->method('setAccessToken')->with('new-token');
        $account->expects($this->once())->method('setTokenExpiresTime');
        $account->expects($this->once())->method('setLastLoginTime');

        $this->processor->ensureValidToken($account, $refreshCallback);
    }

    public function testLogSuccessfulLogin(): void
    {
        $this->expectNotToPerformAssertions();

        /** @var DifyInstance&MockObject $instance */
        $instance = $this->createMock(DifyInstance::class);
        $instance->method('getName')->willReturn('test-instance');

        /** @var DifyAccount&MockObject $account */
        $account = $this->createMock(DifyAccount::class);
        $account->method('getId')->willReturn(123);
        $account->method('getEmail')->willReturn('test@example.com');
        $account->method('getInstance')->willReturn($instance);

        // 这个方法只是记录日志，没有返回值
        // 测试验证:方法执行成功且无异常抛出
        $this->processor->logSuccessfulLogin($account);
    }
}
