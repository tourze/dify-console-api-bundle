<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * WorkflowApp 实体单元测试
 * 测试重点：继承的BaseApp功能、特有的工作流配置字段、JSON序列化
 * @internal
 */
#[CoversClass(WorkflowApp::class)]
class WorkflowAppTest extends AbstractEntityTestCase
{
    private WorkflowApp $workflowApp;

    protected function createEntity(): object
    {
        return new WorkflowApp();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $workflowConfig = ['version' => '2.0', 'steps' => [['id' => 'input', 'type' => 'input']]];
        $inputSchema = ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]];
        $outputSchema = ['type' => 'object', 'properties' => ['result' => ['type' => 'string']]];

        return [
            'instance' => ['instance', $instance],
            'difyAppId' => ['difyAppId', 'workflow_app_789'],
            'name' => ['name', 'Test Workflow'],
            'description' => ['description', 'A test workflow app'],
            'icon' => ['icon', 'workflow_icon.png'],
            'workflowConfig' => ['workflowConfig', $workflowConfig],
            'inputSchema' => ['inputSchema', $inputSchema],
            'outputSchema' => ['outputSchema', $outputSchema],
        ];
    }

    protected function setUp(): void
    {
        $this->workflowApp = new WorkflowApp();
    }

    public function testInheritsBaseAppFunctionality(): void
    {
        // 验证继承自BaseApp的时间戳功能
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->workflowApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->workflowApp->getUpdateTime());

        // 验证继承自BaseApp的基本字段
        $this->assertNull($this->workflowApp->getId());
        $this->assertNull($this->workflowApp->getDescription());
        $this->assertNull($this->workflowApp->getIcon());
        $this->assertFalse($this->workflowApp->isPublic());
    }

    public function testWorkflowConfigDefaultsToNull(): void
    {
        $this->assertNull($this->workflowApp->getWorkflowConfig());
    }

    public function testWorkflowConfigSetterAndGetter(): void
    {
        $workflowConfig = [
            'steps' => [
                ['id' => 'step1', 'type' => 'input', 'config' => ['name' => 'user_input']],
                ['id' => 'step2', 'type' => 'llm', 'config' => ['model' => 'gpt-4', 'temperature' => 0.7]],
                ['id' => 'step3', 'type' => 'output', 'config' => ['format' => 'json']],
            ],
            'connections' => [
                ['from' => 'step1', 'to' => 'step2'],
                ['from' => 'step2', 'to' => 'step3'],
            ],
        ];

        $beforeUpdate = $this->workflowApp->getUpdateTime();
        $this->workflowApp->setWorkflowConfig($workflowConfig);

        $this->assertSame($workflowConfig, $this->workflowApp->getWorkflowConfig());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->workflowApp->getUpdateTime());
    }

    public function testWorkflowConfigCanBeNull(): void
    {
        // 设置配置然后设为null
        $this->workflowApp->setWorkflowConfig(['some' => 'config']);
        $this->workflowApp->setWorkflowConfig(null);
        $this->assertNull($this->workflowApp->getWorkflowConfig());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([[]])] // empty_config
    public function testWorkflowConfigWithVariousStructures(array $config): void
    {
        $this->workflowApp->setWorkflowConfig($config);
        $this->assertSame($config, $this->workflowApp->getWorkflowConfig());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function workflowConfigDataProvider(): array
    {
        return [
            'empty_config' => [[]],
            'simple_config' => [['version' => '1.0', 'name' => 'Simple Workflow']],
            'complex_config' => [
                [
                    'version' => '2.0',
                    'metadata' => ['created_by' => 'user123', 'tags' => ['tag1', 'tag2']],
                    'steps' => [
                        [
                            'id' => 'input_step',
                            'type' => 'input',
                            'position' => ['x' => 100, 'y' => 200],
                            'config' => [
                                'fields' => [
                                    ['name' => 'query', 'type' => 'text', 'required' => true],
                                    ['name' => 'context', 'type' => 'text', 'required' => false],
                                ],
                            ],
                        ],
                        [
                            'id' => 'processing_step',
                            'type' => 'llm',
                            'position' => ['x' => 300, 'y' => 200],
                            'config' => [
                                'model' => 'claude-3-sonnet',
                                'temperature' => 0.7,
                                'max_tokens' => 1000,
                                'system_prompt' => 'You are a helpful assistant.',
                            ],
                        ],
                    ],
                    'connections' => [
                        ['from' => 'input_step', 'to' => 'processing_step', 'port' => 'default'],
                    ],
                ],
            ],
            'nested_arrays' => [
                [
                    'deeply' => [
                        'nested' => [
                            'structure' => [
                                'with' => ['multiple', 'levels'],
                                'and' => ['different', 'data', 'types'],
                                'numbers' => [1, 2, 3, 4.5],
                                'boolean' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testInputSchemaDefaultsToNull(): void
    {
        $this->assertNull($this->workflowApp->getInputSchema());
    }

    public function testInputSchemaSetterAndGetter(): void
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'User query'],
                'context' => ['type' => 'string', 'description' => 'Additional context'],
                'options' => [
                    'type' => 'object',
                    'properties' => [
                        'temperature' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'max_tokens' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
            ],
            'required' => ['query'],
        ];

        $beforeUpdate = $this->workflowApp->getUpdateTime();
        $this->workflowApp->setInputSchema($inputSchema);

        $this->assertSame($inputSchema, $this->workflowApp->getInputSchema());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->workflowApp->getUpdateTime());
    }

    public function testInputSchemaCanBeNull(): void
    {
        $this->workflowApp->setInputSchema(['some' => 'schema']);
        $this->workflowApp->setInputSchema(null);
        $this->assertNull($this->workflowApp->getInputSchema());
    }

    public function testOutputSchemaDefaultsToNull(): void
    {
        $this->assertNull($this->workflowApp->getOutputSchema());
    }

    public function testOutputSchemaSetterAndGetter(): void
    {
        $outputSchema = [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string', 'description' => 'Processed result'],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'processing_time' => ['type' => 'number'],
                        'model_used' => ['type' => 'string'],
                        'tokens_used' => ['type' => 'integer'],
                    ],
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['success', 'error', 'partial'],
                ],
            ],
            'required' => ['result', 'status'],
        ];

        $beforeUpdate = $this->workflowApp->getUpdateTime();
        $this->workflowApp->setOutputSchema($outputSchema);

        $this->assertSame($outputSchema, $this->workflowApp->getOutputSchema());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->workflowApp->getUpdateTime());
    }

    public function testOutputSchemaCanBeNull(): void
    {
        $this->workflowApp->setOutputSchema(['some' => 'schema']);
        $this->workflowApp->setOutputSchema(null);
        $this->assertNull($this->workflowApp->getOutputSchema());
    }

    /**
     * @param array<string, mixed> $schema
     */
    #[TestWith([[]])] // empty_schema
    public function testSchemaWithVariousStructures(array $schema): void
    {
        // 测试输入schema
        $this->workflowApp->setInputSchema($schema);
        $this->assertSame($schema, $this->workflowApp->getInputSchema());

        // 测试输出schema
        $this->workflowApp->setOutputSchema($schema);
        $this->assertSame($schema, $this->workflowApp->getOutputSchema());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function schemaDataProvider(): array
    {
        return [
            'empty_schema' => [[]],
            'simple_string_schema' => [['type' => 'string']],
            'object_schema' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'array_schema' => [
                [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'value' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
            'complex_nested_schema' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'nested_field' => ['type' => 'string'],
                                            'numbers' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'number'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testUpdateTimeChangesOnAllSpecificSetters(): void
    {
        $initialUpdateTime = $this->workflowApp->getUpdateTime();

        // 等待确保时间差异
        usleep(1000);

        // 测试WorkflowApp特有的setter都会更新updateTime
        $this->workflowApp->setWorkflowConfig(['step1' => 'config']);
        $updateTime1 = $this->workflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->workflowApp->setInputSchema(['type' => 'object']);
        $updateTime2 = $this->workflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);

        usleep(1000);
        $this->workflowApp->setOutputSchema(['type' => 'object']);
        $updateTime3 = $this->workflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime2, $updateTime3);
    }

    public function testInheritedSettersAlsoUpdateTime(): void
    {
        $initialUpdateTime = $this->workflowApp->getUpdateTime();

        usleep(1000);

        // 测试继承的setter也会更新updateTime
        $this->workflowApp->setName('Test Workflow App');
        $updateTime1 = $this->workflowApp->getUpdateTime();
        $this->assertGreaterThan($initialUpdateTime, $updateTime1);

        usleep(1000);
        // 创建一个测试实例和账户
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        $this->workflowApp->setInstance($instance);
        $updateTime2 = $this->workflowApp->getUpdateTime();
        $this->assertGreaterThan($updateTime1, $updateTime2);
    }

    public function testCompleteWorkflowAppConfiguration(): void
    {
        // 创建测试实例和账户
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        // 设置基础属性（继承自BaseApp）
        $this->workflowApp->setInstance($instance);
        $this->workflowApp->setDifyAppId('workflow_app_789');
        $this->workflowApp->setName('Complete Workflow Application');
        $this->workflowApp->setDescription('A fully configured workflow application');
        $this->workflowApp->setIcon('workflow_icon.png');
        $this->workflowApp->setIsPublic(true);

        // 设置WorkflowApp特有属性
        $workflowConfig = [
            'version' => '2.0',
            'steps' => [
                ['id' => 'input', 'type' => 'input'],
                ['id' => 'process', 'type' => 'llm'],
                ['id' => 'output', 'type' => 'output'],
            ],
        ];
        $inputSchema = [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ];
        $outputSchema = [
            'type' => 'object',
            'properties' => ['result' => ['type' => 'string']],
            'required' => ['result'],
        ];

        $this->workflowApp->setWorkflowConfig($workflowConfig);
        $this->workflowApp->setInputSchema($inputSchema);
        $this->workflowApp->setOutputSchema($outputSchema);

        // 验证所有属性都正确设置
        $this->assertSame($instance, $this->workflowApp->getInstance());
        $this->assertSame('workflow_app_789', $this->workflowApp->getDifyAppId());
        $this->assertSame('Complete Workflow Application', $this->workflowApp->getName());
        $this->assertSame('A fully configured workflow application', $this->workflowApp->getDescription());
        $this->assertSame('workflow_icon.png', $this->workflowApp->getIcon());
        $this->assertTrue($this->workflowApp->isPublic());

        $this->assertSame($workflowConfig, $this->workflowApp->getWorkflowConfig());
        $this->assertSame($inputSchema, $this->workflowApp->getInputSchema());
        $this->assertSame($outputSchema, $this->workflowApp->getOutputSchema());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->workflowApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->workflowApp->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->workflowApp->getCreateTime(), $this->workflowApp->getUpdateTime());
    }

    public function testWorkflowAppSchemaEvolution(): void
    {
        // 模拟schema演进的场景

        // 初始schema
        $this->workflowApp->setInputSchema([
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ]);

        $this->workflowApp->setOutputSchema([
            'type' => 'object',
            'properties' => ['result' => ['type' => 'string']],
            'required' => ['result'],
        ]);

        // 验证初始状态
        $inputSchema = $this->workflowApp->getInputSchema();
        $this->assertNotNull($inputSchema);
        $this->assertIsArray($inputSchema);
        $this->assertArrayHasKey('properties', $inputSchema);
        $this->assertIsArray($inputSchema['properties']);
        $this->assertArrayHasKey('query', $inputSchema['properties']);

        $outputSchema = $this->workflowApp->getOutputSchema();
        $this->assertNotNull($outputSchema);
        $this->assertIsArray($outputSchema);
        $this->assertArrayHasKey('properties', $outputSchema);
        $this->assertIsArray($outputSchema['properties']);
        $this->assertArrayHasKey('result', $outputSchema['properties']);

        // 演进schema：添加新字段
        $this->workflowApp->setInputSchema([
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'context' => ['type' => 'string'],
                'options' => ['type' => 'object'],
            ],
            'required' => ['query'],
        ]);

        $this->workflowApp->setOutputSchema([
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
                'metadata' => ['type' => 'object'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['result'],
        ]);

        // 验证演进后的状态
        $inputSchema = $this->workflowApp->getInputSchema();
        $this->assertNotNull($inputSchema);
        $this->assertIsArray($inputSchema);
        $this->assertArrayHasKey('properties', $inputSchema);
        $this->assertIsArray($inputSchema['properties']);
        $this->assertArrayHasKey('context', $inputSchema['properties']);
        $this->assertArrayHasKey('options', $inputSchema['properties']);

        $outputSchema = $this->workflowApp->getOutputSchema();
        $this->assertNotNull($outputSchema);
        $this->assertIsArray($outputSchema);
        $this->assertArrayHasKey('properties', $outputSchema);
        $this->assertIsArray($outputSchema['properties']);
        $this->assertArrayHasKey('metadata', $outputSchema['properties']);
        $this->assertArrayHasKey('confidence', $outputSchema['properties']);
    }
}
