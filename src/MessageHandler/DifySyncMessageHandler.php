<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\MessageHandler;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;

/**
 * Dify同步消息处理器
 *
 * 异步处理Dify应用同步任务
 */
#[AsMessageHandler]
#[WithMonologChannel(channel: 'dify_console_api')]
final class DifySyncMessageHandler
{
    public function __construct(
        private readonly AppSyncServiceInterface $appSyncService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DifySyncMessage $message): void
    {
        $messageId = $message->getMessageId();
        $startTime = microtime(true);

        $this->logger->info(
            '开始处理Dify同步消息',
            [
                'message_id' => $messageId,
                'instance_id' => $message->instanceId,
                'account_id' => $message->accountId,
                'app_type' => $message->appType,
                'scope' => $message->getScopeDescription(),
                'message_data' => $message->toArray(),
            ]
        );

        try {
            // 执行同步操作
            $syncStats = $this->appSyncService->syncApps(
                $message->instanceId,
                $message->accountId,
                $message->appType
            );

            $processingTime = microtime(true) - $startTime;

            // 记录成功日志
            $this->logger->info(
                'Dify同步消息处理成功',
                [
                    'message_id' => $messageId,
                    'processing_time' => $processingTime,
                    'sync_stats' => $syncStats,
                    'scope' => $message->getScopeDescription(),
                ]
            );

            // 检查是否有错误
            if (isset($syncStats['errors']) && $syncStats['errors'] > 0) {
                $this->logger->warning(
                    '同步过程中发生了错误',
                    [
                        'message_id' => $messageId,
                        'error_count' => $syncStats['errors'],
                        'sync_stats' => $syncStats,
                    ]
                );
            }

            // 记录成功指标
            $this->recordSuccessMetrics($message, $syncStats, $processingTime);
        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;

            $this->logger->error(
                'Dify同步消息处理失败',
                [
                    'message_id' => $messageId,
                    'instance_id' => $message->instanceId,
                    'account_id' => $message->accountId,
                    'app_type' => $message->appType,
                    'scope' => $message->getScopeDescription(),
                    'processing_time' => $processingTime,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            // 记录失败指标
            $this->recordFailureMetrics($message, $e, $processingTime);

            // 重新抛出异常让 Messenger 处理重试
            throw $e;
        }
    }

    /**
     * 记录成功指标
     *
     * @param array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     */
    private function recordSuccessMetrics(
        DifySyncMessage $message,
        array $syncStats,
        float $processingTime,
    ): void {
        // 这里可以发送指标到监控系统（如 Prometheus、StatsD 等）
        $this->logger->debug(
            '记录同步成功指标',
            [
                'message_id' => $message->getMessageId(),
                'scope' => $message->getScopeDescription(),
                'metrics' => [
                    'processed_instances' => $syncStats['processed_instances'] ?? 0,
                    'processed_accounts' => $syncStats['processed_accounts'] ?? 0,
                    'synced_apps' => $syncStats['synced_apps'] ?? 0,
                    'created_apps' => $syncStats['created_apps'] ?? 0,
                    'updated_apps' => $syncStats['updated_apps'] ?? 0,
                    'errors' => $syncStats['errors'] ?? 0,
                    'processing_time_seconds' => $processingTime,
                ],
            ]
        );

        // 示例：如果有监控系统，可以在这里发送指标
        // $this->metricsCollector->increment('dify_sync.success', [
        //     'instance_id' => $message->instanceId,
        //     'app_type' => $message->appType,
        // ]);
        // $this->metricsCollector->timing('dify_sync.duration', $processingTime);
    }

    /**
     * 记录失败指标
     */
    private function recordFailureMetrics(
        DifySyncMessage $message,
        \Exception $exception,
        float $processingTime,
    ): void {
        // 这里可以发送指标到监控系统
        $this->logger->debug(
            '记录同步失败指标',
            [
                'message_id' => $message->getMessageId(),
                'scope' => $message->getScopeDescription(),
                'error_type' => get_class($exception),
                'processing_time_seconds' => $processingTime,
            ]
        );

        // 示例：如果有监控系统，可以在这里发送指标
        // $this->metricsCollector->increment('dify_sync.failure', [
        //     'instance_id' => $message->instanceId,
        //     'app_type' => $message->appType,
        //     'error_type' => get_class($exception),
        // ]);
    }
}
