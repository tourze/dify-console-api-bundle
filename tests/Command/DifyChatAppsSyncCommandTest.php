<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DifyConsoleApiBundle\Command\DifyChatAppsSyncCommand;
use Tourze\DifyConsoleApiBundle\Message\DifySyncMessage;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DifyChatAppsSyncCommand 单元测试
 * 测试重点：命令配置、参数验证、聊天应用类型处理、同步执行、异步消息发送
 * @internal
 */
#[CoversClass(DifyChatAppsSyncCommand::class)]
#[RunTestsInSeparateProcesses]
final class DifyChatAppsSyncCommandTest extends AbstractCommandTestCase
{
    private string $testScenario = 'chat_apps_sync';

    private const COMMAND_NAME = 'dify:sync:chat-apps';
    private const VALID_INSTANCE_ID = 123;
    private const VALID_ACCOUNT_ID = 456;
    private const VALID_APP_TYPE = 'chat';
    private const INVALID_APP_TYPE = 'invalid_type';

    private DifyChatAppsSyncCommand $command;

    private MockObject $appSyncService;

    protected function onSetUp(): void
    {
        // 只Mock业务服务，避免替换Symfony核心服务
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);

        // 将Mock的业务服务注册到容器中
        self::getContainer()->set(AppSyncServiceInterface::class, $this->appSyncService);

        // 获取真实的Command对象用于测试
        $this->command = self::getService(DifyChatAppsSyncCommand::class);
    }

    /**
     * 创建CommandTester
     */
    private function createCommandTester(): CommandTester
    {
        $command = self::getService(DifyChatAppsSyncCommand::class);

        return new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->createCommandTester();
    }

    public function testCommandInstance(): void
    {
        // 验证命令实例类型正确
        $this->assertInstanceOf(DifyChatAppsSyncCommand::class, $this->command);
        $this->assertSame(self::COMMAND_NAME, $this->command->getName());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame(self::COMMAND_NAME, DifyChatAppsSyncCommand::NAME);
    }

    public function testTestScenario(): void
    {
        $this->assertSame('chat_apps_sync', $this->testScenario);
    }

    /**
     * 测试命令的真实属性：命令名称和描述
     */
    public function testCommandRealProperties(): void
    {
        $command = self::getService(DifyChatAppsSyncCommand::class);

        // 验证命令的真实属性
        $this->assertSame(self::COMMAND_NAME, $command->getName());
        $this->assertStringContainsString('同步', $command->getDescription());
        $this->assertStringContainsString('Chat', $command->getDescription());

        // 验证命令定义的选项
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('instance'));
        $this->assertTrue($definition->hasOption('account'));
        $this->assertTrue($definition->hasOption('type'));
        $this->assertTrue($definition->hasOption('async'));

        // 验证选项的真实属性
        $instanceOption = $definition->getOption('instance');
        $this->assertSame('i', $instanceOption->getShortcut());
        $this->assertTrue($instanceOption->acceptValue());

        $accountOption = $definition->getOption('account');
        $this->assertSame('a', $accountOption->getShortcut());
        $this->assertTrue($accountOption->acceptValue());
    }

    public function testConfigure(): void
    {
        $command = self::getService(DifyChatAppsSyncCommand::class);
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

        // 验证类型选项
        $this->assertTrue($definition->hasOption('type'));
        $typeOption = $definition->getOption('type');
        $this->assertTrue($typeOption->acceptValue());
        $this->assertSame('t', $typeOption->getShortcut());
        $this->assertStringContainsString('指定聊天应用类型', $typeOption->getDescription());

        // 验证异步选项
        $this->assertTrue($definition->hasOption('async'));
        $asyncOption = $definition->getOption('async');
        $this->assertFalse($asyncOption->acceptValue());
        $this->assertSame('异步执行同步任务', $asyncOption->getDescription());
    }

    public function testExecuteWithInvalidAppType(): void
    {
        $commandTester = $this->createCommandTester();

        $result = $commandTester->execute([
            '--type' => self::INVALID_APP_TYPE,
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('不支持的聊天应用类型', $commandTester->getDisplay());
    }

    public function testExecuteAsyncSuccessWithoutType(): void
    {
        $commandTester = $this->createCommandTester();

        $result = $commandTester->execute([
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
    }

    public function testExecuteAsyncSuccessWithType(): void
    {
        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => (string) self::VALID_INSTANCE_ID,
            '--account' => (string) self::VALID_ACCOUNT_ID,
            '--type' => self::VALID_APP_TYPE,
            '--async' => true,
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString(self::VALID_APP_TYPE . ' 应用同步任务已发送到消息队列', $commandTester->getDisplay());
    }

    public function testExecuteSyncSuccessWithoutType(): void
    {
        // 为每个聊天类型设置返回数据
        $syncStats = [
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 0,
        ];

        // 验证为每个聊天类型调用服务
        $this->appSyncService->expects($this->exactly(3))
            ->method('syncApps')
            ->with(null, null, self::callback(static function ($appType): bool {
                return in_array($appType, ['chat', 'completion', 'agent-chat'], true);
            }))
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('聊天应用同步任务完成', $commandTester->getDisplay());
    }

    public function testExecuteSyncSuccessWithType(): void
    {
        $syncStats = [
            'synced_apps' => 5,
            'created_apps' => 2,
            'updated_apps' => 3,
            'errors' => 0,
            'processed_instances' => 1,
        ];

        // 验证服务调用
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(123, null, 'chat')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '123',
            '--type' => 'chat',
        ]);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('聊天应用同步任务完成', $commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $exception = new \RuntimeException('同步失败');

        // 验证服务调用抛出异常
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'chat')
            ->willThrowException($exception)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--type' => 'chat',
        ]);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('同步过程中发生错误: 同步失败', $commandTester->getDisplay());
    }

    public function testBuildScopeDescription(): void
    {
        $command = self::getService(DifyChatAppsSyncCommand::class);
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
            ->with(555, null, 'chat')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--instance' => '555',
            '--type' => 'chat',
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
            ->with(null, 777, 'chat')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--account' => '777',
            '--type' => 'chat',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示了账号限制信息
        $this->assertStringContainsString('账号:777', $display);
        $this->assertStringContainsString('处理的账号数', $display);
        $this->assertStringContainsString('1', $display);
    }

    /**
     * 测试 --type 选项的功能
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionType(): void
    {
        $syncStats = [
            'synced_apps' => 4,
            'created_apps' => 2,
            'updated_apps' => 2,
            'errors' => 0,
        ];

        // 验证服务调用时传递了正确的应用类型
        $this->appSyncService->expects($this->once())
            ->method('syncApps')
            ->with(null, null, 'chat')
            ->willReturn($syncStats)
        ;

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--type' => 'chat',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示了应用类型信息
        $this->assertStringContainsString('应用类型: 聊天', $display);
        $this->assertStringContainsString('同步的应用数', $display);
        $this->assertStringContainsString('4', $display);
    }

    /**
     * 测试 --async 选项的功能
     * 这是AbstractCommandTestCase要求的选项覆盖测试
     */
    public function testOptionAsync(): void
    {
        // async模式不调用appSyncService，只发送消息
        // 不需要设置appSyncService的expects

        $commandTester = $this->createCommandTester();
        $result = $commandTester->execute([
            '--async' => true,
            '--type' => 'chat',
        ]);

        $this->assertSame(0, $result);
        $display = $commandTester->getDisplay();

        // 验证输出显示异步模式信息
        $this->assertStringContainsString('异步', $display);
        $this->assertStringContainsString('消息队列', $display);
        $this->assertStringContainsString('消息ID:', $display);
    }
}
