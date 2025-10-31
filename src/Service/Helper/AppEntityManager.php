<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;

/**
 * 应用实体管理器
 *
 * 负责管理应用实体的创建、查找和持久化
 */
#[WithMonologChannel(channel: 'dify_console_api')]
readonly class AppEntityManager
{
    /**
     * @var array<string, class-string<BaseApp>> 支持的应用类型映射
     */
    private const APP_TYPE_MAPPING = [
        'chat' => ChatAssistantApp::class,
        'agent-chat' => ChatAssistantApp::class,
        'advanced-chat' => ChatAssistantApp::class,
        'completion' => ChatAssistantApp::class,
        'workflow' => WorkflowApp::class,
        'chatflow' => ChatflowApp::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 判断是否为支持的应用类型
     */
    public function isSupportedAppType(mixed $appType): bool
    {
        return is_string($appType) && isset(self::APP_TYPE_MAPPING[$appType]);
    }

    /**
     * 查找或创建应用实体
     *
     * @return array{entity: BaseApp, existing: BaseApp|null}
     */
    public function findOrCreateApp(DifyInstance $instance, DifyAccount $account, string $appId, string $appType): array
    {
        $entityClass = self::APP_TYPE_MAPPING[$appType];
        $repository = $this->entityManager->getRepository($entityClass);

        $existingApp = $repository->findOneBy(
            [
                'difyAppId' => $appId,
                'instance' => $instance,
            ]
        );

        if (null === $existingApp) {
            $newApp = new $entityClass();
            $newApp->setAccount($account);

            return [
                'entity' => $newApp,
                'existing' => null,
            ];
        }

        return [
            'entity' => $existingApp,
            'existing' => $existingApp,
        ];
    }

    /**
     * 持久化应用变更
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                  $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function persistAppChanges(
        BaseApp $app,
        bool $isNewApp,
        string $appType,
        string $appId,
        array $appData,
        array $syncStats,
        SyncStatisticsManager $statsManager,
    ): array {
        try {
            if ($isNewApp) {
                $this->entityManager->persist($app);
                $syncStats = $statsManager->recordAppCreated($syncStats);
            } else {
                $syncStats = $statsManager->recordAppUpdated($syncStats);
            }

            $this->entityManager->flush();
            $syncStats = $statsManager->updateAppTypeStats($syncStats, $appType);
            $this->logAppSyncSuccess($appId, $appData, $appType, $isNewApp);
        } catch (\Exception $e) {
            $this->handleAppPersistError($app, $isNewApp, $appId, $appData, $e);
            throw $e;
        }

        return $syncStats;
    }

    /**
     * 记录应用同步成功
     *
     * @param array<string, mixed> $appData
     */
    private function logAppSyncSuccess(string $appId, array $appData, string $appType, bool $isNewApp): void
    {
        $this->logger->debug(
            '应用同步成功',
            [
                'appId' => $appId,
                'appName' => $appData['name'] ?? 'unknown',
                'appType' => $appType,
                'isNewApp' => $isNewApp,
            ]
        );
    }

    /**
     * 处理应用持久化错误
     *
     * @param array<string, mixed> $appData
     */
    private function handleAppPersistError(BaseApp $app, bool $isNewApp, string $appId, array $appData, \Exception $e): void
    {
        if ($isNewApp && $this->entityManager->contains($app)) {
            $this->entityManager->detach($app);
        }

        $this->logger->error(
            '应用同步失败',
            [
                'appId' => $appId,
                'appName' => $appData['name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]
        );
    }
}
