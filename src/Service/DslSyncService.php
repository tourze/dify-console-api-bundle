<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;

/**
 * DSL 同步服务
 *
 * 负责处理应用 DSL 的同步、版本管理和差异比较
 */
#[WithMonologChannel(channel: 'dify_console_api')]
final class DslSyncService implements DslSyncServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifyClientServiceInterface $difyClient,
        private readonly AppDslVersionRepository $dslVersionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{success: bool, version?: AppDslVersion|null, isNewVersion: bool, message: string}
     */
    public function syncAppDsl(BaseApp $app, DifyAccount $account): array
    {
        try {
            $appId = $app->getDifyAppId();
            $this->logger->debug(
                '开始同步应用 DSL',
                [
                    'app_id' => $appId,
                    'account_id' => $account->getId(),
                ]
            );

            // 导出 DSL
            $exportResult = $this->difyClient->exportAppDsl($account, $appId);

            if (!$exportResult->success || null === $exportResult->dslContent) {
                return [
                    'success' => false,
                    'isNewVersion' => false,
                    'message' => $exportResult->errorMessage ?? 'DSL 导出失败',
                ];
            }

            $dslContent = $exportResult->dslContent;
            $rawContent = $exportResult->rawContent;
            $dslHash = $this->calculateDslHash($dslContent);

            // 检查是否需要创建新版本
            if (!$this->shouldCreateNewVersion($app, $dslHash)) {
                $latestVersion = $this->getLatestVersion($app);
                $this->logger->debug(
                    'DSL 内容未变化，无需创建新版本',
                    [
                        'app_id' => $appId,
                        'hash' => $dslHash,
                        'latest_version' => $latestVersion?->getVersion(),
                    ]
                );

                return [
                    'success' => true,
                    'version' => $latestVersion,
                    'isNewVersion' => false,
                    'message' => 'DSL 内容未变化',
                ];
            }

            // 创建新版本
            $newVersion = $this->createNewVersion($app, $dslContent, $dslHash, $rawContent);

            $this->logger->info(
                'DSL 同步成功，创建新版本',
                [
                    'app_id' => $appId,
                    'version' => $newVersion->getVersion(),
                    'hash' => $dslHash,
                ]
            );

            return [
                'success' => true,
                'version' => $newVersion,
                'isNewVersion' => true,
                'message' => sprintf('创建新版本 v%d', $newVersion->getVersion()),
            ];
        } catch (\Exception $e) {
            $this->logger->error(
                'DSL 同步失败',
                [
                    'app_id' => $app->getDifyAppId(),
                    'account_id' => $account->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return [
                'success' => false,
                'isNewVersion' => false,
                'message' => 'DSL 同步失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $dslContent
     */
    public function calculateDslHash(array $dslContent): string
    {
        // 确保 JSON 编码的一致性，移除可能影响比较的字段
        $normalizedContent = $this->normalizeDslContent($dslContent);
        $sortedContent = $this->sortArrayRecursively($normalizedContent);
        $jsonString = json_encode($sortedContent, \JSON_UNESCAPED_UNICODE);

        return hash('sha256', false !== $jsonString ? $jsonString : '{}');
    }

    public function getLatestVersion(BaseApp $app): ?AppDslVersion
    {
        return $this->dslVersionRepository->findLatestVersionByApp($app);
    }

    public function shouldCreateNewVersion(BaseApp $app, string $dslHash): bool
    {
        $existingVersion = $this->dslVersionRepository->findByAppAndHash($app, $dslHash);

        return null === $existingVersion;
    }

    /**
     * 创建新的 DSL 版本
     */
    /**
     * 创建新的 DSL 版本
     *
     * @param array<string, mixed> $dslContent
     */
    private function createNewVersion(
        BaseApp $app,
        array $dslContent,
        string $dslHash,
        ?string $rawContent = null,
    ): AppDslVersion {
        $nextVersion = $this->dslVersionRepository->getNextVersionNumber($app);

        $dslVersion = AppDslVersion::create($app, $nextVersion);
        $dslVersion->setDslContent($dslContent);
        $dslVersion->setDslHash($dslHash);
        $dslVersion->setDslRawContent($rawContent);

        $this->entityManager->persist($dslVersion);
        $this->entityManager->flush();

        return $dslVersion;
    }

    /**
     * 规范化 DSL 内容，移除可能影响版本比较的字段
     */
    /**
     * 规范化 DSL 内容，移除可能影响版本比较的字段
     *
     * @param array<string, mixed> $dslContent
     * @return array<string, mixed>
     */
    private function normalizeDslContent(array $dslContent): array
    {
        // 移除时间戳等可能变化但不影响实际配置的字段
        $fieldsToRemove = [
            'created_at',
            'updated_at',
            'id',
        ];

        return $this->removeFields($dslContent, $fieldsToRemove);
    }

    /**
     * 递归移除指定字段
     */
    /**
     * 递归移除指定字段
     *
     * @param array<string, mixed> $data
     * @param array<string> $fieldsToRemove
     * @return array<string, mixed>
     */
    private function removeFields(array $data, array $fieldsToRemove): array
    {
        foreach ($fieldsToRemove as $field) {
            unset($data[$field]);
        }

        foreach ($data as $key => $value) {
            if (is_array($value) && is_string($key)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->removeFields($value, $fieldsToRemove);
            }
        }

        return $data;
    }

    /**
     * 递归排序数组
     */
    /**
     * 递归排序数组
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sortArrayRecursively(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value) && is_string($key)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->sortArrayRecursively($value);
            }
        }

        return $data;
    }
}
