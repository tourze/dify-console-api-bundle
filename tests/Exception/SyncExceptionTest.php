<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Exception\SyncException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * SyncException 同步异常类单元测试
 * 测试重点：异常基本属性、同步类型、实体ID、上下文数据、静态工厂方法
 * @internal
 */
#[CoversClass(SyncException::class)]
class SyncExceptionTest extends AbstractExceptionTestCase
{
    public function testBasicExceptionCreation(): void
    {
        $syncType = 'app_sync';
        $message = '同步失败';
        $entityId = 'app_123';
        $context = ['error_code' => 'SYNC_001', 'timestamp' => time()];

        $exception = new SyncException($syncType, $message, $entityId, $context);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($syncType, $exception->getSyncType());
        $this->assertSame($entityId, $exception->getEntityId());
        $this->assertSame($context, $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $syncType = 'test_sync';
        $message = '测试同步异常';

        $exception = new SyncException($syncType, $message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($syncType, $exception->getSyncType());
        $this->assertNull($exception->getEntityId());
        $this->assertSame([], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previousException = new \Exception('Network error');
        $syncType = 'account_sync';
        $message = '账户同步失败';
        $entityId = 'account_456';
        $context = ['retry_count' => 3];

        $exception = new SyncException(
            $syncType,
            $message,
            $entityId,
            $context,
            $previousException
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($syncType, $exception->getSyncType());
        $this->assertSame($entityId, $exception->getEntityId());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testAppSyncFailedStaticMethod(): void
    {
        $appId = 'app_789';
        $reason = 'API endpoint unreachable';
        $previousException = new \Exception('Connection timeout');

        $exception = SyncException::appSyncFailed($appId, $reason, $previousException);

        $this->assertSame("应用同步失败: {$reason}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame('app_sync', $exception->getSyncType());
        $this->assertSame($appId, $exception->getEntityId());
        $this->assertSame(['app_id' => $appId, 'reason' => $reason], $exception->getContext());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testAppSyncFailedWithoutPreviousException(): void
    {
        $appId = 'app_123';
        $reason = 'Invalid response format';

        $exception = SyncException::appSyncFailed($appId, $reason);

        $this->assertSame("应用同步失败: {$reason}", $exception->getMessage());
        $this->assertSame('app_sync', $exception->getSyncType());
        $this->assertSame($appId, $exception->getEntityId());
        $this->assertSame(['app_id' => $appId, 'reason' => $reason], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testAccountSyncFailedStaticMethod(): void
    {
        $accountId = 'account_456';
        $reason = 'Authentication failed';
        $previousException = new \RuntimeException('Invalid credentials');

        $exception = SyncException::accountSyncFailed($accountId, $reason, $previousException);

        $this->assertSame("账户同步失败: {$reason}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame('account_sync', $exception->getSyncType());
        $this->assertSame($accountId, $exception->getEntityId());
        $this->assertSame(['account_id' => $accountId, 'reason' => $reason], $exception->getContext());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testAccountSyncFailedWithoutPreviousException(): void
    {
        $accountId = 'account_789';
        $reason = 'Account not found';

        $exception = SyncException::accountSyncFailed($accountId, $reason);

        $this->assertSame("账户同步失败: {$reason}", $exception->getMessage());
        $this->assertSame('account_sync', $exception->getSyncType());
        $this->assertSame($accountId, $exception->getEntityId());
        $this->assertSame(['account_id' => $accountId, 'reason' => $reason], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testInstanceSyncFailedStaticMethod(): void
    {
        $instanceId = 'instance_123';
        $reason = 'Instance not responding';
        $previousException = new \Exception('Connection refused');

        $exception = SyncException::instanceSyncFailed($instanceId, $reason, $previousException);

        $this->assertSame("实例同步失败: {$reason}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame('instance_sync', $exception->getSyncType());
        $this->assertSame($instanceId, $exception->getEntityId());
        $this->assertSame(['instance_id' => $instanceId, 'reason' => $reason], $exception->getContext());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInstanceSyncFailedWithoutPreviousException(): void
    {
        $instanceId = 'instance_456';
        $reason = 'Configuration error';

        $exception = SyncException::instanceSyncFailed($instanceId, $reason);

        $this->assertSame("实例同步失败: {$reason}", $exception->getMessage());
        $this->assertSame('instance_sync', $exception->getSyncType());
        $this->assertSame($instanceId, $exception->getEntityId());
        $this->assertSame(['instance_id' => $instanceId, 'reason' => $reason], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testDataValidationFailedStaticMethod(): void
    {
        $syncType = 'app_sync';
        $entityId = 'app_789';
        $errors = [
            'name' => 'Name is required',
            'url' => 'Invalid URL format',
            'type' => 'Type must be one of: chatflow, workflow, assistant',
        ];

        $exception = SyncException::dataValidationFailed($syncType, $entityId, $errors);

        $this->assertSame('数据验证失败', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($syncType, $exception->getSyncType());
        $this->assertSame($entityId, $exception->getEntityId());
        $this->assertSame(['validation_errors' => $errors], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function testConflictResolutionFailedStaticMethod(): void
    {
        $syncType = 'account_sync';
        $entityId = 'account_123';
        $conflictType = 'duplicate_email';

        $exception = SyncException::conflictResolutionFailed($syncType, $entityId, $conflictType);

        $this->assertSame("冲突解决失败: {$conflictType}", $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($syncType, $exception->getSyncType());
        $this->assertSame($entityId, $exception->getEntityId());
        $this->assertSame(['conflict_type' => $conflictType], $exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    #[TestWith(['app_sync'])]
    #[TestWith(['account_sync'])]
    #[TestWith(['instance_sync'])]
    #[TestWith(['user_sync'])]
    #[TestWith(['config_sync'])]
    #[TestWith(['custom_sync_type'])]
    #[TestWith([''])]
    #[TestWith(['同步类型'])]
    public function testVariousSyncTypes(string $syncType): void
    {
        $exception = new SyncException($syncType, 'Test message');

        $this->assertSame($syncType, $exception->getSyncType());
    }

    
    #[TestWith([null])]
    #[TestWith(['entity_123'])]
    #[TestWith(['12345'])]
    #[TestWith(['550e8400-e29b-41d4-a716-446655440000'])]
    #[TestWith([''])]
    #[TestWith(['实体_123'])]
    #[TestWith(['entity@#$%^&*()'])]
    public function testVariousEntityIds(?string $entityId): void
    {
        $exception = new SyncException('test_sync', 'Test message', $entityId);

        $this->assertSame($entityId, $exception->getEntityId());
    }

    
    public function testVariousContexts(): void
    {
        $testCases = [
            [],
            ['key' => 'value'],
            [
                'error_code' => 'SYNC_001',
                'timestamp' => 1640995200,
                'retry_count' => 3,
                'metadata' => [
                    'user_id' => 'user_123',
                    'request_id' => 'req_456',
                ],
            ],
            ['count' => 42, 'rate' => 3.14],
            ['success' => false, 'force_update' => true],
            ['optional_field' => null, 'required_field' => 'value'],
            ['消息' => '同步失败', '状态' => '错误'],
        ];

        foreach ($testCases as $context) {
            $exception = new SyncException('test_sync', 'Test message', 'entity_123', $context);
            $this->assertSame($context, $exception->getContext());
        }
    }

    public function testExceptionChaining(): void
    {
        $rootException = new \InvalidArgumentException('Invalid data');
        $networkException = new \RuntimeException('Network error', 0, $rootException);

        $exception = new SyncException(
            'data_sync',
            'Sync failed due to network issues',
            'entity_789',
            ['error_type' => 'network'],
            $networkException
        );

        // Test exception chain
        $this->assertSame($networkException, $exception->getPrevious());
        $this->assertSame($rootException, $exception->getPrevious()->getPrevious());
        $this->assertNull($exception->getPrevious()->getPrevious()->getPrevious());

        // Test messages in chain
        $this->assertSame('Sync failed due to network issues', $exception->getMessage());
        $this->assertSame('Network error', $exception->getPrevious()->getMessage());
        $this->assertSame('Invalid data', $exception->getPrevious()->getPrevious()->getMessage());
    }

    public function testToStringRepresentation(): void
    {
        $exception = new SyncException(
            'app_sync',
            'Application sync failed',
            'app_123',
            ['reason' => 'timeout']
        );

        $stringRepresentation = (string) $exception;

        $this->assertStringContainsString('SyncException', $stringRepresentation);
        $this->assertStringContainsString('Application sync failed', $stringRepresentation);
        $this->assertStringContainsString(__FILE__, $stringRepresentation);
    }

    public function testExceptionSerialization(): void
    {
        $previousException = new \Exception('Previous error');
        $context = ['key' => 'value', 'nested' => ['data' => 123]];

        $exception = new SyncException(
            'serialization_test',
            'Serialization test message',
            'entity_serialization',
            $context,
            $previousException
        );

        // Test that exception can be serialized and unserialized
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SyncException::class, $unserialized);
        $this->assertSame($exception->getMessage(), $unserialized->getMessage());
        $this->assertSame($exception->getCode(), $unserialized->getCode());
        $this->assertSame($exception->getSyncType(), $unserialized->getSyncType());
        $this->assertSame($exception->getEntityId(), $unserialized->getEntityId());
        $this->assertSame($exception->getContext(), $unserialized->getContext());
    }

    public function testStaticMethodsReturnCorrectType(): void
    {
        $appSync = SyncException::appSyncFailed('app_123', 'test reason');
        $accountSync = SyncException::accountSyncFailed('account_456', 'test reason');
        $instanceSync = SyncException::instanceSyncFailed('instance_789', 'test reason');
        $validationFailed = SyncException::dataValidationFailed('sync_type', 'entity_123', ['error' => 'test']);
        $conflictFailed = SyncException::conflictResolutionFailed('sync_type', 'entity_456', 'duplicate');

        $this->assertInstanceOf(SyncException::class, $appSync);
        $this->assertInstanceOf(SyncException::class, $accountSync);
        $this->assertInstanceOf(SyncException::class, $instanceSync);
        $this->assertInstanceOf(SyncException::class, $validationFailed);
        $this->assertInstanceOf(SyncException::class, $conflictFailed);
    }

    public function testStaticMethodsContextGeneration(): void
    {
        // Test app sync context
        $appException = SyncException::appSyncFailed('app_123', 'test reason');
        $this->assertArrayHasKey('app_id', $appException->getContext());
        $this->assertArrayHasKey('reason', $appException->getContext());
        $this->assertSame('app_123', $appException->getContext()['app_id']);
        $this->assertSame('test reason', $appException->getContext()['reason']);

        // Test account sync context
        $accountException = SyncException::accountSyncFailed('account_456', 'auth error');
        $this->assertArrayHasKey('account_id', $accountException->getContext());
        $this->assertArrayHasKey('reason', $accountException->getContext());
        $this->assertSame('account_456', $accountException->getContext()['account_id']);
        $this->assertSame('auth error', $accountException->getContext()['reason']);

        // Test instance sync context
        $instanceException = SyncException::instanceSyncFailed('instance_789', 'config error');
        $this->assertArrayHasKey('instance_id', $instanceException->getContext());
        $this->assertArrayHasKey('reason', $instanceException->getContext());
        $this->assertSame('instance_789', $instanceException->getContext()['instance_id']);
        $this->assertSame('config error', $instanceException->getContext()['reason']);

        // Test validation context
        $validationErrors = ['field1' => 'error1', 'field2' => 'error2'];
        $validationException = SyncException::dataValidationFailed('sync_type', 'entity_123', $validationErrors);
        $this->assertArrayHasKey('validation_errors', $validationException->getContext());
        $this->assertSame($validationErrors, $validationException->getContext()['validation_errors']);

        // Test conflict context
        $conflictException = SyncException::conflictResolutionFailed('sync_type', 'entity_456', 'duplicate_key');
        $this->assertArrayHasKey('conflict_type', $conflictException->getContext());
        $this->assertSame('duplicate_key', $conflictException->getContext()['conflict_type']);
    }

    public function testComplexValidationErrors(): void
    {
        $complexErrors = [
            'name' => [
                'required' => 'Name field is required',
                'min_length' => 'Name must be at least 3 characters',
            ],
            'email' => [
                'format' => 'Invalid email format',
                'unique' => 'Email already exists',
            ],
            'nested' => [
                'config' => [
                    'api_key' => 'API key is invalid',
                    'timeout' => 'Timeout value must be positive',
                ],
            ],
        ];

        $exception = SyncException::dataValidationFailed('user_sync', 'user_123', $complexErrors);

        $this->assertSame('数据验证失败', $exception->getMessage());
        $this->assertSame('user_sync', $exception->getSyncType());
        $this->assertSame('user_123', $exception->getEntityId());
        $this->assertSame(['validation_errors' => $complexErrors], $exception->getContext());
    }

    public function testNamedParameterConstruction(): void
    {
        $exception = new SyncException(
            syncType: 'named_sync',
            message: 'Named parameter test',
            entityId: 'entity_named',
            context: ['test' => 'data'],
            previous: new \Exception('Named previous')
        );

        $this->assertSame('named_sync', $exception->getSyncType());
        $this->assertSame('Named parameter test', $exception->getMessage());
        $this->assertSame('entity_named', $exception->getEntityId());
        $this->assertSame(['test' => 'data'], $exception->getContext());
        $this->assertInstanceOf(\Exception::class, $exception->getPrevious());
    }

    protected function getExceptionClass(): string
    {
        return SyncException::class;
    }

    protected function getParentExceptionClass(): string
    {
        return \Exception::class;
    }
}
