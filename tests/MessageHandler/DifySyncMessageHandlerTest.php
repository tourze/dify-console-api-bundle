<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyGenericException;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\MessageHandler\DifySyncMessageHandler;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * Dify同步消息处理器集成测试
 *
 * 测试重点：依赖注入、消息处理逻辑、日志记录、异常处理
 * @internal
 */
#[CoversClass(DifySyncMessageHandler::class)]
#[RunTestsInSeparateProcesses]
class DifySyncMessageHandlerTest extends AbstractIntegrationTestCase
{
    private MockObject&AppSyncServiceInterface $appSyncService;

    private MockObject&LoggerInterface $logger;

    private DifySyncMessageHandler $messageHandler;

    protected function onSetUp(): void
    {
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 将依赖注入测试容器，确保 Handler 由容器重建
        static::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);
        static::getContainer()->set('monolog.logger.dify_console_api', $this->logger);

        static::clearServiceLocatorCache();
        $this->messageHandler = self::getService(DifySyncMessageHandler::class);
    }

    protected function onTearDown(): void
    {
        // 避免测试间共享 Mock
        unset($this->appSyncService, $this->logger, $this->messageHandler);
        static::clearServiceLocatorCache();
        parent::onTearDown();
    }

    public function testConstructorInjectsDependencies(): void
    {
        $this->assertInstanceOf(DifySyncMessageHandler::class, $this->messageHandler);
    }

    public function testInvokeWithSuccessfulSync(): void
    {
        $message = new DifySyncMessage(
            instanceId: 123,
            accountId: 456,
            appType: 'chatflow',
            metadata: ['request_id' => 'req_123']
        );

        $syncStats = [
            'processed_instances' => 1,
            'processed_accounts' => 1,
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 0,
        ];

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->with(123, 456, 'chatflow')
            ->willReturn($syncStats)
        ;

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use ($syncStats): void {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('开始处理Dify同步消息', $message);
                    $this->assertArrayHasKey('message_id', $context);
                    $this->assertSame(123, $context['instance_id']);
                    $this->assertSame(456, $context['account_id']);
                    $this->assertSame('chatflow', $context['app_type']);
                } elseif (2 === $callCount) {
                    $this->assertSame('Dify同步消息处理成功', $message);
                    $this->assertArrayHasKey('message_id', $context);
                    $this->assertSame($syncStats, $context['sync_stats']);
                    $this->assertArrayHasKey('processing_time', $context);
                    $this->assertIsFloat($context['processing_time']);
                }
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('记录同步成功指标', $logMessage);
                $this->assertArrayHasKey('message_id', $context);
                $this->assertArrayHasKey('metrics', $context);
                $this->assertIsArray($context['metrics']);
            })
        ;

        $this->messageHandler->__invoke($message);
    }

    public function testInvokeWithSyncErrors(): void
    {
        $message = new DifySyncMessage(instanceId: 100, accountId: 200);

        $syncStats = [
            'processed_instances' => 1,
            'processed_accounts' => 1,
            'synced_apps' => 3,
            'created_apps' => 1,
            'updated_apps' => 2,
            'errors' => 2, // 有错误发生
        ];

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->with(100, 200, null)
            ->willReturn($syncStats)
        ;

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('同步过程中发生了错误', $logMessage);
                $this->assertArrayHasKey('message_id', $context);
                $this->assertSame(2, $context['error_count']);
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
        ;

        $this->messageHandler->__invoke($message);
    }

    public function testInvokeWithSyncException(): void
    {
        $message = new DifySyncMessage(instanceId: 999, accountId: 888, appType: 'workflow');
        $exception = DifyGenericException::create('Sync service unavailable', 503);

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->with(999, 888, 'workflow')
            ->willThrowException($exception)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('开始处理Dify同步消息', $logMessage);
                $this->assertIsArray($context);
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $logMessage, array $context) use ($exception): void {
                $this->assertSame('Dify同步消息处理失败', $logMessage);
                $this->assertArrayHasKey('message_id', $context);
                $this->assertSame(999, $context['instance_id']);
                $this->assertSame(888, $context['account_id']);
                $this->assertSame('workflow', $context['app_type']);
                $this->assertSame($exception->getMessage(), $context['error']);
                $this->assertSame($exception, $context['exception']);
                $this->assertArrayHasKey('processing_time', $context);
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('记录同步失败指标', $logMessage);
                $this->assertArrayHasKey('message_id', $context);
                $this->assertSame(DifyGenericException::class, $context['error_type']);
            })
        ;

        $this->expectException(DifyGenericException::class);
        $this->expectExceptionMessage('Sync service unavailable');

        $this->messageHandler->__invoke($message);
    }

    /**
     * @param array{0: int|null, 1: int|null, 2: string|null} $expectedParams
     */
    #[DataProvider('messageScopeProvider')]
    public function testInvokeWithDifferentMessageScopes(DifySyncMessage $message, array $expectedParams): void
    {
        $syncStats = [
            'processed_instances' => 1,
            'synced_apps' => 10,
            'errors' => 0,
        ];

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->with(...$expectedParams)
            ->willReturn($syncStats)
        ;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
        ;

        $this->messageHandler->__invoke($message);
    }

    /**
     * @return array<string, array{message: DifySyncMessage, expectedParams: array{0: int|null, 1: int|null, 2: string|null}}>
     */
    public static function messageScopeProvider(): array
    {
        return [
            'full_scope_message' => [
                'message' => new DifySyncMessage(
                    instanceId: 123,
                    accountId: 456,
                    appType: 'chatflow',
                    metadata: ['source' => 'admin_panel']
                ),
                'expectedParams' => [123, 456, 'chatflow'],
            ],
            'instance_only_message' => [
                'message' => new DifySyncMessage(instanceId: 100),
                'expectedParams' => [100, null, null],
            ],
            'account_only_message' => [
                'message' => new DifySyncMessage(accountId: 200),
                'expectedParams' => [null, 200, null],
            ],
            'app_type_only_message' => [
                'message' => new DifySyncMessage(appType: 'workflow'),
                'expectedParams' => [null, null, 'workflow'],
            ],
            'full_sync_message' => [
                'message' => new DifySyncMessage(),
                'expectedParams' => [null, null, null],
            ],
            'instance_and_type_message' => [
                'message' => new DifySyncMessage(instanceId: 300, appType: 'chat_assistant'),
                'expectedParams' => [300, null, 'chat_assistant'],
            ],
        ];
    }

    public function testLoggingIncludesMessageMetadata(): void
    {
        $metadata = [
            'request_id' => 'req_456',
            'source' => 'api_endpoint',
            'user_id' => 789,
            'timestamp' => '2024-01-15T10:30:00Z',
        ];

        $message = new DifySyncMessage(
            instanceId: 50,
            accountId: 60,
            appType: 'chatflow',
            metadata: $metadata
        );

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willReturn(['synced_apps' => 1, 'errors' => 0])
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $logMessage, array $context) use ($metadata): void {
                $this->assertSame('开始处理Dify同步消息', $logMessage);
                $this->assertArrayHasKey('message_data', $context);
                $messageData = $context['message_data'];
                $this->assertIsArray($messageData);
                $this->assertSame('dify_sync', $messageData['message_type']);
                $this->assertSame(50, $messageData['instance_id']);
                $this->assertSame(60, $messageData['account_id']);
                $this->assertSame('chatflow', $messageData['app_type']);
                $this->assertSame($metadata, $messageData['metadata']);
                $this->assertSame(10, $messageData['priority']);
            })
        ;

        $this->logger->expects($this->atLeastOnce())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $this->messageHandler->__invoke($message);
    }

    public function testProcessingTimeIsMeasured(): void
    {
        $message = new DifySyncMessage(instanceId: 1);

        // 模拟一个需要时间的同步操作
        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willReturnCallback(function (): array {
                usleep(1000); // 模拟1毫秒的处理时间

                return ['synced_apps' => 1, 'errors' => 0];
            })
        ;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                if (isset($context['processing_time'])) {
                    $this->assertIsFloat($context['processing_time']);
                    $this->assertGreaterThan(0, $context['processing_time']);
                }
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('记录同步成功指标', $logMessage);
                $this->assertArrayHasKey('metrics', $context);
                $this->assertArrayHasKey('processing_time_seconds', $context['metrics']);
                $this->assertIsFloat($context['metrics']['processing_time_seconds']);
            })
        ;

        $this->messageHandler->__invoke($message);
    }

    public function testExceptionIsRethrownForMessengerRetry(): void
    {
        $message = new DifySyncMessage();
        $originalException = new \RuntimeException('Database connection failed');

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willThrowException($originalException)
        ;

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('error');
        $this->logger->expects($this->once())->method('debug');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->messageHandler->__invoke($message);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('exceptionTypeProvider')]
    public function testDifferentExceptionTypesAreHandled(string $exceptionClass, string $message): void
    {
        $difyMessage = new DifySyncMessage(instanceId: 1);
        $exception = new $exceptionClass($message);

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willThrowException($exception)
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $logMessage, array $context) use ($exception): void {
                $this->assertSame('Dify同步消息处理失败', $logMessage);
                $this->assertSame($exception->getMessage(), $context['error']);
                $this->assertSame($exception, $context['exception']);
            })
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context) use ($exceptionClass): void {
                $this->assertSame('记录同步失败指标', $logMessage);
                $this->assertSame($exceptionClass, $context['error_type']);
            })
        ;

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $this->messageHandler->__invoke($difyMessage);
    }

    /**
     * @return array<string, array{exceptionClass: class-string<\Throwable>, message: string}>
     */
    public static function exceptionTypeProvider(): array
    {
        return [
            'dify_api_exception' => [
                'exceptionClass' => DifyApiException::class,
                'message' => 'Dify API authentication failed',
            ],
            'runtime_exception' => [
                'exceptionClass' => \RuntimeException::class,
                'message' => 'Service runtime error',
            ],
            'invalid_argument_exception' => [
                'exceptionClass' => \InvalidArgumentException::class,
                'message' => 'Invalid sync parameters',
            ],
            'timeout_exception' => [
                'exceptionClass' => \Exception::class,
                'message' => 'Request timeout occurred',
            ],
        ];
    }

    public function testSyncStatsMetricsAreLoggedCorrectly(): void
    {
        $message = new DifySyncMessage();
        $syncStats = [
            'processed_instances' => 3,
            'processed_accounts' => 5,
            'synced_apps' => 15,
            'created_apps' => 8,
            'updated_apps' => 7,
            'errors' => 1,
        ];

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willReturn($syncStats)
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('记录同步成功指标', $logMessage);
                $metrics = $context['metrics'];
                $this->assertIsArray($metrics);
                $this->assertSame(3, $metrics['processed_instances']);
                $this->assertSame(5, $metrics['processed_accounts']);
                $this->assertSame(15, $metrics['synced_apps']);
                $this->assertSame(8, $metrics['created_apps']);
                $this->assertSame(7, $metrics['updated_apps']);
                $this->assertSame(1, $metrics['errors']);
                $this->assertArrayHasKey('processing_time_seconds', $metrics);
            })
        ;

        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->messageHandler->__invoke($message);
    }

    public function testEmptyOrMissingStatsFieldsAreHandled(): void
    {
        $message = new DifySyncMessage();
        $incompleteSyncStats = [
            'synced_apps' => 5,
            // Missing other expected fields
        ];

        $this->appSyncService
            ->expects($this->once())
            ->method('syncApps')
            ->willReturn($incompleteSyncStats)
        ;

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->willReturnCallback(function (string $logMessage, array $context): void {
                $this->assertSame('记录同步成功指标', $logMessage);
                $metrics = $context['metrics'];
                $this->assertIsArray($metrics);
                $this->assertSame(0, $metrics['processed_instances']);
                $this->assertSame(0, $metrics['processed_accounts']);
                $this->assertSame(5, $metrics['synced_apps']);
                $this->assertSame(0, $metrics['created_apps']);
                $this->assertSame(0, $metrics['updated_apps']);
                $this->assertSame(0, $metrics['errors']);
            })
        ;

        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->messageHandler->__invoke($message);
    }
}
