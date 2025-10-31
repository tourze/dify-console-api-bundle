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
    description: '同步所有Chatflow类型的Dify应用'
)]
#[WithMonologChannel(channel: 'dify_console_api')]
class DifyChatflowAppsSyncCommand extends Command
{
    public const NAME = 'dify:sync:chatflow-apps';
    private const APP_TYPE = 'chatflow';

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

        $instanceOption = $input->getOption('instance');
        $accountOption = $input->getOption('account');
        $isAsync = (bool) $input->getOption('async');

        $instanceId = null !== $instanceOption && is_numeric($instanceOption) ? (int) $instanceOption : null;
        $accountId = null !== $accountOption && is_numeric($accountOption) ? (int) $accountOption : null;

        $scope = $this->buildScopeDescription($instanceId, $accountId);

        $io->title('Dify Chatflow应用同步');
        $io->info("同步范围: {$scope}");
        $io->info('应用类型: Chatflow');
        $io->info('执行模式: ' . ($isAsync ? '异步' : '同步'));

        $this->logger->info(
            '开始Dify Chatflow应用同步',
            [
                'instance_id' => $instanceId,
                'account_id' => $accountId,
                'app_type' => self::APP_TYPE,
                'async' => $isAsync,
            ]
        );

        try {
            if ($isAsync) {
                return $this->executeAsync($io, $instanceId, $accountId);
            }

            return $this->executeSync($io, $instanceId, $accountId);
        } catch (\Exception $e) {
            return $this->handleSyncError($io, $instanceId, $accountId, $e, $output);
        }
    }

    private function executeAsync(SymfonyStyle $io, ?int $instanceId, ?int $accountId): int
    {
        $message = new DifySyncMessage(
            instanceId: $instanceId,
            accountId: $accountId,
            appType: self::APP_TYPE,
            metadata: [
                'request_id' => uniqid('chatflow_sync_', true),
                'initiated_at' => new \DateTimeImmutable(),
                'source' => 'chatflow_sync_command',
            ]
        );

        $this->messageBus->dispatch($message);

        $io->success('Chatflow应用同步任务已发送到消息队列');
        $io->writeln(sprintf('消息ID: %s', $message->getMessageId()));

        $this->logger->info(
            '异步Chatflow应用同步任务已发送',
            [
                'message_id' => $message->getMessageId(),
                'instance_id' => $instanceId,
                'account_id' => $accountId,
            ]
        );

        return Command::SUCCESS;
    }

    private function executeSync(SymfonyStyle $io, ?int $instanceId, ?int $accountId): int
    {
        $io->section('执行同步...');

        $progressBar = new ProgressBar($io);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        $progressBar->setMessage('准备同步Chatflow应用...');
        $progressBar->start();

        try {
            $progressBar->setMessage('同步Chatflow应用数据...');
            $progressBar->advance();

            $syncStats = $this->appSyncService->syncApps($instanceId, $accountId, self::APP_TYPE);

            $progressBar->setMessage('同步完成');
            $progressBar->finish();
            $io->newLine(2);

            $this->displaySyncStats($io, $syncStats);

            if ($syncStats['errors'] > 0) {
                $io->warning('同步过程中发生了一些错误，请检查日志');

                return Command::FAILURE;
            }

            $io->success('Chatflow应用同步任务完成');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $progressBar->setMessage('同步失败: ' . $e->getMessage());
            $progressBar->finish();
            $io->newLine(2);
            throw $e;
        }
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

    private function handleSyncError(SymfonyStyle $io, ?int $instanceId, ?int $accountId, \Exception $e, OutputInterface $output): int
    {
        $this->logger->error(
            'Dify Chatflow应用同步失败',
            [
                'instance_id' => $instanceId,
                'account_id' => $accountId,
                'app_type' => self::APP_TYPE,
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
