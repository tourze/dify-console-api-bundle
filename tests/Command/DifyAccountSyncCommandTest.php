<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Command\DifyAccountSyncCommand;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyAccountSyncCommand 单元测试
 * 测试重点：命令配置、参数验证、账号同步处理、异步消息发送、错误处理
 * @internal
 */
#[CoversClass(DifyAccountSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyAccountSyncCommandTest extends AbstractCommandTestCase
{
    private const COMMAND_NAME = 'dify:sync:account';

    private string $testScenario = 'account_sync';

    private const VALID_ACCOUNT_ID = 123;
    private const INVALID_ACCOUNT_ID = 'invalid_id';
    private const TEST_SYNC_STATS = [
        'synced_apps' => 10,
        'created_apps' => 3,
        'updated_apps' => 7,
        'synced_sites' => 5,
        'created_sites' => 1,
        'updated_sites' => 4,
        'errors' => 0,
        'app_types' => [
            'chat' => 5,
            'workflow' => 3,
            'chatflow' => 2,
        ],
    ];

    private DifyAccountSyncCommand $command;

    private MockObject $appSyncService;

    protected function onSetUp(): void
    {
        // 只Mock业务服务，避免替换Symfony核心服务
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);

        // 将Mock的业务服务注册到容器中
        self::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);

        // 获取真实的Command对象用于测试
        $this->command = self::getService(DifyAccountSyncCommand::class);
    }

    /**
     * 创建CommandTester
     */
    private function createCommandTester(): CommandTester
    {
        $command = self::getService(DifyAccountSyncCommand::class);

        return new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->createCommandTester();
    }

    public function testCommandInstance(): void
    {
        // 验证命令实例类型正确
        $this->assertInstanceOf(DifyAccountSyncCommand::class, $this->command);
        $this->assertSame(self::COMMAND_NAME, $this->command->getName());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame(self::COMMAND_NAME, DifyAccountSyncCommand::NAME);
    }

    public function testTestScenario(): void
    {
        $this->assertSame('account_sync', $this->testScenario);
    }

    /**
     * 测试命令的真实属性：命令名称和描述
     */
    public function testCommandRealProperties(): void
    {
        $command = self::getService(DifyAccountSyncCommand::class);

        // 验证命令的真实属性
        $this->assertSame('dify:sync:account', $command->getName());
        $this->assertStringContainsString('同步', $command->getDescription());
        $this->assertStringContainsString('账号', $command->getDescription());

        // 验证命令定义的参数和选项
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('account-id'));
        $this->assertTrue($definition->hasOption('async'));

        // 验证参数的真实属性
        $accountArg = $definition->getArgument('account-id');
        $this->assertTrue($accountArg->isRequired());
        $this->assertStringContainsString('ID', $accountArg->getDescription());
    }

    public function testConfigure(): void
    {
        $command = self::getService(DifyAccountSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证账号ID参数
        $this->assertTrue($definition->hasArgument('account-id'));
        $accountIdArgument = $definition->getArgument('account-id');
        $this->assertTrue($accountIdArgument->isRequired());
        $this->assertSame('Dify账号ID', $accountIdArgument->getDescription());

        // 验证异步选项
        $this->assertTrue($definition->hasOption('async'));
        $asyncOption = $definition->getOption('async');
        $this->assertFalse($asyncOption->acceptValue());
        $this->assertSame('异步执行同步任务', $asyncOption->getDescription());
    }

    public function testArgumentAccountId(): void
    {
        $command = self::getService(DifyAccountSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证账号ID参数的详细属性
        $this->assertTrue($definition->hasArgument('account-id'));
        $accountIdArgument = $definition->getArgument('account-id');
        $this->assertTrue($accountIdArgument->isRequired());
        $this->assertSame('account-id', $accountIdArgument->getName());
        $this->assertSame('Dify账号ID', $accountIdArgument->getDescription());
        $this->assertNull($accountIdArgument->getDefault());
    }

    public function testOptionAsync(): void
    {
        $command = self::getService(DifyAccountSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证异步选项的详细属性
        $this->assertTrue($definition->hasOption('async'));
        $asyncOption = $definition->getOption('async');
        $this->assertSame('async', $asyncOption->getName());
        $this->assertSame('异步执行同步任务', $asyncOption->getDescription());
        $this->assertFalse($asyncOption->acceptValue());
        $this->assertFalse($asyncOption->isValueRequired());
        $this->assertNull($asyncOption->getShortcut());
    }

    public function testExecuteWithInvalidAccountId(): void
    {
        $commandTester = $this->createCommandTester();

        // 测试非数字账号ID
        $result = $commandTester->execute([
            'account-id' => self::INVALID_ACCOUNT_ID,
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('账号ID必须是有效的数字', $commandTester->getDisplay());
    }

    public function testExecuteWithMissingAccountId(): void
    {
        $commandTester = $this->createCommandTester();

        // 不提供账号ID参数应该导致RuntimeException
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "account-id")');

        $commandTester->execute([]);
    }

    public function testExecuteAsyncSuccess(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'account-id' => (string) self::VALID_ACCOUNT_ID,
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('账号同步任务已发送到消息队列', $display);
        $this->assertStringContainsString('消息ID:', $display);
        $this->assertStringContainsString('同步账号: ' . self::VALID_ACCOUNT_ID, $display);
        $this->assertStringContainsString('执行模式: 异步', $display);
    }

    public function testExecuteSyncSuccess(): void
    {
        $syncStats = self::TEST_SYNC_STATS;

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, self::VALID_ACCOUNT_ID, null)
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'account-id' => (string) self::VALID_ACCOUNT_ID,
        ]);

        $this->assertSame(0, $result);

        // 验证命令输出和统计信息显示
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('账号同步任务完成', $display);
        $this->assertStringContainsString('同步账号: ' . self::VALID_ACCOUNT_ID, $display);
        $this->assertStringContainsString('执行模式: 同步', $display);
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString((string) $syncStats['synced_apps'], $display);
        $this->assertStringContainsString('应用类型: chat', $display);
        $this->assertStringContainsString((string) $syncStats['app_types']['chat'], $display);
    }

    public function testExecuteSyncWithErrors(): void
    {
        $syncStats = [
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 2,
        ];

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, 456, null)
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'account-id' => '456',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生了一些错误，请检查日志', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $exception = new \RuntimeException('账号同步失败');

        // 验证服务调用抛出异常
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, 789, null)
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'account-id' => '789',
        ]);

        $this->assertSame(1, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('同步过程中发生错误: 账号同步失败', $display);
        $this->assertStringContainsString('同步账号: 789', $display);
    }

    public function testExecuteWithVerboseExceptionOutput(): void
    {
        $exception = new \RuntimeException('详细错误信息');

        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();

        // 使用CommandTester的setInputs方法设置verbose模式
        $commandTester->execute(
            ['account-id' => '100'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $result = $commandTester->getStatusCode();

        $this->assertSame(1, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('详细错误信息', $display);
        // 验证包含堆栈跟踪信息
        $this->assertStringContainsString('#0', $display);
    }

    public function testDisplaySyncStatsWithoutAppTypes(): void
    {
        $syncStats = [
            'synced_apps' => 3,
            'created_apps' => 1,
            'updated_apps' => 2,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
        ];

        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'account-id' => '200',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString('3', $display);
        $this->assertStringNotContainsString('应用类型:', $display);
    }
}
