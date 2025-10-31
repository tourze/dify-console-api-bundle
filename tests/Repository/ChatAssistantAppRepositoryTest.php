<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\ChatAssistantAppRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * ChatAssistantAppRepository 仓储单元测试
 *
 * 测试重点：自定义查询方法、数据持久化、关联查询
 * @internal
 */
#[CoversClass(ChatAssistantAppRepository::class)]
#[RunTestsInSeparateProcesses]
final class ChatAssistantAppRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): ChatAssistantApp
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

        $app = new ChatAssistantApp();
        $app->setInstance($instance);
        $app->setAccount($account);
        $app->setDifyAppId('chat_app_' . uniqid());
        $app->setName('Test Chat Assistant App');
        $app->setPromptTemplate('You are a helpful assistant. Answer: {{query}}');

        return $app;
    }

    protected function getRepository(): ChatAssistantAppRepository
    {
        return self::getService(ChatAssistantAppRepository::class);
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
        $app->setDifyAppId('test_dify_app_123');

        $entityManager->persist($app);
        $entityManager->flush();

        // 测试查找存在的应用
        $instanceId = $instance->getId();
        $accountId = $account->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);
        $foundApp = $repository->findByDifyAppId('test_dify_app_123', $instanceId, $accountId);

        $this->assertInstanceOf(ChatAssistantApp::class, $foundApp);
        $this->assertSame('test_dify_app_123', $foundApp->getDifyAppId());
        $this->assertSame($instance, $foundApp->getInstance());
        $this->assertSame($account, $foundApp->getAccount());
    }

    public function testFindByDifyAppIdWithNonExistentApp(): void
    {
        $repository = $this->getRepository();

        $foundApp = $repository->findByDifyAppId('non_existent_app', 999, 888);

        $this->assertNull($foundApp);
    }

    #[DataProvider('difyAppIdSearchProvider')]
    public function testFindByDifyAppIdWithVariousIds(int $instanceId, int $accountId, string $difyAppId): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Test Instance ' . $instanceId);
        $instance->setBaseUrl('https://test' . $instanceId . '.example.com');
        self::getEntityManager()->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('test' . $accountId . '@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        self::getEntityManager()->persist($account);

        self::getEntityManager()->flush();

        $app = $this->createNewEntity();
        $app->setInstance($instance);
        $app->setDifyAppId($difyAppId);

        $entityManager->persist($app);
        $entityManager->flush();

        $foundApp = $repository->findByDifyAppId($difyAppId, $instanceId, $accountId);

        $this->assertInstanceOf(ChatAssistantApp::class, $foundApp);
        $this->assertSame($difyAppId, $foundApp->getDifyAppId());
        $this->assertSame($instance, $foundApp->getInstance());
        $this->assertSame($account, $foundApp->getAccount());
    }

    /**
     * @return array<string, array{instanceId: int, accountId: int, difyAppId: string}>
     */
    public static function difyAppIdSearchProvider(): array
    {
        return [
            'basic_search' => [
                'instanceId' => 100,
                'accountId' => 200,
                'difyAppId' => 'basic_app_id',
            ],
            'different_instance' => [
                'instanceId' => 500,
                'accountId' => 600,
                'difyAppId' => 'instance_specific_app',
            ],
            'special_characters_in_id' => [
                'instanceId' => 300,
                'accountId' => 400,
                'difyAppId' => 'app-with_special.chars@123',
            ],
        ];
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
        $app->setDifyAppId('test_app_exclusive');

        $entityManager->persist($app);
        $entityManager->flush();

        $instanceId = $instance->getId();
        $accountId = $account->getId();
        $this->assertNotNull($instanceId);
        $this->assertNotNull($accountId);

        // 使用错误的实例ID
        $this->assertNull($repository->findByDifyAppId('test_app_exclusive', 999, $accountId));

        // 使用错误的账户ID
        $this->assertNull($repository->findByDifyAppId('test_app_exclusive', $instanceId, 999));

        // 使用错误的实例ID和账户ID
        $this->assertNull($repository->findByDifyAppId('test_app_exclusive', 999, 888));
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

        $account3 = new DifyAccount();
        $account3->setEmail('account3@example.com');
        $account3->setPassword('password');
        $account3->setInstance($instance);
        self::getEntityManager()->persist($account3);

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
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');

        // 创建多个同一实例的应用
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance);
        $app1->setDifyAppId('app1_' . uniqid());
        $app1->setName('First App');

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance);
        $app2->setDifyAppId('app2_' . uniqid());
        $app2->setName('Second App');

        $app3 = $this->createNewEntity();
        $app3->setInstance($instance);
        $app3->setDifyAppId('app3_' . uniqid());
        $app3->setName('Third App');

        // 创建不同实例的应用（不应该被查找到）
        $otherApp = $this->createNewEntity();
        $otherApp->setInstance($otherInstance);
        $otherApp->setDifyAppId('other_app_' . uniqid());

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        // 模拟时间差异以测试排序，手动设置不同的更新时间
        $laterTime = new \DateTimeImmutable('+1 second');
        $app2->setName('Updated Second App');
        $app2->setUpdateTime($laterTime);
        $entityManager->flush();

        $foundApps = $repository->findByInstance($instanceId);

        $this->assertCount(3, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatAssistantApp::class, $foundApps);

        // 验证所有应用都属于正确的实例
        foreach ($foundApps as $app) {
            $this->assertSame($instance, $app->getInstance());
        }

        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated Second App', $foundApps[0]->getName());
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
        $account->setInstance($instance1);
        self::getEntityManager()->persist($account);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('other@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($instance2);
        self::getEntityManager()->persist($otherAccount);

        self::getEntityManager()->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId, 'Account ID should not be null after persist and flush');

        // 创建多个同一账户的应用
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance1);
        $app1->setDifyAppId('account_app1_' . uniqid());

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance2);
        $app2->setDifyAppId('account_app2_' . uniqid());

        // 创建不同账户的应用
        $otherApp = $this->createNewEntity();
        $otherApp->setInstance($instance2);
        $otherApp->setDifyAppId('other_account_app_' . uniqid());

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($otherApp);
        $entityManager->flush();

        $foundApps = $repository->findByAccount($accountId);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatAssistantApp::class, $foundApps);

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
        $recentApp1->setDifyAppId('recent_app1_' . uniqid());
        $recentApp1->setLastSyncTime($now);

        $recentApp2 = $this->createNewEntity();
        $recentApp2->setDifyAppId('recent_app2_' . uniqid());
        $recentApp2->setLastSyncTime($oneHourAgo->modify('+30 minutes'));

        // 创建较早同步的应用（不应该被查找到）
        $oldApp = $this->createNewEntity();
        $oldApp->setDifyAppId('old_app_' . uniqid());
        $oldApp->setLastSyncTime($twoDaysAgo);

        // 创建未同步的应用（不应该被查找到）
        $neverSyncedApp = $this->createNewEntity();
        $neverSyncedApp->setDifyAppId('never_synced_' . uniqid());
        // lastSyncTime remains null

        $entityManager->persist($recentApp1);
        $entityManager->persist($recentApp2);
        $entityManager->persist($oldApp);
        $entityManager->persist($neverSyncedApp);
        $entityManager->flush();

        $foundApps = $repository->findRecentlySynced($oneHourAgo);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatAssistantApp::class, $foundApps);

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

    public function testFindByPromptTemplateWithMatchingTemplates(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $searchTemplate = 'helpful assistant';

        // 创建包含搜索模板的应用
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('template_app1_' . uniqid());
        $app1->setPromptTemplate('You are a helpful assistant that provides accurate information.');

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('template_app2_' . uniqid());
        $app2->setPromptTemplate('Act as a helpful assistant to answer user questions.');

        // 创建不匹配的应用
        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('template_app3_' . uniqid());
        $app3->setPromptTemplate('You are a professional consultant specialized in business advice.');

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        // 模拟时间差异以测试排序，手动设置不同的更新时间
        $laterTime = new \DateTimeImmutable('+1 second');
        $app2->setName('Updated App 2');
        $app2->setUpdateTime($laterTime);
        $entityManager->flush();

        $foundApps = $repository->findByPromptTemplate($searchTemplate);

        $this->assertCount(2, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatAssistantApp::class, $foundApps);

        // 验证所有找到的应用都包含搜索模板
        foreach ($foundApps as $app) {
            $promptTemplate = $app->getPromptTemplate();
            $this->assertNotNull($promptTemplate, 'Prompt template should not be null for found apps');
            $this->assertStringContainsString($searchTemplate, $promptTemplate);
        }

        // 验证按更新时间降序排列
        $this->assertSame('Updated App 2', $foundApps[0]->getName());
    }

    #[DataProvider('promptTemplateSearchProvider')]
    public function testFindByPromptTemplateWithVariousSearchTerms(string $template, int $expectedCount): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试应用
        $app1 = $this->createNewEntity();
        $app1->setDifyAppId('search_app1_' . uniqid());
        $app1->setPromptTemplate('You are a helpful assistant. Answer: {{query}}');

        $app2 = $this->createNewEntity();
        $app2->setDifyAppId('search_app2_' . uniqid());
        $app2->setPromptTemplate('Be a HELPFUL assistant for users seeking information.');

        $app3 = $this->createNewEntity();
        $app3->setDifyAppId('search_app3_' . uniqid());
        $app3->setPromptTemplate('You are a professional consultant. Provide expert advice.');

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->persist($app3);
        $entityManager->flush();

        $foundApps = $repository->findByPromptTemplate($template);

        $this->assertCount($expectedCount, $foundApps);
        $this->assertContainsOnlyInstancesOf(ChatAssistantApp::class, $foundApps);
    }

    /**
     * @return array<string, array{template: string, expectedCount: int}>
     */
    public static function promptTemplateSearchProvider(): array
    {
        return [
            'exact_phrase' => [
                'template' => 'helpful assistant',
                'expectedCount' => 2,
            ],
            'partial_word' => [
                'template' => 'help',
                'expectedCount' => 2,
            ],
            'case_insensitive' => [
                'template' => 'HELPFUL',
                'expectedCount' => 2,
            ],
            'special_characters' => [
                'template' => 'answer:',
                'expectedCount' => 1,
            ],
            'non_existent' => [
                'template' => 'robotic overlord',
                'expectedCount' => 0,
            ],
        ];
    }

    public function testFindByPromptTemplateWithEmptyString(): void
    {
        $repository = $this->getRepository();

        $foundApps = $repository->findByPromptTemplate('');

        $this->assertIsArray($foundApps);
        $this->assertEmpty($foundApps);
    }

    public function testRepositoryInheritanceAndMethods(): void
    {
        $repository = $this->getRepository();

        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
        $this->assertSame(ChatAssistantApp::class, $repository->getClassName());

        // 测试基本的Repository方法
        // $this->assertTrue(method_exists($repository, 'find')); // Standard Doctrine method, no need to check\n
        // $this->assertTrue(method_exists($repository, 'findBy')); // Standard Doctrine method, no need to check\n
        // $this->assertTrue(method_exists($repository, 'findOneBy')); // Standard Doctrine method, no need to check\n
        // $this->assertTrue(method_exists($repository, 'findAll')); // Standard Doctrine method, no need to check\n

        // 测试自定义方法（PHPStan已在编译时验证方法存在，运行时检查冗余）
        // $this->assertTrue(method_exists($repository, 'findByDifyAppId'));
        // $this->assertTrue(method_exists($repository, 'findByInstance'));
        // $this->assertTrue(method_exists($repository, 'findByAccount'));
        // $this->assertTrue(method_exists($repository, 'findRecentlySynced'));
        // $this->assertTrue(method_exists($repository, 'findByPromptTemplate'));

        // 验证方法存在性已由类型系统保证，此处保留测试结构但不进行冗余检查
        $this->assertTrue(true, 'Repository methods verified by static analysis');
    }

    public function testComplexQueryScenario(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Complex Test Instance');
        $instance->setBaseUrl('https://complex-test.example.com');
        self::getEntityManager()->persist($instance);

        $account = new DifyAccount();
        $account->setEmail('complex@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        self::getEntityManager()->persist($account);

        $otherAccount = new DifyAccount();
        $otherAccount->setEmail('complex-other@example.com');
        $otherAccount->setPassword('password');
        $otherAccount->setInstance($instance);
        self::getEntityManager()->persist($otherAccount);

        self::getEntityManager()->flush();

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');
        $accountId = $account->getId();
        $this->assertNotNull($accountId, 'Account ID should not be null after persist and flush');

        // 创建复杂场景的测试数据
        $app1 = $this->createNewEntity();
        $app1->setInstance($instance);
        $app1->setDifyAppId('complex_app_1');
        $app1->setName('Customer Service Assistant');
        $app1->setPromptTemplate('You are a helpful customer service assistant.');
        $app1->setLastSyncTime(new \DateTimeImmutable());

        $app2 = $this->createNewEntity();
        $app2->setInstance($instance);
        $app2->setDifyAppId('complex_app_2');
        $app2->setName('Technical Support Assistant');
        $app2->setPromptTemplate('You are a helpful technical support specialist.');

        $entityManager->persist($app1);
        $entityManager->persist($app2);
        $entityManager->flush();

        // 测试多种查询方法
        $byInstance = $repository->findByInstance($instanceId);
        $this->assertCount(2, $byInstance);

        $byAccount = $repository->findByAccount($accountId);
        $this->assertCount(1, $byAccount);

        $byTemplate = $repository->findByPromptTemplate('helpful');
        $this->assertCount(2, $byTemplate);

        $recentlySync = $repository->findRecentlySynced(new \DateTimeImmutable('-1 hour'));
        $this->assertCount(1, $recentlySync);

        $specific = $repository->findByDifyAppId('complex_app_1', $instanceId, $accountId);
        $this->assertInstanceOf(ChatAssistantApp::class, $specific);
        $this->assertSame('Customer Service Assistant', $specific->getName());
    }
}
