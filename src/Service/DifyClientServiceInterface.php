<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Tourze\DifyConsoleApiBundle\DTO\AppDetailResult;
use Tourze\DifyConsoleApiBundle\DTO\AppDslExportResult;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\DTO\AppListResult;
use Tourze\DifyConsoleApiBundle\DTO\AuthenticationResult;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;

interface DifyClientServiceInterface
{
    public function login(DifyAccount $account): AuthenticationResult;

    public function getApps(DifyAccount $account, AppListQuery $query): AppListResult;

    public function getAppDetail(DifyAccount $account, string $appId): AppDetailResult;

    public function refreshToken(DifyAccount $account): AuthenticationResult;

    /**
     * 导出应用的 DSL 配置
     */
    public function exportAppDsl(DifyAccount $account, string $appId): AppDslExportResult;
}
