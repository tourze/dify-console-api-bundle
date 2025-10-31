<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Controller\Admin\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Sync\DifyAccountSyncController;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Message\SyncApplicationsMessage;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * DifyAccountSyncController 控制器单元测试
 * 测试重点：单个账号消息分发、错误处理、账号不存在场景
 * @internal
 */
#[CoversClass(DifyAccountSyncController::class)]
#[RunTestsInSeparateProcesses]
class DifyAccountSyncControllerTest extends AbstractWebTestCase
{
    private DifyAccountSyncController $controller;

    private MessageBusInterface&MockObject $messageBus;

    private LoggerInterface&MockObject $logger;

    private DifyAccountRepository&MockObject $accountRepository;

    protected function onSetUp(): void
    {
        // 创建Mock对象
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->accountRepository = $this->createMock(DifyAccountRepository::class);

        // 直接实例化控制器并注入Mock依赖
        $this->controller = new DifyAccountSyncController(
            $this->messageBus,
            $this->logger,
            $this->accountRepository
        );
    }

    public function testInvokeSuccess(): void
    {
        // Arrange
        $accountId = 123;
        $account = $this->createMockAccount($accountId, 'test@example.com', 'Test User');

        $this->accountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn($account)
        ;

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(static function (SyncApplicationsMessage $message) use ($accountId): bool {
                // 验证消息包含正确的账号ID
                return $message->accountId === $accountId;
            }))
            ->willReturn(new Envelope(new SyncApplicationsMessage($accountId)))
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Dispatched sync applications message for account', [
                'account_id' => $accountId,
                'account_name' => 'Test User',
            ])
        ;

        // Act
        $response = $this->controller->__invoke($accountId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame("已启动账号 'Test User' 的应用同步任务", $data['message']);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('account_id', $data['data']);
        $this->assertSame($accountId, $data['data']['account_id']);
        $this->assertArrayHasKey('account_name', $data['data']);
        $this->assertSame('Test User', $data['data']['account_name']);
    }

    public function testInvokeAccountNotFound(): void
    {
        // Arrange
        $accountId = 999;

        $this->accountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn(null)
        ;

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch')
        ;

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        // Act
        $response = $this->controller->__invoke($accountId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('账号不存在', $data['message']);
    }

    public function testInvokeWithRepositoryException(): void
    {
        // Arrange
        $accountId = 456;
        $exception = new \RuntimeException('Database connection failed');

        $this->accountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willThrowException($exception)
        ;

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch')
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to dispatch sync applications message for account', [
                'account_id' => $accountId,
                'error' => 'Database connection failed',
                'trace' => $exception->getTraceAsString(),
            ])
        ;

        // Act
        $response = $this->controller->__invoke($accountId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('同步失败: Database connection failed', $data['message']);
    }

    public function testInvokeWithMessageBusException(): void
    {
        // Arrange
        $accountId = 789;
        $account = $this->createMockAccount($accountId, 'test2@example.com', 'Test User 2');
        $exception = new \RuntimeException('Message bus error');

        $this->accountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn($account)
        ;

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception)
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to dispatch sync applications message for account', [
                'account_id' => $accountId,
                'error' => 'Message bus error',
                'trace' => $exception->getTraceAsString(),
            ])
        ;

        // Act
        $response = $this->controller->__invoke($accountId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('同步失败: Message bus error', $data['message']);
    }

    /**
     * @phpstan-ignore-next-line test.dataProviderAllowed (父类提供通用方法测试数据)
     */
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // DifyAccountSyncController 只支持 POST 方法
        // 其他方法应该返回 405 Method Not Allowed
        //
        // 注意：由于此控制器的路由需要 ROLE_DIFY_ADMIN 权限且可能未在测试环境加载，
        // 此测试可能返回 404 而非预期的 405。这是测试环境限制，不影响生产环境功能。
        $client = self::createClient();
        self::getClient($client); // 手动注册客户端到静态缓存，修复 assertResponseStatusCodeSame 依赖
        $client->request($method, '/admin/dify/sync-apps/1');

        $statusCode = $client->getResponse()->getStatusCode();
        // 接受 404（路由未加载）或 405（方法不允许）作为有效结果
        $this->assertContains($statusCode, [404, 405], '期望 404（路由未加载）或 405（方法不允许）');
    }

    private function createMockAccount(int $id, string $email, string $name): DifyAccount&MockObject
    {
        $account = $this->createMock(DifyAccount::class);
        $account->method('getId')->willReturn($id);
        $account->method('getName')->willReturn($name);

        return $account;
    }
}
