<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Command;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;

#[AsCommand(
    name: self::NAME,
    description: '同步所有Chat类型的Dify应用（chat, completion, agent-chat）'
)]
#[WithMonologChannel(channel: 'dify_console_api')]
class DifyChatAppsSyncCommand extends Command
{
    public const NAME = 'dify:sync:chat-apps';

    /**
     * @var array<string> 聊天类应用类型
     */
    private const CHAT_APP_TYPES = ['chat', 'completion', 'agent-chat'];

    /**
     * @var array<string, string> 应用类型名称映射
     */
    private const APP_TYPE_LABELS = [
        'chat' => '聊天',
        'completion' => '补全',
        'agent-chat' => '智能体聊天',
    ];

    public function __construct(
        private readonly AppSyncServiceInterface $appSyncService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                '限制同步指定实例ID的应用'
            )
            ->addOption(
                'account',
                'a',
                InputOption::VALUE_REQUIRED,
                '限制同步指定账号ID的应用'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                sprintf('指定聊天应用类型 (%s)', implode('|', self::CHAT_APP_TYPES))
            )
            ->addOption(
                'async',
                null,
                InputOption::VALUE_NONE,
                '异步执行同步任务'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $options = $this->parseAndValidateOptions($input, $io);
        if (null === $options) {
            return Command::FAILURE;
        }

        [$instanceId, $accountId, $appType, $isAsync] = $options;

        $this->displaySyncInfo($io, $instanceId, $accountId, $appType, $isAsync);
        $this->logSyncStart($instanceId, $accountId, $appType, $isAsync);

        try {
            if ($isAsync) {
                return $this->executeAsync($io, $instanceId, $accountId, $appType);
            }

            return $this->executeSync($io, $instanceId, $accountId, $appType);
        } catch (\Exception $e) {
            return $this->handleSyncError($io, $instanceId, $accountId, $appType, $e, $output);
        }
    }

    private function executeAsync(SymfonyStyle $io, ?int $instanceId, ?int $accountId, ?string $appType): int
    {
        if (null === $appType) {
            // 如果没有指定类型，发送多个消息分别同步各类型
            $messageIds = [];
            foreach (self::CHAT_APP_TYPES as $type) {
                $message = $this->createSyncMessage($instanceId, $accountId, $type);
                $this->messageBus->dispatch($message);
                $messageIds[] = $message->getMessageId();
            }

            $io->success('所有聊天应用同步任务已发送到消息队列');
            foreach ($messageIds as $messageId) {
                $io->writeln("消息ID: {$messageId}");
            }
        } else {
            $message = $this->createSyncMessage($instanceId, $accountId, $appType);
            $this->messageBus->dispatch($message);

            $io->success("{$appType} 应用同步任务已发送到消息队列");
            $io->writeln(sprintf('消息ID: %s', $message->getMessageId()));
        }

        return Command::SUCCESS;
    }

    private function executeSync(SymfonyStyle $io, ?int $instanceId, ?int $accountId, ?string $appType): int
    {
        $io->section('执行同步...');

        if (null === $appType) {
            // 同步所有聊天应用类型
            $totalStats = [
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

            foreach (self::CHAT_APP_TYPES as $type) {
                $io->writeln("正在同步 {$type} 应用...");
                $stats = $this->appSyncService->syncApps($instanceId, $accountId, $type);

                $totalStats['processed_instances'] += $stats['processed_instances'];
                $totalStats['processed_accounts'] += $stats['processed_accounts'];
                $totalStats['synced_apps'] += $stats['synced_apps'];
                $totalStats['created_apps'] += $stats['created_apps'];
                $totalStats['updated_apps'] += $stats['updated_apps'];
                $totalStats['synced_sites'] += $stats['synced_sites'];
                $totalStats['created_sites'] += $stats['created_sites'];
                $totalStats['updated_sites'] += $stats['updated_sites'];
                $totalStats['errors'] += $stats['errors'];

                // 合并app_types统计
                foreach ($stats['app_types'] ?? [] as $statsAppType => $count) {
                    $totalStats['app_types'][$statsAppType] = ($totalStats['app_types'][$statsAppType] ?? 0) + $count;
                }

                // 合并错误详情
                $totalStats['error_details'] = array_merge($totalStats['error_details'], $stats['error_details'] ?? []);
            }

            $this->displaySyncStats($io, $totalStats);
        } else {
            // 同步指定类型
            $progressBar = new ProgressBar($io);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
            $progressBar->setMessage("准备同步 {$appType} 应用...");
            $progressBar->start();

            try {
                $progressBar->setMessage("同步 {$appType} 应用数据...");
                $progressBar->advance();

                $syncStats = $this->appSyncService->syncApps($instanceId, $accountId, $appType);

                $progressBar->setMessage('同步完成');
                $progressBar->finish();
                $io->newLine(2);

                $this->displaySyncStats($io, $syncStats);
            } catch (\Exception $e) {
                $progressBar->setMessage('同步失败: ' . $e->getMessage());
                $progressBar->finish();
                $io->newLine(2);
                throw $e;
            }
        }

        $io->success('聊天应用同步任务完成');

        return Command::SUCCESS;
    }

    private function createSyncMessage(?int $instanceId, ?int $accountId, string $appType): DifySyncMessage
    {
        return new DifySyncMessage(
            instanceId: $instanceId,
            accountId: $accountId,
            appType: $appType,
            metadata: [
                'request_id' => uniqid("{$appType}_sync_", true),
                'initiated_at' => new \DateTimeImmutable(),
                'source' => 'chat_apps_sync_command',
            ]
        );
    }

    /**
     * 解析并验证输入选项
     *
     * @return array{int|null, int|null, string|null, bool}|null 返回 [instanceId, accountId, appType, isAsync]，验证失败返回 null
     */
    private function parseAndValidateOptions(InputInterface $input, SymfonyStyle $io): ?array
    {
        $instanceOption = $input->getOption('instance');
        $accountOption = $input->getOption('account');
        $typeOption = $input->getOption('type');
        $isAsync = (bool) $input->getOption('async');

        $instanceId = null !== $instanceOption && is_numeric($instanceOption) ? (int) $instanceOption : null;
        $accountId = null !== $accountOption && is_numeric($accountOption) ? (int) $accountOption : null;
        $appType = is_string($typeOption) ? $typeOption : null;

        // 验证应用类型
        if (null !== $appType && !in_array($appType, self::CHAT_APP_TYPES, true)) {
            $io->error(
                sprintf(
                    '不支持的聊天应用类型: %s。支持的类型: %s',
                    $appType,
                    implode(', ', self::CHAT_APP_TYPES)
                )
            );

            return null;
        }

        return [$instanceId, $accountId, $appType, $isAsync];
    }

    /**
     * 显示同步信息
     */
    private function displaySyncInfo(SymfonyStyle $io, ?int $instanceId, ?int $accountId, ?string $appType, bool $isAsync): void
    {
        $scope = $this->buildScopeDescription($instanceId, $accountId);
        $targetType = null !== $appType ? self::APP_TYPE_LABELS[$appType] : '所有聊天类型';

        $io->title('Dify 聊天应用同步');
        $io->info("同步范围: {$scope}");
        $io->info("应用类型: {$targetType}");
        $io->info('执行模式: ' . ($isAsync ? '异步' : '同步'));
    }

    /**
     * 记录同步开始日志
     */
    private function logSyncStart(?int $instanceId, ?int $accountId, ?string $appType, bool $isAsync): void
    {
        $this->logger->info(
            '开始Dify聊天应用同步',
            [
                'instance_id' => $instanceId,
                'account_id' => $accountId,
                'app_type' => $appType,
                'async' => $isAsync,
            ]
        );
    }

    /**
     * @param array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $stats
     */
    private function displaySyncStats(SymfonyStyle $io, array $stats): void
    {
        $io->section('同步统计');

        $headers = ['指标', '数量'];
        $rows = [
            ['同步的应用数', $stats['synced_apps'] ?? 0],
            ['新创建应用', $stats['created_apps'] ?? 0],
            ['更新应用', $stats['updated_apps'] ?? 0],
            ['错误数量', $stats['errors'] ?? 0],
        ];

        if (isset($stats['processed_instances'])) {
            array_unshift($rows, ['处理的实例数', $stats['processed_instances']]);
        }

        if (isset($stats['processed_accounts'])) {
            array_unshift($rows, ['处理的账号数', $stats['processed_accounts']]);
        }

        $io->table($headers, $rows);
    }

    private function buildScopeDescription(?int $instanceId, ?int $accountId): string
    {
        $scopes = [];

        if (null !== $instanceId) {
            $scopes[] = "实例:{$instanceId}";
        }

        if (null !== $accountId) {
            $scopes[] = "账号:{$accountId}";
        }

        if (0 === count($scopes)) {
            return '全量同步';
        }

        return implode(', ', $scopes);
    }

    private function handleSyncError(SymfonyStyle $io, ?int $instanceId, ?int $accountId, ?string $appType, \Exception $e, OutputInterface $output): int
    {
        $this->logger->error(
            'Dify聊天应用同步失败',
            [
                'instance_id' => $instanceId,
                'account_id' => $accountId,
                'app_type' => $appType,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]
        );

        $io->error('同步过程中发生错误: ' . $e->getMessage());

        if ($output->isVerbose()) {
            $io->writeln($e->getTraceAsString());
        }

        return Command::FAILURE;
    }
}
