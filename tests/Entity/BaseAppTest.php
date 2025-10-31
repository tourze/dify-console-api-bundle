<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * BaseApp 抽象基类单元测试
 * 测试重点：公共字段管理、时间戳自动更新、抽象基类行为
 * @internal
 */
#[CoversClass(BaseApp::class)]
class BaseAppTest extends AbstractEntityTestCase
{
    private BaseApp $baseApp;

    protected function createEntity(): object
    {
        return new class extends BaseApp {
            // 为测试目的创建的具体实现
        };
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        return [
            'instance' => ['instance', $instance],
            'difyAppId' => ['difyAppId', 'app_12345678'],
            'name' => ['name', 'Test Application'],
            'description' => ['description', 'Test description'],
            'icon' => ['icon', 'test.png'],
            'createdByDifyUser' => ['createdByDifyUser', 'user_12345'],
            'difyCreateTime' => ['difyCreateTime', new \DateTimeImmutable('2024-01-15 10:30:00')],
            'difyUpdateTime' => ['difyUpdateTime', new \DateTimeImmutable('2024-01-15 11:30:00')],
            'lastSyncTime' => ['lastSyncTime', new \DateTimeImmutable('2024-01-15 12:30:00')],
        ];
    }

    protected function setUp(): void
    {
        // 创建匿名类来测试抽象基类
        $this->baseApp = new class extends BaseApp {
            // 为测试目的创建的具体实现
        };
    }

    public function testConstructorSetsCreateAndUpdateTimeToCurrentTime(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $entity = new class extends BaseApp {};
        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getCreateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getCreateTime());
        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getUpdateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getUpdateTime());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->baseApp->getId());
    }

    public function testInstanceSetterAndGetter(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setInstance($instance);

        $this->assertSame($instance, $this->baseApp->getInstance());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testDifyAppIdSetterAndGetter(): void
    {
        $difyAppId = 'app_12345678';

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setDifyAppId($difyAppId);

        $this->assertSame($difyAppId, $this->baseApp->getDifyAppId());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    #[TestWith(['12345678-1234-1234-1234-123456789012'])] // uuid_format
    #[TestWith(['app_123'])] // short_id
    #[TestWith(['app123test456'])] // alphanumeric
    #[TestWith(['app-123_test'])] // with_special_chars
    public function testDifyAppIdWithVariousValues(string $difyAppId): void
    {
        $this->baseApp->setDifyAppId($difyAppId);
        $this->assertSame($difyAppId, $this->baseApp->getDifyAppId());
    }

    public function testDifyAppIdWithLongValue(): void
    {
        $difyAppId = 'app_' . str_repeat('a', 90);
        $this->baseApp->setDifyAppId($difyAppId);
        $this->assertSame($difyAppId, $this->baseApp->getDifyAppId());
    }

    public function testNameSetterAndGetter(): void
    {
        $name = 'Test Application';

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setName($name);

        $this->assertSame($name, $this->baseApp->getName());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    #[TestWith(['My App'])] // simple_name
    #[TestWith(['App v2.1'])] // name_with_numbers
    #[TestWith(['My-App_v1.0'])] // name_with_special_chars
    #[TestWith(['我的应用'])] // chinese_name
    #[TestWith(['My App 🚀'])] // name_with_emojis
    public function testNameWithVariousValues(string $name): void
    {
        $this->baseApp->setName($name);
        $this->assertSame($name, $this->baseApp->getName());
    }

    public function testNameWithLongValue(): void
    {
        $name = str_repeat('A', 200);
        $this->baseApp->setName($name);
        $this->assertSame($name, $this->baseApp->getName());
    }

    public function testDescriptionSetterAndGetter(): void
    {
        $description = 'This is a test application description';

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setDescription($description);

        $this->assertSame($description, $this->baseApp->getDescription());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testDescriptionCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getDescription());

        // 测试设置为null
        $this->baseApp->setDescription('Some description');
        $this->baseApp->setDescription(null);
        $this->assertNull($this->baseApp->getDescription());
    }

    public function testIconSetterAndGetter(): void
    {
        $icon = 'https://images.unsplash.com/photo-1611224923853-80b023f02d71?w=32&h=32&fit=crop';

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setIcon($icon);

        $this->assertSame($icon, $this->baseApp->getIcon());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testIconCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getIcon());

        // 测试设置为null
        $this->baseApp->setIcon('https://images.unsplash.com/photo-1611224923853-80b023f02d71?w=32&h=32&fit=crop');
        $this->baseApp->setIcon(null);
        $this->assertNull($this->baseApp->getIcon());
    }

    #[TestWith(['https://images.unsplash.com/photo-1611224923853-80b023f02d71?w=32&h=32&fit=crop'])] // unsplash_url
    #[TestWith(['https://images.unsplash.com/photo-1550399105-c4db5fb85c18?w=32&h=32&fit=crop'])] // unsplash_svg_alt
    #[TestWith(['/images/icon.jpg'])] // relative_path
    #[TestWith(['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='])] // data_uri
    #[TestWith(['https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=64&h=64&fit=crop'])] // unsplash_long_url
    public function testIconWithVariousValues(string $icon): void
    {
        $this->baseApp->setIcon($icon);
        $this->assertSame($icon, $this->baseApp->getIcon());
    }

    public function testIsPublicDefaultsToFalse(): void
    {
        $this->assertFalse($this->baseApp->isPublic());
    }

    public function testIsPublicSetterAndGetter(): void
    {
        $beforeUpdate = $this->baseApp->getUpdateTime();

        // 测试设置为true
        $this->baseApp->setIsPublic(true);
        $this->assertTrue($this->baseApp->isPublic());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());

        $beforeUpdate = $this->baseApp->getUpdateTime();

        // 测试设置为false
        $this->baseApp->setIsPublic(false);
        $this->assertFalse($this->baseApp->isPublic());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testCreatedByDifyUserSetterAndGetter(): void
    {
        $createdByUser = 'user_12345';

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setCreatedByDifyUser($createdByUser);

        $this->assertSame($createdByUser, $this->baseApp->getCreatedByDifyUser());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testCreatedByDifyUserCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getCreatedByDifyUser());

        // 测试设置为null
        $this->baseApp->setCreatedByDifyUser('user_123');
        $this->baseApp->setCreatedByDifyUser(null);
        $this->assertNull($this->baseApp->getCreatedByDifyUser());
    }

    public function testDifyCreateTimeSetterAndGetter(): void
    {
        $difyCreateTime = new \DateTimeImmutable('2024-01-15 10:30:00');

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setDifyCreateTime($difyCreateTime);

        $this->assertSame($difyCreateTime, $this->baseApp->getDifyCreateTime());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testDifyCreateTimeCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getDifyCreateTime());

        // 测试设置为null
        $this->baseApp->setDifyCreateTime(new \DateTimeImmutable());
        $this->baseApp->setDifyCreateTime(null);
        $this->assertNull($this->baseApp->getDifyCreateTime());
    }

    public function testDifyUpdateTimeSetterAndGetter(): void
    {
        $difyUpdateTime = new \DateTimeImmutable('2024-01-15 11:30:00');

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setDifyUpdateTime($difyUpdateTime);

        $this->assertSame($difyUpdateTime, $this->baseApp->getDifyUpdateTime());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testDifyUpdateTimeCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getDifyUpdateTime());

        // 测试设置为null
        $this->baseApp->setDifyUpdateTime(new \DateTimeImmutable());
        $this->baseApp->setDifyUpdateTime(null);
        $this->assertNull($this->baseApp->getDifyUpdateTime());
    }

    public function testLastSyncTimeSetterAndGetter(): void
    {
        $lastSyncTime = new \DateTimeImmutable('2024-01-15 12:30:00');

        $beforeUpdate = $this->baseApp->getUpdateTime();
        $this->baseApp->setLastSyncTime($lastSyncTime);

        $this->assertSame($lastSyncTime, $this->baseApp->getLastSyncTime());
        $this->assertGreaterThan($beforeUpdate, $this->baseApp->getUpdateTime());
    }

    public function testLastSyncTimeCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->baseApp->getLastSyncTime());

        // 测试设置为null
        $this->baseApp->setLastSyncTime(new \DateTimeImmutable());
        $this->baseApp->setLastSyncTime(null);
        $this->assertNull($this->baseApp->getLastSyncTime());
    }

    public function testCreateTimeIsImmutable(): void
    {
        $createTime = $this->baseApp->getCreateTime();

        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        // 任何操作都不应改变createTime
        $this->baseApp->setInstance($instance);
        $this->baseApp->setDifyAppId('app_123');
        $this->baseApp->setName('Test App');
        $this->baseApp->setDescription('Test description');
        $this->baseApp->setIcon('test.png');
        $this->baseApp->setIsPublic(true);

        $this->assertSame($createTime, $this->baseApp->getCreateTime());
    }

    public function testUpdateTimeChangesOnAllSetters(): void
    {
        $initialUpdateTime = $this->baseApp->getUpdateTime();

        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        // 等待确保时间差异
        usleep(1000);

        // 测试每个setter都会更新updateTime
        $this->baseApp->setInstance($instance);
        $updateTime1 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->baseApp->setDifyAppId('app_123');
        $updateTime2 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime1, $updateTime2);

        usleep(1000);
        $this->baseApp->setName('Test App');
        $updateTime3 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime2, $updateTime3);

        usleep(1000);
        $this->baseApp->setDescription('Description');
        $updateTime4 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime3, $updateTime4);

        usleep(1000);
        $this->baseApp->setIcon('icon.png');
        $updateTime5 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime4, $updateTime5);

        usleep(1000);
        $this->baseApp->setIsPublic(true);
        $updateTime6 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime5, $updateTime6);

        usleep(1000);
        $this->baseApp->setCreatedByDifyUser('user_123');
        $updateTime7 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime6, $updateTime7);

        usleep(1000);
        $this->baseApp->setDifyCreateTime(new \DateTimeImmutable());
        $updateTime8 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime7, $updateTime8);

        usleep(1000);
        $this->baseApp->setDifyUpdateTime(new \DateTimeImmutable());
        $updateTime9 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime8, $updateTime9);

        usleep(1000);
        $this->baseApp->setLastSyncTime(new \DateTimeImmutable());
        $updateTime10 = $this->baseApp->getUpdateTime();
        $this->assertGreaterThan($updateTime9, $updateTime10);
    }

    public function testCompleteAppConfiguration(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $difyAppId = 'app_12345678';
        $name = 'Production Application';
        $description = 'This is a production application';
        $icon = 'https://images.unsplash.com/photo-1611224923853-80b023f02d71?w=32&h=32&fit=crop';
        $isPublic = true;
        $createdByUser = 'user_admin';
        $difyCreateTime = new \DateTimeImmutable('2024-01-15 10:00:00');
        $difyUpdateTime = new \DateTimeImmutable('2024-01-15 11:00:00');
        $lastSyncTime = new \DateTimeImmutable('2024-01-15 12:00:00');

        $this->baseApp->setInstance($instance);
        $this->baseApp->setDifyAppId($difyAppId);
        $this->baseApp->setName($name);
        $this->baseApp->setDescription($description);
        $this->baseApp->setIcon($icon);
        $this->baseApp->setIsPublic($isPublic);
        $this->baseApp->setCreatedByDifyUser($createdByUser);
        $this->baseApp->setDifyCreateTime($difyCreateTime);
        $this->baseApp->setDifyUpdateTime($difyUpdateTime);
        $this->baseApp->setLastSyncTime($lastSyncTime);

        // 验证所有属性都正确设置
        $this->assertSame($instance, $this->baseApp->getInstance());
        $this->assertSame($difyAppId, $this->baseApp->getDifyAppId());
        $this->assertSame($name, $this->baseApp->getName());
        $this->assertSame($description, $this->baseApp->getDescription());
        $this->assertSame($icon, $this->baseApp->getIcon());
        $this->assertSame($isPublic, $this->baseApp->isPublic());
        $this->assertSame($createdByUser, $this->baseApp->getCreatedByDifyUser());
        $this->assertSame($difyCreateTime, $this->baseApp->getDifyCreateTime());
        $this->assertSame($difyUpdateTime, $this->baseApp->getDifyUpdateTime());
        $this->assertSame($lastSyncTime, $this->baseApp->getLastSyncTime());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->baseApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->baseApp->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->baseApp->getCreateTime(), $this->baseApp->getUpdateTime());
    }

    public function testSyncScenario(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        // 模拟从Dify同步应用信息的场景
        $this->baseApp->setInstance($instance);
        $this->baseApp->setDifyAppId('app_from_dify');

        // 初次同步
        $initialSyncTime = new \DateTimeImmutable();
        $this->baseApp->setName('Synced App');
        $this->baseApp->setDescription('Synced from Dify');
        $this->baseApp->setIcon('synced_icon.png');
        $this->baseApp->setIsPublic(false);
        $this->baseApp->setCreatedByDifyUser('dify_user_123');
        $this->baseApp->setDifyCreateTime(new \DateTimeImmutable('2024-01-15 09:00:00'));
        $this->baseApp->setDifyUpdateTime(new \DateTimeImmutable('2024-01-15 09:30:00'));
        $this->baseApp->setLastSyncTime($initialSyncTime);

        // 验证同步状态
        $this->assertSame('Synced App', $this->baseApp->getName());
        $this->assertSame($initialSyncTime, $this->baseApp->getLastSyncTime());

        // 后续更新同步
        $updateSyncTime = new \DateTimeImmutable();
        $this->baseApp->setName('Updated Synced App');
        $this->baseApp->setDifyUpdateTime(new \DateTimeImmutable('2024-01-15 10:30:00'));
        $this->baseApp->setLastSyncTime($updateSyncTime);

        // 验证更新后的状态
        $this->assertSame('Updated Synced App', $this->baseApp->getName());
        $this->assertSame($updateSyncTime, $this->baseApp->getLastSyncTime());
        $this->assertGreaterThan($initialSyncTime, $updateSyncTime);
    }
}
