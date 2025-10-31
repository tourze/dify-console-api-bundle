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
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\ChatAssistantAppRepository;
use Tourze\DifyConsoleApiBundle\Repository\ChatflowAppRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;
use Tourze\DifyConsoleApiBundle\Repository\WorkflowAppRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;

#[AsCommand(
    name: self::NAME,
    description: '同步应用的 DSL 配置并进行版本管理'
)]
#[WithMonologChannel(channel: 'dify_console_api')]
class DifyDslSyncCommand extends Command
{
    public const NAME = 'dify:sync:dsl';

    public function __construct(
        private readonly DslSyncServiceInterface $dslSyncService,
        private readonly DifyInstanceRepository $instanceRepository,
        private readonly DifyAccountRepository $accountRepository,
        private readonly ChatAssistantAppRepository $chatAppRepository,
        private readonly ChatflowAppRepository $chatflowAppRepository,
        private readonly WorkflowAppRepository $workflowAppRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'app-id',
                null,
                InputOption::VALUE_REQUIRED,
                '指定应用 ID'
            )
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                '限制同步指定实例 ID 的应用'
            )
            ->addOption(
                'account',
                'a',
                InputOption::VALUE_REQUIRED,
                '限制同步指定账号 ID 的应用'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                '同步所有应用的 DSL'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                '仅显示将要同步的应用，不执行实际同步'
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

        $io->title('Dify DSL 同步');

        try {
            return $this->executeSyncOperation($io, $options);
        } catch (\Exception $e) {
            return $this->handleExecutionError($io, $e);
        }
    }

    /**
     * 同步单个应用
     */
    private function syncSingleApp(SymfonyStyle $io, int $appId, bool $dryRun): int
    {
        $app = $this->findAppById($appId);
        if (null === $app) {
            $io->error("应用 ID {$appId} 不存在");

            return Command::FAILURE;
        }

        $io->info("应用信息: {$app->getName()} (ID: {$appId})");

        if ($dryRun) {
            $io->note('这是一次 dry-run，不会执行实际的同步操作');

            return Command::SUCCESS;
        }

        // 获取应用的账号
        $accounts = $this->accountRepository->findBy(['instance' => $app->getInstance()]);
        if ([] === $accounts) {
            $io->error('未找到可用的账号');

            return Command::FAILURE;
        }

        $account = $accounts[0];
        $io->info("使用账号: {$account->getEmail()}");

        $result = $this->dslSyncService->syncAppDsl($app, $account);

        if ($result['success']) {
            if ($result['isNewVersion']) {
                $io->success("DSL 同步成功: {$result['message']}");
            } else {
                $io->info("DSL 无变化: {$result['message']}");
            }

            return Command::SUCCESS;
        }

        $io->error("DSL 同步失败: {$result['message']}");

        return Command::FAILURE;
    }

    /**
     * 同步所有应用
     */
    private function syncAllApps(
        SymfonyStyle $io,
        ?int $instanceId,
        ?int $accountId,
        bool $dryRun,
    ): int {
        $apps = $this->getAllApps($instanceId);

        if ([] === $apps) {
            $io->warning('未找到任何应用');

            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 个应用', count($apps)));

        if ($dryRun) {
            return $this->handleDryRun($io, $apps);
        }

        $filteredApps = $this->filterAppsByAccount($io, $apps, $accountId);
        if (null === $filteredApps) {
            return Command::FAILURE;
        }

        return $this->executeBatchSync($io, $filteredApps, $accountId);
    }

    /**
     * 处理 dry-run 模式
     *
     * @param BaseApp[] $apps
     */
    private function handleDryRun(SymfonyStyle $io, array $apps): int
    {
        $io->note('这是一次 dry-run，将显示要同步的应用但不执行实际同步');
        $io->table(
            ['应用 ID', '应用名称', '类型', '实例'],
            array_map(
                static fn ($app) => [
                    $app->getId(),
                    $app->getName(),
                    get_class($app),
                    $app->getInstance()->getName(),
                ],
                $apps
            )
        );

        return Command::SUCCESS;
    }

    /**
     * 按账号过滤应用
     *
     * @param BaseApp[] $apps
     * @return BaseApp[]|null
     */
    private function filterAppsByAccount(SymfonyStyle $io, array $apps, ?int $accountId): ?array
    {
        if (null === $accountId) {
            return $apps;
        }

        $account = $this->accountRepository->find($accountId);
        if (null === $account) {
            $io->error("账号 ID {$accountId} 不存在");

            return null;
        }

        return array_filter($apps, static fn ($app) => $app->getInstance() === $account->getInstance());
    }

    /**
     * 执行批量同步
     *
     * @param BaseApp[] $apps
     */
    private function executeBatchSync(SymfonyStyle $io, array $apps, ?int $accountId): int
    {
        $progressBar = new ProgressBar($io, count($apps));
        $progressBar->start();

        $stats = $this->createSyncStats(count($apps));

        foreach ($apps as $app) {
            $stats = $this->processSingleAppSync($app, $accountId, $stats, $progressBar);
        }

        $progressBar->finish();
        $io->newLine(2);

        $this->displaySyncResults($io, $stats);

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * 创建同步统计
     *
     * @return array{total: int, success: int, new_versions: int, no_changes: int, errors: int}
     */
    private function createSyncStats(int $total): array
    {
        return [
            'total' => $total,
            'success' => 0,
            'new_versions' => 0,
            'no_changes' => 0,
            'errors' => 0,
        ];
    }

    /**
     * 处理单个应用同步
     *
     * @param array{total: int, success: int, new_versions: int, no_changes: int, errors: int} $stats
     * @return array{total: int, success: int, new_versions: int, no_changes: int, errors: int}
     */
    private function processSingleAppSync(BaseApp $app, ?int $accountId, array $stats, ProgressBar $progressBar): array
    {
        try {
            $account = $this->getAppAccount($app, $accountId);
            if (null === $account) {
                ++$stats['errors'];
                $progressBar->advance();

                return $stats;
            }

            $result = $this->dslSyncService->syncAppDsl($app, $account);
            $stats = $this->updateStatsFromResult($stats, $result);
        } catch (\Exception $e) {
            ++$stats['errors'];
            $this->logger->error(
                'App DSL sync failed',
                [
                    'app_id' => $app->getId(),
                    'error' => $e->getMessage(),
                ]
            );
        }

        $progressBar->advance();

        return $stats;
    }

    /**
     * 获取应用对应的账号
     */
    private function getAppAccount(BaseApp $app, ?int $accountId): ?DifyAccount
    {
        $accounts = $this->accountRepository->findBy(['instance' => $app->getInstance()]);
        if ([] === $accounts) {
            return null;
        }

        $account = $accounts[0];
        if (null !== $accountId && $account->getId() !== $accountId) {
            return null;
        }

        return $account;
    }

    /**
     * 根据结果更新统计
     *
     * @param array{total: int, success: int, new_versions: int, no_changes: int, errors: int} $stats
     * @param array{success: bool, isNewVersion?: bool} $result
     * @return array{total: int, success: int, new_versions: int, no_changes: int, errors: int}
     */
    private function updateStatsFromResult(array $stats, array $result): array
    {
        if ($result['success']) {
            ++$stats['success'];
            if ($result['isNewVersion'] ?? false) {
                ++$stats['new_versions'];
            } else {
                ++$stats['no_changes'];
            }
        } else {
            ++$stats['errors'];
        }

        return $stats;
    }

    /**
     * 显示同步结果
     *
     * @param array{total: int, success: int, new_versions: int, no_changes: int, errors: int} $stats
     */
    private function displaySyncResults(SymfonyStyle $io, array $stats): void
    {
        $io->success('DSL 同步完成');
        $io->table(
            ['统计项', '数量'],
            [
                ['总应用数', $stats['total']],
                ['成功同步', $stats['success']],
                ['新版本', $stats['new_versions']],
                ['无变化', $stats['no_changes']],
                ['错误', $stats['errors']],
            ]
        );
    }

    /**
     * 根据 ID 查找应用
     */
    private function findAppById(int $appId): ?BaseApp
    {
        $app = $this->chatAppRepository->find($appId);
        if (null !== $app) {
            return $app;
        }

        $app = $this->chatflowAppRepository->find($appId);
        if (null !== $app) {
            return $app;
        }

        $app = $this->workflowAppRepository->find($appId);
        if (null !== $app) {
            return $app;
        }

        return null;
    }

    /**
     * 解析并验证命令选项
     *
     * @return array{appId: int|null, instanceId: int|null, accountId: int|null, syncAll: bool, dryRun: bool}|null
     */
    private function parseAndValidateOptions(InputInterface $input, SymfonyStyle $io): ?array
    {
        $appId = $this->parseNumericOption($input->getOption('app-id'));
        $instanceId = $this->parseNumericOption($input->getOption('instance'));
        $accountId = $this->parseNumericOption($input->getOption('account'));
        $syncAll = (bool) $input->getOption('all');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$this->validateSyncOptions($io, $syncAll, $appId)) {
            return null;
        }

        return [
            'appId' => $appId,
            'instanceId' => $instanceId,
            'accountId' => $accountId,
            'syncAll' => $syncAll,
            'dryRun' => $dryRun,
        ];
    }

    /**
     * 解析数值选项
     */
    private function parseNumericOption(mixed $option): ?int
    {
        return null !== $option && is_numeric($option) ? (int) $option : null;
    }

    /**
     * 验证同步选项
     */
    private function validateSyncOptions(SymfonyStyle $io, bool $syncAll, ?int $appId): bool
    {
        if (!$syncAll && null === $appId) {
            $io->error('必须指定 --app-id 或使用 --all 同步所有应用');

            return false;
        }

        if (null !== $appId && $syncAll) {
            $io->error('不能同时使用 --app-id 和 --all 选项');

            return false;
        }

        return true;
    }

    /**
     * 执行同步操作
     *
     * @param array{appId: int|null, instanceId: int|null, accountId: int|null, syncAll: bool, dryRun: bool} $options
     */
    private function executeSyncOperation(SymfonyStyle $io, array $options): int
    {
        if (null !== $options['appId']) {
            return $this->syncSingleApp($io, $options['appId'], $options['dryRun']);
        }

        return $this->syncAllApps($io, $options['instanceId'], $options['accountId'], $options['dryRun']);
    }

    /**
     * 处理执行错误
     */
    private function handleExecutionError(SymfonyStyle $io, \Exception $e): int
    {
        $this->logger->error(
            'DSL sync command failed',
            [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]
        );

        $io->error('DSL 同步失败: ' . $e->getMessage());

        return Command::FAILURE;
    }

    /**
     * 获取所有应用
     *
     * @return BaseApp[]
     */
    private function getAllApps(?int $instanceId): array
    {
        $criteria = [];
        if (null !== $instanceId) {
            $instance = $this->instanceRepository->find($instanceId);
            if (null === $instance) {
                return [];
            }
            $criteria['instance'] = $instance;
        }

        $apps = [];
        $apps = array_merge($apps, $this->chatAppRepository->findBy($criteria));
        $apps = array_merge($apps, $this->chatflowAppRepository->findBy($criteria));

        return array_merge($apps, $this->workflowAppRepository->findBy($criteria));
    }
}
