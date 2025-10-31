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
use Tourze\DifyConsoleApiBundle\Command\DifyInstanceSyncCommand;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyInstanceSyncCommand 单元测试
 * 测试重点：命令配置、参数验证、实例同步处理、异步消息发送、错误处理
 * @internal
 */
#[CoversClass(DifyInstanceSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyInstanceSyncCommandTest extends AbstractCommandTestCase
{
    private const COMMAND_NAME = 'dify:sync:instance';

    private string $testScenario = 'instance_sync';

    private DifyInstanceSyncCommand $command;

    private MockObject $appSyncService;

    protected function onSetUp(): void
    {
        // 只Mock业务服务，避免替换Symfony核心服务
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);

        // 将Mock的业务服务注册到容器中
        self::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);

        // 获取真实的Command对象用于测试
        $this->command = self::getService(DifyInstanceSyncCommand::class);
    }

    /**
     * 创建CommandTester
     */
    private function createCommandTester(): CommandTester
    {
        $command = self::getService(DifyInstanceSyncCommand::class);

        return new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->createCommandTester();
    }

    public function testCommandInstance(): void
    {
        // 验证命令实例类型正确
        $this->assertInstanceOf(DifyInstanceSyncCommand::class, $this->command);
        $this->assertSame(self::COMMAND_NAME, $this->command->getName());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame(self::COMMAND_NAME, DifyInstanceSyncCommand::NAME);
    }

    public function testTestScenario(): void
    {
        $this->assertSame('instance_sync', $this->testScenario);
    }

    public function testConfigure(): void
    {
        $command = self::getService(DifyInstanceSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证实例ID参数
        $this->assertTrue($definition->hasArgument('instance-id'));
        $instanceIdArgument = $definition->getArgument('instance-id');
        $this->assertTrue($instanceIdArgument->isRequired());
        $this->assertSame('Dify实例ID', $instanceIdArgument->getDescription());

        // 验证异步选项
        $this->assertTrue($definition->hasOption('async'));
        $asyncOption = $definition->getOption('async');
        $this->assertFalse($asyncOption->acceptValue());
        $this->assertSame('异步执行同步任务', $asyncOption->getDescription());
    }

    public function testExecuteWithInvalidInstanceId(): void
    {
        $commandTester = $this->createCommandTester();

        // 测试非数字实例ID
        $result = $commandTester->execute([
            'instance-id' => 'invalid_id',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('实例ID必须是有效的数字', $commandTester->getDisplay());
    }

    public function testExecuteWithMissingInstanceId(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $commandTester = $this->createCommandTester();
        $commandTester->execute([]);
    }

    public function testExecuteAsyncSuccess(): void
    {
        // async模式不调用appSyncService，只发送消息
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '123',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('实例同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteSyncSuccess(): void
    {
        $syncStats = [
            'processed_accounts' => 5,
            'synced_apps' => 20,
            'created_apps' => 8,
            'updated_apps' => 12,
            'errors' => 0,
            'app_types' => [
                'chat' => 10,
                'workflow' => 6,
                'chatflow' => 4,
            ],
        ];

        // 验证服务调用 - 使用正确的instance ID: 456
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(456, null, null)
            ->willReturn($syncStats)
        ;
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '456',
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('实例同步任务完成', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $exception = new \RuntimeException('实例同步失败');

        // 验证服务调用抛出异常 - 使用正确的instance ID: 100
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(100, null, null)
            ->willThrowException($exception)
        ;
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '100',
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(1, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('同步过程中发生错误', $display);
    }

    public function testDisplaySyncStatsWithoutAppTypes(): void
    {
        $syncStats = [
            'processed_accounts' => 2,
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 0,
        ];

        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '200',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('处理的账号数', $display);
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString('5', $display);
        $this->assertStringNotContainsString('应用类型:', $display);
    }

    public function testAsyncMessageMetadata(): void
    {
        // 测试async模式包含正确的元数据
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '400',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证async输出
        $this->assertStringContainsString('异步', $display);
        $this->assertStringContainsString('消息ID:', $display);
    }

    public function testSyncDisplaysCorrectInfo(): void
    {
        $syncStats = [
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'errors' => 0,
        ];

        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '500',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证显示实例同步信息
        $this->assertStringContainsString('Dify实例数据同步', $display);
        $this->assertStringContainsString('同步实例: 500', $display);
        $this->assertStringContainsString('执行模式: 同步', $display);
    }

    public function testAsyncDisplaysCorrectInfo(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '600',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证显示异步实例同步信息
        $this->assertStringContainsString('Dify实例数据同步', $display);
        $this->assertStringContainsString('同步实例: 600', $display);
        $this->assertStringContainsString('执行模式: 异步', $display);
        $this->assertStringContainsString('消息ID:', $display);
    }

    /**
     * 测试 instance-id 参数
     * 这是AbstractCommandTestCase要求的参数覆盖测试
     */
    public function testArgumentInstanceId(): void
    {
        $command = self::getService(DifyInstanceSyncCommand::class);
        $definition = $command->getDefinition();

        // 验证参数存在且必填
        $this->assertTrue($definition->hasArgument('instance-id'));
        $argument = $definition->getArgument('instance-id');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('Dify实例ID', $argument->getDescription());
    }

    /**
     * 测试 --async 选项
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAsync(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            'instance-id' => '700',
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证异步模式信息
        $this->assertStringContainsString('异步', $display);
        $this->assertStringContainsString('消息队列', $display);
        $this->assertStringContainsString('消息ID:', $display);
    }
}
