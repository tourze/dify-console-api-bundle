<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * AppDslVersion 实体测试
 * @internal
 */
#[CoversClass(AppDslVersion::class)]
final class AppDslVersionTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        // 创建Mock BaseApp以满足AppDslVersion的构造要求
        $mockApp = $this->createMock(BaseApp::class);
        $mockApp->method('getId')->willReturn(1);
        $mockApp->method('getDifyAppId')->willReturn('test-app-id');
        $mockApp->method('getName')->willReturn('Test App');

        return AppDslVersion::create($mockApp, 1);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $app = new ChatAssistantApp();
        $dslContent = ['name' => 'Test App', 'description' => 'Test Description'];
        $syncTime = new \DateTimeImmutable('2023-01-01 12:00:00');

        return [
            'app' => ['app', $app],
            'version' => ['version', 5],
            'dslContent' => ['dslContent', $dslContent],
            'dslHash' => ['dslHash', 'sha256-hash-value'],
            'syncTime' => ['syncTime', $syncTime],
            'dslRawContent' => ['dslRawContent', 'version: "1.0"\napp:\n  name: "Test App"'],
        ];
    }

    public function testConstructor(): void
    {
        $app = new ChatAssistantApp();
        $version = AppDslVersion::create($app, 5);

        $this->assertNull($version->getId());
        $this->assertSame($app, $version->getApp());
        $this->assertSame(5, $version->getVersion());
        $this->assertInstanceOf(\DateTimeImmutable::class, $version->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $version->getUpdateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $version->getSyncTime());
    }

    public function testSetAndGetApp(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $app = new ChatAssistantApp();

        $version->setApp($app);

        $this->assertSame($app, $version->getApp());
    }

    public function testSetAndGetVersion(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $versionNumber = 5;

        $version->setVersion($versionNumber);

        $this->assertSame($versionNumber, $version->getVersion());
    }

    public function testSetAndGetDslContent(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $dslContent = [
            'name' => 'Test App',
            'description' => 'Test Description',
            'model_config' => ['model' => 'gpt-3.5-turbo'],
        ];

        $version->setDslContent($dslContent);

        $this->assertSame($dslContent, $version->getDslContent());
    }

    public function testSetAndGetDslHash(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $hash = 'sha256-hash-value';

        $version->setDslHash($hash);

        $this->assertSame($hash, $version->getDslHash());
    }

    public function testSetAndGetSyncTime(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $syncTime = new \DateTimeImmutable('2023-01-01 12:00:00');

        $version->setSyncTime($syncTime);

        $this->assertSame($syncTime, $version->getSyncTime());
    }

    public function testSetAndGetDslRawContent(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $rawContent = 'version: "1.0"\napp:\n  name: "Test App"';

        $version->setDslRawContent($rawContent);

        $this->assertSame($rawContent, $version->getDslRawContent());
    }

    public function testDslRawContentDefaultsToNull(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);

        $this->assertNull($version->getDslRawContent());
    }

    public function testUpdateTimestampOnSet(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $originalUpdateTime = $version->getUpdateTime();

        // 等待一小段时间确保时间戳不同
        usleep(1000);

        $version->setVersion(1);
        $newUpdateTime = $version->getUpdateTime();

        $this->assertGreaterThan($originalUpdateTime, $newUpdateTime);
    }

    public function testTimestampUpdatesOnAllSetters(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);
        $app = new ChatAssistantApp();

        $originalTime = $version->getUpdateTime();
        usleep(1000);

        // 测试每个 setter 都会更新时间戳
        $version->setApp($app);
        $this->assertGreaterThan($originalTime, $version->getUpdateTime());

        $time1 = $version->getUpdateTime();
        usleep(1000);
        $version->setDslContent(['test' => 'data']);
        $this->assertGreaterThan($time1, $version->getUpdateTime());

        $time2 = $version->getUpdateTime();
        usleep(1000);
        $version->setDslHash('new-hash');
        $this->assertGreaterThan($time2, $version->getUpdateTime());

        $time3 = $version->getUpdateTime();
        usleep(1000);
        $version->setSyncTime(new \DateTimeImmutable());
        $this->assertGreaterThan($time3, $version->getUpdateTime());
    }

    public function testDefaultValues(): void
    {
        $version = AppDslVersion::create(new ChatAssistantApp(), 1);

        $this->assertSame([], $version->getDslContent());
    }
}
