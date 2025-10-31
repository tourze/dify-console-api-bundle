<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

/**
 * 同步统计管理器
 *
 * 负责管理应用同步过程中的统计数据
 */
readonly class SyncStatisticsManager
{
    /**
     * 初始化同步统计数据
     *
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function initializeSyncStats(): array
    {
        return [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'app_types' => [],
            'error_details' => [],
        ];
    }

    /**
     * 更新应用类型统计
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function updateAppTypeStats(array $syncStats, string $appType): array
    {
        if (!isset($syncStats['app_types'][$appType])) {
            $syncStats['app_types'][$appType] = 0;
        }
        ++$syncStats['app_types'][$appType];

        return $syncStats;
    }

    /**
     * 合并同步错误
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}  $syncStats
     * @param  array{errors: int, message: string}                                                                                                                                                                                                                  $validationResult
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function mergeSyncErrors(array $syncStats, array $validationResult): array
    {
        $syncStats['errors'] += $validationResult['errors'];
        $syncStats['error_details'][] = $validationResult['message'];

        return $syncStats;
    }

    /**
     * 添加同步错误
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function addSyncError(array $syncStats, string $errorMessage): array
    {
        ++$syncStats['errors'];
        $syncStats['error_details'][] = $errorMessage;

        return $syncStats;
    }

    /**
     * 记录应用创建
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function recordAppCreated(array $syncStats): array
    {
        ++$syncStats['created_apps'];
        ++$syncStats['synced_apps'];

        return $syncStats;
    }

    /**
     * 记录应用更新
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function recordAppUpdated(array $syncStats): array
    {
        ++$syncStats['updated_apps'];
        ++$syncStats['synced_apps'];

        return $syncStats;
    }

    /**
     * 记录实例处理
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function recordInstanceProcessed(array $syncStats): array
    {
        ++$syncStats['processed_instances'];

        return $syncStats;
    }

    /**
     * 记录账号处理
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function recordAccountProcessed(array $syncStats): array
    {
        ++$syncStats['processed_accounts'];

        return $syncStats;
    }

    /**
     * 从数组中提取字符串值
     *
     * @param array<string, mixed> $data
     */
    public function extractStringFromArray(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
