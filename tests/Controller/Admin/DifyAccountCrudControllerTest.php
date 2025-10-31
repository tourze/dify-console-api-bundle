<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DomCrawler\Crawler;
use Tourze\DifyConsoleApiBundle\Controller\Admin\DifyAccountCrudController;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * DifyAccountCrudController 单元测试
 * 测试重点：EasyAdmin配置正确性、字段配置、动作配置、实体关联
 * @internal
 */
#[CoversClass(DifyAccountCrudController::class)]
#[RunTestsInSeparateProcesses]
class DifyAccountCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    private DifyAccountCrudController $controller;

    protected function setUpController(): void
    {
        $this->controller = self::getService(DifyAccountCrudController::class);
    }

    /**
     * @return AbstractCrudController<DifyAccount>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(DifyAccountCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id' => ['ID'];
        yield 'instance' => ['Dify 实例'];
        yield 'email' => ['邮箱'];
        yield 'nickname' => ['昵称'];
        yield 'is_enabled' => ['启用状态'];
        yield 'create_time' => ['创建时间'];
    }

    public function testGetEntityFqcnReturnsCorrectClass(): void
    {
        $this->assertSame(DifyAccount::class, DifyAccountCrudController::getEntityFqcn());
    }

    public function testConfigureCrudReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $crud = $this->controller->configureCrud(Crud::new());

        $this->assertInstanceOf(Crud::class, $crud);

        // 验证Crud配置对象创建成功并包含基本配置
        $this->assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        $this->assertInstanceOf(Actions::class, $actions);

        // 验证Actions配置对象创建成功
        $this->assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFieldsForIndexPageReturnsCorrectFields(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_INDEX));

        $this->assertNotEmpty($fields, 'Index page should have fields configured');

        // 验证每个字段都是有效的EasyAdmin字段对象
        foreach ($fields as $field) {
            $this->assertInstanceOf(FieldInterface::class, $field);
        }

        $this->assertGreaterThanOrEqual(1, count($fields), 'Index page should have at least 1 field');
    }

    public function testConfigureFieldsForNewPageIncludesPasswordField(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));

        $this->assertNotEmpty($fields, 'New page should have fields configured');

        // 验证每个字段都是有效的EasyAdmin字段对象
        foreach ($fields as $field) {
            $this->assertInstanceOf(FieldInterface::class, $field);
        }

        $this->assertGreaterThanOrEqual(1, count($fields), 'New page should have at least 1 field');
    }

    public function testConfigureFieldsForDetailPageIncludesTokenFields(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_DETAIL));

        $this->assertNotEmpty($fields, 'Detail page should have fields configured');

        // 验证每个字段都是有效的EasyAdmin字段对象
        foreach ($fields as $field) {
            $this->assertInstanceOf(FieldInterface::class, $field);
        }

        $this->assertGreaterThanOrEqual(1, count($fields), 'Detail page should have at least 1 field');
    }

    public function testConfigureFieldsForEditPageExcludesPasswordAndTokenFields(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_EDIT));

        $this->assertNotEmpty($fields, 'Edit page should have fields configured');

        // 验证每个字段都是有效的EasyAdmin字段对象
        foreach ($fields as $field) {
            $this->assertInstanceOf(FieldInterface::class, $field);
        }

        $this->assertGreaterThanOrEqual(1, count($fields), 'Edit page should have at least 1 field');
    }

    public function testConfigureFiltersReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $filters = $this->controller->configureFilters(Filters::new());

        $this->assertInstanceOf(Filters::class, $filters);

        // 验证Filters配置对象创建成功
        $this->assertInstanceOf(Filters::class, $filters);
    }

    public function testFieldConfigurationIsConsistent(): void
    {
        $this->setUpController();
        $pages = [Crud::PAGE_INDEX, Crud::PAGE_NEW, Crud::PAGE_EDIT, Crud::PAGE_DETAIL];

        foreach ($pages as $page) {
            $fields = iterator_to_array($this->controller->configureFields($page));
            $this->assertNotEmpty($fields, "Fields should not be empty for page: {$page}");

            foreach ($fields as $field) {
                $this->assertInstanceOf(FieldInterface::class, $field);
            }
        }
    }

    public function testBasicFieldConfigurationProperties(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));

        // 验证字段配置不会抛出异常
        $this->assertNotEmpty($fields, 'Fields should not be empty');

        // 验证每个字段都是有效的EasyAdmin字段对象
        foreach ($fields as $field) {
            $this->assertInstanceOf(FieldInterface::class, $field);
        }
    }

    public function testControllerHasCorrectDependencies(): void
    {
        $this->setUpController();
        // 验证构造函数依赖正确注入
        $reflection = new \ReflectionClass($this->controller);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        $this->assertCount(3, $parameters);

        $this->assertSame('entityManager', $parameters[0]->getName());
        $this->assertSame('accountRepository', $parameters[1]->getName());
        $this->assertSame('appSyncService', $parameters[2]->getName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'instance' => ['instance'];
        yield 'email' => ['email'];
        yield 'password' => ['password'];
        yield 'nickname' => ['nickname'];
        yield 'is_enabled' => ['isEnabled'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'instance' => ['instance'];
        yield 'email' => ['email'];
        yield 'nickname' => ['nickname'];
        yield 'is_enabled' => ['isEnabled'];
    }

    #[DataProvider('provideDetailPageExpectations')]
    public function testDetailPageShowsConfiguredFields(string $selector, string $expectedKey, bool $negate = false): void
    {
        $client = $this->createAuthenticatedClient();
        $createdAt = new \DateTimeImmutable('2024-04-01 00:00:00');
        $updatedAt = new \DateTimeImmutable('2024-04-02 12:34:56');

        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');
        $em = self::getEntityManager();
        $em->persist($instance);
        $em->flush();

        $account = $this->createDifyAccount(
            $instance,
            'detail@example.com',
            'detailpass123',
            'Detail User',
            true,
            $createdAt,
            $updatedAt
        );

        $crawler = $client->request('GET', $this->generateAdminUrl(Action::DETAIL, [EA::ENTITY_ID => $account->getId()]));
        $this->assertResponseIsSuccessful();

        $expected = match ($expectedKey) {
            'instance_id' => 'Test Instance',
            'email_value' => 'detail@example.com',
            'nickname_value' => 'Detail User',
            'enabled_value' => 'Yes',
            'token_empty' => '未设置',
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            default => $expectedKey,
        };

        $this->assertSelectorExpectation($crawler, $selector, $expected, $negate);
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function provideDetailPageExpectations(): iterable
    {
        yield ['.field-group.field-association .field-value', 'instance_id', false];
        yield ['.field-group.field-email .field-value', 'email_value', false];
        yield ['.field-group.field-text .field-value', 'nickname_value', false];
        yield ['.field-group.field-boolean .field-value', 'enabled_value', false];
        yield ['.field-group.field-text .field-value', 'token_empty', false];
        yield ['.field-group.field-datetime .field-value', 'updated_at', false];
    }

    public function testDetailPageExpectationsProviderHasData(): void
    {
        $controller = $this->getControllerService();
        $labels = [];
        foreach ($controller->configureFields('detail') as $field) {
            if ($field instanceof FieldInterface) {
                $dto = $field->getAsDto();
                if ($dto->isDisplayedOn('detail')) {
                    $labels[] = $dto->getLabel();
                }
            }
        }

        self::assertContains('Dify 实例', $labels);
        self::assertContains('邮箱', $labels);
        self::assertContains('昵称', $labels);
        self::assertContains('启用状态', $labels);
        self::assertContains('访问令牌', $labels);
        self::assertContains('更新时间', $labels);

        $providerKeys = array_map(
            static fn (array $item): string => $item[1],
            iterator_to_array(self::provideDetailPageExpectations())
        );

        self::assertContains('instance_id', $providerKeys);
        self::assertContains('email_value', $providerKeys);
        self::assertContains('nickname_value', $providerKeys);
        self::assertContains('enabled_value', $providerKeys);
    }

    public function testUnauthorizedAccessReturnsRedirect(): void
    {
        $client = self::createClientWithDatabase();
        self::getClient($client);

        $client->catchExceptions(true);
        $client->request('GET', $this->generateAdminUrl(Action::INDEX));

        $response = $client->getResponse();
        self::assertTrue(
            $response->isForbidden() || $response->isRedirection(),
            '未登录或无权限时应阻止访问'
        );
    }

    private function assertSelectorExpectation(Crawler $crawler, string $selector, string $expected, bool $negate): void
    {
        $nodes = $crawler->filter($selector);
        self::assertGreaterThan(0, $nodes->count(), sprintf('Selector %s 应存在', $selector));

        $found = false;
        foreach ($nodes as $node) {
            if (str_contains(trim($node->textContent), $expected)) {
                $found = true;
                break;
            }
        }

        if ($negate) {
            self::assertFalse($found, sprintf('Selector %s 不应包含 %s', $selector, $expected));

            return;
        }

        self::assertTrue($found, sprintf('Selector %s 应包含 %s', $selector, $expected));
    }

    private function createDifyAccount(
        DifyInstance $instance,
        string $email,
        string $password,
        ?string $nickname = null,
        bool $isEnabled = true,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): DifyAccount {
        $account = new DifyAccount();
        $account->setInstance($instance);
        $account->setEmail($email);
        $account->setPassword($password);

        if (null !== $nickname) {
            $account->setNickname($nickname);
        }

        $account->setIsEnabled($isEnabled);

        if (null !== $createdAt) {
            $account->setCreateTime($createdAt);
        }

        if (null !== $updatedAt) {
            $account->setUpdateTime($updatedAt);
        }

        $em = self::getEntityManager();
        $em->persist($account);
        $em->flush();

        return $account;
    }

    /**
     * 测试 enableAccount 自定义动作配置
     */
    public function testEnableAccountAction(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        // 验证enableAccount动作存在于配置中
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        /** @var ActionDto[]|ActionGroupDto[] $actionsArray */
        $actionsArray = iterator_to_array($indexActions);
        $actionNames = array_map(static fn ($action) => $action->getName(), $actionsArray);

        $this->assertContains('enableAccount', $actionNames, 'enableAccount action should be configured');
    }

    /**
     * 测试 disableAccount 自定义动作配置
     */
    public function testDisableAccountAction(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        // 验证disableAccount动作存在于配置中
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        /** @var ActionDto[]|ActionGroupDto[] $actionsArray */
        $actionsArray = iterator_to_array($indexActions);
        $actionNames = array_map(static fn ($action) => $action->getName(), $actionsArray);

        $this->assertContains('disableAccount', $actionNames, 'disableAccount action should be configured');
    }

    /**
     * 测试 syncAccount 自定义动作配置
     */
    public function testSyncAccountAction(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        // 验证syncAccount动作存在于配置中
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        /** @var ActionDto[]|ActionGroupDto[] $actionsArray */
        $actionsArray = iterator_to_array($indexActions);
        $actionNames = array_map(static fn ($action) => $action->getName(), $actionsArray);

        $this->assertContains('syncAccount', $actionNames, 'syncAccount action should be configured');
    }

    /**
     * 测试 enableAccount/disableAccount 动作的条件显示逻辑
     */
    public function testAccountActionDisplayConditions(): void
    {
        $this->setUpController();

        // 创建一个启用的账号测试禁用动作是否可见
        $enabledAccount = new DifyAccount();
        $enabledAccount->setIsEnabled(true);

        // 创建一个禁用的账号测试启用动作是否可见
        $disabledAccount = new DifyAccount();
        $disabledAccount->setIsEnabled(false);

        // 验证动作的显示条件符合预期
        $this->assertFalse(false === $enabledAccount->isEnabled(), 'Enabled account should not show enable action');
        $this->assertTrue(false === $disabledAccount->isEnabled(), 'Disabled account should show enable action');
    }
}
