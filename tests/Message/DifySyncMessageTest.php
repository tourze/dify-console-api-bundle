<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;

/**
 * DifySyncMessage 消息类单元测试
 * 测试重点：消息属性、优先级计算、序列化、同步范围识别
 * @internal
 */
#[CoversClass(DifySyncMessage::class)]
class DifySyncMessageTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $message = new DifySyncMessage();

        $this->assertNull($message->instanceId);
        $this->assertNull($message->accountId);
        $this->assertNull($message->appType);
        $this->assertSame([], $message->metadata);
    }

    public function testConstructorWithAllParameters(): void
    {
        $instanceId = 123;
        $accountId = 456;
        $appType = 'chatflow';
        $metadata = ['request_id' => 'req_12345', 'timestamp' => time()];

        $message = new DifySyncMessage($instanceId, $accountId, $appType, $metadata);

        $this->assertSame($instanceId, $message->instanceId);
        $this->assertSame($accountId, $message->accountId);
        $this->assertSame($appType, $message->appType);
        $this->assertSame($metadata, $message->metadata);
    }

    public function testConstructorWithNamedParameters(): void
    {
        $message = new DifySyncMessage(
            instanceId: 789,
            accountId: 101,
            appType: 'workflow',
            metadata: ['source' => 'api', 'version' => '1.0']
        );

        $this->assertSame(789, $message->instanceId);
        $this->assertSame(101, $message->accountId);
        $this->assertSame('workflow', $message->appType);
        $this->assertSame(['source' => 'api', 'version' => '1.0'], $message->metadata);
    }

    public function testPartialParameterConstruction(): void
    {
        // Only instanceId
        $message1 = new DifySyncMessage(instanceId: 123);
        $this->assertSame(123, $message1->instanceId);
        $this->assertNull($message1->accountId);
        $this->assertNull($message1->appType);
        $this->assertSame([], $message1->metadata);

        // Only appType
        $message2 = new DifySyncMessage(appType: 'assistant');
        $this->assertNull($message2->instanceId);
        $this->assertNull($message2->accountId);
        $this->assertSame('assistant', $message2->appType);
        $this->assertSame([], $message2->metadata);
    }

    public function testGetMessageId(): void
    {
        $message = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_789']);

        $messageId = $message->getMessageId();

        $this->assertIsString($messageId);
        $this->assertSame(32, strlen($messageId)); // MD5 hash length
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $messageId);
    }

    public function testGetMessageIdConsistency(): void
    {
        $message1 = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_789']);
        $message2 = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_789']);

        // Same parameters should generate same message ID
        $this->assertSame($message1->getMessageId(), $message2->getMessageId());
    }

    public function testGetMessageIdUniqueness(): void
    {
        $message1 = new DifySyncMessage(123, 456, 'chatflow');
        $message2 = new DifySyncMessage(123, 456, 'workflow'); // Different appType
        $message3 = new DifySyncMessage(123, 457, 'chatflow'); // Different accountId

        // Different parameters should generate different message IDs
        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());
        $this->assertNotSame($message1->getMessageId(), $message3->getMessageId());
        $this->assertNotSame($message2->getMessageId(), $message3->getMessageId());
    }

    public function testGetMessageType(): void
    {
        $message = new DifySyncMessage();

        $this->assertSame('dify_sync', $message->getMessageType());
    }

    public function testGetMessageTypeIsConstant(): void
    {
        $message1 = new DifySyncMessage();
        $message2 = new DifySyncMessage(123, 456);

        // Message type should be constant regardless of parameters
        $this->assertSame($message1->getMessageType(), $message2->getMessageType());
        $this->assertSame('dify_sync', $message2->getMessageType());
    }

    #[TestWith([123, true])] // valid_instance_id
    public function testHasInstance(?int $instanceId, bool $expected): void
    {
        $message = new DifySyncMessage(instanceId: $instanceId);

        $this->assertSame($expected, $message->hasInstance());
    }

    /**
     * @return array<string, array{0: ?int, 1: bool}>
     */
    public static function hasInstanceDataProvider(): array
    {
        return [
            'null_instance' => [null, false],
            'zero_instance' => [0, true],
            'positive_instance' => [123, true],
            'negative_instance' => [-1, true], // Edge case
        ];
    }

    #[TestWith([456, true])] // valid_account_id
    public function testHasAccount(?int $accountId, bool $expected): void
    {
        $message = new DifySyncMessage(accountId: $accountId);

        $this->assertSame($expected, $message->hasAccount());
    }

    /**
     * @return array<string, array{0: ?int, 1: bool}>
     */
    public static function hasAccountDataProvider(): array
    {
        return [
            'null_account' => [null, false],
            'zero_account' => [0, true],
            'positive_account' => [456, true],
            'negative_account' => [-1, true], // Edge case
        ];
    }

    #[TestWith(['chatflow', true])] // valid_app_type
    public function testHasAppType(?string $appType, bool $expected): void
    {
        $message = new DifySyncMessage(appType: $appType);

        $this->assertSame($expected, $message->hasAppType());
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function hasAppTypeDataProvider(): array
    {
        return [
            'null_app_type' => [null, false],
            'empty_app_type' => ['', true],
            'chatflow_type' => ['chatflow', true],
            'workflow_type' => ['workflow', true],
            'assistant_type' => ['assistant', true],
            'unknown_type' => ['unknown', true],
        ];
    }

    #[TestWith([123, 456, 'chatflow', 10])] // full_sync_priority
    public function testGetPriority(?int $instanceId, ?int $accountId, ?string $appType, int $expectedPriority): void
    {
        $message = new DifySyncMessage($instanceId, $accountId, $appType);

        $this->assertSame($expectedPriority, $message->getPriority());
    }

    /**
     * @return array<string, array{0: ?int, 1: ?int, 2: ?string, 3: int}>
     */
    public static function priorityDataProvider(): array
    {
        return [
            'no_filters' => [null, null, null, 5],
            'instance_only' => [123, null, null, 10],
            'account_only' => [null, 456, null, 10],
            'app_type_only' => [null, null, 'chatflow', 10],
            'instance_and_account' => [123, 456, null, 10],
            'instance_and_app_type' => [123, null, 'workflow', 10],
            'account_and_app_type' => [null, 456, 'assistant', 10],
            'all_filters' => [123, 456, 'chatflow', 10],
            'zero_instance' => [0, null, null, 10],
            'zero_account' => [null, 0, null, 10],
            'empty_app_type' => [null, null, '', 10],
        ];
    }

    public function testToArray(): void
    {
        $instanceId = 123;
        $accountId = 456;
        $appType = 'chatflow';
        $metadata = ['request_id' => 'req_789', 'user_id' => 'user_123'];

        $message = new DifySyncMessage($instanceId, $accountId, $appType, $metadata);
        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('message_id', $array);
        $this->assertArrayHasKey('message_type', $array);
        $this->assertArrayHasKey('instance_id', $array);
        $this->assertArrayHasKey('account_id', $array);
        $this->assertArrayHasKey('app_type', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('priority', $array);

        $this->assertSame($message->getMessageId(), $array['message_id']);
        $this->assertSame($message->getMessageType(), $array['message_type']);
        $this->assertSame($instanceId, $array['instance_id']);
        $this->assertSame($accountId, $array['account_id']);
        $this->assertSame($appType, $array['app_type']);
        $this->assertSame($metadata, $array['metadata']);
        $this->assertSame($message->getPriority(), $array['priority']);
    }

    public function testToArrayWithDefaults(): void
    {
        $message = new DifySyncMessage();
        $array = $message->toArray();

        $this->assertNull($array['instance_id']);
        $this->assertNull($array['account_id']);
        $this->assertNull($array['app_type']);
        $this->assertSame([], $array['metadata']);
        $this->assertSame(5, $array['priority']);
        $this->assertSame('dify_sync', $array['message_type']);
    }

    #[TestWith([null, null, null, '全量同步'])] // global_scope
    public function testGetScopeDescription(
        ?int $instanceId,
        ?int $accountId,
        ?string $appType,
        string $expectedDescription,
    ): void {
        $message = new DifySyncMessage($instanceId, $accountId, $appType);

        $this->assertSame($expectedDescription, $message->getScopeDescription());
    }

    /**
     * @return array<string, array{0: ?int, 1: ?int, 2: ?string, 3: string}>
     */
    public static function scopeDescriptionDataProvider(): array
    {
        return [
            'no_scope' => [null, null, null, '全量同步'],
            'instance_only' => [123, null, null, '实例:123'],
            'account_only' => [null, 456, null, '账号:456'],
            'app_type_only' => [null, null, 'chatflow', '类型:chatflow'],
            'instance_and_account' => [123, 456, null, '实例:123, 账号:456'],
            'instance_and_app_type' => [123, null, 'workflow', '实例:123, 类型:workflow'],
            'account_and_app_type' => [null, 456, 'assistant', '账号:456, 类型:assistant'],
            'all_scope' => [123, 456, 'chatflow', '实例:123, 账号:456, 类型:chatflow'],
            'zero_ids' => [0, 0, '', '实例:0, 账号:0, 类型:'],
        ];
    }

    public function testReadonlyProperties(): void
    {
        $message = new DifySyncMessage(123, 456, 'chatflow', ['test' => 'data']);

        // Readonly properties cannot be modified after construction
        $this->assertSame(123, $message->instanceId);
        $this->assertSame(456, $message->accountId);
        $this->assertSame('chatflow', $message->appType);
        $this->assertSame(['test' => 'data'], $message->metadata);

        // The following would cause fatal errors if attempted:
        // $message->instanceId = 999;
        // $message->accountId = 999;
        // $message->appType = 'workflow';
        // $message->metadata = [];
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(DifySyncMessage::class);

        $this->assertTrue($reflection->isFinal(), 'DifySyncMessage should be final');
        $this->assertTrue($reflection->isReadOnly(), 'DifySyncMessage should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(DifySyncMessage::class);
        $properties = $reflection->getProperties();

        $this->assertCount(4, $properties, 'Should have exactly 4 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('instanceId', $propertyNames);
        $this->assertContains('accountId', $propertyNames);
        $this->assertContains('appType', $propertyNames);
        $this->assertContains('metadata', $propertyNames);
    }

    public function testMetadataHandling(): void
    {
        $complexMetadata = [
            'request_id' => 'req_123456',
            'timestamp' => time(),
            'source' => 'api',
            'user_context' => [
                'user_id' => 'user_789',
                'organization' => 'test_org',
                'permissions' => ['read', 'write'],
            ],
            'sync_options' => [
                'force_update' => true,
                'batch_size' => 100,
                'timeout' => 300,
            ],
        ];

        $message = new DifySyncMessage(123, 456, 'chatflow', $complexMetadata);

        $this->assertSame($complexMetadata, $message->metadata);

        // Verify metadata affects message ID
        $messageWithMetadata = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_abc']);
        $messageWithoutMetadata = new DifySyncMessage(123, 456, 'chatflow', []);

        $this->assertNotSame($messageWithMetadata->getMessageId(), $messageWithoutMetadata->getMessageId());
    }

    public function testMessageIdWithRequestIdInMetadata(): void
    {
        $message1 = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_123']);
        $message2 = new DifySyncMessage(123, 456, 'chatflow', ['request_id' => 'req_456']);
        $message3 = new DifySyncMessage(123, 456, 'chatflow', ['other_field' => 'value']);

        // Different request_id should generate different message IDs
        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());

        // Missing request_id should generate different message ID
        $this->assertNotSame($message1->getMessageId(), $message3->getMessageId());
    }

    public function testMessageSerialization(): void
    {
        $message = new DifySyncMessage(
            123,
            456,
            'chatflow',
            ['request_id' => 'req_789', 'timestamp' => 1234567890]
        );

        // Test that message can be serialized and unserialized
        $serialized = serialize($message);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DifySyncMessage::class, $unserialized);
        $this->assertSame($message->instanceId, $unserialized->instanceId);
        $this->assertSame($message->accountId, $unserialized->accountId);
        $this->assertSame($message->appType, $unserialized->appType);
        $this->assertSame($message->metadata, $unserialized->metadata);
        $this->assertSame($message->getMessageId(), $unserialized->getMessageId());
    }

    public function testRealWorldSyncScenarios(): void
    {
        // Scenario 1: Full sync of all apps
        $fullSync = new DifySyncMessage();
        $this->assertSame('全量同步', $fullSync->getScopeDescription());
        $this->assertSame(5, $fullSync->getPriority());

        // Scenario 2: Sync specific instance
        $instanceSync = new DifySyncMessage(instanceId: 1);
        $this->assertSame('实例:1', $instanceSync->getScopeDescription());
        $this->assertSame(10, $instanceSync->getPriority());

        // Scenario 3: Sync specific user's apps
        $userSync = new DifySyncMessage(accountId: 42);
        $this->assertSame('账号:42', $userSync->getScopeDescription());
        $this->assertSame(10, $userSync->getPriority());

        // Scenario 4: Sync only chatflow apps
        $typeSync = new DifySyncMessage(appType: 'chatflow');
        $this->assertSame('类型:chatflow', $typeSync->getScopeDescription());
        $this->assertSame(10, $typeSync->getPriority());

        // Scenario 5: Targeted sync with metadata
        $targetedSync = new DifySyncMessage(
            instanceId: 1,
            accountId: 42,
            appType: 'workflow',
            metadata: [
                'request_id' => 'sync_20240115_001',
                'triggered_by' => 'user_action',
                'incremental' => true,
            ]
        );
        $this->assertSame('实例:1, 账号:42, 类型:workflow', $targetedSync->getScopeDescription());
        $this->assertSame(10, $targetedSync->getPriority());
    }

    public function testEdgeCasesAndBoundaryValues(): void
    {
        // Test with zero values
        $zeroMessage = new DifySyncMessage(0, 0, '');
        $this->assertTrue($zeroMessage->hasInstance());
        $this->assertTrue($zeroMessage->hasAccount());
        $this->assertTrue($zeroMessage->hasAppType());
        $this->assertSame(10, $zeroMessage->getPriority());

        // Test with negative values
        $negativeMessage = new DifySyncMessage(-1, -1, 'negative');
        $this->assertTrue($negativeMessage->hasInstance());
        $this->assertTrue($negativeMessage->hasAccount());
        $this->assertTrue($negativeMessage->hasAppType());

        // Test with very large values
        $largeMessage = new DifySyncMessage(PHP_INT_MAX, PHP_INT_MAX, str_repeat('A', 1000));
        $this->assertTrue($largeMessage->hasInstance());
        $this->assertTrue($largeMessage->hasAccount());
        $this->assertTrue($largeMessage->hasAppType());
    }

    public function testToArrayJsonSerializability(): void
    {
        $message = new DifySyncMessage(
            123,
            456,
            'chatflow',
            ['request_id' => 'req_789', 'unicode' => '测试数据']
        );

        $array = $message->toArray();
        $json = json_encode($array);

        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame($array, $decoded);
    }
}
