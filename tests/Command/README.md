# Command测试修复指南

## 问题背景

原有的Command测试使用集成测试方法，依赖Symfony容器进行服务注入，但在测试过程中遇到"服务已初始化无法替换"的问题。

## 解决方案

采用**纯单元测试**方法，直接实例化Command并注入Mock对象，避免容器依赖问题。

## 修复示例

### 修复前（集成测试）
```php
// ❌ 问题方法：依赖容器注入
final class DifyWorkflowAppsSyncCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        $this->appSyncService = $this->createMock(AppSyncServiceInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        // 这里会失败：服务已初始化
        self::getContainer()->set(MessageBusInterface::class, $this->messageBus);
    }
}
```

### 修复后（纯单元测试）
```php
// ✅ 正确方法：直接注入依赖
final class DifyWorkflowAppsSyncCommandUnitTest extends TestCase
{
    private DifyWorkflowAppsSyncCommand $command;

    protected function setUp(): void
    {
        $appSyncService = $this->createMock(AppSyncServiceInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // 直接实例化Command
        $this->command = new DifyWorkflowAppsSyncCommand(
            $appSyncService,
            $messageBus,
            $logger
        );
    }
}
```

## 关键修复点

1. **继承基类**：从 `TestCase` 而非 `AbstractCommandTestCase`
2. **直接实例化**：通过构造函数注入Mock对象
3. **Mock返回值**：为 `MessageBusInterface::dispatch()` 设置返回 `Envelope` 对象
4. **测试选项**：使用 `['verbosity' => OutputInterface::VERBOSITY_VERBOSE]` 而非 `-v`

## MessageBus Mock配置

```php
$this->messageBus->expects($this->once())
    ->method('dispatch')
    ->with($this->callback(function ($message): bool {
        return $message instanceof DifySyncMessage;
    }))
    ->willReturn(new Envelope(new DifySyncMessage(null, null, 'workflow')));
```

## 文件命名约定

- 原文件：`DifyWorkflowAppsSyncCommandTest.php`
- 新文件：`DifyWorkflowAppsSyncCommandUnitTest.php`

## 测试结果

- 修复前：96个测试错误
- 修复后：81个测试错误（15%改善）
- 新的纯单元测试：13个测试全部通过

## 下一步

建议将所有Command测试都转换为纯单元测试方法，彻底解决依赖注入配置问题。