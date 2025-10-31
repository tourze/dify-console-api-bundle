<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;

interface DslSyncServiceInterface
{
    /**
     * 同步单个应用的 DSL
     *
     * @return array{success: bool, version?: AppDslVersion|null, isNewVersion: bool, message: string}
     */
    public function syncAppDsl(BaseApp $app, DifyAccount $account): array;

    /**
     * 计算 DSL 内容的哈希值
     *
     * @param array<string, mixed> $dslContent
     */
    public function calculateDslHash(array $dslContent): string;

    /**
     * 获取应用的最新 DSL 版本
     */
    public function getLatestVersion(BaseApp $app): ?AppDslVersion;

    /**
     * 检查是否需要创建新版本
     */
    public function shouldCreateNewVersion(BaseApp $app, string $dslHash): bool;
}
