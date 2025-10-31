<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;
use Tourze\DifyConsoleApiBundle\Service\Helper\AppEntityManager;
use Tourze\DifyConsoleApiBundle\Service\Helper\SyncStatisticsManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AppEntityManager::class)]
#[RunTestsInSeparateProcesses]
class AppEntityManagerTest extends AbstractIntegrationTestCase
{
    private AppEntityManager $manager;

    protected function onSetUp(): void
    {
        $this->manager = self::getService(AppEntityManager::class);
    }

    public function testIsSupportedAppType(): void
    {
        $this->assertTrue($this->manager->isSupportedAppType('chat'));
        $this->assertTrue($this->manager->isSupportedAppType('workflow'));
        $this->assertTrue($this->manager->isSupportedAppType('chatflow'));
        $this->assertTrue($this->manager->isSupportedAppType('agent-chat'));
        $this->assertTrue($this->manager->isSupportedAppType('advanced-chat'));
        $this->assertTrue($this->manager->isSupportedAppType('completion'));
        $this->assertFalse($this->manager->isSupportedAppType('unknown'));
        $this->assertFalse($this->manager->isSupportedAppType(123));
        $this->assertFalse($this->manager->isSupportedAppType(null));
        $this->assertFalse($this->manager->isSupportedAppType([]));
    }

    public function testFindOrCreateAppWithExistingApp(): void
    {
        // 先创建一个现有的应用实体并保存到数据库
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        $existingApp = new ChatAssistantApp();
        $existingApp->setDifyAppId('app-123');
        $existingApp->setInstance($instance);
        $existingApp->setAccount($account);
        $existingApp->setName('Existing App');

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($instance);
        $entityManager->persist($account);
        $entityManager->persist($existingApp);
        $entityManager->flush();

        $appId = 'app-123';
        $appType = 'chat';

        $result = $this->manager->findOrCreateApp($instance, $account, $appId, $appType);

        $this->assertArrayHasKey('entity', $result);
        $this->assertArrayHasKey('existing', $result);
        $this->assertInstanceOf(ChatAssistantApp::class, $result['entity']);
        $this->assertNotNull($result['existing']);
        $this->assertSame($existingApp, $result['entity']);
        $this->assertSame($existingApp, $result['existing']);
    }

    public function testFindOrCreateAppWithNewApp(): void
    {
        $instance = new DifyInstance();
        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        $appId = 'app-456';
        $appType = 'workflow';

        $result = $this->manager->findOrCreateApp($instance, $account, $appId, $appType);

        $this->assertInstanceOf(WorkflowApp::class, $result['entity']);
        $this->assertNull($result['existing']);
    }

    public function testFindOrCreateAppWithChatflowType(): void
    {
        $instance = new DifyInstance();
        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);
        $appId = 'app-789';
        $appType = 'chatflow';

        $result = $this->manager->findOrCreateApp($instance, $account, $appId, $appType);

        $this->assertInstanceOf(ChatflowApp::class, $result['entity']);
        $this->assertNull($result['existing']);
    }

    public function testPersistAppChangesForNewApp(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        $app = new ChatAssistantApp();
        $app->setDifyAppId('app-new');
        $app->setInstance($instance);
        $app->setAccount($account);
        $app->setName('Test App');

        $isNewApp = true;
        $appType = 'chat';
        $appId = 'app-new';
        $appData = ['name' => 'Test App', 'description' => 'Test description'];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($instance);
        $entityManager->persist($account);
        $entityManager->flush();

        $statsManager = self::getService(SyncStatisticsManager::class);

        $result = $this->manager->persistAppChanges($app, $isNewApp, $appType, $appId, $appData, $syncStats, $statsManager);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('created_apps', $result);
        $this->assertArrayHasKey('synced_apps', $result);
        $this->assertArrayHasKey('app_types', $result);
    }

    public function testPersistAppChangesForExistingApp(): void
    {
        $app = new WorkflowApp();
        $isNewApp = false;
        $appType = 'workflow';
        $appId = 'app-existing';
        $appData = ['name' => 'Existing App'];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $statsManager = self::getService(SyncStatisticsManager::class);

        $result = $this->manager->persistAppChanges($app, $isNewApp, $appType, $appId, $appData, $syncStats, $statsManager);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('updated_apps', $result);
        $this->assertArrayHasKey('synced_apps', $result);
        $this->assertArrayHasKey('app_types', $result);
    }

    public function testPersistAppChangesWithException(): void
    {
        $this->expectException(\Exception::class);

        $app = new ChatAssistantApp();
        $isNewApp = true;
        $appType = 'chat';
        $appId = 'app-error';
        $appData = ['name' => 'Error App'];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $statsManager = self::getService(SyncStatisticsManager::class);

        // 这个测试会因为没有有效的数据库连接而抛出异常
        $this->manager->persistAppChanges($app, $isNewApp, $appType, $appId, $appData, $syncStats, $statsManager);
    }

    public function testPersistAppChangesWithExceptionForExistingApp(): void
    {
        $this->expectException(\Exception::class);

        // 创建一个应用实例，但不将其持久化到数据库
        $app = new WorkflowApp();

        // 模拟数据库连接问题，通过关闭 EntityManager 并尝试操作
        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->close();

        $isNewApp = false;
        $appType = 'workflow';
        $appId = 'app-existing-error';
        $appData = ['name' => 'Existing Error App'];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $statsManager = self::getService(SyncStatisticsManager::class);

        // 尝试在关闭的 EntityManager 上执行操作会抛出异常
        $this->manager->persistAppChanges($app, $isNewApp, $appType, $appId, $appData, $syncStats, $statsManager);
    }

    public function testPersistAppChangesWithMissingAppName(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://example.com');

        $account = new DifyAccount();
        $account->setEmail('test@example.com');
        $account->setPassword('password');
        $account->setInstance($instance);

        $app = new ChatAssistantApp();
        $app->setDifyAppId('app-no-name');
        $app->setInstance($instance);
        $app->setAccount($account);
        $app->setName('Default Name'); // 设置默认名称

        $isNewApp = true;
        $appType = 'chat';
        $appId = 'app-no-name';
        $appData = []; // No name provided in appData
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($instance);
        $entityManager->persist($account);
        $entityManager->flush();

        $statsManager = self::getService(SyncStatisticsManager::class);

        $result = $this->manager->persistAppChanges($app, $isNewApp, $appType, $appId, $appData, $syncStats, $statsManager);

        $this->assertIsArray($result);
    }
}
