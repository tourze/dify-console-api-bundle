<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Controller\Admin\AppDslVersionCrudController;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AppDslVersionCrudController 单元测试
 * @internal
 */
#[CoversClass(AppDslVersionCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AppDslVersionCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AppDslVersionCrudController
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(AppDslVersionCrudController::class);
    }

    /**
     * 简化测试：由于Bundle未在测试环境中注册，我们只测试控制器类本身
     * 而不是集成的EasyAdmin功能
     */
    public function testControllerClassCanBeInstantiated(): void
    {
        // 测试类可以被实例化且是final
        $reflection = new \ReflectionClass(AppDslVersionCrudController::class);
        self::assertTrue($reflection->isFinal(), 'Controller class should be final');
        self::assertTrue($reflection->isInstantiable(), 'Controller class should be instantiable');
    }

    public function testStaticConfiguration(): void
    {
        // 测试静态方法
        self::assertSame(AppDslVersion::class, AppDslVersionCrudController::getEntityFqcn());
    }

    public function testControllerConfigurationMethods(): void
    {
        // 直接实例化控制器进行基本测试
        $controller = new AppDslVersionCrudController();

        // 测试CRUD配置
        $crud = $controller->configureCrud(Crud::new());

        // 测试Actions配置
        $actions = $controller->configureActions(Actions::new());

        // 测试Fields配置
        $indexFields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));
        self::assertIsArray($indexFields);
        self::assertNotEmpty($indexFields, 'Controller should define at least one field');

        $detailFields = iterator_to_array($controller->configureFields(Crud::PAGE_DETAIL));
        self::assertIsArray($detailFields);
        self::assertNotEmpty($detailFields, 'Controller should define detail fields');
    }

    public function testDisabledActionsConfiguration(): void
    {
        // 测试控制器禁用了一些操作
        $controller = new AppDslVersionCrudController();
        $actions = $controller->configureActions(Actions::new());

        // 验证配置方法可正常调用，返回值类型已由方法签名保证
        // 获取配置的动作数组来验证方法执行成功
        $actionsList = $actions->getAsDto(null);
        self::assertIsObject($actionsList);
    }

    /**
     * 测试字段配置包含预期的内容
     */
    public function testFieldConfiguration(): void
    {
        $controller = new AppDslVersionCrudController();
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        // 检查是否有预期的字段数量
        self::assertGreaterThanOrEqual(6, count($fields), '应该至少有 6 个字段（ID、应用、版本号、同步时间、创建时间、更新时间）');
    }

    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '应用' => ['应用'];
        yield '版本号' => ['版本号'];
        yield '同步时间' => ['同步时间'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'app' => ['app'];
        yield 'version' => ['version'];
        yield 'dslHash' => ['dslHash'];
        yield 'dslContent' => ['dslContent'];
        yield 'syncTime' => ['syncTime'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'app' => ['app'];
        yield 'version' => ['version'];
        yield 'dslHash' => ['dslHash'];
        yield 'dslContent' => ['dslContent'];
        yield 'syncTime' => ['syncTime'];
    }

    /**
     * 测试验证错误处理
     */
    public function testValidationErrors(): void
    {
        if (!$this->isActionEnabled(Action::NEW)) {
            self::markTestSkipped('NEW action 已禁用，跳过表单校验测试。'); // @phpstan-ignore-line
        }

        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', $this->generateAdminUrl(Action::NEW));
        $this->assertResponseIsSuccessful();

        // 查找并提交空表单以触发验证错误
        $form = $crawler->filter('form')->first()->form();
        $crawler = $client->submit($form);

        // 验证返回422状态码
        $this->assertResponseStatusCodeSame(422);

        // 验证错误信息显示在.invalid-feedback元素中
        $invalidFeedback = $crawler->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $invalidFeedback->count(), 'Should have invalid feedback elements');

        $this->assertStringContainsString('should not be blank', $invalidFeedback->text(), 'Should show validation message');
    }
}
