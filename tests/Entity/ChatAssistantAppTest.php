<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * ChatAssistantApp 实体单元测试
 * 测试重点：继承的BaseApp功能、助手配置、提示模板、知识库配置
 * @internal
 */
#[CoversClass(ChatAssistantApp::class)]
class ChatAssistantAppTest extends AbstractEntityTestCase
{
    private ChatAssistantApp $chatAssistantApp;

    protected function createEntity(): object
    {
        return new ChatAssistantApp();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $assistantConfig = ['role' => 'helper', 'personality' => 'friendly'];
        $promptTemplate = 'You are {{role}}. Answer: {{query}}';
        $knowledgeBase = ['sources' => ['docs']];

        return [
            'instance' => ['instance', $instance],
            'difyAppId' => ['difyAppId', 'assistant_app_789'],
            'name' => ['name', 'Test Chat Assistant'],
            'description' => ['description', 'A test chat assistant'],
            'icon' => ['icon', 'assistant_icon.png'],
            'assistantConfig' => ['assistantConfig', $assistantConfig],
            'promptTemplate' => ['promptTemplate', $promptTemplate],
            'knowledgeBase' => ['knowledgeBase', $knowledgeBase],
        ];
    }

    protected function setUp(): void
    {
        $this->chatAssistantApp = new ChatAssistantApp();
    }

    public function testInheritsBaseAppFunctionality(): void
    {
        // 验证继承自BaseApp的时间戳功能
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatAssistantApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatAssistantApp->getUpdateTime());

        // 验证继承自BaseApp的基本字段
        $this->assertNull($this->chatAssistantApp->getId());
        $this->assertNull($this->chatAssistantApp->getDescription());
        $this->assertNull($this->chatAssistantApp->getIcon());
        $this->assertFalse($this->chatAssistantApp->isPublic());
    }

    public function testAssistantConfigDefaultsToNull(): void
    {
        $this->assertNull($this->chatAssistantApp->getAssistantConfig());
    }

    public function testAssistantConfigSetterAndGetter(): void
    {
        $assistantConfig = [
            'personality' => [
                'tone' => 'professional',
                'style' => 'helpful',
                'formality' => 'formal',
            ],
            'capabilities' => [
                'can_search_knowledge_base' => true,
                'can_generate_code' => true,
                'can_analyze_documents' => true,
                'can_create_summaries' => true,
            ],
            'behavior' => [
                'max_response_length' => 2000,
                'response_format' => 'markdown',
                'include_sources' => true,
                'confidence_threshold' => 0.8,
            ],
            'restrictions' => [
                'no_personal_advice' => true,
                'no_financial_advice' => true,
                'cite_sources_required' => true,
            ],
            'greeting' => [
                'message' => 'Hello! I\'m your AI assistant. How can I help you today?',
                'show_capabilities' => true,
                'suggest_topics' => ['General questions', 'Document analysis', 'Code help'],
            ],
        ];

        $beforeUpdate = $this->chatAssistantApp->getUpdateTime();
        $this->chatAssistantApp->setAssistantConfig($assistantConfig);

        $this->assertSame($assistantConfig, $this->chatAssistantApp->getAssistantConfig());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatAssistantApp->getUpdateTime());
    }

    public function testAssistantConfigCanBeNull(): void
    {
        $this->chatAssistantApp->setAssistantConfig(['some' => 'config']);
        $this->chatAssistantApp->setAssistantConfig(null);
        $this->assertNull($this->chatAssistantApp->getAssistantConfig());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([[]])] // empty_config
    public function testAssistantConfigWithVariousStructures(array $config): void
    {
        $this->chatAssistantApp->setAssistantConfig($config);
        $this->assertSame($config, $this->chatAssistantApp->getAssistantConfig());
    }

    public function testBasicAssistantConfig(): void
    {
        $config = ['personality' => 'friendly', 'response_style' => 'conversational'];
        $this->chatAssistantApp->setAssistantConfig($config);
        $this->assertSame($config, $this->chatAssistantApp->getAssistantConfig());
    }

    public function testDetailedPersonalityConfig(): void
    {
        $config = [
            'personality' => [
                'primary_traits' => ['helpful', 'accurate', 'patient'],
                'communication_style' => [
                    'tone' => 'warm but professional',
                    'verbosity' => 'concise',
                    'humor' => 'minimal',
                ],
                'expertise_areas' => [
                    'technology',
                    'business',
                    'education',
                ],
            ],
            'interaction_patterns' => [
                'ask_clarifying_questions' => true,
                'provide_examples' => true,
                'offer_follow_up_suggestions' => true,
            ],
        ];
        $this->chatAssistantApp->setAssistantConfig($config);
        $this->assertSame($config, $this->chatAssistantApp->getAssistantConfig());
    }

    public function testRoleSpecificConfig(): void
    {
        $config = [
            'role' => 'technical_writer',
            'specialization' => [
                'documentation_types' => ['API docs', 'user guides', 'tutorials'],
                'target_audiences' => ['developers', 'end_users', 'administrators'],
                'style_preferences' => [
                    'use_active_voice' => true,
                    'include_code_examples' => true,
                    'structure_with_headings' => true,
                ],
            ],
            'quality_standards' => [
                'fact_check_responses' => true,
                'include_disclaimers' => true,
                'verify_code_syntax' => true,
            ],
        ];
        $this->chatAssistantApp->setAssistantConfig($config);
        $this->assertSame($config, $this->chatAssistantApp->getAssistantConfig());
    }

    public function testMultilingualConfig(): void
    {
        $config = [
            'languages' => [
                'primary' => 'en',
                'supported' => ['en', 'zh', 'es', 'fr'],
                'auto_detect' => true,
            ],
            'localization' => [
                'adapt_examples_to_culture' => true,
                'use_local_date_formats' => true,
                'respect_cultural_norms' => true,
            ],
        ];
        $this->chatAssistantApp->setAssistantConfig($config);
        $this->assertSame($config, $this->chatAssistantApp->getAssistantConfig());
    }

    public function testPromptTemplateDefaultsToNull(): void
    {
        $this->assertNull($this->chatAssistantApp->getPromptTemplate());
    }

    public function testPromptTemplateSetterAndGetter(): void
    {
        $promptTemplate = <<<'PROMPT'
            You are a helpful AI assistant with expertise in {{domain}}.

            Your role is to:
            - Provide accurate and helpful information
            - Ask clarifying questions when needed
            - Cite sources when appropriate
            - Maintain a {{tone}} tone throughout the conversation

            Context about the user:
            {{user_context}}

            Current conversation context:
            {{conversation_history}}

            Knowledge base information:
            {{knowledge_base_context}}

            Please respond to the following query:
            {{user_query}}

            Remember to:
            1. Be concise but thorough
            2. Provide examples when helpful
            3. Suggest related topics if relevant
            4. Always maintain accuracy over speed
            PROMPT;

        $beforeUpdate = $this->chatAssistantApp->getUpdateTime();
        $this->chatAssistantApp->setPromptTemplate($promptTemplate);

        $this->assertSame($promptTemplate, $this->chatAssistantApp->getPromptTemplate());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatAssistantApp->getUpdateTime());
    }

    public function testPromptTemplateCanBeNull(): void
    {
        $this->chatAssistantApp->setPromptTemplate('Some template');
        $this->chatAssistantApp->setPromptTemplate(null);
        $this->assertNull($this->chatAssistantApp->getPromptTemplate());
    }

    #[TestWith(['You are a helpful assistant. Answer: {{query}}'])] // simple_template
    #[TestWith(["You are a helpful assistant.\n\nUser Query: {{query}}\n\nPlease provide a helpful response."])] // multi_line_template
    #[TestWith(['Role: {{role}}\nExpertise: {{expertise}}\nTone: {{tone}}\n\nQuery: {{query}}\nContext: {{context}}'])] // template_with_variables
    public function testPromptTemplateWithVariousFormats(string $template): void
    {
        $this->chatAssistantApp->setPromptTemplate($template);
        $this->assertSame($template, $this->chatAssistantApp->getPromptTemplate());
    }

    public function testDetailedPromptTemplate(): void
    {
        $template = <<<'TEMPLATE'
            ## Assistant Configuration
            - **Role**: {{assistant_role}}
            - **Expertise**: {{expertise_areas}}
            - **Response Style**: {{response_style}}

            ## Current Context
            **User Profile**: {{user_profile}}
            **Session Context**: {{session_context}}
            **Previous Interactions**: {{interaction_history}}

            ## Knowledge Sources
            {{knowledge_sources}}

            ## User Query
            {{user_query}}

            ## Response Guidelines
            1. Provide accurate information
            2. Be helpful and clear
            3. Use appropriate tone: {{tone}}
            4. Include examples if helpful
            5. Suggest follow-up questions

            ## Response
            TEMPLATE;
        $this->chatAssistantApp->setPromptTemplate($template);
        $this->assertSame($template, $this->chatAssistantApp->getPromptTemplate());
    }

    public function testCodeFocusedPromptTemplate(): void
    {
        $template = <<<'TEMPLATE'
            You are a programming assistant specializing in {{programming_language}}.

            **Code Context:**
            ```{{language}}
            {{existing_code}}
            ```

            **User Request:**
            {{user_request}}

            **Available Libraries:**
            {{available_libraries}}

            Please provide:
            1. Clear explanation of the solution
            2. Complete, working code example
            3. Comments explaining key parts
            4. Best practices and potential improvements
            5. Testing suggestions

            **Response:**
            TEMPLATE;
        $this->chatAssistantApp->setPromptTemplate($template);
        $this->assertSame($template, $this->chatAssistantApp->getPromptTemplate());
    }

    public function testKnowledgeBaseDefaultsToNull(): void
    {
        $this->assertNull($this->chatAssistantApp->getKnowledgeBase());
    }

    public function testKnowledgeBaseSetterAndGetter(): void
    {
        $knowledgeBase = [
            'sources' => [
                [
                    'id' => 'kb_1',
                    'name' => 'Product Documentation',
                    'type' => 'documentation',
                    'url' => 'https://docs.example.com',
                    'weight' => 0.8,
                    'enabled' => true,
                ],
                [
                    'id' => 'kb_2',
                    'name' => 'FAQ Database',
                    'type' => 'structured_data',
                    'connection' => 'database://faq.db',
                    'weight' => 0.9,
                    'enabled' => true,
                ],
                [
                    'id' => 'kb_3',
                    'name' => 'Best Practices Guide',
                    'type' => 'document_collection',
                    'path' => '/knowledge/best_practices/',
                    'weight' => 0.7,
                    'enabled' => false,
                ],
            ],
            'search_configuration' => [
                'algorithm' => 'semantic_search',
                'similarity_threshold' => 0.75,
                'max_results' => 5,
                'rerank_results' => true,
            ],
            'integration_settings' => [
                'auto_update_sources' => true,
                'cache_results' => true,
                'cache_ttl' => 3600,
                'fallback_to_general_knowledge' => true,
            ],
            'content_filtering' => [
                'remove_duplicates' => true,
                'confidence_threshold' => 0.6,
                'content_freshness_weight' => 0.2,
            ],
        ];

        $beforeUpdate = $this->chatAssistantApp->getUpdateTime();
        $this->chatAssistantApp->setKnowledgeBase($knowledgeBase);

        $this->assertSame($knowledgeBase, $this->chatAssistantApp->getKnowledgeBase());
        $this->assertGreaterThanOrEqual($beforeUpdate, $this->chatAssistantApp->getUpdateTime());
    }

    public function testKnowledgeBaseCanBeNull(): void
    {
        $this->chatAssistantApp->setKnowledgeBase(['some' => 'config']);
        $this->chatAssistantApp->setKnowledgeBase(null);
        $this->assertNull($this->chatAssistantApp->getKnowledgeBase());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([['source' => 'internal_docs', 'enabled' => true]])] // simple_knowledge_base
    public function testKnowledgeBaseWithVariousStructures(array $config): void
    {
        $this->chatAssistantApp->setKnowledgeBase($config);
        $this->assertSame($config, $this->chatAssistantApp->getKnowledgeBase());
    }

    public function testMultipleSourcesKnowledgeBase(): void
    {
        $config = [
            'sources' => [
                ['name' => 'Documentation', 'type' => 'web'],
                ['name' => 'FAQ', 'type' => 'database'],
                ['name' => 'Tutorials', 'type' => 'files'],
            ],
            'search_limit' => 10,
        ];
        $this->chatAssistantApp->setKnowledgeBase($config);
        $this->assertSame($config, $this->chatAssistantApp->getKnowledgeBase());
    }

    public function testAdvancedKnowledgeBaseConfiguration(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'pinecone',
                'index_name' => 'company_knowledge',
                'dimensions' => 1536,
                'metric' => 'cosine',
            ],
            'embedding_model' => [
                'provider' => 'openai',
                'model' => 'text-embedding-ada-002',
                'chunk_size' => 1000,
                'chunk_overlap' => 200,
            ],
            'retrieval_strategy' => [
                'method' => 'hybrid_search',
                'semantic_weight' => 0.7,
                'keyword_weight' => 0.3,
                'reranking' => true,
            ],
        ];
        $this->chatAssistantApp->setKnowledgeBase($config);
        $this->assertSame($config, $this->chatAssistantApp->getKnowledgeBase());
    }

    public function testDomainSpecificKnowledgeBase(): void
    {
        $config = [
            'domains' => [
                'technical' => [
                    'sources' => ['api_docs', 'code_examples'],
                    'priority' => 'high',
                    'freshness_required' => true,
                ],
                'business' => [
                    'sources' => ['policies', 'procedures'],
                    'priority' => 'medium',
                    'access_control' => 'restricted',
                ],
                'support' => [
                    'sources' => ['tickets', 'resolutions'],
                    'priority' => 'high',
                    'auto_update' => true,
                ],
            ],
            'routing_rules' => [
                'query_classification' => true,
                'domain_detection_confidence' => 0.8,
                'fallback_strategy' => 'search_all_domains',
            ],
        ];
        $this->chatAssistantApp->setKnowledgeBase($config);
        $this->assertSame($config, $this->chatAssistantApp->getKnowledgeBase());
    }

    public function testUpdateTimeChangesOnAllSpecificSetters(): void
    {
        $initialUpdateTime = $this->chatAssistantApp->getUpdateTime();

        // 等待确保时间差异
        usleep(1000);

        // 测试ChatAssistantApp特有的setter都会更新updateTime
        $this->chatAssistantApp->setAssistantConfig(['role' => 'helper']);
        $updateTime1 = $this->chatAssistantApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->chatAssistantApp->setPromptTemplate('You are {{role}}. Answer: {{query}}');
        $updateTime2 = $this->chatAssistantApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);

        usleep(1000);
        $this->chatAssistantApp->setKnowledgeBase(['sources' => ['docs']]);
        $updateTime3 = $this->chatAssistantApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime2, $updateTime3);
    }

    public function testInheritedSettersAlsoUpdateTime(): void
    {
        $initialUpdateTime = $this->chatAssistantApp->getUpdateTime();

        usleep(1000);

        // 测试继承的setter也会更新updateTime
        $this->chatAssistantApp->setName('Test Chat Assistant App');
        $updateTime1 = $this->chatAssistantApp->getUpdateTime();
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

        $this->chatAssistantApp->setInstance($instance);
        $updateTime2 = $this->chatAssistantApp->getUpdateTime();
        $this->assertGreaterThanOrEqual($updateTime1, $updateTime2);
    }

    public function testCompleteChatAssistantAppConfiguration(): void
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
        $this->chatAssistantApp->setInstance($instance);
        $this->chatAssistantApp->setDifyAppId('assistant_app_789');
        $this->chatAssistantApp->setName('Complete Chat Assistant Application');
        $this->chatAssistantApp->setDescription('A fully configured chat assistant application');
        $this->chatAssistantApp->setIcon('assistant_icon.png');
        $this->chatAssistantApp->setIsPublic(true);

        // 设置ChatAssistantApp特有属性
        $assistantConfig = [
            'role' => 'technical_support',
            'personality' => 'helpful_and_patient',
            'capabilities' => ['search_knowledge', 'generate_examples'],
        ];

        $promptTemplate = 'You are a {{role}} assistant. Be {{personality}}. Query: {{query}}';

        $knowledgeBase = [
            'sources' => [
                ['name' => 'Tech Docs', 'type' => 'documentation'],
                ['name' => 'FAQ', 'type' => 'structured'],
            ],
            'search_limit' => 5,
        ];

        $this->chatAssistantApp->setAssistantConfig($assistantConfig);
        $this->chatAssistantApp->setPromptTemplate($promptTemplate);
        $this->chatAssistantApp->setKnowledgeBase($knowledgeBase);

        // 验证所有属性都正确设置
        $this->assertSame($instance, $this->chatAssistantApp->getInstance());
        $this->assertSame('assistant_app_789', $this->chatAssistantApp->getDifyAppId());
        $this->assertSame('Complete Chat Assistant Application', $this->chatAssistantApp->getName());
        $this->assertSame('A fully configured chat assistant application', $this->chatAssistantApp->getDescription());
        $this->assertSame('assistant_icon.png', $this->chatAssistantApp->getIcon());
        $this->assertTrue($this->chatAssistantApp->isPublic());

        $this->assertSame($assistantConfig, $this->chatAssistantApp->getAssistantConfig());
        $this->assertSame($promptTemplate, $this->chatAssistantApp->getPromptTemplate());
        $this->assertSame($knowledgeBase, $this->chatAssistantApp->getKnowledgeBase());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatAssistantApp->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->chatAssistantApp->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->chatAssistantApp->getCreateTime(), $this->chatAssistantApp->getUpdateTime());
    }

    public function testCustomerSupportAssistantScenario(): void
    {
        // 模拟配置客服助手的完整场景

        $this->chatAssistantApp->setName('Customer Support Assistant');
        $this->chatAssistantApp->setDescription('AI assistant for customer support operations');

        // 配置助手特性
        $this->chatAssistantApp->setAssistantConfig([
            'role' => 'customer_support_specialist',
            'personality' => [
                'empathetic' => true,
                'patient' => true,
                'solution_focused' => true,
                'professional' => true,
            ],
            'capabilities' => [
                'handle_complaints' => true,
                'process_refunds' => false, // requires human approval
                'access_order_history' => true,
                'schedule_callbacks' => true,
                'escalate_to_human' => true,
            ],
            'response_guidelines' => [
                'acknowledge_feelings' => true,
                'provide_clear_next_steps' => true,
                'include_contact_information' => true,
                'follow_up_timeline' => true,
            ],
            'escalation_triggers' => [
                'angry_customer' => true,
                'complex_technical_issue' => true,
                'refund_request_over_amount' => 500,
                'legal_threat' => true,
            ],
        ]);

        // 设置提示模板
        $this->chatAssistantApp->setPromptTemplate(<<<'TEMPLATE'
            You are a professional customer support specialist for {{company_name}}.

            **Your Role:**
            - Provide helpful, empathetic customer service
            - Resolve issues efficiently and professionally
            - Escalate complex issues when appropriate

            **Customer Information:**
            - Name: {{customer_name}}
            - Account Type: {{account_type}}
            - Support History: {{support_history}}

            **Current Issue:**
            {{customer_query}}

            **Available Knowledge:**
            {{knowledge_context}}

            **Response Guidelines:**
            1. Acknowledge the customer's concern
            2. Provide clear, actionable solutions
            3. Be empathetic and professional
            4. Offer additional help if needed
            5. Escalate if necessary based on: {{escalation_criteria}}

            Please respond to the customer:
            TEMPLATE);

        // 配置知识库
        $this->chatAssistantApp->setKnowledgeBase([
            'customer_data' => [
                'order_history' => ['enabled' => true, 'real_time' => true],
                'account_information' => ['enabled' => true, 'sensitive_data_mask' => true],
                'previous_interactions' => ['enabled' => true, 'context_window' => 30],
            ],
            'knowledge_sources' => [
                [
                    'name' => 'Product Documentation',
                    'type' => 'documentation',
                    'categories' => ['troubleshooting', 'how_to', 'specifications'],
                    'weight' => 0.9,
                ],
                [
                    'name' => 'Policy Database',
                    'type' => 'structured_data',
                    'categories' => ['refund_policy', 'warranty', 'shipping'],
                    'weight' => 1.0,
                ],
                [
                    'name' => 'Resolution Templates',
                    'type' => 'templates',
                    'categories' => ['common_issues', 'escalation_scripts'],
                    'weight' => 0.8,
                ],
            ],
            'search_strategy' => [
                'prioritize_customer_specific' => true,
                'include_policy_context' => true,
                'check_known_issues' => true,
            ],
        ]);

        // 验证客服助手配置
        $this->assertSame('Customer Support Assistant', $this->chatAssistantApp->getName());

        $assistantConfig = $this->chatAssistantApp->getAssistantConfig();
        $this->assertIsArray($assistantConfig);
        $this->assertArrayHasKey('role', $assistantConfig);
        $this->assertSame('customer_support_specialist', $assistantConfig['role']);
        $this->assertArrayHasKey('personality', $assistantConfig);
        $this->assertIsArray($assistantConfig['personality']);
        $this->assertArrayHasKey('empathetic', $assistantConfig['personality']);
        $this->assertTrue($assistantConfig['personality']['empathetic']);
        $this->assertArrayHasKey('capabilities', $assistantConfig);
        $this->assertIsArray($assistantConfig['capabilities']);
        $this->assertArrayHasKey('handle_complaints', $assistantConfig['capabilities']);
        $this->assertTrue($assistantConfig['capabilities']['handle_complaints']);
        $this->assertArrayHasKey('process_refunds', $assistantConfig['capabilities']);
        $this->assertFalse($assistantConfig['capabilities']['process_refunds']);

        $promptTemplate = $this->chatAssistantApp->getPromptTemplate();
        $this->assertIsString($promptTemplate);
        $this->assertStringContainsString('customer support specialist', $promptTemplate);
        $this->assertStringContainsString('{{customer_name}}', $promptTemplate);
        $this->assertStringContainsString('{{escalation_criteria}}', $promptTemplate);

        $knowledgeBase = $this->chatAssistantApp->getKnowledgeBase();
        $this->assertIsArray($knowledgeBase);
        $this->assertArrayHasKey('customer_data', $knowledgeBase);
        $this->assertArrayHasKey('knowledge_sources', $knowledgeBase);
        $this->assertIsArray($knowledgeBase['knowledge_sources']);
        $this->assertCount(3, $knowledgeBase['knowledge_sources']);
    }

    public function testTechnicalDocumentationAssistantScenario(): void
    {
        // 模拟配置技术文档助手的场景

        $this->chatAssistantApp->setName('Technical Documentation Assistant');
        $this->chatAssistantApp->setAssistantConfig([
            'role' => 'technical_writer',
            'expertise' => ['API documentation', 'code examples', 'developer guides'],
            'output_format' => 'markdown',
            'include_code_examples' => true,
            'verify_code_syntax' => true,
        ]);

        $this->chatAssistantApp->setPromptTemplate(<<<'TEMPLATE'
            You are a technical documentation expert specializing in {{technology_stack}}.

            **Documentation Request:**
            {{user_request}}

            **Available Code Context:**
            ```{{programming_language}}
            {{code_context}}
            ```

            **Style Guide:**
            - Use clear, concise language
            - Include working code examples
            - Provide step-by-step instructions
            - Add troubleshooting tips

            **Reference Materials:**
            {{reference_docs}}

            Please create comprehensive documentation:
            TEMPLATE);

        $this->chatAssistantApp->setKnowledgeBase([
            'code_repositories' => [
                ['name' => 'Main API', 'language' => 'PHP', 'framework' => 'Symfony'],
                ['name' => 'Frontend', 'language' => 'TypeScript', 'framework' => 'React'],
                ['name' => 'Mobile App', 'language' => 'Dart', 'framework' => 'Flutter'],
            ],
            'documentation_standards' => [
                'style_guide' => 'company_style_guide.md',
                'code_standards' => 'coding_conventions.md',
                'examples_template' => 'example_template.md',
            ],
            'reference_materials' => [
                'existing_docs' => true,
                'api_specifications' => true,
                'architecture_diagrams' => true,
            ],
        ]);

        // 验证技术文档助手配置
        $assistantConfig = $this->chatAssistantApp->getAssistantConfig();
        $this->assertIsArray($assistantConfig);
        $this->assertSame('technical_writer', $assistantConfig['role'] ?? null);
        $this->assertTrue($assistantConfig['include_code_examples'] ?? false);
        $this->assertTrue($assistantConfig['verify_code_syntax'] ?? false);

        $knowledgeBase = $this->chatAssistantApp->getKnowledgeBase();
        $this->assertIsArray($knowledgeBase);
        $this->assertArrayHasKey('code_repositories', $knowledgeBase);
        $this->assertIsArray($knowledgeBase['code_repositories']);
        $this->assertCount(3, $knowledgeBase['code_repositories']);
        $this->assertArrayHasKey('documentation_standards', $knowledgeBase);
    }
}
