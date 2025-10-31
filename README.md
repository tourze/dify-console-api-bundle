# Dify Console API Bundle

[English](README.md) | [中文](README.zh-CN.md)

一个用于管理 Dify AI 平台的 Symfony Bundle，提供控制台命令和管理界面。

## 安装

```bash
composer require tourze/dify-console-api-bundle
```

## 配置

### 1. 注册 Bundle

在 `config/bundles.php` 中添加：

```php
return [
    // ...
    Tourze\DifyConsoleApiBundle\DifyConsoleApiBundle::class => ['all' => true],
];
```

### 2. 数据库配置

运行数据库迁移：

```bash
php bin/console doctrine:migrations:migrate
```

### 3. 安全配置

在 `config/packages/security.yaml` 中配置权限：

```yaml
security:
    role_hierarchy:
        ROLE_DIFY_ADMIN: [ROLE_DIFY_USER]
        ROLE_DIFY_MANAGER: [ROLE_DIFY_ADMIN]
```

### 4. EasyAdmin 集成

在你的 DashboardController 中添加菜单：

```php
use Tourze\DifyConsoleApiBundle\Service\AdminMenu;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminMenu $difyAdminMenu
    ) {
    }

    public function configureMenuItems(): iterable
    {
        // 主菜单
        yield from $this->difyAdminMenu->getMenuItems();

        // 子菜单项
        yield from $this->difyAdminMenu->getSubMenuItems();

        // 快速操作
        yield from $this->difyAdminMenu->getQuickActionItems();

        // 同步操作
        yield from $this->difyAdminMenu->getSyncActionItems();
    }
}
```

## 功能特性

### 1. Dify 实例管理
- 配置多个 Dify 实例
- 连接状态监控
- 基础URL管理

### 2. Dify 账号管理
- 管理 Dify 用户账号
- 配置 API Token
- 账号状态管理

### 3. 应用管理
- 聊天助手应用
- 聊天流应用
- 工作流应用
- 应用配置同步

### 4. 数据同步
- 自动同步应用数据
- 增量同步支持
- 错误重试机制

## 控制台命令

Bundle 提供以下控制台命令用于数据同步和管理：

### 实例同步命令

```bash
# 同步 Dify 实例配置
php bin/console dify:sync:instance

# 同步指定实例
php bin/console dify:sync:instance --instance-id=1

# 详细输出
php bin/console dify:sync:instance --verbose
```

### 账号同步命令

```bash
# 同步 Dify 账号数据
php bin/console dify:sync:account

# 同步指定账号
php bin/console dify:sync:account --account-id=1

# 强制更新
php bin/console dify:sync:account --force
```

### 应用同步命令

```bash
# 同步聊天助手应用
php bin/console dify:sync:chat-apps

# 同步聊天流应用
php bin/console dify:sync:chatflow-apps

# 同步工作流应用
php bin/console dify:sync:workflow-apps

# 按账号过滤同步
php bin/console dify:sync:chat-apps --account-id=1

# 按实例过滤同步
php bin/console dify:sync:workflow-apps --instance-id=2
```

### DSL 同步命令

```bash
# 同步应用的 DSL 配置并进行版本管理
php bin/console dify:sync:dsl

# 同步指定应用的 DSL
php bin/console dify:sync:dsl --app-id=123

# 同步所有应用的 DSL
php bin/console dify:sync:dsl --all

# 按实例过滤同步
php bin/console dify:sync:dsl --all --instance=1

# 按账号过滤同步
php bin/console dify:sync:dsl --all --account=2

# 查看同步计划（不执行实际同步）
php bin/console dify:sync:dsl --all --dry-run

# 组合使用选项
php bin/console dify:sync:dsl --all --instance=1 --account=2 --dry-run
```

## API 接口

### 同步接口
- `POST /admin/dify/sync-apps` - 同步所有应用
- `POST /admin/dify/sync-apps/{accountId}` - 同步指定账号应用
- `POST /admin/dify/test-connections` - 测试所有连接
- `POST /admin/dify/test-connection/{instanceId}` - 测试指定实例连接

## 消息队列

Bundle 使用 Symfony Messenger 组件处理异步同步任务，可配置队列处理器。

## 开发

### 运行测试

```bash
vendor/bin/phpunit packages/dify-console-api-bundle/tests
```

### 静态分析

```bash
vendor/bin/phpstan analyse packages/dify-console-api-bundle/src --level=9
```

## 许可证

MIT License