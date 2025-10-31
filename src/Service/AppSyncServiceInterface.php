<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

interface AppSyncServiceInterface
{
    /**
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function syncApps(?int $instanceId = null, ?int $accountId = null, ?string $appType = null): array;
}
