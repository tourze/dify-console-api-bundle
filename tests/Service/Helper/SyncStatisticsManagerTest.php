<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Service\Helper\SyncStatisticsManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncStatisticsManager::class)]
#[RunTestsInSeparateProcesses]
class SyncStatisticsManagerTest extends AbstractIntegrationTestCase
{
    private SyncStatisticsManager $manager;

    protected function onSetUp(): void
    {
        $this->manager = self::getService(SyncStatisticsManager::class);
    }

    public function testInitializeSyncStats(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $this->assertArrayHasKey('processed_instances', $stats);
        $this->assertArrayHasKey('processed_accounts', $stats);
        $this->assertArrayHasKey('synced_apps', $stats);
        $this->assertArrayHasKey('errors', $stats);
        $this->assertSame(0, $stats['processed_instances']);
        $this->assertSame(0, $stats['errors']);
    }

    public function testUpdateAppTypeStats(): void
    {
        $stats = $this->manager->initializeSyncStats();
        $result = $this->manager->updateAppTypeStats($stats, 'chat');

        $this->assertSame(1, $result['app_types']['chat']);
    }

    public function testAddSyncError(): void
    {
        $stats = $this->manager->initializeSyncStats();
        $result = $this->manager->addSyncError($stats, 'Test error');

        $this->assertSame(1, $result['errors']);
        $this->assertContains('Test error', $result['error_details']);
    }

    public function testExtractStringFromArray(): void
    {
        $data = ['key' => 'value', 'number' => 123];

        $this->assertSame('value', $this->manager->extractStringFromArray($data, 'key', 'default'));
        $this->assertSame('default', $this->manager->extractStringFromArray($data, 'number', 'default'));
        $this->assertSame('default', $this->manager->extractStringFromArray($data, 'missing', 'default'));
    }

    public function testMergeSyncErrors(): void
    {
        $stats = $this->manager->initializeSyncStats();
        $validationResult = [
            'errors' => 3,
            'message' => 'Validation failed for account ABC123',
        ];

        $result = $this->manager->mergeSyncErrors($stats, $validationResult);

        $this->assertSame(3, $result['errors']);
        $this->assertContains('Validation failed for account ABC123', $result['error_details']);
        $this->assertCount(1, $result['error_details']);
    }

    public function testMergeSyncErrorsAccumulative(): void
    {
        $stats = $this->manager->initializeSyncStats();
        $stats['errors'] = 2;
        $stats['error_details'] = ['Previous error'];

        $validationResult = [
            'errors' => 3,
            'message' => 'New validation error',
        ];

        $result = $this->manager->mergeSyncErrors($stats, $validationResult);

        $this->assertSame(5, $result['errors']); // 2 + 3
        $this->assertContains('Previous error', $result['error_details']);
        $this->assertContains('New validation error', $result['error_details']);
        $this->assertCount(2, $result['error_details']);
    }

    public function testRecordAppCreated(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAppCreated($stats);

        $this->assertSame(1, $result['created_apps']);
        $this->assertSame(1, $result['synced_apps']);
        $this->assertSame(0, $result['updated_apps']);
    }

    public function testRecordAppCreatedMultiple(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAppCreated($stats);
        $result = $this->manager->recordAppCreated($result);

        $this->assertSame(2, $result['created_apps']);
        $this->assertSame(2, $result['synced_apps']);
    }

    public function testRecordAppUpdated(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAppUpdated($stats);

        $this->assertSame(1, $result['updated_apps']);
        $this->assertSame(1, $result['synced_apps']);
        $this->assertSame(0, $result['created_apps']);
    }

    public function testRecordAppUpdatedMultiple(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAppUpdated($stats);
        $result = $this->manager->recordAppUpdated($result);

        $this->assertSame(2, $result['updated_apps']);
        $this->assertSame(2, $result['synced_apps']);
    }

    public function testRecordInstanceProcessed(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordInstanceProcessed($stats);

        $this->assertSame(1, $result['processed_instances']);
        $this->assertSame(0, $result['processed_accounts']);
    }

    public function testRecordInstanceProcessedMultiple(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordInstanceProcessed($stats);
        $result = $this->manager->recordInstanceProcessed($result);
        $result = $this->manager->recordInstanceProcessed($result);

        $this->assertSame(3, $result['processed_instances']);
    }

    public function testRecordAccountProcessed(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAccountProcessed($stats);

        $this->assertSame(1, $result['processed_accounts']);
        $this->assertSame(0, $result['processed_instances']);
    }

    public function testRecordAccountProcessedMultiple(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->recordAccountProcessed($stats);
        $result = $this->manager->recordAccountProcessed($result);

        $this->assertSame(2, $result['processed_accounts']);
    }

    public function testComplexWorkflow(): void
    {
        $stats = $this->manager->initializeSyncStats();

        // 处理实例和账号
        $stats = $this->manager->recordInstanceProcessed($stats);
        $stats = $this->manager->recordAccountProcessed($stats);

        // 更新应用类型统计
        $stats = $this->manager->updateAppTypeStats($stats, 'chat');
        $stats = $this->manager->updateAppTypeStats($stats, 'workflow');
        $stats = $this->manager->updateAppTypeStats($stats, 'chat');

        // 记录应用操作
        $stats = $this->manager->recordAppCreated($stats);
        $stats = $this->manager->recordAppUpdated($stats);

        // 添加错误
        $stats = $this->manager->addSyncError($stats, 'Connection timeout');
        $validationError = ['errors' => 2, 'message' => 'Invalid data format'];
        $stats = $this->manager->mergeSyncErrors($stats, $validationError);

        // 验证最终状态
        $this->assertSame(1, $stats['processed_instances']);
        $this->assertSame(1, $stats['processed_accounts']);
        $this->assertSame(2, $stats['synced_apps']);
        $this->assertSame(1, $stats['created_apps']);
        $this->assertSame(1, $stats['updated_apps']);
        $this->assertSame(3, $stats['errors']); // 1 from addSyncError + 2 from mergeSyncErrors
        $this->assertSame(2, $stats['app_types']['chat']);
        $this->assertSame(1, $stats['app_types']['workflow']);
        $this->assertCount(2, $stats['error_details']);
        $this->assertContains('Connection timeout', $stats['error_details']);
        $this->assertContains('Invalid data format', $stats['error_details']);
    }

    public function testUpdateAppTypeStatsNewType(): void
    {
        $stats = $this->manager->initializeSyncStats();

        $result = $this->manager->updateAppTypeStats($stats, 'completion');

        $this->assertArrayHasKey('completion', $result['app_types']);
        $this->assertSame(1, $result['app_types']['completion']);
    }

    public function testUpdateAppTypeStatsExistingType(): void
    {
        $stats = $this->manager->initializeSyncStats();
        $stats['app_types']['chat'] = 5;

        $result = $this->manager->updateAppTypeStats($stats, 'chat');

        $this->assertSame(6, $result['app_types']['chat']);
    }
}
