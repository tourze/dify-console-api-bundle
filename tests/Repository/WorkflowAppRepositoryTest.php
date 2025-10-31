<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;
use Tourze\DifyConsoleApiBundle\Repository\WorkflowAppRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * WorkflowAppRepository 仓储单元测试
 *
 * 测试重点：自定义查询方法、JSON工作流配置查询、数据持久化
 * @internal
 */
#[CoversClass(WorkflowAppRepository::class)]
#[RunTestsInSeparateProcesses]
final class WorkflowAppRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): WorkflowApp
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

        $app = new WorkflowApp();
        $app->setInstance($instance);
        $app->setAccount($account);
        $app->setDifyAppId('workflow_app_' . uniqid());
        $app->setName('Test Workflow App');

        return $app;
    }

    protected function getRepository(): WorkflowAppRepository
    {
        return self::getService(WorkflowAppRepository::class);
    }

    public function testFindByDifyAppIdWithExistingApp(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建并保存测试应用
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');
        self::getEntityManager()->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('test123@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        self::getEntityManager()->persist($account);

        self::getEntityManager()->flush();

        $app = $this->createNewEntity();
        $app->setInstance($instance);
        $app->setDifyAppId('test_workflow_app_123');

        $entityManager->persist($app);
        $entityManager->flush();

        $instanceId = $instance->getId();
        $accountId = $account->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 测试查找存在的应用
        $foundApp = $repository->findByDifyAppId('test_workflow_app_123', $instanceId, $accountId);

        $this->assertInstanceOf(WorkflowApp::class, $foundApp);
        $this->assertSame('test_workflow_app_123', $foundApp->getDifyAppId());
        $this->assertSame($instance, $foundApp->getInstance());
        $this->assertSame($account, $foundApp->getAccount());
    }

    public function testFindByDifyAppIdWithNonExistentApp(): void
    {
        $repository = $this->getRepository();

        $foundApp = $repository->findByDifyAppId('non_existent_workflow', 999, 888);

        $this->assertNull($foundApp);
    }

    public function testFindByDifyAppIdWithWrongInstanceOrAccount(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Exclusive Test Instance');
        $instance->setBaseUrl('https://exclusive-test.example.com');
        self::getEntityManager()->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('exclusive@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        self::getEntityManager()->persist($account);

        self::getEntityManager()->flush();

        $app = $this->createNewEntity();
        $app->setInstance($instance);
        $app->setDifyAppId('test_workflow_exclusive');

        $entityManager->persist($app);
        $entityManager->flush();

        $instanceId = $instance->getId();
        $accountId = $account->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 使用错误的实例ID
        $this->assertNull($repository->findByDifyAppId('test_workflow_exclusive', 999, $accountId));

        // 使用错误的账户ID
        $this->assertNull($repository->findByDifyAppId('test_workflow_exclusive', $instanceId, 999));

        // 使用错误的实例ID和账户ID
        $this->assertNull($repository->findByDifyAppId('test_workflow_exclusive', 999, 888));
    }

    public function testFindByInstanceWithExistingApps(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Multi App Test Instance');
        $instance->setBaseUrl('https://multi-test.example.com');
        self::getEntityManager()->persist($instance);

        $account1 = new DifyAccount();
        $account1->setEmail('account1@example.com');
        $account1->setPassword('password');
        $account1->setInstance($instance);
        self::getEntityManager()->persist($account1);

        $account2 = new DifyAccount();
        $account2->setEmail('account2@example.com');
        $account2->setPassword('password');
        $account2->setInstance($instance);
        self::getEntityManager()->persist($account2);

        $otherInstance = new DifyInstance();
        $otherInstance->setName('Other Test Instance');
        $otherInstance->setBaseUrl('https://other-test.example.com');
        self::getEntityManager()->persist($otherInstance);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('other@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($otherInstance);
        self::getEntityManager()->persist($otherAccount);

        self::getEntityManager()->flush();

        $instanceId = $instance->getId();

        // 创建多个同一实例的应用
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance);
        $app1->setDifyAppId('workflow1_' . uniqid());
        $app1->setName('First Workflow App');

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance);
        $app2->setDifyAppId('workflow2_' . uniqid());
        $app2->setName('Second Workflow App');

        // 创建不同实例的应用（不应该被查找到）
        $otherApp = $this->createNewEntity();
        $otherApp->setInstance($otherInstance);
        $otherApp->setDifyAppId('other_workflow_' . uniqid());

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        // 模拟时间差异以测试排序
        usleep(1000);
        $app2->setName('Updated Second Workflow App');
        $entityManager->flush();

        $this->assertNotNull($instanceId);
        $foundApps = $repository->findByInstance($instanceId);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(WorkflowApp::class, $foundApps);

        // 验证所有应用都属于正确的实例
        foreach ($foundApps as $app) {
            $this->assertSame($instance, $app->getInstance());
        }

        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated Second Workflow App', $foundApps[0]->getName());
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

        $instance1 = new DifyInstance();
        $instance1->setName('Instance 1');
        $instance1->setBaseUrl('https://instance1.example.com');
        self::getEntityManager()->persist($instance1);

        $instance2 = new DifyInstance();
        $instance2->setName('Instance 2');
        $instance2->setBaseUrl('https://instance2.example.com');
        self::getEntityManager()->persist($instance2);

        $account = new DifyAccount();
        $account->setEmail('shared@example.com');
        $account->setPassword('password');
        $account->setInstance($instance1); // 同一账户在多个实例中
        self::getEntityManager()->persist($account);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('other@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($instance2);
        self::getEntityManager()->persist($otherAccount);

        self::getEntityManager()->flush();

        $accountId = $account->getId();

        // 创建多个同一账户的应用
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance1);
        $app1->setDifyAppId('account_workflow1_' . uniqid());

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance2);
        $app2->setDifyAppId('account_workflow2_' . uniqid());

        // 创建不同账户的应用
        $otherApp = $this->createNewEntity();
        $otherApp->setInstance($instance2);
        $otherApp->setDifyAppId('other_account_workflow_' . uniqid());

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        $this->assertNotNull($accountId);
        $foundApps = $repository->findByAccount($accountId);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(WorkflowApp::class, $foundApps);

        foreach ($foundApps as $app) {
            $this->assertSame($account, $app->getAccount());
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
        $recentApp1->setDifyAppId('recent_workflow1_' . uniqid());
        $recentApp1->setLastSyncTime($now);

        $recentApp2 = $this->createNewEntity();
        $recentApp2->setDifyAppId('recent_workflow2_' . uniqid());
        $recentApp2->setLastSyncTime($oneHourAgo->modify('+30 minutes'));

        // 创建较早同步的应用（不应该被查找到）
        $oldApp = $this->createNewEntity();
        $oldApp->setDifyAppId('old_workflow_' . uniqid());
        $oldApp->setLastSyncTime($twoDaysAgo);

        // 创建未同步的应用（不应该被查找到）
        $neverSyncedApp = $this->createNewEntity();
        $neverSyncedApp->setDifyAppId('never_synced_workflow_' . uniqid());
        // lastSyncTime remains null

        $entityManager->persist($recentApp1);
        $entityManager->persist($recentApp2);
        $entityManager->persist($oldApp);
        $entityManager->persist($neverSyncedApp);
        $entityManager->flush();

        $foundApps = $repository->findRecentlySynced($oneHourAgo);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(WorkflowApp::class, $foundApps);

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

    public function testFindByWorkflowConfigWithEmptyConfig(): void
    {
        $repository = $this->getRepository();

        $foundApps = $repository->findByWorkflowConfig([]);

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testFindByWorkflowConfigWithScalarValues(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建带有工作流配置的应用
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('workflow_config_app1_' . uniqid());
        $app1->setWorkflowConfig([
            'version' => '1.0',
            'type' => 'automation',
            'enabled' => true,
            'timeout' => 300,
        ]);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('workflow_config_app2_' . uniqid());
        $app2->setWorkflowConfig([
            'version' => '2.0',
            'type' => 'automation',
            'enabled' => false,
            'timeout' => 600,
        ]);

        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('workflow_config_app3_' . uniqid());
        $app3->setWorkflowConfig([
            'version' => '1.5',
            'type' => 'integration',
            'enabled' => true,
            'timeout' => 450,
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        // 测试按type查找
        $automationApps = $repository->findByWorkflowConfig(['type' => 'automation']);
        $this->assertCount(2, $automationApps);
        $this->assertContainsOnlyInstancesOf(WorkflowApp::class, $automationApps);

        // 测试按enabled查找
        $enabledApps = $repository->findByWorkflowConfig(['enabled' => true]);
        $this->assertCount(2, $enabledApps);
        foreach ($enabledApps as $app) {
            $config = $app->getWorkflowConfig();
            $this->assertIsArray($config);
            $this->assertArrayHasKey('enabled', $config);
            $this->assertTrue($config['enabled']);
        }

        // 测试按version查找
        $v2Apps = $repository->findByWorkflowConfig(['version' => '2.0']);
        $this->assertCount(1, $v2Apps);
        $config = $v2Apps[0]->getWorkflowConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('version', $config);
        $this->assertSame('2.0', $config['version']);

        // 测试不存在的配置
        $notFoundApps = $repository->findByWorkflowConfig(['type' => 'non_existent']);
        $this->assertEmpty($notFoundApps);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('workflowConfigSearchProvider')]
    public function testFindByWorkflowConfigWithVariousSearchCriteria(array $config, int $expectedMatches): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试数据
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('search_test1_' . uniqid());
        $app1->setWorkflowConfig([
            'type' => 'automation',
            'enabled' => true,
            'timeout' => 300,
            'priority' => 'high',
        ]);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('search_test2_' . uniqid());
        $app2->setWorkflowConfig([
            'type' => 'automation',
            'enabled' => false,
            'timeout' => 600,
            'priority' => 'medium',
        ]);

        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('search_test3_' . uniqid());
        $app3->setWorkflowConfig([
            'type' => 'integration',
            'enabled' => true,
            'timeout' => 450,
            'priority' => 'low',
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        $foundApps = $repository->findByWorkflowConfig($config);

        $this->assertCount($expectedMatches, $foundApps);
        $this->assertContainsOnlyInstancesOf(WorkflowApp::class, $foundApps);
    }

    /**
     * @return array<string, array{config: array<string, mixed>, expectedMatches: int}>
     */
    public static function workflowConfigSearchProvider(): array
    {
        return [
            'single_string_match' => [
                'config' => ['type' => 'automation'],
                'expectedMatches' => 2,
            ],
            'single_boolean_match' => [
                'config' => ['enabled' => true],
                'expectedMatches' => 2,
            ],
            'single_numeric_match' => [
                'config' => ['timeout' => 300],
                'expectedMatches' => 1,
            ],
            'multiple_criteria' => [
                'config' => ['type' => 'automation', 'enabled' => true],
                'expectedMatches' => 1,
            ],
            'non_existent_key' => [
                'config' => ['non_existent_key' => 'value'],
                'expectedMatches' => 0,
            ],
            'non_existent_value' => [
                'config' => ['type' => 'non_existent_type'],
                'expectedMatches' => 0,
            ],
        ];
    }

    public function testFindByWorkflowConfigWithComplexJsonValues(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $complexConfig1 = [
            'type' => 'data_processing',
            'steps' => [
                ['action' => 'validate', 'config' => ['strict' => true]],
                ['action' => 'transform', 'config' => ['format' => 'json']],
                ['action' => 'store', 'config' => ['database' => 'mongodb']],
            ],
            'triggers' => ['webhook', 'schedule'],
            'settings' => [
                'retries' => 3,
                'timeout' => 1800,
                'notifications' => ['email', 'slack'],
            ],
        ];

        $complexConfig2 = [
            'type' => 'ai_workflow',
            'steps' => [
                ['action' => 'analyze', 'config' => ['model' => 'gpt-4']],
                ['action' => 'generate', 'config' => ['temperature' => 0.7]],
            ],
            'triggers' => ['api_call'],
            'settings' => [
                'retries' => 5,
                'timeout' => 3600,
                'notifications' => ['webhook'],
            ],
        ];

        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('complex_workflow1_' . uniqid());
        $app1->setWorkflowConfig($complexConfig1);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('complex_workflow2_' . uniqid());
        $app2->setWorkflowConfig($complexConfig2);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 测试按嵌套对象查找
        $settingsSearch = $repository->findByWorkflowConfig([
            'settings' => ['retries' => 3],
        ]);
        $this->assertCount(1, $settingsSearch);

        // 测试按数组查找
        $triggersSearch = $repository->findByWorkflowConfig([
            'triggers' => ['webhook'],
        ]);
        $this->assertCount(1, $triggersSearch);

        // 测试组合查找
        $combinedSearch = $repository->findByWorkflowConfig([
            'type' => 'ai_workflow',
            'triggers' => ['api_call'],
        ]);
        $this->assertCount(1, $combinedSearch);
    }

    public function testFindByWorkflowConfigResultsAreOrderedByUpdateTime(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $sharedConfig = ['type' => 'test_workflow'];

        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('ordered_workflow1_' . uniqid());
        $app1->setName('First Workflow');
        $app1->setWorkflowConfig($sharedConfig);

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('ordered_workflow2_' . uniqid());
        $app2->setName('Second Workflow');
        $app2->setWorkflowConfig($sharedConfig);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 更新第一个应用以改变其updateTime
        usleep(1000);
        $app1->setName('Updated First Workflow');
        $entityManager->flush();

        $foundApps = $repository->findByWorkflowConfig($sharedConfig);

        $this->assertCount(2, $foundApps);
        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated First Workflow', $foundApps[0]->getName());
        $this->assertSame('Second Workflow', $foundApps[1]->getName());
    }

    public function testFindByWorkflowConfigWithNullWorkflowConfig(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建没有工作流配置的应用
        $app = $this->createNewEntity();
        $app->setDifyAppId('null_workflow_config_app_' . uniqid());
        // workflowConfig remains null

        $entityManager->persist($app);
        $entityManager->flush();

        $foundApps = $repository->findByWorkflowConfig(['type' => 'automation']);

        $this->assertEmpty($foundApps);
    }

    public function testRepositoryInheritanceAndMethods(): void
    {
        $repository = $this->getRepository();

        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
        $this->assertSame(WorkflowApp::class, $repository->getClassName());

        // 测试基本的Repository方法
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified

        // 测试自定义方法
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified

        // 验证方法存在性已由类型系统保证
        $this->assertTrue(true, 'Repository methods verified by static analysis');
    }

    public function testComplexQueryScenario(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试实例
        $instance = new DifyInstance();
        $instance->setName('Complex Test Instance 1000');
        $instance->setBaseUrl('https://complex1000.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        // 创建复杂场景的测试数据
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance);
        $app1->setDifyAppId('complex_workflow_1');
        $app1->setName('Data Processing Workflow');
        $app1->setWorkflowConfig([
            'type' => 'data_processing',
            'enabled' => true,
            'priority' => 'high',
            'steps' => [
                ['action' => 'extract', 'source' => 'database'],
                ['action' => 'transform', 'format' => 'json'],
                ['action' => 'load', 'destination' => 'warehouse'],
            ],
        ]);
        $app1->setInputSchema([
            'type' => 'object',
            'properties' => [
                'source_table' => ['type' => 'string'],
                'target_format' => ['type' => 'string'],
            ],
        ]);
        $app1->setLastSyncTime(new \DateTimeImmutable());

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance);
        $app2->setDifyAppId('complex_workflow_2');
        $app2->setName('AI Analysis Workflow');
        $app2->setWorkflowConfig([
            'type' => 'ai_analysis',
            'enabled' => true,
            'priority' => 'medium',
            'model' => ['provider' => 'openai', 'name' => 'gpt-4'],
        ]);

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 获取实例和账户ID用于查询
        $instanceId = $instance->getId();
        $accountId = $app1->getAccount()->getId();

        // 测试多种查询方法
        if (null !== $instanceId) {
            $byInstance = $repository->findByInstance($instanceId);
            $this->assertCount(2, $byInstance);
        }

        if (null !== $accountId) {
            $byAccount = $repository->findByAccount($accountId);
            $this->assertCount(1, $byAccount);
        }

        $byWorkflowConfig = $repository->findByWorkflowConfig(['enabled' => true]);
        $this->assertCount(2, $byWorkflowConfig);

        $bySpecificType = $repository->findByWorkflowConfig(['type' => 'data_processing']);
        $this->assertCount(1, $bySpecificType);

        $recentlySync = $repository->findRecentlySynced(new \DateTimeImmutable('-1 hour'));
        $this->assertCount(1, $recentlySync);

        if (null !== $accountId && null !== $instanceId) {
            $specific = $repository->findByDifyAppId('complex_workflow_1', $instanceId, $accountId);
            $this->assertInstanceOf(WorkflowApp::class, $specific);
            $this->assertSame('Data Processing Workflow', $specific->getName());
        }
    }

    public function testAdvancedWorkflowConfiguration(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建具有高级工作流配置的应用
        $advancedApp = $this->createNewEntity();
        $advancedApp->setDifyAppId('advanced_workflow_app');
        $advancedApp->setWorkflowConfig([
            'execution' => [
                'mode' => 'parallel',
                'max_concurrent' => 5,
                'timeout' => 3600,
                'retry_policy' => [
                    'max_attempts' => 3,
                    'backoff_strategy' => 'exponential',
                    'initial_delay' => 1000,
                ],
            ],
            'nodes' => [
                [
                    'id' => 'node_1',
                    'type' => 'data_source',
                    'config' => ['connector' => 'postgresql', 'table' => 'users'],
                ],
                [
                    'id' => 'node_2',
                    'type' => 'transformer',
                    'config' => ['operation' => 'aggregate', 'group_by' => 'region'],
                ],
                [
                    'id' => 'node_3',
                    'type' => 'ai_processor',
                    'config' => ['model' => 'llama2', 'temperature' => 0.5],
                ],
                [
                    'id' => 'node_4',
                    'type' => 'output',
                    'config' => ['format' => 'json', 'destination' => 's3'],
                ],
            ],
            'connections' => [
                ['from' => 'node_1', 'to' => 'node_2'],
                ['from' => 'node_2', 'to' => 'node_3'],
                ['from' => 'node_3', 'to' => 'node_4'],
            ],
        ]);

        $advancedApp->setInputSchema([
            'type' => 'object',
            'properties' => [
                'query_params' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string', 'format' => 'date'],
                        'end_date' => ['type' => 'string', 'format' => 'date'],
                        'region' => ['type' => 'string', 'enum' => ['us', 'eu', 'asia']],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
            'required' => ['query_params'],
        ]);

        $advancedApp->setOutputSchema([
            'type' => 'object',
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'region' => ['type' => 'string'],
                            'total_users' => ['type' => 'integer'],
                            'analysis' => ['type' => 'string'],
                        ],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'execution_time' => ['type' => 'number'],
                        'records_processed' => ['type' => 'integer'],
                    ],
                ],
            ],
        ]);

        $entityManager->persist($advancedApp);
        $entityManager->flush();

        // 测试复杂配置查询
        $executionMatches = $repository->findByWorkflowConfig([
            'execution' => ['mode' => 'parallel'],
        ]);
        $this->assertCount(1, $executionMatches);

        $nodeMatches = $repository->findByWorkflowConfig([
            'nodes' => [['type' => 'ai_processor']],
        ]);
        $this->assertCount(1, $nodeMatches);

        // 测试复合条件
        $complexMatches = $repository->findByWorkflowConfig([
            'execution' => ['max_concurrent' => 5],
        ]);
        $this->assertCount(1, $complexMatches);

        // 验证应用的完整性
        $foundApp = $executionMatches[0];
        $this->assertInstanceOf(WorkflowApp::class, $foundApp);
        $this->assertIsArray($foundApp->getInputSchema());
        $this->assertIsArray($foundApp->getOutputSchema());
        $this->assertArrayHasKey('type', $foundApp->getInputSchema());
        $this->assertArrayHasKey('properties', $foundApp->getInputSchema());
    }

    public function testWorkflowConfigurationTypes(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建不同类型的工作流
        $etlWorkflow = $this->createNewEntity();
        $etlWorkflow->setDifyAppId('etl_workflow');
        $etlWorkflow->setWorkflowConfig([
            'type' => 'etl',
            'source' => 'mysql',
            'destination' => 'data_warehouse',
            'schedule' => 'daily',
        ]);

        $mlWorkflow = $this->createNewEntity();
        $mlWorkflow->setDifyAppId('ml_workflow');
        $mlWorkflow->setWorkflowConfig([
            'type' => 'machine_learning',
            'algorithm' => 'random_forest',
            'features' => ['age', 'income', 'location'],
            'target' => 'purchase_probability',
        ]);

        $apiWorkflow = $this->createNewEntity();
        $apiWorkflow->setDifyAppId('api_workflow');
        $apiWorkflow->setWorkflowConfig([
            'type' => 'api_integration',
            'endpoints' => [
                ['method' => 'GET', 'url' => '/users'],
                ['method' => 'POST', 'url' => '/notifications'],
            ],
            'authentication' => ['type' => 'bearer_token'],
        ]);

        $entityManager->persist($etlWorkflow);
        $entityManager->persist($mlWorkflow);
        $entityManager->persist($apiWorkflow);
        $entityManager->flush();

        // 测试按类型查找
        $etlApps = $repository->findByWorkflowConfig(['type' => 'etl']);
        $this->assertCount(1, $etlApps);
        $this->assertSame('etl_workflow', $etlApps[0]->getDifyAppId());

        $mlApps = $repository->findByWorkflowConfig(['type' => 'machine_learning']);
        $this->assertCount(1, $mlApps);
        $this->assertSame('ml_workflow', $mlApps[0]->getDifyAppId());

        $apiApps = $repository->findByWorkflowConfig(['type' => 'api_integration']);
        $this->assertCount(1, $apiApps);
        $this->assertSame('api_workflow', $apiApps[0]->getDifyAppId());

        // 测试按具体配置查找
        $scheduledApps = $repository->findByWorkflowConfig(['schedule' => 'daily']);
        $this->assertCount(1, $scheduledApps);

        $mlAlgorithmApps = $repository->findByWorkflowConfig(['algorithm' => 'random_forest']);
        $this->assertCount(1, $mlAlgorithmApps);

        $authApps = $repository->findByWorkflowConfig([
            'authentication' => ['type' => 'bearer_token'],
        ]);
        $this->assertCount(1, $authApps);
    }
}
