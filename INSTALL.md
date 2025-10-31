# Dify Console API Bundle 安装指南

## 快速安装检查清单

### 1. 安装依赖 ✓

```bash
composer require tourze/dify-console-api-bundle
```

### 2. 启用 Bundle ✓

在 `config/bundles.php` 中确认包含：

```php
Tourze\DifyConsoleApiBundle\DifyConsoleApiBundle::class => ['all' => true],
```

### 3. 验证服务注册

运行以下命令检查服务是否正确注册：

```bash
php bin/console debug:container | grep -i dify
```

应该看到类似输出：
```
Tourze\DifyConsoleApiBundle\Service\AdminMenu
Tourze\DifyConsoleApiBundle\Service\DifyClientService
Tourze\DifyConsoleApiBundle\Controller\Admin\DifySyncController
```

### 4. 验证数据库表

检查数据库中是否包含 Dify 相关表：

```sql
SHOW TABLES LIKE '%dify%';
```

应该看到：
- dify_instance
- dify_account
- workflow_app
- chatflow_app
- chat_assistant_app

### 5. 验证命令行工具

```bash
php bin/console list | grep dify
```

应该看到：
```
dify:sync  同步 Dify 应用数据
```

### 6. 验证路由

```bash
php bin/console debug:router | grep dify
```

应该看到同步相关的路由。

### 7. 集成 EasyAdmin 菜单

在您的 `DashboardController` 中：

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
        // 您现有的菜单项...

        // 添加 Dify 菜单
        yield from $this->difyAdminMenu->getMenuItems();
    }
}
```

## 常见问题排查

### 问题1：Bundle 未被识别

**症状**：找不到相关服务或控制器
**解决**：
1. 检查 `composer.json` 中是否正确安装了依赖
2. 运行 `composer dump-autoload`
3. 清除缓存：`php bin/console cache:clear`

### 问题2：数据库表不存在

**症状**：访问管理界面时出现数据库错误
**解决**：
1. 确保 Doctrine 配置正确
2. 运行数据库迁移：`php bin/console doctrine:migrations:migrate`
3. 如果没有迁移文件，手动创建表结构

### 问题3：权限错误

**症状**：访问 Dify 管理界面时提示无权限
**解决**：
1. 在 `security.yaml` 中配置角色层次结构
2. 为用户分配 `ROLE_DIFY_USER` 或更高权限

### 问题4：菜单不显示

**症状**：EasyAdmin 中看不到 Dify 相关菜单
**解决**：
1. 确保在 DashboardController 中正确集成了 AdminMenu
2. 检查用户权限
3. 清除缓存

## 验证安装成功

访问 `/admin` 管理界面，您应该能看到：

1. **Dify 控制台管理** 菜单分组
2. 包含以下子菜单：
   - Dify 实例
   - Dify 账号
   - 工作流应用
   - 聊天流应用
   - 聊天助手应用

## 下一步

1. 创建第一个 Dify 实例
2. 添加 Dify 账号并配置 API Token
3. 运行同步命令测试功能：`php bin/console dify:sync`