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
use Tourze\DifyConsoleApiBundle\Command\DifyChatflowAppsSyncCommand;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyChatflowAppsSyncCommand 单元测试
 * 测试重点：命令配置、参数验证、Chatflow应用类型处理、同步执行、异步消息发送
 * @internal
 */
#[CoversClass(DifyChatflowAppsSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyChatflowAppsSyncCommandTest extends AbstractCommandTestCase
{
    private const COMMAND_NAME = 'dify:sync:chatflow-apps';

    private string $testScenario = 'chatflow_sync';

    private DifyChatflowAppsSyncCommand $command;

    private MockObject $appSyncService;

    protected function onSetUp(): void
    {
        // 只Mock业务服务，避免替换Symfony核心服务
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);

        // 将Mock的业务服务注册到容器中
        self::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);

        // 获取真实的Command对象用于测试
        $this->command = self::getService(DifyChatflowAppsSyncCommand::class);
    }

    /**
     * 创建CommandTester
     */
    private function createCommandTester(): CommandTester
    {
        $command = self::getService(DifyChatflowAppsSyncCommand::class);

        return new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->createCommandTester();
    }

    public function testCommandInstance(): void
    {
        // 验证命令实例类型正确
        $this->assertInstanceOf(DifyChatflowAppsSyncCommand::class, $this->command);
        $this->assertSame(self::COMMAND_NAME, $this->command->getName());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame(self::COMMAND_NAME, DifyChatflowAppsSyncCommand::NAME);
    }

    public function testTestScenario(): void
    {
        $this->assertSame('chatflow_sync', $this->testScenario);
    }

    public function testConfigure(): void
    {
        $command = self::getService(DifyChatflowAppsSyncCommand::class);
        $definition = $command->getDefinition();

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

    public function testExecuteAsyncSuccessWithoutParams(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Chatflow应用同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteAsyncSuccessWithParams(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '123',
            '--account' => '456',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Chatflow应用同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteSyncSuccessWithoutParams(): void
    {
        $syncStats = [
            'synced_apps' => 8,
            'created_apps' => 3,
            'updated_apps' => 5,
            'errors' => 0,
        ];

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'chatflow')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Chatflow应用同步任务完成', $commandTester->getDisplay());

        // 验证统计信息显示
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString('8', $display);
    }

    public function testExecuteSyncSuccessWithParams(): void
    {
        $syncStats = [
            'processed_instances' => 1,
            'processed_accounts' => 2,
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 0,
        ];

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(123, 456, 'chatflow')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '123',
            '--account' => '456',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Chatflow应用同步任务完成', $display);
        $this->assertStringContainsString('处理的实例数', $display);
        $this->assertStringContainsString('处理的账号数', $display);
    }

    public function testExecuteSyncWithErrors(): void
    {
        $syncStats = [
            'synced_apps' => 3,
            'created_apps' => 1,
            'updated_apps' => 2,
            'errors' => 2,
        ];

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'chatflow')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生了一些错误，请检查日志', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $exception = new \RuntimeException('Chatflow同步失败');

        // 验证服务调用抛出异常
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(123, null, 'chatflow')
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '123',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生错误: Chatflow同步失败', $commandTester->getDisplay());
    }

    public function testBuildScopeDescription(): void
    {
        $command = self::getService(DifyChatflowAppsSyncCommand::class);
        // 使用反射测试私有方法
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('buildScopeDescription');
        $method->setAccessible(true);

        // 测试全量同步
        $this->assertSame('全量同步', $method->invoke($command, null, null));

        // 测试仅实例
        $this->assertSame('实例:123', $method->invoke($command, 123, null));

        // 测试仅账号
        $this->assertSame('账号:456', $method->invoke($command, null, 456));

        // 测试实例和账号
        $this->assertSame('实例:123, 账号:456', $method->invoke($command, 123, 456));
    }

    public function testExecuteWithNonNumericInstanceId(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => 'invalid',
        ]);

        // 命令应该正常执行，但实例ID会被忽略
        $this->assertSame(0, $result);
    }

    public function testExecuteWithNonNumericAccountId(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--account' => 'invalid',
        ]);

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

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(1, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('详细错误信息', $display);
        // 验证包含堆栈跟踪信息
        $this->assertStringContainsString('#0', $display);
    }

    /**
     * 测试 --instance 选项的功能
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionInstance(): void
    {
        $syncStats = [
            'processed_instances' => 1,
            'synced_apps' => 3,
            'created_apps' => 1,
            'updated_apps' => 2,
            'errors' => 0,
        ];

        // 验证服务调用时传递了正确的实例ID
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(555, null, 'chatflow')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '555',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示了实例限制信息
        $this->assertStringContainsString('实例:555', $display);
        $this->assertStringContainsString('处理的实例数', $display);
        $this->assertStringContainsString('1', $display);
    }

    /**
     * 测试 --account 选项的功能
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAccount(): void
    {
        $syncStats = [
            'processed_accounts' => 1,
            'synced_apps' => 6,
            'created_apps' => 2,
            'updated_apps' => 4,
            'errors' => 0,
        ];

        // 验证服务调用时传递了正确的账号ID
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, 777, 'chatflow')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--account' => '777',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示了账号限制信息
        $this->assertStringContainsString('账号:777', $display);
        $this->assertStringContainsString('处理的账号数', $display);
        $this->assertStringContainsString('1', $display);
    }

    /**
     * 测试 --async 选项的功能
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAsync(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示异步模式信息
        $this->assertStringContainsString('异步', $display);
        $this->assertStringContainsString('消息队列', $display);
        $this->assertStringContainsString('消息ID:', $display);
    }
}
