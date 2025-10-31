<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\DifyConsoleApiBundle\Command\DifyDslSyncCommand;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\ChatAssistantAppRepository;
use Tourze\DifyConsoleApiBundle\Repository\ChatflowAppRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;
use Tourze\DifyConsoleApiBundle\Repository\WorkflowAppRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyDslSyncCommand 单元测试
 * 测试重点：命令配置、参数验证、DSL同步处理、批量操作、dry-run模式
 * @internal
 */
#[CoversClass(DifyDslSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyDslSyncCommandTest extends AbstractCommandTestCase
{
    private const COMMAND_NAME = 'dify:sync:dsl';

    private string $testScenario = 'dsl_sync';

    private DifyDslSyncCommand $command;

    private MockObject $dslSyncService;

    private MockObject $instanceRepository;

    private MockObject $accountRepository;

    private MockObject $chatAppRepository;

    private MockObject $chatflowAppRepository;

    private MockObject $workflowAppRepository;

    protected function onSetUp(): void
    {
        $this->dslSyncService = $this->createMock(DslSyncServiceInterface::class);
        $this->instanceRepository = $this->createMock(DifyInstanceRepository::class);
        $this->accountRepository = $this->createMock(DifyAccountRepository::class);
        $this->chatAppRepository = $this->createMock(ChatAssistantAppRepository::class);
        $this->chatflowAppRepository = $this->createMock(ChatflowAppRepository::class);
        $this->workflowAppRepository = $this->createMock(WorkflowAppRepository::class);

        // 将Mock对象注册到容器中
        self::getContainer()->set(DslSyncServiceInterface::class, $this->dslSyncService);
        self::getContainer()->set(DifyInstanceRepository::class, $this->instanceRepository);
        self::getContainer()->set(DifyAccountRepository::class, $this->accountRepository);
        self::getContainer()->set(ChatAssistantAppRepository::class, $this->chatAppRepository);
        self::getContainer()->set(ChatflowAppRepository::class, $this->chatflowAppRepository);
        self::getContainer()->set(WorkflowAppRepository::class, $this->workflowAppRepository);

        // 获取真实的Command对象用于测试
        $this->command = self::getService(DifyDslSyncCommand::class);
    }

    /**
     * 创建CommandTester
     */
    private function createCommandTester(): CommandTester
    {
        $command = self::getService(DifyDslSyncCommand::class);

        return new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->createCommandTester();
    }

    public function testCommandInstance(): void
    {
        // 验证命令实例类型正确
        $this->assertInstanceOf(DifyDslSyncCommand::class, $this->command);
        $this->assertSame(self::COMMAND_NAME, $this->command->getName());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame(self::COMMAND_NAME, DifyDslSyncCommand::NAME);
    }

    public function testTestScenario(): void
    {
        $this->assertSame('dsl_sync', $this->testScenario);
    }

    public function testConfigure(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证app-id选项
        $this->assertTrue($definition->hasOption('app-id'));
        $appIdOption = $definition->getOption('app-id');
        $this->assertTrue($appIdOption->acceptValue());
        $this->assertSame('指定应用 ID', $appIdOption->getDescription());

        // 验证instance选项
        $this->assertTrue($definition->hasOption('instance'));
        $instanceOption = $definition->getOption('instance');
        $this->assertTrue($instanceOption->acceptValue());
        $this->assertSame('i', $instanceOption->getShortcut());
        $this->assertSame('限制同步指定实例 ID 的应用', $instanceOption->getDescription());

        // 验证account选项
        $this->assertTrue($definition->hasOption('account'));
        $accountOption = $definition->getOption('account');
        $this->assertTrue($accountOption->acceptValue());
        $this->assertSame('a', $accountOption->getShortcut());
        $this->assertSame('限制同步指定账号 ID 的应用', $accountOption->getDescription());

        // 验证all选项
        $this->assertTrue($definition->hasOption('all'));
        $allOption = $definition->getOption('all');
        $this->assertFalse($allOption->acceptValue());
        $this->assertSame('同步所有应用的 DSL', $allOption->getDescription());

        // 验证dry-run选项
        $this->assertTrue($definition->hasOption('dry-run'));
        $dryRunOption = $definition->getOption('dry-run');
        $this->assertFalse($dryRunOption->acceptValue());
        $this->assertSame('仅显示将要同步的应用，不执行实际同步', $dryRunOption->getDescription());
    }

    public function testExecuteWithMissingRequiredOptions(): void
    {
        $commandTester = $this->createCommandTester();

        // 不提供app-id且不使用--all应该失败
        $result = $commandTester->execute([]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('必须指定 --app-id 或使用 --all 同步所有应用', $commandTester->getDisplay());
    }

    public function testExecuteWithConflictingOptions(): void
    {
        $commandTester = $this->createCommandTester();

        // 同时使用app-id和--all应该失败
        $result = $commandTester->execute([
            '--app-id' => '123',
            '--all' => true,
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('不能同时使用 --app-id 和 --all 选项', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppNotFound(): void
    {
        // 模拟未找到应用
        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn(null)
        ;

        $this->chatflowAppRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn(null)
        ;

        $this->workflowAppRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn(null)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('应用 ID 123 不存在', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppDryRun(): void
    {
        // 创建mock应用和实例
        $instance = $this->createMock(DifyInstance::class);
        $app = $this->createMock(BaseApp::class);
        $app->method('getName')->willReturn('Test App');
        $app->method('getInstance')->willReturn($instance);

        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($app)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('这是一次 dry-run，不会执行实际的同步操作', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppSyncSuccessNewVersion(): void
    {
        // 创建mock对象
        $instance = $this->createMock(DifyInstance::class);
        $app = $this->createMock(BaseApp::class);
        $app->method('getName')->willReturn('Test App');
        $app->method('getInstance')->willReturn($instance);

        $account = $this->createMock(DifyAccount::class);
        $account->method('getEmail')->willReturn('test@example.com');

        // 模拟找到应用
        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($app)
        ;

        // 模拟找到账号
        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->with(['instance' => $instance])
            ->willReturn([$account])
        ;

        // 模拟DSL同步成功且有新版本
        $this->dslSyncService->expects($this->once())
            ->method('syncAppDsl')
            ->with($app, $account)
            ->willReturn([
                'success' => true,
                'isNewVersion' => true,
                'message' => '新版本已创建',
            ])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('DSL 同步成功: 新版本已创建', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppSyncSuccessNoChange(): void
    {
        // 创建mock对象
        $instance = $this->createMock(DifyInstance::class);
        $app = $this->createMock(BaseApp::class);
        $app->method('getName')->willReturn('Test App');
        $app->method('getInstance')->willReturn($instance);

        $account = $this->createMock(DifyAccount::class);
        $account->method('getEmail')->willReturn('test@example.com');

        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->willReturn($app)
        ;

        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$account])
        ;

        // 模拟DSL同步成功但无变化
        $this->dslSyncService->expects($this->once())
            ->method('syncAppDsl')
            ->willReturn([
                'success' => true,
                'isNewVersion' => false,
                'message' => 'DSL无变化',
            ])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('DSL 无变化: DSL无变化', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppSyncFailure(): void
    {
        // 创建mock对象
        $instance = $this->createMock(DifyInstance::class);
        $app = $this->createMock(BaseApp::class);
        $app->method('getName')->willReturn('Test App');
        $app->method('getInstance')->willReturn($instance);

        $account = $this->createMock(DifyAccount::class);
        $account->method('getEmail')->willReturn('test@example.com');

        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->willReturn($app)
        ;

        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$account])
        ;

        // 模拟DSL同步失败
        $this->dslSyncService->expects($this->once())
            ->method('syncAppDsl')
            ->willReturn([
                'success' => false,
                'message' => '同步失败',
            ])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('DSL 同步失败: 同步失败', $commandTester->getDisplay());
    }

    public function testExecuteSingleAppNoAccounts(): void
    {
        // 创建mock对象
        $instance = $this->createMock(DifyInstance::class);
        $app = $this->createMock(BaseApp::class);
        $app->method('getName')->willReturn('Test App');
        $app->method('getInstance')->willReturn($instance);

        $this->chatAppRepository->expects($this->once())
            ->method('find')
            ->willReturn($app)
        ;

        // 模拟未找到账号
        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->with(['instance' => $instance])
            ->willReturn([])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--app-id' => '123',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('未找到可用的账号', $commandTester->getDisplay());
    }

    public function testExecuteAllAppsDryRun(): void
    {
        // 创建mock应用
        $instance = $this->createMock(DifyInstance::class);
        $instance->method('getName')->willReturn('Test Instance');

        $app1 = $this->createMock(BaseApp::class);
        $app1->method('getId')->willReturn(1);
        $app1->method('getName')->willReturn('App 1');
        $app1->method('getInstance')->willReturn($instance);

        $app2 = $this->createMock(BaseApp::class);
        $app2->method('getId')->willReturn(2);
        $app2->method('getName')->willReturn('App 2');
        $app2->method('getInstance')->willReturn($instance);

        // 模拟仓库返回应用列表
        $this->chatAppRepository->expects($this->once())
            ->method('findBy')
            ->with([])
            ->willReturn([$app1])
        ;

        $this->chatflowAppRepository->expects($this->once())
            ->method('findBy')
            ->with([])
            ->willReturn([$app2])
        ;

        $this->workflowAppRepository->expects($this->once())
            ->method('findBy')
            ->with([])
            ->willReturn([])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--all' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('这是一次 dry-run，将显示要同步的应用但不执行实际同步', $display);
        $this->assertStringContainsString('App 1', $display);
        $this->assertStringContainsString('App 2', $display);
    }

    public function testExecuteAllAppsNoAppsFound(): void
    {
        // 模拟仓库返回空列表
        $this->chatAppRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->chatflowAppRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->workflowAppRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--all' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('未找到任何应用', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        // 模拟抛出异常
        $exception = new \RuntimeException('DSL同步异常');

        $this->chatAppRepository->expects($this->once())
            ->method('findBy')
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--all' => true,
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('DSL 同步失败: DSL同步异常', $commandTester->getDisplay());
    }

    public function testExecuteWithNonNumericOptions(): void
    {
        $commandTester = $this->createCommandTester();

        // 使用非数字的选项值应该被忽略
        $result = $commandTester->execute([
            '--app-id' => 'invalid',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('必须指定 --app-id 或使用 --all 同步所有应用', $commandTester->getDisplay());
    }

    /**
     * 测试 --app-id 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAppId(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证选项存在
        $this->assertTrue($definition->hasOption('app-id'));
        $option = $definition->getOption('app-id');
        $this->assertTrue($option->acceptValue());
        $this->assertStringContainsString('应用', $option->getDescription());
    }

    /**
     * 测试 --instance 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionInstance(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证选项存在
        $this->assertTrue($definition->hasOption('instance'));
        $option = $definition->getOption('instance');
        $this->assertTrue($option->acceptValue());
        $this->assertStringContainsString('实例', $option->getDescription());
    }

    /**
     * 测试 --account 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAccount(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证选项存在
        $this->assertTrue($definition->hasOption('account'));
        $option = $definition->getOption('account');
        $this->assertTrue($option->acceptValue());
        $this->assertStringContainsString('账号', $option->getDescription());
    }

    /**
     * 测试 --all 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAll(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证选项存在
        $this->assertTrue($definition->hasOption('all'));
        $option = $definition->getOption('all');
        $this->assertFalse($option->acceptValue());
        $this->assertStringContainsString('所有应用', $option->getDescription());
    }

    /**
     * 测试 --dry-run 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionDryRun(): void
    {
        $command = self::getService(DifyDslSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证选项存在
        $this->assertTrue($definition->hasOption('dry-run'));
        $option = $definition->getOption('dry-run');
        $this->assertFalse($option->acceptValue());
        $this->assertStringContainsString('显示', $option->getDescription());
    }
}
