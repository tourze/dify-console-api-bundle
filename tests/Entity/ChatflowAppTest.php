<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * ChatflowApp 实体单元测试
 * 测试重点：继承的BaseApp功能、聊天流配置、模型配置、对话配置
 * @internal
 */
#[CoversClass(ChatflowApp::class)]
class ChatflowAppTest extends AbstractEntityTestCase
{
    private ChatflowApp $chatflowApp;

    protected function createEntity(): object
    {
        return new ChatflowApp();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $chatflowConfig = ['flow_type' => 'interactive', 'nodes' => ['greeting', 'input', 'process']];
        $modelConfig = ['model' => 'claude-3-sonnet', 'temperature' => 0.7];
        $conversationConfig = ['session_timeout' => 3600, 'max_messages' => 50];

        return [
            'instance' => ['instance', $instance],
            'difyAppId' => ['difyAppId', 'chatflow_app_789'],
            'name' => ['name', 'Test Chatflow'],
            'description' => ['description', 'A test chatflow app'],
            'icon' => ['icon', 'chatflow_icon.png'],
            'chatflowConfig' => ['chatflowConfig', $chatflowConfig],
            'modelConfig' => ['modelConfig', $modelConfig],
            'conversationConfig' => ['conversationConfig', $conversationConfig],
        ];
    }

    protected function setUp(): void
    {
        $this->chatflowApp = new ChatflowApp();
    }

    public function testInheritsBaseAppFunctionality(): void
    {
        // 验证继承自BaseApp的时间戳功能
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatflowApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatflowApp->getUpdateTime());

        // 验证继承自BaseApp的基本字段
        $this->assertNull($this->chatflowApp->getId());
        $this->assertNull($this->chatflowApp->getDescription());
        $this->assertNull($this->chatflowApp->getIcon());
        $this->assertFalse($this->chatflowApp->isPublic());
    }

    public function testChatflowConfigDefaultsToNull(): void
    {
        $this->assertNull($this->chatflowApp->getChatflowConfig());
    }

    public function testChatflowConfigSetterAndGetter(): void
    {
        $chatflowConfig = [
            'chat_style' => 'conversational',
            'flow_definition' => [
                'start_node' => 'greeting',
                'nodes' => [
                    'greeting' => [
                        'type' => 'message',
                        'content' => 'Hello! How can I help you?',
                        'next' => 'user_input',
                    ],
                    'user_input' => [
                        'type' => 'input',
                        'variable' => 'user_query',
                        'next' => 'process_query',
                    ],
                    'process_query' => [
                        'type' => 'llm',
                        'model' => 'claude-3-sonnet',
                        'prompt' => 'Process this query: {{user_query}}',
                        'next' => 'response',
                    ],
                    'response' => [
                        'type' => 'message',
                        'content' => '{{llm_response}}',
                        'next' => 'end',
                    ],
                ],
            ],
            'ui_config' => [
                'theme' => 'light',
                'show_typing_indicator' => true,
                'message_delay' => 500,
            ],
        ];

        $beforeUpdate = $this->chatflowApp->getUpdateTime();
        $this->chatflowApp->setChatflowConfig($chatflowConfig);

        $this->assertSame($chatflowConfig, $this->chatflowApp->getChatflowConfig());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatflowApp->getUpdateTime());
    }

    public function testChatflowConfigCanBeNull(): void
    {
        $this->chatflowApp->setChatflowConfig(['some' => 'config']);
        $this->chatflowApp->setChatflowConfig(null);
        $this->assertNull($this->chatflowApp->getChatflowConfig());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([[]])] // empty_config
    public function testChatflowConfigWithVariousStructures(array $config): void
    {
        $this->chatflowApp->setChatflowConfig($config);
        $this->assertSame($config, $this->chatflowApp->getChatflowConfig());
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function chatflowConfigDataProvider(): array
    {
        return [
            'empty_config' => [[]],
            'simple_config' => [['version' => '1.0', 'type' => 'chatflow']],
            'linear_flow' => [
                [
                    'flow_type' => 'linear',
                    'steps' => [
                        ['id' => 'step1', 'type' => 'greeting', 'message' => 'Welcome!'],
                        ['id' => 'step2', 'type' => 'question', 'text' => 'What can I help you with?'],
                        ['id' => 'step3', 'type' => 'llm_response'],
                    ],
                ],
            ],
            'branching_flow' => [
                [
                    'flow_type' => 'branching',
                    'nodes' => [
                        'start' => [
                            'type' => 'condition',
                            'condition' => 'user_type',
                            'branches' => [
                                'new_user' => 'onboarding_flow',
                                'existing_user' => 'main_flow',
                            ],
                        ],
                        'onboarding_flow' => [
                            'type' => 'sequence',
                            'steps' => ['welcome', 'tutorial', 'first_question'],
                        ],
                        'main_flow' => [
                            'type' => 'direct',
                            'target' => 'llm_processor',
                        ],
                    ],
                ],
            ],
            'complex_config' => [
                [
                    'metadata' => [
                        'version' => '2.1',
                        'created_by' => 'user123',
                        'last_modified' => '2024-01-15T10:30:00Z',
                    ],
                    'flow_definition' => [
                        'variables' => [
                            'user_name' => ['type' => 'string', 'default' => ''],
                            'session_id' => ['type' => 'string', 'auto_generate' => true],
                            'conversation_context' => ['type' => 'array', 'default' => []],
                        ],
                        'triggers' => [
                            'on_start' => ['action' => 'initialize_session'],
                            'on_error' => ['action' => 'show_error_message'],
                            'on_timeout' => ['action' => 'prompt_continue'],
                        ],
                    ],
                    'integrations' => [
                        'knowledge_base' => ['enabled' => true, 'sources' => ['kb1', 'kb2']],
                        'analytics' => ['track_events' => true, 'session_recording' => false],
                    ],
                ],
            ],
        ];
    }

    public function testModelConfigDefaultsToNull(): void
    {
        $this->assertNull($this->chatflowApp->getModelConfig());
    }

    public function testModelConfigSetterAndGetter(): void
    {
        $modelConfig = [
            'primary_model' => 'claude-3-sonnet',
            'fallback_model' => 'claude-3-haiku',
            'model_parameters' => [
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'top_p' => 0.9,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
            ],
            'model_routing' => [
                'strategy' => 'primary_with_fallback',
                'fallback_conditions' => [
                    'rate_limit_exceeded',
                    'model_unavailable',
                    'timeout',
                ],
            ],
            'response_format' => [
                'type' => 'text',
                'streaming' => true,
                'include_metadata' => false,
            ],
        ];

        $beforeUpdate = $this->chatflowApp->getUpdateTime();
        $this->chatflowApp->setModelConfig($modelConfig);

        $this->assertSame($modelConfig, $this->chatflowApp->getModelConfig());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatflowApp->getUpdateTime());
    }

    public function testModelConfigCanBeNull(): void
    {
        $this->chatflowApp->setModelConfig(['some' => 'config']);
        $this->chatflowApp->setModelConfig(null);
        $this->assertNull($this->chatflowApp->getModelConfig());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([[]])] // empty_config
    public function testModelConfigWithVariousStructures(array $config): void
    {
        $this->chatflowApp->setModelConfig($config);
        $this->assertSame($config, $this->chatflowApp->getModelConfig());
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function modelConfigDataProvider(): array
    {
        return [
            'simple_model_config' => [
                ['model' => 'gpt-4', 'temperature' => 0.5],
            ],
            'detailed_config' => [
                [
                    'model_selection' => [
                        'primary' => 'claude-3-opus',
                        'backup' => 'claude-3-sonnet',
                        'fast' => 'claude-3-haiku',
                    ],
                    'parameters' => [
                        'temperature' => 0.8,
                        'max_tokens' => 4000,
                        'stop_sequences' => ['\n\nHuman:', '\n\nAssistant:'],
                    ],
                    'optimization' => [
                        'cache_responses' => true,
                        'parallel_requests' => false,
                        'response_compression' => true,
                    ],
                ],
            ],
            'multi_model_setup' => [
                [
                    'models' => [
                        'classification' => [
                            'model' => 'claude-3-haiku',
                            'purpose' => 'intent_classification',
                            'parameters' => ['temperature' => 0.1],
                        ],
                        'generation' => [
                            'model' => 'claude-3-sonnet',
                            'purpose' => 'response_generation',
                            'parameters' => ['temperature' => 0.7],
                        ],
                        'summarization' => [
                            'model' => 'claude-3-haiku',
                            'purpose' => 'conversation_summary',
                            'parameters' => ['temperature' => 0.3],
                        ],
                    ],
                    'routing_rules' => [
                        'use_classification_first' => true,
                        'fallback_strategy' => 'use_generation_model',
                    ],
                ],
            ],
        ];
    }

    public function testConversationConfigDefaultsToNull(): void
    {
        $this->assertNull($this->chatflowApp->getConversationConfig());
    }

    public function testConversationConfigSetterAndGetter(): void
    {
        $conversationConfig = [
            'session_management' => [
                'session_timeout' => 3600, // 1 hour in seconds
                'max_session_length' => 50, // max messages per session
                'session_persistence' => true,
            ],
            'conversation_flow' => [
                'allow_topic_switching' => true,
                'context_window_size' => 10,
                'conversation_memory' => [
                    'type' => 'sliding_window',
                    'size' => 20,
                    'summarization_threshold' => 30,
                ],
            ],
            'user_interaction' => [
                'typing_indicators' => true,
                'read_receipts' => false,
                'message_reactions' => true,
                'file_uploads' => [
                    'enabled' => true,
                    'max_file_size' => '10MB',
                    'allowed_types' => ['image', 'document', 'text'],
                ],
            ],
            'moderation' => [
                'content_filtering' => true,
                'spam_detection' => true,
                'rate_limiting' => [
                    'messages_per_minute' => 10,
                    'messages_per_hour' => 100,
                ],
            ],
        ];

        $beforeUpdate = $this->chatflowApp->getUpdateTime();
        $this->chatflowApp->setConversationConfig($conversationConfig);

        $this->assertSame($conversationConfig, $this->chatflowApp->getConversationConfig());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatflowApp->getUpdateTime());
    }

    public function testConversationConfigCanBeNull(): void
    {
        $this->chatflowApp->setConversationConfig(['some' => 'config']);
        $this->chatflowApp->setConversationConfig(null);
        $this->assertNull($this->chatflowApp->getConversationConfig());
    }

    public function testUpdateTimeChangesOnAllSpecificSetters(): void
    {
        $initialUpdateTime = $this->chatflowApp->getUpdateTime();

        // 等待确保时间差异
        usleep(1000);

        // 测试ChatflowApp特有的setter都会更新updateTime
        $this->chatflowApp->setChatflowConfig(['flow' => 'config']);
        $updateTime1 = $this->chatflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->chatflowApp->setModelConfig(['model' => 'claude-3-sonnet']);
        $updateTime2 = $this->chatflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);

        usleep(1000);
        $this->chatflowApp->setConversationConfig(['session_timeout' => 3600]);
        $updateTime3 = $this->chatflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime2, $updateTime3);
    }

    public function testInheritedSettersAlsoUpdateTime(): void
    {
        $initialUpdateTime = $this->chatflowApp->getUpdateTime();

        usleep(1000);

        // 测试继承的setter也会更新updateTime
        $this->chatflowApp->setName('Test Chatflow App');
        $updateTime1 = $this->chatflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($initialUpdateTime, $updateTime1);

        usleep(1000);
        // 创建一个测试实例和账户
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        $this->chatflowApp->setInstance($instance);
        $updateTime2 = $this->chatflowApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);
    }

    public function testCompleteChatflowAppConfiguration(): void
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
        $this->chatflowApp->setInstance($instance);
        $this->chatflowApp->setDifyAppId('chatflow_app_789');
        $this->chatflowApp->setName('Complete Chatflow Application');
        $this->chatflowApp->setDescription('A fully configured chatflow application');
        $this->chatflowApp->setIcon('chatflow_icon.png');
        $this->chatflowApp->setIsPublic(false);

        // 设置ChatflowApp特有属性
        $chatflowConfig = [
            'flow_type' => 'interactive',
            'nodes' => ['greeting', 'input', 'process', 'response'],
        ];
        $modelConfig = [
            'model' => 'claude-3-sonnet',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ];
        $conversationConfig = [
            'session_timeout' => 3600,
            'max_messages' => 50,
        ];

        $this->chatflowApp->setChatflowConfig($chatflowConfig);
        $this->chatflowApp->setModelConfig($modelConfig);
        $this->chatflowApp->setConversationConfig($conversationConfig);

        // 验证所有属性都正确设置
        $this->assertSame($instance, $this->chatflowApp->getInstance());
        $this->assertSame('chatflow_app_789', $this->chatflowApp->getDifyAppId());
        $this->assertSame('Complete Chatflow Application', $this->chatflowApp->getName());
        $this->assertSame('A fully configured chatflow application', $this->chatflowApp->getDescription());
        $this->assertSame('chatflow_icon.png', $this->chatflowApp->getIcon());
        $this->assertFalse($this->chatflowApp->isPublic());

        $this->assertSame($chatflowConfig, $this->chatflowApp->getChatflowConfig());
        $this->assertSame($modelConfig, $this->chatflowApp->getModelConfig());
        $this->assertSame($conversationConfig, $this->chatflowApp->getConversationConfig());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatflowApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatflowApp->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->chatflowApp->getCreateTime(), $this->chatflowApp->getUpdateTime());
    }

    public function testChatflowConfigurationEvolution(): void
    {
        // 模拟聊天流配置演进的场景

        // 第一阶段：简单配置
        $this->chatflowApp->setChatflowConfig([
            'flow_type' => 'basic',
            'greeting' => 'Hello!',
        ]);

        $this->chatflowApp->setModelConfig([
            'model' => 'claude-3-haiku',
            'temperature' => 0.5,
        ]);

        // 验证第一阶段
        $chatflowConfig = $this->chatflowApp->getChatflowConfig();
        $this->assertNotNull($chatflowConfig);
        $this->assertSame('basic', $chatflowConfig['flow_type']);

        $modelConfig = $this->chatflowApp->getModelConfig();
        $this->assertNotNull($modelConfig);
        $this->assertSame('claude-3-haiku', $modelConfig['model']);

        // 第二阶段：增强配置
        $this->chatflowApp->setChatflowConfig([
            'flow_type' => 'advanced',
            'greeting' => 'Hello! I\'m your AI assistant.',
            'features' => ['context_aware', 'multi_turn'],
            'personalization' => ['remember_preferences' => true],
        ]);

        $this->chatflowApp->setModelConfig([
            'model' => 'claude-3-sonnet',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'streaming' => true,
        ]);

        $this->chatflowApp->setConversationConfig([
            'session_management' => ['timeout' => 1800],
            'context_retention' => ['messages' => 20],
        ]);

        // 验证第二阶段
        $chatflowConfig = $this->chatflowApp->getChatflowConfig();
        $this->assertNotNull($chatflowConfig);
        $this->assertIsArray($chatflowConfig);
        $this->assertSame('advanced', $chatflowConfig['flow_type']);
        $this->assertArrayHasKey('features', $chatflowConfig);
        $this->assertArrayHasKey('personalization', $chatflowConfig);

        $modelConfig = $this->chatflowApp->getModelConfig();
        $this->assertNotNull($modelConfig);
        $this->assertIsArray($modelConfig);
        $this->assertSame('claude-3-sonnet', $modelConfig['model']);
        $this->assertArrayHasKey('streaming', $modelConfig);

        $conversationConfig = $this->chatflowApp->getConversationConfig();
        $this->assertNotNull($conversationConfig);
        $this->assertIsArray($conversationConfig);
        $this->assertArrayHasKey('session_management', $conversationConfig);
        $this->assertArrayHasKey('context_retention', $conversationConfig);
    }

    public function testChatflowInteractionScenario(): void
    {
        // 模拟实际的聊天流交互场景配置

        // 设置多轮对话配置
        $this->chatflowApp->setName('Customer Support Chatflow');
        $this->chatflowApp->setChatflowConfig([
            'conversation_type' => 'customer_support',
            'initial_greeting' => 'Welcome to our support! How can I help you today?',
            'fallback_responses' => [
                'no_match' => 'I\'m not sure I understand. Could you please rephrase?',
                'escalation' => 'Let me connect you with a human agent.',
            ],
            'intent_recognition' => [
                'enabled' => true,
                'confidence_threshold' => 0.8,
                'supported_intents' => [
                    'technical_issue',
                    'billing_question',
                    'general_inquiry',
                    'complaint',
                ],
            ],
        ]);

        // 设置多模型配置用于不同任务
        $this->chatflowApp->setModelConfig([
            'intent_classifier' => [
                'model' => 'claude-3-haiku',
                'temperature' => 0.1,
                'purpose' => 'Fast intent classification',
            ],
            'response_generator' => [
                'model' => 'claude-3-sonnet',
                'temperature' => 0.6,
                'purpose' => 'Detailed response generation',
            ],
            'escalation_detector' => [
                'model' => 'claude-3-haiku',
                'temperature' => 0.2,
                'purpose' => 'Detect when to escalate to human',
            ],
        ]);

        // 设置对话管理配置
        $this->chatflowApp->setConversationConfig([
            'session_behavior' => [
                'track_user_satisfaction' => true,
                'collect_feedback' => true,
                'save_conversation_summary' => true,
            ],
            'escalation_rules' => [
                'max_bot_attempts' => 3,
                'escalation_keywords' => ['frustrated', 'manager', 'cancel subscription'],
                'auto_escalate_after_minutes' => 15,
            ],
            'context_management' => [
                'remember_user_info' => true,
                'track_conversation_history' => true,
                'context_expiry_hours' => 24,
            ],
        ]);

        // 验证完整的客服聊天流配置
        $this->assertSame('Customer Support Chatflow', $this->chatflowApp->getName());

        $chatflowConfig = $this->chatflowApp->getChatflowConfig();
        $this->assertNotNull($chatflowConfig);
        $this->assertIsArray($chatflowConfig);
        $this->assertSame('customer_support', $chatflowConfig['conversation_type']);
        $this->assertArrayHasKey('intent_recognition', $chatflowConfig);
        $this->assertArrayHasKey('fallback_responses', $chatflowConfig);

        $modelConfig = $this->chatflowApp->getModelConfig();
        $this->assertNotNull($modelConfig);
        $this->assertIsArray($modelConfig);
        $this->assertArrayHasKey('intent_classifier', $modelConfig);
        $this->assertArrayHasKey('response_generator', $modelConfig);
        $this->assertArrayHasKey('escalation_detector', $modelConfig);

        $conversationConfig = $this->chatflowApp->getConversationConfig();
        $this->assertNotNull($conversationConfig);
        $this->assertIsArray($conversationConfig);
        $this->assertArrayHasKey('session_behavior', $conversationConfig);
        $this->assertArrayHasKey('escalation_rules', $conversationConfig);
        $this->assertArrayHasKey('context_management', $conversationConfig);
    }
}
