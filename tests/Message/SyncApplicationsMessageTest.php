<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\Message\SyncApplicationsMessage;

/**
 * SyncApplicationsMessage 同步应用消息类单元测试
 * 测试重点：消息属性、唯一标识生成、序列化、优先级、范围描述
 * @internal
 */
#[CoversClass(SyncApplicationsMessage::class)]
class SyncApplicationsMessageTest extends TestCase
{
    public function testBasicConstructor(): void
    {
        $accountId = 123;
        $metadata = ['request_id' => 'req_456', 'timestamp' => time()];

        $message = new SyncApplicationsMessage($accountId, $metadata);

        $this->assertSame($accountId, $message->accountId);
        $this->assertSame($metadata, $message->metadata);
    }

    public function testConstructorWithDefaults(): void
    {
        $accountId = 456;

        $message = new SyncApplicationsMessage($accountId);

        $this->assertSame($accountId, $message->accountId);
        $this->assertSame([], $message->metadata);
    }

    public function testConstructorWithNamedParameters(): void
    {
        $message = new SyncApplicationsMessage(
            accountId: 789,
            metadata: ['source' => 'api', 'version' => '2.0']
        );

        $this->assertSame(789, $message->accountId);
        $this->assertSame(['source' => 'api', 'version' => '2.0'], $message->metadata);
    }

    public function testGetMessageId(): void
    {
        $accountId = 123;
        $metadata = ['request_id' => 'req_789'];

        $message = new SyncApplicationsMessage($accountId, $metadata);
        $messageId = $message->getMessageId();

        $this->assertIsString($messageId);
        $this->assertSame(32, strlen($messageId)); // MD5 hash length
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $messageId);
    }

    public function testGetMessageIdConsistency(): void
    {
        $accountId = 123;
        $metadata = ['request_id' => 'req_789'];

        $message1 = new SyncApplicationsMessage($accountId, $metadata);
        $message2 = new SyncApplicationsMessage($accountId, $metadata);

        // Same parameters should generate same message ID
        $this->assertSame($message1->getMessageId(), $message2->getMessageId());
    }

    public function testGetMessageIdUniqueness(): void
    {
        $message1 = new SyncApplicationsMessage(123, ['request_id' => 'req_123']);
        $message2 = new SyncApplicationsMessage(456, ['request_id' => 'req_123']); // Different accountId
        $message3 = new SyncApplicationsMessage(123, ['request_id' => 'req_456']); // Different metadata

        // Different parameters should generate different message IDs
        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());
        $this->assertNotSame($message1->getMessageId(), $message3->getMessageId());
        $this->assertNotSame($message2->getMessageId(), $message3->getMessageId());
    }

    public function testGetMessageIdWithEmptyMetadata(): void
    {
        $message1 = new SyncApplicationsMessage(123, []);
        $message2 = new SyncApplicationsMessage(123, []);

        // Empty metadata should still generate consistent IDs
        $this->assertSame($message1->getMessageId(), $message2->getMessageId());
    }

    public function testGetMessageType(): void
    {
        $message = new SyncApplicationsMessage(123);

        $this->assertSame('sync_applications', $message->getMessageType());
    }

    public function testGetMessageTypeIsConstant(): void
    {
        $message1 = new SyncApplicationsMessage(123);
        $message2 = new SyncApplicationsMessage(456, ['request_id' => 'req_789']);

        // Message type should be constant regardless of parameters
        $this->assertSame($message1->getMessageType(), $message2->getMessageType());
        $this->assertSame('sync_applications', $message2->getMessageType());
    }

    public function testGetPriority(): void
    {
        $message = new SyncApplicationsMessage(123);

        $this->assertSame(10, $message->getPriority());
    }

    public function testGetPriorityIsConstant(): void
    {
        $message1 = new SyncApplicationsMessage(123);
        $message2 = new SyncApplicationsMessage(456, ['priority' => 'high']);

        // Priority should be constant regardless of parameters
        $this->assertSame($message1->getPriority(), $message2->getPriority());
        $this->assertSame(10, $message2->getPriority());
    }

    public function testToArray(): void
    {
        $accountId = 123;
        $metadata = ['request_id' => 'req_789', 'user_id' => 'user_456'];

        $message = new SyncApplicationsMessage($accountId, $metadata);
        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('message_id', $array);
        $this->assertArrayHasKey('message_type', $array);
        $this->assertArrayHasKey('account_id', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('priority', $array);

        $this->assertSame($message->getMessageId(), $array['message_id']);
        $this->assertSame($message->getMessageType(), $array['message_type']);
        $this->assertSame($accountId, $array['account_id']);
        $this->assertSame($metadata, $array['metadata']);
        $this->assertSame($message->getPriority(), $array['priority']);
    }

    public function testToArrayWithDefaults(): void
    {
        $accountId = 456;
        $message = new SyncApplicationsMessage($accountId);
        $array = $message->toArray();

        $this->assertSame($accountId, $array['account_id']);
        $this->assertSame([], $array['metadata']);
        $this->assertSame(10, $array['priority']);
        $this->assertSame('sync_applications', $array['message_type']);
        $this->assertIsString($array['message_id']);
    }

    public function testGetScopeDescription(): void
    {
        $accountId = 123;
        $message = new SyncApplicationsMessage($accountId);

        $expectedDescription = "账号:{$accountId}的应用同步";
        $this->assertSame($expectedDescription, $message->getScopeDescription());
    }

    #[TestWith([123, '账号:123的应用同步'])]
    #[TestWith([0, '账号:0的应用同步'])]
    #[TestWith([-1, '账号:-1的应用同步'])]
    #[TestWith([999999, '账号:999999的应用同步'])]
    #[TestWith([PHP_INT_MAX, '账号:' . PHP_INT_MAX . '的应用同步'])]
    public function testVariousAccountIds(int $accountId, string $expectedDescription): void
    {
        $message = new SyncApplicationsMessage($accountId);

        $this->assertSame($expectedDescription, $message->getScopeDescription());
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[TestWith([[]])]
    #[TestWith([['request_id' => 'req_123']])]
    #[TestWith([[
        'request_id' => 'req_456',
        'timestamp' => 1640995200,
        'source' => 'api',
        'user_context' => [
            'user_id' => 'user_789',
            'permissions' => ['read', 'write'],
        ],
        'sync_options' => [
            'force_update' => true,
            'batch_size' => 50,
        ],
    ]])]
    #[TestWith([['count' => 42, 'rate' => 3.14]])]
    #[TestWith([['enabled' => true, 'force' => false]])]
    #[TestWith([['optional' => null, 'required' => 'value']])]
    #[TestWith([['消息' => '同步应用', '状态' => '进行中']])]
    public function testVariousMetadata(array $metadata): void
    {
        $accountId = 123;
        $message = new SyncApplicationsMessage($accountId, $metadata);

        $this->assertSame($metadata, $message->metadata);
        $this->assertSame($metadata, $message->toArray()['metadata']);
    }

    public function testReadonlyProperties(): void
    {
        $accountId = 123;
        $metadata = ['test' => 'data'];
        $message = new SyncApplicationsMessage($accountId, $metadata);

        // Readonly properties cannot be modified after construction
        $this->assertSame($accountId, $message->accountId);
        $this->assertSame($metadata, $message->metadata);

        // The following would cause fatal errors if attempted:
        // $message->accountId = 456;
        // $message->metadata = ['new' => 'data'];
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(SyncApplicationsMessage::class);

        $this->assertTrue($reflection->isFinal(), 'SyncApplicationsMessage should be final');
        $this->assertTrue($reflection->isReadOnly(), 'SyncApplicationsMessage should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(SyncApplicationsMessage::class);
        $properties = $reflection->getProperties();

        $this->assertCount(2, $properties, 'Should have exactly 2 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('accountId', $propertyNames);
        $this->assertContains('metadata', $propertyNames);
    }

    public function testMessageIdWithRequestIdInMetadata(): void
    {
        $message1 = new SyncApplicationsMessage(123, ['request_id' => 'req_123']);
        $message2 = new SyncApplicationsMessage(123, ['request_id' => 'req_456']);
        $message3 = new SyncApplicationsMessage(123, ['other_field' => 'value']);

        // Different request_id should generate different message IDs
        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());

        // Missing request_id should generate different message ID
        $this->assertNotSame($message1->getMessageId(), $message3->getMessageId());
    }

    public function testMessageSerialization(): void
    {
        $accountId = 123;
        $metadata = ['request_id' => 'req_789', 'timestamp' => 1234567890];

        $message = new SyncApplicationsMessage($accountId, $metadata);

        // Test that message can be serialized and unserialized
        $serialized = serialize($message);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SyncApplicationsMessage::class, $unserialized);
        $this->assertSame($message->accountId, $unserialized->accountId);
        $this->assertSame($message->metadata, $unserialized->metadata);
        $this->assertSame($message->getMessageId(), $unserialized->getMessageId());
        $this->assertSame($message->getMessageType(), $unserialized->getMessageType());
        $this->assertSame($message->getPriority(), $unserialized->getPriority());
        $this->assertSame($message->getScopeDescription(), $unserialized->getScopeDescription());
    }

    public function testToArrayJsonSerializability(): void
    {
        $accountId = 123;
        $metadata = [
            'request_id' => 'req_789',
            'unicode' => '测试数据',
            'nested' => ['key' => 'value'],
        ];

        $message = new SyncApplicationsMessage($accountId, $metadata);
        $array = $message->toArray();
        $json = json_encode($array);

        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame($array, $decoded);
    }

    public function testEdgeCasesAndBoundaryValues(): void
    {
        // Test with zero account ID
        $zeroMessage = new SyncApplicationsMessage(0);
        $this->assertSame(0, $zeroMessage->accountId);
        $this->assertSame('账号:0的应用同步', $zeroMessage->getScopeDescription());

        // Test with negative account ID
        $negativeMessage = new SyncApplicationsMessage(-1);
        $this->assertSame(-1, $negativeMessage->accountId);
        $this->assertSame('账号:-1的应用同步', $negativeMessage->getScopeDescription());

        // Test with very large account ID
        $largeMessage = new SyncApplicationsMessage(PHP_INT_MAX);
        $this->assertSame(PHP_INT_MAX, $largeMessage->accountId);
        $this->assertStringContainsString((string) PHP_INT_MAX, $largeMessage->getScopeDescription());
    }

    public function testComplexMetadataHandling(): void
    {
        $complexMetadata = [
            'request_id' => 'req_complex_123',
            'timestamp' => time(),
            'source' => 'scheduled_task',
            'user_context' => [
                'user_id' => 'user_456',
                'organization_id' => 'org_789',
                'permissions' => ['sync:apps', 'read:metadata'],
                'preferences' => [
                    'batch_size' => 100,
                    'timeout' => 300,
                    'retry_attempts' => 3,
                ],
            ],
            'sync_filters' => [
                'app_types' => ['chatflow', 'workflow'],
                'status' => ['active', 'draft'],
                'updated_since' => '2024-01-01T00:00:00Z',
            ],
            'debug_info' => [
                'correlation_id' => 'corr_abc123',
                'trace_id' => 'trace_def456',
                'version' => '1.2.3',
            ],
        ];

        $message = new SyncApplicationsMessage(123, $complexMetadata);

        $this->assertSame($complexMetadata, $message->metadata);

        // Verify metadata affects message ID
        $messageWithDifferentMetadata = new SyncApplicationsMessage(123, ['different' => 'data']);
        $this->assertNotSame($message->getMessageId(), $messageWithDifferentMetadata->getMessageId());

        // Verify toArray includes all metadata
        $array = $message->toArray();
        $this->assertSame($complexMetadata, $array['metadata']);
    }

    public function testRealWorldSyncScenarios(): void
    {
        // Scenario 1: User-triggered sync
        $userTriggeredSync = new SyncApplicationsMessage(
            42,
            [
                'request_id' => 'sync_user_20240115_001',
                'triggered_by' => 'user_action',
                'source' => 'web_dashboard',
                'user_id' => 'user_789',
            ]
        );
        $this->assertSame('账号:42的应用同步', $userTriggeredSync->getScopeDescription());
        $this->assertSame(10, $userTriggeredSync->getPriority());

        // Scenario 2: Scheduled automatic sync
        $scheduledSync = new SyncApplicationsMessage(
            100,
            [
                'request_id' => 'sync_scheduled_20240115_002',
                'triggered_by' => 'scheduler',
                'source' => 'cron_job',
                'schedule_id' => 'daily_sync_01',
                'last_run' => '2024-01-14T23:59:59Z',
            ]
        );
        $this->assertSame('账号:100的应用同步', $scheduledSync->getScopeDescription());
        $this->assertSame(10, $scheduledSync->getPriority());

        // Scenario 3: API-triggered sync with filters
        $apiTriggeredSync = new SyncApplicationsMessage(
            200,
            [
                'request_id' => 'sync_api_20240115_003',
                'triggered_by' => 'api_call',
                'source' => 'external_system',
                'api_key_id' => 'key_abc123',
                'filters' => [
                    'app_types' => ['workflow'],
                    'updated_since' => '2024-01-15T00:00:00Z',
                ],
                'options' => [
                    'force_update' => true,
                    'skip_validation' => false,
                ],
            ]
        );
        $this->assertSame('账号:200的应用同步', $apiTriggeredSync->getScopeDescription());
        $this->assertSame(10, $apiTriggeredSync->getPriority());

        // Verify all messages have unique IDs
        $this->assertNotSame($userTriggeredSync->getMessageId(), $scheduledSync->getMessageId());
        $this->assertNotSame($userTriggeredSync->getMessageId(), $apiTriggeredSync->getMessageId());
        $this->assertNotSame($scheduledSync->getMessageId(), $apiTriggeredSync->getMessageId());
    }

    public function testMessageIdConsistencyWithComplexMetadata(): void
    {
        $metadata = [
            'nested' => ['deep' => ['value' => 123]],
            'array' => [1, 2, 3],
            'unicode' => '测试数据',
        ];

        $message1 = new SyncApplicationsMessage(123, $metadata);
        $message2 = new SyncApplicationsMessage(123, $metadata);

        // Complex metadata should still generate consistent message IDs
        $this->assertSame($message1->getMessageId(), $message2->getMessageId());

        // Slight change in metadata should generate different ID
        $modifiedMetadata = $metadata;
        $modifiedMetadata['array'] = [1, 2, 4]; // Changed last element

        $message3 = new SyncApplicationsMessage(123, $modifiedMetadata);
        $this->assertNotSame($message1->getMessageId(), $message3->getMessageId());
    }
}
