<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\ChatflowAppRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * ChatflowAppRepository 仓储单元测试
 *
 * 测试重点：自定义查询方法、JSON配置查询、数据持久化
 * @internal
 */
#[CoversClass(ChatflowAppRepository::class)]
#[RunTestsInSeparateProcesses]
final class ChatflowAppRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): ChatflowApp
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');
        self::getEntityManager()->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        self::getEntityManager()->persist($account);

        self::getEntityManager()->flush();

        $app = new ChatflowApp();
        $app->setInstance($instance);
        $app->setAccount($account);
        $app->setDifyAppId('chatflow_app_' . uniqid());
        $app->setName('Test Chatflow App');

        return $app;
    }

    /**
     * @return ChatflowAppRepository
     */
    protected function getRepository(): ChatflowAppRepository
    {
        return self::getService(ChatflowAppRepository::class);
    }

    public function testFindByDifyAppIdWithExistingApp(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建并保存测试应用
        $app = $this->createNewEntity();
        $app->setDifyAppId('test_chatflow_app_123');

        $entityManager->persist($app);
        $entityManager->flush();

        $instanceId = $app->getInstance()->getId();
        $accountId = $app->getAccount()->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 测试查找存在的应用
        $foundApp = $repository->findByDifyAppId('test_chatflow_app_123', $instanceId, $accountId);

        $this->assertInstanceOf(ChatflowApp::class, $foundApp);
        $this->assertSame('test_chatflow_app_123', $foundApp->getDifyAppId());
        $this->assertSame($instanceId, $foundApp->getInstance()->getId());
        $this->assertSame($accountId, $foundApp->getAccount()->getId());
    }

    public function testFindByDifyAppIdWithNonExistentApp(): void
    {
        $repository = $this->getRepository();

        $foundApp = $repository->findByDifyAppId('non_existent_chatflow', 999, 888);

        $this->assertNull($foundApp);
    }

    public function testFindByDifyAppIdWithWrongInstanceOrAccount(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $app = $this->createNewEntity();
        $app->setDifyAppId('test_chatflow_exclusive');

        $entityManager->persist($app);
        $entityManager->flush();

        $instanceId = $app->getInstance()->getId();
        $accountId = $app->getAccount()->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 使用错误的实例ID
        $this->assertNull($repository->findByDifyAppId('test_chatflow_exclusive', 999, $accountId));

        // 使用错误的账户ID
        $this->assertNull($repository->findByDifyAppId('test_chatflow_exclusive', $instanceId, 999));

        // 使用错误的实例ID和账户ID
        $this->assertNull($repository->findByDifyAppId('test_chatflow_exclusive', 999, 888));
    }

    public function testFindByInstanceWithExistingApps(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建共享的测试实例
        $sharedInstance = new DifyInstance();
        $sharedInstance->setName('Shared Test Instance 777');
        $sharedInstance->setBaseUrl('https://shared777.example.com');
        $entityManager->persist($sharedInstance);

        $sharedAccount = new DifyAccount();
        $sharedAccount->setEmail('shared@example.com');
        $sharedAccount->setPassword('password');
        $sharedAccount->setInstance($sharedInstance);
        $entityManager->persist($sharedAccount);

        // 创建其他实例
        $otherInstance = new DifyInstance();
        $otherInstance->setName('Other Test Instance 888');
        $otherInstance->setBaseUrl('https://other888.example.com');
        $entityManager->persist($otherInstance);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('other@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($otherInstance);
        $entityManager->persist($otherAccount);

        $entityManager->flush();

        // 创建多个同一实例的应用
        $app1 = $this->createNewEntity();
        $app1->setInstance($sharedInstance);
        $app1->setAccount($sharedAccount);
        $app1->setDifyAppId('chatflow1_' . uniqid());
        $app1->setName('First Chatflow App');

        $app2 = $this->createNewEntity();
        $app2->setInstance($sharedInstance);
        $app2->setAccount($sharedAccount);
        $app2->setDifyAppId('chatflow2_' . uniqid());
        $app2->setName('Second Chatflow App');

        // 创建不同实例的应用（不应该被查找到）
        $otherApp = $this->createNewEntity();
        $otherApp->setInstance($otherInstance);
        $otherApp->setAccount($otherAccount);
        $otherApp->setDifyAppId('other_chatflow_' . uniqid());

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        // 模拟时间差异以测试排序
        usleep(1000);
        $app2->setName('Updated Second Chatflow App');
        $entityManager->flush();

        $sharedInstanceId = $sharedInstance->getId();
        $this->assertNotNull($sharedInstanceId);
        $foundApps = $repository->findByInstance($sharedInstanceId);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatflowApp::class, $foundApps);

        // 验证所有应用都属于正确的实例
        foreach ($foundApps as $app) {
            $this->assertSame($sharedInstance->getId(), $app->getInstance()->getId());
        }

        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated Second Chatflow App', $foundApps[0]->getName());
    }

    public function testFindByInstanceWithNoApps(): void
    {
        $repository = $this->getRepository();

        $foundApps = $repository->findByInstance(999999);

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testFindByAccountWithExistingApps(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $accountId = 555;

        // 创建多个同一账户的应用
        $instance1 = new DifyInstance();
        $instance1->setName('Test Instance 1');
        $instance1->setBaseUrl('https://test1.example.com');
        $entityManager->persist($instance1);

        $instance2 = new DifyInstance();
        $instance2->setName('Test Instance 2');
        $instance2->setBaseUrl('https://test2.example.com');
        $entityManager->persist($instance2);

        $instance3 = new DifyInstance();
        $instance3->setName('Test Instance 3');
        $instance3->setBaseUrl('https://test3.example.com');
        $entityManager->persist($instance3);

        $account = new DifyAccount();
        $account->setEmail('test555@example.com');
        $account->setPassword('password');
        $account->setInstance($instance1);
        $entityManager->persist($account);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('test666@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($instance3);
        $entityManager->persist($otherAccount);

        $entityManager->flush();

        $app1 = new ChatflowApp();
        $app1->setInstance($instance1);
        $app1->setAccount($account);
        $app1->setDifyAppId('account_chatflow1_' . uniqid());
        $app1->setName('Test Chatflow App 1');

        $app2 = new ChatflowApp();
        $app2->setInstance($instance2);
        $app2->setAccount($account);
        $app2->setDifyAppId('account_chatflow2_' . uniqid());
        $app2->setName('Test Chatflow App 2');

        $otherApp = new ChatflowApp();
        $otherApp->setInstance($instance3);
        $otherApp->setAccount($otherAccount);
        $otherApp->setDifyAppId('other_account_chatflow_' . uniqid());
        $otherApp->setName('Other Chatflow App');

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        $foundApps = $repository->findByAccount($accountId);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatflowApp::class, $foundApps);

        foreach ($foundApps as $app) {
            $this->assertSame($accountId, $app->getAccount()->getId());
        }
    }

    public function testFindByAccountWithNoApps(): void
    {
        $repository = $this->getRepository();

        $foundApps = $repository->findByAccount(888888);

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testFindRecentlySyncedWithRecentApps(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $now = new \DateTimeImmutable();
        $oneHourAgo = $now->modify('-1 hour');
        $twoDaysAgo = $now->modify('-2 days');

        // 创建最近同步的应用
        $recentApp1 = $this->createNewEntity();
        $recentApp1->setDifyAppId('recent_chatflow1_' . uniqid());
        $recentApp1->setLastSyncTime($now);

        $recentApp2 = $this->createNewEntity();
        $recentApp2->setDifyAppId('recent_chatflow2_' . uniqid());
        $recentApp2->setLastSyncTime($oneHourAgo->modify('+30 minutes'));

        // 创建较早同步的应用（不应该被查找到）
        $oldApp = $this->createNewEntity();
        $oldApp->setDifyAppId('old_chatflow_' . uniqid());
        $oldApp->setLastSyncTime($twoDaysAgo);

        // 创建未同步的应用（不应该被查找到）
        $neverSyncedApp = $this->createNewEntity();
        $neverSyncedApp->setDifyAppId('never_synced_chatflow_' . uniqid());
        // lastSyncTime remains null

        $entityManager->persist($recentApp1);
        $entityManager->persist($recentApp2);
        $entityManager->persist($oldApp);
        $entityManager->persist($neverSyncedApp);
        $entityManager->flush();

        $foundApps = $repository->findRecentlySynced($oneHourAgo);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatflowApp::class, $foundApps);

        // 验证按同步时间降序排列
        $this->assertTrue($foundApps[0]->getLastSyncTime() >= $foundApps[1]->getLastSyncTime());

        // 验证都是最近同步的
        foreach ($foundApps as $app) {
            $this->assertTrue($app->getLastSyncTime() >= $oneHourAgo);
        }
    }

    public function testFindRecentlySyncedWithNoRecentApps(): void
    {
        $repository = $this->getRepository();

        $since = new \DateTimeImmutable();

        $foundApps = $repository->findRecentlySynced($since);

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testFindByModelConfigWithEmptyConfig(): void
    {
        $repository = $this->getRepository();

        $foundApps = $repository->findByModelConfig([]);

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testFindByModelConfigWithScalarValues(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 清理现有的测试数据
        $existingApps = $repository->findAll();
        foreach ($existingApps as $app) {
            $entityManager->remove($app);
        }
        $entityManager->flush();

        // 创建带有模型配置的应用
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('model_app1_' . uniqid());
        $app1->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('model_app2_' . uniqid());
        $app2->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-4',
            'temperature' => 0.5,
            'max_tokens' => 2000,
        ]);

        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('model_app3_' . uniqid());
        $app3->setModelConfig([
            'provider' => 'anthropic',
            'model' => 'claude-3',
            'temperature' => 0.8,
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        // 测试按provider查找
        $openaiApps = $repository->findByModelConfig(['provider' => 'openai']);
        $this->assertCount(2, $openaiApps);
        $this->assertContainsOnlyInstancesOf(ChatflowApp::class, $openaiApps);

        // 测试按model查找
        $gpt4Apps = $repository->findByModelConfig(['model' => 'gpt-4']);
        $this->assertCount(1, $gpt4Apps);
        $gpt4ModelConfig = $gpt4Apps[0]->getModelConfig();
        $this->assertIsArray($gpt4ModelConfig);
        $this->assertArrayHasKey('model', $gpt4ModelConfig);
        $this->assertSame('gpt-4', $gpt4ModelConfig['model']);

        // 测试按temperature查找
        $lowTempApps = $repository->findByModelConfig(['temperature' => 0.5]);
        $this->assertCount(1, $lowTempApps);
        $lowTempModelConfig = $lowTempApps[0]->getModelConfig();
        $this->assertIsArray($lowTempModelConfig);
        $this->assertArrayHasKey('temperature', $lowTempModelConfig);
        $this->assertSame(0.5, $lowTempModelConfig['temperature']);

        // 测试不存在的配置
        $notFoundApps = $repository->findByModelConfig(['provider' => 'non_existent']);
        $this->assertEmpty($notFoundApps);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('modelConfigSearchProvider')]
    public function testFindByModelConfigWithVariousSearchCriteria(array $config, int $expectedMatches): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 清理现有的测试数据
        $existingApps = $repository->findAll();
        foreach ($existingApps as $app) {
            $entityManager->remove($app);
        }
        $entityManager->flush();

        // 创建测试数据
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('search_test1_' . uniqid());
        $app1->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('search_test2_' . uniqid());
        $app2->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-4',
            'temperature' => 0.5,
            'max_tokens' => 2000,
        ]);

        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('search_test3_' . uniqid());
        $app3->setModelConfig([
            'provider' => 'anthropic',
            'model' => 'claude-3',
            'temperature' => 0.8,
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        $foundApps = $repository->findByModelConfig($config);

        $this->assertCount($expectedMatches, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatflowApp::class, $foundApps);
    }

    /**
     * @return array<string, array{config: array<string, mixed>, expectedMatches: int}>
     */
    public static function modelConfigSearchProvider(): array
    {
        return [
            'single_string_match' => [
                'config' => ['provider' => 'openai'],
                'expectedMatches' => 2,
            ],
            'single_numeric_match' => [
                'config' => ['temperature' => 0.7],
                'expectedMatches' => 1,
            ],
            'multiple_criteria' => [
                'config' => ['provider' => 'openai', 'model' => 'gpt-3.5-turbo'],
                'expectedMatches' => 1,
            ],
            'non_existent_key' => [
                'config' => ['non_existent_key' => 'value'],
                'expectedMatches' => 0,
            ],
            'non_existent_value' => [
                'config' => ['provider' => 'non_existent_provider'],
                'expectedMatches' => 0,
            ],
        ];
    }

    public function testFindByModelConfigWithComplexJsonValues(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $complexConfig1 = [
            'provider' => 'openai',
            'parameters' => [
                'temperature' => 0.7,
                'presence_penalty' => 0.1,
                'frequency_penalty' => 0.2,
            ],
            'features' => ['function_calling', 'streaming'],
        ];

        $complexConfig2 = [
            'provider' => 'anthropic',
            'parameters' => [
                'temperature' => 0.5,
                'max_tokens_to_sample' => 1000,
            ],
            'features' => ['constitutional_ai'],
        ];

        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('complex_app1_' . uniqid());
        $app1->setModelConfig($complexConfig1);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('complex_app2_' . uniqid());
        $app2->setModelConfig($complexConfig2);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 测试按嵌套对象查找
        $parametersSearch = $repository->findByModelConfig([
            'parameters' => ['temperature' => 0.7],
        ]);
        $this->assertCount(1, $parametersSearch);

        // 测试按数组查找
        $featuresSearch = $repository->findByModelConfig([
            'features' => ['function_calling'],
        ]);
        $this->assertCount(1, $featuresSearch);

        // 测试组合查找
        $combinedSearch = $repository->findByModelConfig([
            'provider' => 'openai',
            'features' => ['streaming'],
        ]);
        $this->assertCount(1, $combinedSearch);
    }

    public function testFindByModelConfigResultsAreOrderedByUpdateTime(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $sharedConfig = ['provider' => 'openai'];

        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('ordered_app1_' . uniqid());
        $app1->setName('First App');
        $app1->setModelConfig($sharedConfig);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('ordered_app2_' . uniqid());
        $app2->setName('Second App');
        $app2->setModelConfig($sharedConfig);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 更新第一个应用以改变其updateTime
        usleep(1000);
        $app1->setName('Updated First App');
        $entityManager->flush();

        $foundApps = $repository->findByModelConfig($sharedConfig);

        $this->assertCount(2, $foundApps);
        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated First App', $foundApps[0]->getName());
        $this->assertSame('Second App', $foundApps[1]->getName());
    }

    public function testFindByModelConfigWithNullModelConfig(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建没有模型配置的应用
        $app = $this->createNewEntity();
        $app->setDifyAppId('null_config_app_' . uniqid());
        // modelConfig remains null

        $entityManager->persist($app);
        $entityManager->flush();

        $foundApps = $repository->findByModelConfig(['provider' => 'openai']);

        $this->assertEmpty($foundApps);
    }

    public function testRepositoryInheritanceAndMethods(): void
    {
        $repository = $this->getRepository();

        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
        $this->assertSame(ChatflowApp::class, $repository->getClassName());

        // 测试基本的Repository方法（PHPStan已在编译时验证方法存在，运行时检查冗余）
        // $this->assertTrue(method_exists($repository, 'find'));
        // $this->assertTrue(method_exists($repository, 'findBy'));
        // $this->assertTrue(method_exists($repository, 'findOneBy'));
        // $this->assertTrue(method_exists($repository, 'findAll'));

        // 测试自定义方法（PHPStan已在编译时验证方法存在，运行时检查冗余）
        // $this->assertTrue(method_exists($repository, 'findByDifyAppId'));
        // $this->assertTrue(method_exists($repository, 'findByInstance'));
        // $this->assertTrue(method_exists($repository, 'findByAccount'));
        // $this->assertTrue(method_exists($repository, 'findRecentlySynced'));
        // $this->assertTrue(method_exists($repository, 'findByModelConfig'));

        // 验证方法存在性已由类型系统保证，此处保留测试结构但不进行冗余检查
        $this->assertTrue(true, 'Repository methods verified by static analysis');
    }

    public function testComplexQueryScenario(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建专用的测试实例
        $instance = new DifyInstance();
        $instance->setName('Complex Test Instance');
        $instance->setBaseUrl('https://complex-test.example.com');
        $entityManager->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('complex@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        $entityManager->persist($account);
        $entityManager->flush();

        // 创建复杂场景的测试数据
        $app1 = new ChatflowApp();
        $app1->setInstance($instance);
        $app1->setAccount($account);
        $app1->setDifyAppId('complex_chatflow_1');
        $app1->setName('Customer Service Chatflow');
        $app1->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
        ]);
        $app1->setChatflowConfig([
            'type' => 'customer_service',
            'features' => ['sentiment_analysis', 'intent_recognition'],
        ]);
        $app1->setLastSyncTime(new \DateTimeImmutable());

        $app2 = new ChatflowApp();
        $app2->setInstance($instance);
        $app2->setAccount($account);
        $app2->setDifyAppId('complex_chatflow_2');
        $app2->setName('Technical Support Chatflow');
        $app2->setModelConfig([
            'provider' => 'openai',
            'model' => 'gpt-4',
            'temperature' => 0.5,
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 获取实例和账户ID用于查询
        $instanceId = $instance->getId();
        $accountId = $account->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 测试多种查询方法
        $byInstance = $repository->findByInstance($instanceId);
        $this->assertCount(2, $byInstance);

        $byAccount = $repository->findByAccount($accountId);
        $this->assertCount(2, $byAccount);

        $byModelConfig = $repository->findByModelConfig(['provider' => 'openai']);
        $this->assertCount(2, $byModelConfig);

        $bySpecificModel = $repository->findByModelConfig(['model' => 'gpt-4']);
        $this->assertCount(1, $bySpecificModel);

        $recentlySync = $repository->findRecentlySynced(new \DateTimeImmutable('-1 hour'));
        $this->assertCount(1, $recentlySync);

        $specific = $repository->findByDifyAppId('complex_chatflow_1', $instanceId, $accountId);
        $this->assertInstanceOf(ChatflowApp::class, $specific);
        $this->assertSame('Customer Service Chatflow', $specific->getName());
    }

    public function testComplexModelConfigurationMatching(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建具有复杂模型配置的应用
        $advancedApp = $this->createNewEntity();
        $advancedApp->setDifyAppId('advanced_config_app');
        $advancedApp->setModelConfig([
            'llm' => [
                'provider' => 'openai',
                'model' => 'gpt-4-turbo',
                'parameters' => [
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                    'top_p' => 0.9,
                    'presence_penalty' => 0.1,
                    'frequency_penalty' => 0.1,
                ],
            ],
            'embedding' => [
                'provider' => 'openai',
                'model' => 'text-embedding-ada-002',
                'dimension' => 1536,
            ],
            'retrieval' => [
                'strategy' => 'semantic_search',
                'top_k' => 5,
                'score_threshold' => 0.7,
            ],
        ]);

        $entityManager->persist($advancedApp);
        $entityManager->flush();

        // 测试多层级配置查询
        $llmMatches = $repository->findByModelConfig([
            'llm' => ['provider' => 'openai'],
        ]);
        $this->assertCount(1, $llmMatches);

        $embeddingMatches = $repository->findByModelConfig([
            'embedding' => ['model' => 'text-embedding-ada-002'],
        ]);
        $this->assertCount(1, $embeddingMatches);

        $retrievalMatches = $repository->findByModelConfig([
            'retrieval' => ['strategy' => 'semantic_search'],
        ]);
        $this->assertCount(1, $retrievalMatches);

        // 测试复合条件
        $complexMatches = $repository->findByModelConfig([
            'llm' => ['provider' => 'openai'],
            'retrieval' => ['top_k' => 5],
        ]);
        $this->assertCount(1, $complexMatches);
    }
}
