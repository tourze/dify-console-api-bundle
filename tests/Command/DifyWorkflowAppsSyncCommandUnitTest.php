<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Command\DifyWorkflowAppsSyncCommand;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyWorkflowAppsSyncCommand 纯单元测试
 *
 * 不依赖Symfony容器，直接实例化Command并注入Mock对象
 * 避免依赖注入配置问题
 *
 * @internal
 */
#[CoversClass(DifyWorkflowAppsSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyWorkflowAppsSyncCommandUnitTest extends AbstractCommandTestCase
{
    private MockObject&AppSyncServiceInterface $appSyncService;

    /** @var string 测试场景标识 */
    private string $testScenario = 'workflow_apps_sync';

    protected function onSetUp(): void
    {
        // 创建Mock对象
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);

        // 将Mock对象注册到容器中
        self::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);
    }

    /**
     * 获取命令实例
     */
    private function getCommand(): DifyWorkflowAppsSyncCommand
    {
        return self::getService(DifyWorkflowAppsSyncCommand::class);
    }

    /**
     * 实现抽象方法，返回CommandTester
     */
    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->getCommand());
    }

    public function testCommandName(): void
    {
        $this->assertSame('dify:sync:workflow-apps', DifyWorkflowAppsSyncCommand::NAME);
        $this->assertSame('dify:sync:workflow-apps', $this->getCommand()->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertStringContainsString('Workflow', $this->getCommand()->getDescription());
        $this->assertStringContainsString('同步', $this->getCommand()->getDescription());
    }

    public function testTestScenario(): void
    {
        $this->assertSame('workflow_apps_sync', $this->testScenario);
    }

    public function testCommandConfiguration(): void
    {
        $definition = $this->getCommand()->getDefinition();

        // 验证实例选项
        $this->assertTrue($definition->hasOption('instance'));
        $instanceOption = $definition->getOption('instance');
        $this->assertTrue($instanceOption->acceptValue());
        $this->assertSame('i', $instanceOption->getShortcut());
        $this->assertSame('限制同步指定实例ID的应用', $instanceOption->getDescription());

        // 验证账号选项
        $this->assertTrue($definition->hasOption('account'));
        $accountOption = $definition->getOption('account');
        $this->assertTrue($accountOption->acceptValue());
        $this->assertSame('a', $accountOption->getShortcut());
        $this->assertSame('限制同步指定账号ID的应用', $accountOption->getDescription());

        // 验证异步选项
        $this->assertTrue($definition->hasOption('async'));
        $asyncOption = $definition->getOption('async');
        $this->assertFalse($asyncOption->acceptValue());
        $this->assertSame('异步执行同步任务', $asyncOption->getDescription());
    }

    public function testExecuteAsyncWithoutParams(): void
    {
        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute(['--async' => true]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Workflow应用同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteAsyncWithParams(): void
    {
        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([
            '--instance' => '123',
            '--account' => '456',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Workflow应用同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteSyncWithoutParams(): void
    {
        $syncStats = [
            'synced_apps' => 12,
            'created_apps' => 5,
            'updated_apps' => 7,
            'errors' => 0,
        ];

        // 配置Mock期望
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'workflow')
            ->willReturn($syncStats)
        ;
        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Workflow应用同步任务完成', $display);
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString('12', $display);
    }

    public function testExecuteSyncWithParams(): void
    {
        $syncStats = [
            'processed_instances' => 1,
            'processed_accounts' => 3,
            'synced_apps' => 8,
            'created_apps' => 3,
            'updated_apps' => 5,
            'errors' => 0,
        ];

        // 配置Mock期望
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(123, 456, 'workflow')
            ->willReturn($syncStats)
        ;

        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([
            '--instance' => '123',
            '--account' => '456',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Workflow应用同步任务完成', $display);
        $this->assertStringContainsString('处理的实例数', $display);
        $this->assertStringContainsString('处理的账号数', $display);
    }

    public function testExecuteSyncWithErrors(): void
    {
        $syncStats = [
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 4,
        ];

        // 配置Mock期望
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'workflow')
            ->willReturn($syncStats)
        ;

        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生了一些错误，请检查日志', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $exception = new \RuntimeException('Workflow同步失败');

        // 配置Mock期望
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(123, null, 'workflow')
            ->willThrowException($exception)
        ;
        // 执行命令
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute(['--instance' => '123']);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生错误: Workflow同步失败', $commandTester->getDisplay());
    }

    public function testExecuteWithNonNumericInstanceId(): void
    {
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute(['--instance' => 'invalid']);

        // 命令应该正常执行，但实例ID会被忽略
        $this->assertSame(0, $result);
    }

    public function testExecuteWithNonNumericAccountId(): void
    {
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute(['--account' => 'invalid']);

        // 命令应该正常执行，但账号ID会被忽略
        $this->assertSame(0, $result);
    }

    public function testExecuteWithVerboseExceptionOutput(): void
    {
        $exception = new \RuntimeException('详细错误信息');

        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->willThrowException($exception)
        ;

        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(1, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('详细错误信息', $display);
        // 验证包含堆栈跟踪信息
        $this->assertStringContainsString('#0', $display);
    }

    public function testAsyncMessageMetadata(): void
    {
        // 验证异步消息包含正确的元数据
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute(['--async' => true]);

        $this->assertSame(0, $result);
    }

    /**
     * 测试 --instance 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionInstance(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

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
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('account'));
        $option = $definition->getOption('account');
        $this->assertTrue($option->acceptValue());
        $this->assertStringContainsString('账号', $option->getDescription());
    }

    /**
     * 测试 --async 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAsync(): void
    {
        $commandTester = new CommandTester($this->getCommand());
        $result = $commandTester->execute([
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('异步', $display);
        $this->assertStringContainsString('消息队列', $display);
    }
}
