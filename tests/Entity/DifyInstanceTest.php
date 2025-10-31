<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * DifyInstance 实体单元测试
 * 测试重点：数据完整性、时间戳自动管理、状态控制
 * @internal
 */
#[CoversClass(DifyInstance::class)]
class DifyInstanceTest extends AbstractEntityTestCase
{
    private DifyInstance $instance;

    protected function createEntity(): object
    {
        return new DifyInstance();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            'name' => ['name', 'Test Dify Instance'],
            'baseUrl' => ['baseUrl', 'https://dify.example.com'],
            'description' => ['description', 'This is a test Dify instance for development'],
        ];
    }

    protected function setUp(): void
    {
        $this->instance = new DifyInstance();
    }

    public function testConstructorSetsCreateAndUpdateTimeToCurrentTime(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $entity = new DifyInstance();
        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getCreateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getCreateTime());
        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getUpdateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getUpdateTime());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->instance->getId());
    }

    public function testNameSetterAndGetter(): void
    {
        $testName = 'Test Dify Instance';

        $beforeUpdate = $this->instance->getUpdateTime();
        $this->instance->setName($testName);

        $this->assertSame($testName, $this->instance->getName());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->instance->getUpdateTime());
    }

    #[TestWith(['Test Instance'], 'simple_name')]
    #[TestWith(['Dify Instance 2024'], 'name_with_numbers')]
    #[TestWith(['Test-Instance_v1.0'], 'name_with_special_chars')]
    #[TestWith(['测试实例'], 'chinese_name')]
    public function testNameWithVariousValues(string $name): void
    {
        $this->instance->setName($name);
        $this->assertSame($name, $this->instance->getName());
    }

    public function testBaseUrlSetterAndGetter(): void
    {
        $testUrl = 'https://dify.example.com';

        $beforeUpdate = $this->instance->getUpdateTime();
        $this->instance->setBaseUrl($testUrl);

        $this->assertSame($testUrl, $this->instance->getBaseUrl());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->instance->getUpdateTime());
    }

    #[TestWith(['http://localhost:3000'], 'http_url')]
    #[TestWith(['https://dify.example.com'], 'https_url')]
    #[TestWith(['https://dify.example.com:8080'], 'url_with_port')]
    #[TestWith(['https://dify.example.com/api'], 'url_with_path')]
    #[TestWith(['https://api.dify.example.com'], 'url_with_subdomain')]
    public function testBaseUrlWithVariousValues(string $baseUrl): void
    {
        $this->instance->setBaseUrl($baseUrl);
        $this->assertSame($baseUrl, $this->instance->getBaseUrl());
    }

    public function testDescriptionSetterAndGetter(): void
    {
        $testDescription = 'This is a test Dify instance for development';

        $beforeUpdate = $this->instance->getUpdateTime();
        $this->instance->setDescription($testDescription);

        $this->assertSame($testDescription, $this->instance->getDescription());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->instance->getUpdateTime());
    }

    public function testDescriptionCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->instance->getDescription());

        // 测试设置为null
        $this->instance->setDescription('Some description');
        $this->instance->setDescription(null);
        $this->assertNull($this->instance->getDescription());
    }

    public function testDescriptionWithLongText(): void
    {
        $longDescription = str_repeat('This is a very long description. ', 20);
        $this->instance->setDescription($longDescription);
        $this->assertSame($longDescription, $this->instance->getDescription());
    }

    public function testIsEnabledDefaultsToTrue(): void
    {
        $this->assertTrue($this->instance->isEnabled());
    }

    public function testIsEnabledSetterAndGetter(): void
    {
        $beforeUpdate = $this->instance->getUpdateTime();

        // 测试设置为false
        $this->instance->setIsEnabled(false);
        $this->assertFalse($this->instance->isEnabled());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->instance->getUpdateTime());

        $beforeUpdate = $this->instance->getUpdateTime();

        // 测试设置为true
        $this->instance->setIsEnabled(true);
        $this->assertTrue($this->instance->isEnabled());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->instance->getUpdateTime());
    }

    public function testCreateTimeIsImmutable(): void
    {
        $createTime = $this->instance->getCreateTime();

        // 任何操作都不应改变createTime
        $this->instance->setName('New Name');
        $this->instance->setBaseUrl('https://new.url.com');
        $this->instance->setDescription('New description');
        $this->instance->setIsEnabled(false);

        $this->assertSame($createTime, $this->instance->getCreateTime());
    }

    public function testUpdateTimeChangesOnAllSetters(): void
    {
        $initialUpdateTime = $this->instance->getUpdateTime();

        // 等待确保时间差异
        usleep(1000);

        // 测试每个setter都会更新updateTime
        $this->instance->setName('Test Name');
        $updateTime1 = $this->instance->getUpdateTime();
        $this->assertGreaterThanOrEqual($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->instance->setBaseUrl('https://test.com');
        $updateTime2 = $this->instance->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);

        usleep(1000);
        $this->instance->setDescription('Test description');
        $updateTime3 = $this->instance->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime2, $updateTime3);

        usleep(1000);
        $this->instance->setIsEnabled(false);
        $updateTime4 = $this->instance->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime3, $updateTime4);
    }

    public function testCompleteInstanceConfiguration(): void
    {
        $name = 'Production Dify Instance';
        $baseUrl = 'https://dify.production.com';
        $description = 'Production environment for Dify AI applications';
        $isEnabled = true;

        $this->instance->setName($name);
        $this->instance->setBaseUrl($baseUrl);
        $this->instance->setDescription($description);
        $this->instance->setIsEnabled($isEnabled);

        // 验证所有属性都正确设置
        $this->assertSame($name, $this->instance->getName());
        $this->assertSame($baseUrl, $this->instance->getBaseUrl());
        $this->assertSame($description, $this->instance->getDescription());
        $this->assertSame($isEnabled, $this->instance->isEnabled());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->instance->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->instance->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->instance->getCreateTime(), $this->instance->getUpdateTime());
    }

    public function testEntityStateAfterMultipleOperations(): void
    {
        // 模拟实际使用场景
        $this->instance->setName('Initial Name');
        $this->instance->setBaseUrl('https://initial.url.com');
        $this->instance->setIsEnabled(true);

        // 后续更新
        $this->instance->setName('Updated Name');
        $this->instance->setDescription('Added description later');
        $this->instance->setIsEnabled(false);

        // 验证最终状态
        $this->assertSame('Updated Name', $this->instance->getName());
        $this->assertSame('https://initial.url.com', $this->instance->getBaseUrl());
        $this->assertSame('Added description later', $this->instance->getDescription());
        $this->assertFalse($this->instance->isEnabled());
    }
}
