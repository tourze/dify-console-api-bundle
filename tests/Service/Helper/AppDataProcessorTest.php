<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Service\Helper\AppDataProcessor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AppDataProcessor::class)]
#[RunTestsInSeparateProcesses]
class AppDataProcessorTest extends AbstractIntegrationTestCase
{
    private AppDataProcessor $processor;

    protected function onSetUp(): void
    {
        // 从容器获取服务实例，避免直接实例化
        $this->processor = self::getService(AppDataProcessor::class);
    }

    public function testUpdateAppBasicFields(): void
    {
        $app = new ChatAssistantApp();
        /** @var DifyInstance&MockObject $instance */
        $instance = $this->createMock(DifyInstance::class);
        $instance->method('getId')->willReturn(1);

        $appData = [
            'id' => 'test-app-id',
            'name' => 'Test App',
            'description' => 'Test Description',
            'icon' => 'test-icon',
            'is_public' => true,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:00:00Z',
        ];

        $this->processor->updateAppBasicFields($app, $instance, $appData);

        $this->assertSame('test-app-id', $app->getDifyAppId());
        $this->assertSame('Test App', $app->getName());
        $this->assertSame('Test Description', $app->getDescription());
        $this->assertSame('test-icon', $app->getIcon());
        $this->assertTrue($app->isPublic());
        $this->assertSame($instance, $app->getInstance());
    }

    public function testSetAppSpecificFields(): void
    {
        $app = new ChatAssistantApp();
        $appData = [
            'prompt_template' => 'Test Template',
            'model_config' => ['model' => 'gpt-4'],
            'retrieval_setting' => ['enabled' => true],
        ];

        $this->processor->setAppSpecificFields($app, $appData);

        $this->assertSame('Test Template', $app->getPromptTemplate());
        $this->assertSame(['model' => 'gpt-4'], $app->getAssistantConfig());
        $this->assertSame(['enabled' => true], $app->getKnowledgeBase());
    }
}
