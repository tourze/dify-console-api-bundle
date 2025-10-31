<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Command;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;

#[AsCommand(
    name: self::NAME,
    description: '同步指定Dify实例的所有数据'
)]
#[WithMonologChannel(channel: 'dify_console_api')]
class DifyInstanceSyncCommand extends Command
{
    public const NAME = 'dify:sync:instance';

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
            ->addArgument(
                'instance-id',
                InputArgument::REQUIRED,
                'Dify实例ID'
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

        $instanceIdArg = $input->getArgument('instance-id');
        if (!is_string($instanceIdArg) || !is_numeric($instanceIdArg)) {
            $io->error('实例ID必须是有效的数字');

            return Command::FAILURE;
        }

        $instanceId = (int) $instanceIdArg;
        $isAsync = (bool) $input->getOption('async');

        $io->title('Dify实例数据同步');
        $io->info("同步实例: {$instanceId}");
        $io->info('执行模式: ' . ($isAsync ? '异步' : '同步'));

        $this->logger->info(
            '开始Dify实例同步',
            [
                'instance_id' => $instanceId,
                'async' => $isAsync,
            ]
        );

        try {
            if ($isAsync) {
                return $this->executeAsync($io, $instanceId);
            }

            return $this->executeSync($io, $instanceId);
        } catch (\Exception $e) {
            return $this->handleSyncError($io, $instanceId, $e, $output);
        }
    }

    private function executeAsync(SymfonyStyle $io, int $instanceId): int
    {
        $message = new DifySyncMessage(
            instanceId: $instanceId,
            accountId: null,
            appType: null,
            metadata: [
                'request_id' => uniqid('instance_sync_', true),
                'initiated_at' => new \DateTimeImmutable(),
                'source' => 'instance_sync_command',
            ]
        );

        $this->messageBus->dispatch($message);

        $io->success('实例同步任务已发送到消息队列');
        $io->writeln(sprintf('消息ID: %s', $message->getMessageId()));

        $this->logger->info(
            '异步实例同步任务已发送',
            [
                'message_id' => $message->getMessageId(),
                'instance_id' => $instanceId,
            ]
        );

        return Command::SUCCESS;
    }

    private function executeSync(SymfonyStyle $io, int $instanceId): int
    {
        $io->section('执行同步...');

        $progressBar = new ProgressBar($io);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        $progressBar->setMessage('准备同步实例数据...');
        $progressBar->start();

        try {
            $progressBar->setMessage('同步实例所有应用数据...');
            $progressBar->advance();

            $syncStats = $this->appSyncService->syncApps($instanceId, null, null);

            $progressBar->setMessage('同步完成');
            $progressBar->finish();
            $io->newLine(2);

            $this->displaySyncStats($io, $syncStats);

            if ($syncStats['errors'] > 0) {
                $io->warning('同步过程中发生了一些错误，请检查日志');

                return Command::FAILURE;
            }

            $io->success('实例同步任务完成');

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
            ['处理的账号数', $stats['processed_accounts'] ?? 0],
            ['同步的应用数', $stats['synced_apps'] ?? 0],
            ['新创建应用', $stats['created_apps'] ?? 0],
            ['更新应用', $stats['updated_apps'] ?? 0],
            ['错误数量', $stats['errors'] ?? 0],
        ];

        $appTypes = $stats['app_types'] ?? null;
        if (is_array($appTypes) && count($appTypes) > 0) {
            $rows[] = ['---', '---'];
            foreach ($appTypes as $type => $count) {
                $countStr = is_scalar($count) ? (string) $count : '0';
                $rows[] = ["应用类型: {$type}", $countStr];
            }
        }

        $io->table($headers, $rows);
    }

    private function handleSyncError(SymfonyStyle $io, int $instanceId, \Exception $e, OutputInterface $output): int
    {
        $this->logger->error(
            'Dify实例同步失败',
            [
                'instance_id' => $instanceId,
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
