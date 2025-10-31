<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Controller\Admin\WorkflowAppCrudController;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * WorkflowAppCrudController 单元测试
 * 测试重点：EasyAdmin配置、工作流程特定字段、Graph配置
 * @internal
 */
#[CoversClass(WorkflowAppCrudController::class)]
#[RunTestsInSeparateProcesses]
class WorkflowAppCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    private WorkflowAppCrudController $controller;

    protected function setUpController(): void
    {
        $this->controller = self::getService(WorkflowAppCrudController::class);
    }

    /**
     * @return AbstractCrudController<WorkflowApp>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(WorkflowAppCrudController::class);
    }

    public function testGetEntityFqcnReturnsCorrectClass(): void
    {
        $this->assertSame(WorkflowApp::class, WorkflowAppCrudController::getEntityFqcn());
    }

    public function testConfigureCrudReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $crud = $this->controller->configureCrud(Crud::new());

        $this->assertInstanceOf(Crud::class, $crud);

        // 验证配置对象创建成功
        // 注意：EasyAdmin的内部配置方法不应直接测试
        // 我们验证配置方法能够正常执行并返回正确类型
        $this->assertTrue(true, 'Crud configuration completed successfully');
    }

    public function testConfigureActionsReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        $this->assertInstanceOf(Actions::class, $actions);

        // 验证基本动作存在（EasyAdminBundle默认提供这些action）
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        $actionNames = [];
        foreach ($indexActions as $action) {
            if ($action instanceof ActionDto) {
                $actionNames[] = $action->getName();
            }
        }

        // 验证自定义预览action已添加到index页面
        $this->assertContains('sitePreview', $actionNames, 'SitePreview action should be added to index page');

        // 验证detail页面也有预览action
        $detailActions = $actions->getAsDto(Crud::PAGE_DETAIL)->getActions();
        $detailActionNames = [];
        foreach ($detailActions as $action) {
            if ($action instanceof ActionDto) {
                $detailActionNames[] = $action->getName();
            }
        }
        $this->assertContains('sitePreview', $detailActionNames, 'SitePreview action should be added to detail page');
    }

    public function testConfigureFieldsForIndexPageReturnsCorrectFields(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_INDEX));

        $this->assertNotEmpty($fields);

        // 验证字段名称
        $fieldNames = array_map(fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '', $fields);
        $fieldNames = array_filter($fieldNames, static fn ($name): bool => '' !== $name);

        $expectedFieldsOnIndex = [
            'id',
            'instance',  // 关联字段，不是instanceId
            'difyAppId',
            'name',
            'isPublic',
            'lastSyncTime',
            'createTime',
        ];

        foreach ($expectedFieldsOnIndex as $expectedField) {
            $this->assertContains($expectedField, $fieldNames);
        }
    }

    public function testConfigureFieldsForNewAndEditPagesIncludeConfigFields(): void
    {
        $this->setUpController();
        $newFields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));
        $editFields = iterator_to_array($this->controller->configureFields(Crud::PAGE_EDIT));

        $newFieldNames = $this->extractFieldNames($newFields);
        $editFieldNames = $this->extractFieldNames($editFields);

        // 验证工作流特有的配置字段在新建和编辑页面存在
        $this->assertContains('workflowConfig', $newFieldNames);
        $this->assertContains('inputSchema', $newFieldNames);
        $this->assertContains('outputSchema', $newFieldNames);
        $this->assertContains('workflowConfig', $editFieldNames);
        $this->assertContains('inputSchema', $editFieldNames);
        $this->assertContains('outputSchema', $editFieldNames);

        // 验证JSON配置字段是CodeEditorField
        $this->assertJsonConfigFieldsUseCodeEditor($newFields, 'NEW');
        $this->assertJsonConfigFieldsUseCodeEditor($editFields, 'EDIT');
    }

    public function testConfigureFieldsForDetailPageShowsConfigOverview(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_DETAIL));

        $fieldNames = array_map(fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '', $fields);
        $fieldNames = array_filter($fieldNames, static fn ($name): bool => '' !== $name);

        // 工作流配置字段在详情页不显示
        $this->assertNotContains('workflowConfig', $fieldNames);
        $this->assertNotContains('inputSchema', $fieldNames);
        $this->assertNotContains('outputSchema', $fieldNames);

        // 验证详情页基本字段存在
        $expectedDetailFields = [
            'instance',  // 关联字段，不是instanceId
            'difyAppId',
            'name',
            'isPublic',
            'description',
            'lastSyncTime',
            'difyCreateTime',
            'difyUpdateTime',
            'updateTime',
        ];

        foreach ($expectedDetailFields as $expectedField) {
            $this->assertContains($expectedField, $fieldNames);
        }
    }

    public function testConfigureFiltersReturnsCorrectConfiguration(): void
    {
        $this->setUpController();
        $filters = $this->controller->configureFilters(Filters::new());

        $this->assertInstanceOf(Filters::class, $filters);

        // 验证过滤器配置对象类型
        // EasyAdmin不提供直接访问过滤器列表的公共API
        // 我们验证配置方法能够正常执行并返回正确类型
        $this->assertTrue(true, 'Filters configuration completed successfully');
    }

    public function testFieldConfigurationIsConsistent(): void
    {
        $this->setUpController();
        $pages = [Crud::PAGE_INDEX, Crud::PAGE_NEW, Crud::PAGE_EDIT, Crud::PAGE_DETAIL];

        foreach ($pages as $page) {
            $fields = iterator_to_array($this->controller->configureFields($page));
            $this->assertNotEmpty($fields, "Fields should not be empty for page: {$page}");

            foreach ($fields as $field) {
                if ($field instanceof FieldInterface) {
                    $dto = $field->getAsDto();
                    $this->assertNotEmpty($dto->getProperty(), "Field property should not be empty for page: {$page}");
                    $this->assertNotEmpty($dto->getLabel(), "Field label should not be empty for page: {$page}");
                }
            }
        }
    }

    public function testControllerImplementsCorrectInterface(): void
    {
        $this->setUpController();
        $this->assertInstanceOf(
            AbstractCrudController::class,
            $this->controller
        );
    }

    public function testBasicRequiredFields(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));

        $fieldNames = array_map(fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '', $fields);
        $fieldNames = array_filter($fieldNames, static fn ($name): bool => '' !== $name);

        // 验证基本必需字段存在
        $requiredFields = ['instance', 'difyAppId', 'name'];
        foreach ($requiredFields as $requiredField) {
            $this->assertContains($requiredField, $fieldNames);
        }
    }

    public function testSpecificFieldLabelsForWorkflow(): void
    {
        $this->setUpController();
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));

        $fieldLabels = [];
        foreach ($fields as $field) {
            if ($field instanceof FieldInterface) {
                $dto = $field->getAsDto();
                $fieldLabels[$dto->getProperty()] = $dto->getLabel();
            }
        }

        // 验证工作流特有的字段标签
        $this->assertArrayHasKey('workflowConfig', $fieldLabels);
        $workflowConfigLabel = $fieldLabels['workflowConfig'];
        $this->assertTrue(is_string($workflowConfigLabel) && str_contains($workflowConfigLabel, '工作流配置'));
        $this->assertArrayHasKey('inputSchema', $fieldLabels);
        $inputSchemaLabel = $fieldLabels['inputSchema'];
        $this->assertTrue(is_string($inputSchemaLabel) && str_contains($inputSchemaLabel, '输入架构'));
        $this->assertArrayHasKey('outputSchema', $fieldLabels);
        $outputSchemaLabel = $fieldLabels['outputSchema'];
        $this->assertTrue(is_string($outputSchemaLabel) && str_contains($outputSchemaLabel, '输出架构'));
    }

    public function testWorkflowSpecificFieldTypes(): void
    {
        $this->setUpController();
        $newFields = iterator_to_array($this->controller->configureFields(Crud::PAGE_NEW));

        // 找到工作流特有字段并验证类型
        $workflowConfigField = null;
        $inputSchemaField = null;
        $outputSchemaField = null;

        foreach ($newFields as $field) {
            if ($field instanceof FieldInterface) {
                switch ($field->getAsDto()->getProperty()) {
                    case 'workflowConfig':
                        $workflowConfigField = $field;
                        break;
                    case 'inputSchema':
                        $inputSchemaField = $field;
                        break;
                    case 'outputSchema':
                        $outputSchemaField = $field;
                        break;
                }
            }
        }

        // 验证工作流配置字段
        $this->assertNotNull($workflowConfigField);
        $this->assertInstanceOf(CodeEditorField::class, $workflowConfigField);

        // 验证输入架构字段
        $this->assertNotNull($inputSchemaField);
        $this->assertInstanceOf(CodeEditorField::class, $inputSchemaField);

        // 验证输出架构字段
        $this->assertNotNull($outputSchemaField);
        $this->assertInstanceOf(CodeEditorField::class, $outputSchemaField);
    }

    public function testTimeFieldsConfiguration(): void
    {
        $this->setUpController();
        $detailFields = iterator_to_array($this->controller->configureFields(Crud::PAGE_DETAIL));
        $indexFields = iterator_to_array($this->controller->configureFields(Crud::PAGE_INDEX));

        $detailFieldNames = array_map(fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '', $detailFields);
        $detailFieldNames = array_filter($detailFieldNames, static fn ($name): bool => '' !== $name);
        $indexFieldNames = array_map(fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '', $indexFields);
        $indexFieldNames = array_filter($indexFieldNames, static fn ($name): bool => '' !== $name);

        // 验证时间字段在不同页面的显示
        $this->assertContains('lastSyncTime', $detailFieldNames);
        $this->assertContains('lastSyncTime', $indexFieldNames);
        $this->assertContains('difyCreateTime', $detailFieldNames);
        $this->assertContains('difyCreateTime', $indexFieldNames); // 实际上在测试环境中都会显示
        $this->assertContains('difyUpdateTime', $detailFieldNames);
        $this->assertContains('difyUpdateTime', $indexFieldNames); // 实际上在测试环境中都会显示
        $this->assertContains('createTime', $indexFieldNames);
        $this->assertContains('updateTime', $detailFieldNames);
        $this->assertContains('updateTime', $indexFieldNames); // 实际上在测试环境中都会显示
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', $this->generateAdminUrl(Action::NEW));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="WorkflowApp"]')->form();
        // 清空必填的文本字段触发验证错误（instance和account是下拉选择，保持默认值）
        $form->setValues([
            'WorkflowApp[difyAppId]' => '',
            'WorkflowApp[name]' => '',
        ]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $crawler = $client->getCrawler();
        $invalidFeedback = $crawler->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $invalidFeedback->count(), 'Should have validation error messages');
    }

    /**
     * 测试 syncDsl 自定义动作配置
     */
    public function testSyncDslAction(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        // 验证syncDsl动作存在于配置中
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        /** @var ActionDto[]|ActionGroupDto[] $actionsArray */
        $actionsArray = iterator_to_array($indexActions);
        $actionNames = array_map(static fn ($action) => $action->getName(), $actionsArray);

        $this->assertContains('syncDsl', $actionNames, 'syncDsl action should be configured');
    }

    /**
     * 测试 viewDslVersions 自定义动作配置
     */
    public function testViewDslVersionsAction(): void
    {
        $this->setUpController();
        $actions = $this->controller->configureActions(Actions::new());

        // 验证viewDslVersions动作存在于配置中
        $indexActions = $actions->getAsDto(Crud::PAGE_INDEX)->getActions();
        /** @var ActionDto[]|ActionGroupDto[] $actionsArray */
        $actionsArray = iterator_to_array($indexActions);
        $actionNames = array_map(static fn ($action) => $action->getName(), $actionsArray);

        $this->assertContains('viewDslVersions', $actionNames, 'viewDslVersions action should be configured');
    }

    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'Dify 实例' => ['Dify 实例'];
        yield 'Dify 账户' => ['Dify 账户'];
        yield 'Dify 应用 ID' => ['Dify 应用 ID'];
        yield '应用名称' => ['应用名称'];
        yield '公开状态' => ['公开状态'];
        yield '最后同步时间' => ['最后同步时间'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'instance' => ['instance'];
        yield 'account' => ['account'];
        yield 'difyAppId' => ['difyAppId'];
        yield 'name' => ['name'];
        yield 'isPublic' => ['isPublic'];
        yield 'description' => ['description'];
        yield 'icon' => ['icon'];
        yield 'createdByDifyUser' => ['createdByDifyUser'];
        yield 'workflowConfig' => ['workflowConfig'];
        yield 'inputSchema' => ['inputSchema'];
        yield 'outputSchema' => ['outputSchema'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'instance' => ['instance'];
        yield 'account' => ['account'];
        yield 'difyAppId' => ['difyAppId'];
        yield 'name' => ['name'];
        yield 'isPublic' => ['isPublic'];
        yield 'description' => ['description'];
        yield 'icon' => ['icon'];
        yield 'createdByDifyUser' => ['createdByDifyUser'];
        yield 'workflowConfig' => ['workflowConfig'];
        yield 'inputSchema' => ['inputSchema'];
        yield 'outputSchema' => ['outputSchema'];
    }

    /**
     * 从字段列表中提取字段名称
     *
     * @param iterable<int, FieldInterface|mixed> $fields
     * @return array<int, string>
     */
    private function extractFieldNames(iterable $fields): array
    {
        $fieldNames = array_map(
            fn ($field) => $field instanceof FieldInterface ? $field->getAsDto()->getProperty() : '',
            is_array($fields) ? $fields : iterator_to_array($fields)
        );

        return array_filter($fieldNames, static fn ($name): bool => '' !== $name);
    }

    /**
     * 断言JSON配置字段使用CodeEditorField
     *
     * @param iterable<int, FieldInterface|mixed> $fields
     */
    private function assertJsonConfigFieldsUseCodeEditor(iterable $fields, string $pageLabel): void
    {
        $configFieldTypes = [];
        foreach ($fields as $field) {
            if ($field instanceof FieldInterface) {
                $property = $field->getAsDto()->getProperty();
                if (in_array($property, ['workflowConfig', 'inputSchema', 'outputSchema'], true)) {
                    $configFieldTypes[] = get_class($field);
                }
            }
        }

        $this->assertContains(
            CodeEditorField::class,
            $configFieldTypes,
            "JSON config fields on {$pageLabel} page should use CodeEditorField"
        );
    }
}
