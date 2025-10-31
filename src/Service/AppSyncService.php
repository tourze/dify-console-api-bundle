<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Service\Helper\AppDataProcessor;
use Tourze\DifyConsoleApiBundle\Service\Helper\AppEntityManager;
use Tourze\DifyConsoleApiBundle\Service\Helper\SiteDataProcessor;
use Tourze\DifyConsoleApiBundle\Service\Helper\SyncStatisticsManager;

/**
 * Dify应用同步服务
 *
 * 负责从Dify Console API同步应用数据到本地数据库
 * 支持按实例、账号、应用类型进行过滤同步
 */
#[WithMonologChannel(channel: 'dify_console_api')]
final readonly class AppSyncService implements AppSyncServiceInterface
{
    public function __construct(
        private DifyClientServiceInterface $difyClient,
        private InstanceManagementServiceInterface $instanceManagement,
        private AccountManagementServiceInterface $accountManagement,
        private AppEntityManager $entityManager,
        private AppDataProcessor $dataProcessor,
        private SiteDataProcessor $siteProcessor,
        private SyncStatisticsManager $statsManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function syncApps(?int $instanceId = null, ?int $accountId = null, ?string $appType = null): array
    {
        $this->logSyncStart($instanceId, $accountId, $appType);
        $syncStats = $this->statsManager->initializeSyncStats();

        try {
            $instanceId = $this->resolveInstanceId($instanceId, $accountId);
            $instances = $this->getInstancesToProcess($instanceId);
            $syncStats = $this->processAllInstances($instances, $accountId, $appType, $syncStats);
            $this->logger->info('Dify应用同步完成', $syncStats);
        } catch (\Exception $e) {
            $syncStats = $this->handleSyncError($e, $syncStats);
            throw $e;
        }

        return $syncStats;
    }

    /**
     * 获取需要处理的实例列表
     *
     * @return DifyInstance[]
     */
    private function getInstancesToProcess(?int $instanceId): array
    {
        if (null !== $instanceId) {
            // 对于指定实例ID，直接查找该实例（无论是否启用）
            $allInstances = $this->instanceManagement->getAllInstances();

            return array_filter($allInstances, fn ($instance) => $instance->getId() === $instanceId);
        }

        return $this->instanceManagement->getEnabledInstances();
    }

    /**
     * 从账户ID获取其关联的实例ID
     */
    private function getInstanceIdFromAccount(int $accountId): ?int
    {
        return $this->findInstanceIdInAccounts($accountId, $this->accountManagement->getEnabledAccounts())
            ?? $this->findInstanceIdInAccounts($accountId, $this->accountManagement->getAllAccounts());
    }

    /**
     * 在账户列表中查找实例ID
     *
     * @param DifyAccount[] $accounts
     */
    private function findInstanceIdInAccounts(int $accountId, array $accounts): ?int
    {
        foreach ($accounts as $account) {
            if ($account->getId() === $accountId) {
                return $account->getInstance()->getId();
            }
        }

        return null;
    }

    /**
     * 获取需要处理的账号列表
     *
     * @return DifyAccount[]
     */
    private function getAccountsToProcess(int $instanceId, ?int $accountId): array
    {
        if (null !== $accountId) {
            $accounts = $this->accountManagement->getEnabledAccounts($instanceId);

            return array_filter($accounts, fn ($account) => $account->getId() === $accountId);
        }

        return $this->accountManagement->getEnabledAccounts($instanceId);
    }

    /**
     * 为指定账号同步应用
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function syncAppsForAccount(
        DifyInstance $instance,
        DifyAccount $account,
        ?string $appType,
        array $syncStats,
    ): array {
        $this->logAccountSyncStart($instance, $account);
        $apps = $this->fetchAccountApps($account, $appType);

        return $this->processAppsForAccount($instance, $account, $apps, $appType, $syncStats);
    }

    /**
     * 记录账号同步开始
     */
    private function logAccountSyncStart(DifyInstance $instance, DifyAccount $account): void
    {
        $this->logger->debug(
            '开始为账号同步应用',
            [
                'instanceId' => $instance->getId(),
                'accountId' => $account->getId(),
                'instanceUrl' => $instance->getBaseUrl(),
                'accountEmail' => $account->getEmail(),
            ]
        );
    }

    /**
     * 获取账号的应用列表
     *
     * @return array<mixed>
     */
    private function fetchAccountApps(DifyAccount $account, ?string $appType): array
    {
        $query = new AppListQuery(mode: $appType);
        $appListResult = $this->difyClient->getApps($account, $query);

        return $appListResult->apps;
    }

    /**
     * 处理账号的所有应用
     *
     * @param  array<mixed>                                                                                                                                                                                                                                              $apps
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processAppsForAccount(
        DifyInstance $instance,
        DifyAccount $account,
        array $apps,
        ?string $appType,
        array $syncStats,
    ): array {
        foreach ($apps as $appData) {
            if (!is_array($appData)) {
                continue;
            }
            /** @var array<string, mixed> $appData */
            $syncStats = $this->processSingleAppData($instance, $account, $appData, $appType, $syncStats);
        }

        return $syncStats;
    }

    /**
     * 处理单个应用数据
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                      $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processSingleAppData(
        DifyInstance $instance,
        DifyAccount $account,
        array $appData,
        ?string $appType,
        array $syncStats,
    ): array {
        //        try {
        if (!$this->shouldProcessApp($appData, $appType)) {
            return $syncStats;
        }

        return $this->syncSingleApp($instance, $account, $appData, $syncStats);
        //        } catch (\Exception $e) {
        //            return $this->handleAppSyncException($instance, $account, $appData, $e, $syncStats);
        //        }
    }

    /**
     * 判断是否应该处理该应用
     *
     * @param array<string, mixed> $appData
     */
    private function shouldProcessApp(array $appData, ?string $appType): bool
    {
        $appMode = $appData['mode'] ?? null;

        return null === $appType || $appMode === $appType;
    }

    /**
     * 同步单个应用
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                  $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function syncSingleApp(
        DifyInstance $instance,
        DifyAccount $account,
        array $appData,
        array $syncStats,
    ): array {
        $validationResult = $this->validateSingleAppData($account, $appData);
        if (null !== $validationResult) {
            return $this->statsManager->mergeSyncErrors($syncStats, $validationResult);
        }

        $appType = $appData['mode'];
        $appId = $appData['id'];

        if (!is_string($appType) || !is_string($appId)) {
            return $syncStats;
        }

        if (!$this->entityManager->isSupportedAppType($appType)) {
            $this->logUnsupportedAppType($appType, $appId, $appData);

            return $syncStats;
        }

        return $this->processSupportedApp($instance, $account, $appData, $appType, $appId, $syncStats);
    }

    /**
     * 验证单个应用数据
     *
     * @param  array<string, mixed> $appData
     * @return array{errors: int, message: string}|null
     */
    private function validateSingleAppData(DifyAccount $account, array $appData): ?array
    {
        $accountId = $account->getId();
        if (null === $accountId) {
            return [
                'errors' => 1,
                'message' => '账号ID不可用',
            ];
        }

        return $this->validateAppData($appData, $accountId);
    }

    /**
     * 处理支持的应用类型
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                  $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processSupportedApp(
        DifyInstance $instance,
        DifyAccount $account,
        array $appData,
        string $appType,
        string $appId,
        array $syncStats,
    ): array {
        $appData = $this->fetchAppDetails($account, $appId, $appData);
        $app = $this->entityManager->findOrCreateApp($instance, $account, $appId, $appType);
        $isNewApp = null === $app['existing'];

        $syncStats = $this->updateAppData($app['entity'], $instance, $appData, $syncStats);

        return $this->entityManager->persistAppChanges($app['entity'], $isNewApp, $appType, $appId, $appData, $syncStats, $this->statsManager);
    }

    /**
     * 验证应用数据
     *
     * @param  array<string, mixed> $appData
     * @return array{errors: int, message: string}|null
     */
    private function validateAppData(array $appData, int $accountId): ?array
    {
        $appType = $appData['mode'] ?? null;
        $appId = $appData['id'] ?? null;

        if (!is_string($appType) || !is_string($appId)) {
            return [
                'errors' => 1,
                'message' => sprintf('应用数据类型错误[账号ID:%d]: appType或appId不是字符串类型', $accountId),
            ];
        }

        return null;
    }

    /**
     * 记录不支持的应用类型
     *
     * @param array<string, mixed> $appData
     */
    private function logUnsupportedAppType(string $appType, string $appId, array $appData): void
    {
        $this->logger->warning(
            '不支持的应用类型',
            [
                'appType' => $appType,
                'appId' => $appId,
                'appName' => $appData['name'] ?? 'unknown',
            ]
        );
    }

    /**
     * 获取应用详情
     *
     * @param  array<string, mixed> $appData
     * @return array<string, mixed>
     */
    private function fetchAppDetails(DifyAccount $account, string $appId, array $appData): array
    {
        try {
            $appDetailResult = $this->difyClient->getAppDetail($account, $appId);

            if ($appDetailResult->success && null !== $appDetailResult->appData) {
                $this->logger->debug(
                    '获取应用详情成功',
                    [
                        'appId' => $appId,
                        'appName' => $appData['name'] ?? 'unknown',
                        'hasSiteData' => isset($appDetailResult->appData['site']),
                    ]
                );

                return $appDetailResult->appData;
            }

            $this->logger->warning(
                '应用详情获取失败',
                [
                    'appId' => $appId,
                    'appName' => $appData['name'] ?? 'unknown',
                    'errorMessage' => $appDetailResult->errorMessage,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->warning(
                '获取应用详情失败，使用列表数据',
                [
                    'appId' => $appId,
                    'appName' => $appData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]
            );
        }

        return $appData;
    }

    /**
     * 更新应用数据
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                  $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function updateAppData(
        BaseApp $app,
        DifyInstance $instance,
        array $appData,
        array $syncStats,
    ): array {
        $this->dataProcessor->updateAppBasicFields($app, $instance, $appData);
        $syncStats = $this->siteProcessor->processAppSiteData($app, $appData, $syncStats);
        $this->dataProcessor->setAppSpecificFields($app, $appData);

        return $syncStats;
    }

    /**
     * 记录同步开始
     */
    private function logSyncStart(?int $instanceId, ?int $accountId, ?string $appType): void
    {
        $this->logger->info(
            '开始同步Dify应用',
            [
                'instanceId' => $instanceId,
                'accountId' => $accountId,
                'appType' => $appType,
            ]
        );
    }

    /**
     * 解析实例ID
     */
    private function resolveInstanceId(?int $instanceId, ?int $accountId): ?int
    {
        if (null === $instanceId && null !== $accountId) {
            return $this->getInstanceIdFromAccount($accountId);
        }

        return $instanceId;
    }

    /**
     * 处理所有实例
     *
     * @param  DifyInstance[]                                                                                                                                                                                                                                        $instances
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processAllInstances(array $instances, ?int $accountId, ?string $appType, array $syncStats): array
    {
        foreach ($instances as $instance) {
            $syncStats = $this->statsManager->recordInstanceProcessed($syncStats);
            $syncStats = $this->processInstanceAccounts($instance, $accountId, $appType, $syncStats);
        }

        return $syncStats;
    }

    /**
     * 处理实例下的所有账号
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processInstanceAccounts(DifyInstance $instance, ?int $accountId, ?string $appType, array $syncStats): array
    {
        $instanceId = $instance->getId();
        if (null === $instanceId) {
            return $syncStats;
        }

        $accounts = $this->getAccountsToProcess($instanceId, $accountId);

        foreach ($accounts as $account) {
            $syncStats = $this->statsManager->recordAccountProcessed($syncStats);
            $syncStats = $this->processSingleAccount($instance, $account, $appType, $syncStats);
        }

        return $syncStats;
    }

    /**
     * 处理单个账号
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function processSingleAccount(DifyInstance $instance, DifyAccount $account, ?string $appType, array $syncStats): array
    {
        try {
            return $this->syncAppsForAccount($instance, $account, $appType, $syncStats);
        } catch (\Exception $e) {
            return $this->handleAccountSyncError($instance, $account, $e, $syncStats);
        }
    }

    /**
     * 处理账号同步错误
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function handleAccountSyncError(DifyInstance $instance, DifyAccount $account, \Exception $e, array $syncStats): array
    {
        $errorMessage = sprintf('账号应用同步失败[账号ID:%d]: %s', $account->getId(), $e->getMessage());
        $syncStats = $this->statsManager->addSyncError($syncStats, $errorMessage);
        $this->logger->error(
            '账号应用同步失败',
            [
                'instanceId' => $instance->getId(),
                'accountId' => $account->getId(),
                'error' => $e->getMessage(),
            ]
        );

        return $syncStats;
    }

    /**
     * 处理同步错误
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function handleSyncError(\Exception $e, array $syncStats): array
    {
        $errorMessage = sprintf('Dify应用同步过程发生错误: %s', $e->getMessage());
        $syncStats = $this->statsManager->addSyncError($syncStats, $errorMessage);
        $this->logger->error(
            'Dify应用同步过程发生错误',
            [
                'error' => $e->getMessage(),
                'stats' => $syncStats,
            ]
        );

        return $syncStats;
    }
}
