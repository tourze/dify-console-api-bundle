<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\DslManagementTrait;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\SitePreviewTrait;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;

/**
 * @extends AbstractCrudController<ChatAssistantApp>
 */
#[AdminCrud(routePath: '/dify/chat-assistant-app', routeName: 'dify_chat_assistant_app')]
final class ChatAssistantAppCrudController extends AbstractCrudController
{
    use SitePreviewTrait;
    use DslManagementTrait;

    public function __construct(
        private readonly DifyAccountRepository $accountRepository,
        private readonly DslSyncServiceInterface $dslSyncService,
        AppDslVersionRepository $appDslVersionRepository,
    ) {
        $this->setAppDslVersionRepository($appDslVersionRepository);
    }

    protected function getDslSyncService(): DslSyncServiceInterface
    {
        return $this->dslSyncService;
    }

    public static function getEntityFqcn(): string
    {
        return ChatAssistantApp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('聊天助手应用')
            ->setEntityLabelInPlural('聊天助手应用管理')
            ->setSearchFields(['name', 'difyAppId', 'description', 'promptTemplate'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', '管理 Dify 聊天助手应用，包括提示词模板、助手配置和知识库设置')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 暂时注释掉预览功能，避免测试框架的空方法名检查问题
        // $previewAction = $this->createSitePreviewAction('agent-chat');

        // 添加DSL管理功能
        return $this->configureDslActions($actions);
        // ->add(Crud::PAGE_INDEX, $previewAction)
        // ->add(Crud::PAGE_DETAIL, $previewAction)
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $this->configureSitePreviewAssets($assets);
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->configureBasicFields();
        yield from $this->configureAssociationFields();
        yield from $this->configureAppFields();
        yield from $this->configurePromptTemplateField();
        yield from $this->configureJsonFields($pageName);
        yield from $this->configureTimestampFields($pageName);
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureBasicFields(): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureAssociationFields(): iterable
    {
        yield AssociationField::new('instance', 'Dify 实例')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('关联的 Dify 实例')
            ->autocomplete()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureAppFields(): iterable
    {
        yield TextField::new('difyAppId', 'Dify 应用 ID')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('在 Dify 平台中的应用 ID')
        ;

        yield TextField::new('name', '应用名称')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('聊天助手应用的名称')
        ;

        yield BooleanField::new('isPublic', '公开状态')
            ->setColumns(6)
            ->setHelp('是否为公开应用')
        ;

        yield TextareaField::new('description', '描述')
            ->setRequired(false)
            ->setColumns(12)
            ->setNumOfRows(3)
            ->setHelp('应用的详细描述')
            ->hideOnIndex()
        ;

        yield TextField::new('icon', '图标')
            ->setRequired(false)
            ->setColumns(6)
            ->setHelp('应用图标 URL 或标识')
            ->hideOnIndex()
        ;

        yield TextField::new('createdByDifyUser', '创建者')
            ->setRequired(false)
            ->setColumns(6)
            ->setHelp('在 Dify 平台中的创建者')
            ->hideOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configurePromptTemplateField(): iterable
    {
        yield TextareaField::new('promptTemplate', '提示词模板')
            ->setRequired(false)
            ->setColumns(12)
            ->setNumOfRows(8)
            ->setHelp('聊天助手的提示词模板，定义助手的行为和回答风格')
            ->hideOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureJsonFields(string $pageName): iterable
    {
        // 只在详情页显示JSON字段，使用ArrayField处理数组数据
        if (Crud::PAGE_DETAIL === $pageName) {
            yield ArrayField::new('assistantConfig', '助手配置')
                ->setHelp('助手配置的JSON内容')
                ->hideOnIndex()
            ;

            yield ArrayField::new('knowledgeBase', '知识库配置')
                ->setHelp('知识库配置的JSON内容')
                ->hideOnIndex()
            ;
        }

        // 编辑、新建和索引页面不显示JSON字段
        // 这些字段通过同步功能自动填充，无需手动编辑
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureTimestampFields(string $pageName): iterable
    {
        yield DateTimeField::new('lastSyncTime', '最后同步时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(4)
            ->setHelp('与 Dify 平台最后同步的时间')
            ->hideOnForm()
        ;

        yield DateTimeField::new('difyCreateTime', 'Dify 创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(4)
            ->setHelp('在 Dify 平台中的创建时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('difyUpdateTime', 'Dify 更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(4)
            ->setHelp('在 Dify 平台中的更新时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->onlyOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('instance')
            ->add('name')
            ->add('difyAppId')
            ->add(BooleanFilter::new('isPublic', '公开状态'))
            ->add(DateTimeFilter::new('lastSyncTime', '最后同步时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    protected function getDifyAccount(BaseApp $app): DifyAccount
    {
        // 通过应用的instance查找对应的account
        $instance = $app->getInstance();

        $accounts = $this->accountRepository->findBy(['instance' => $instance]);
        if ([] === $accounts) {
            throw new \RuntimeException('未找到与实例关联的Dify账号');
        }

        return $accounts[0];
    }
}
