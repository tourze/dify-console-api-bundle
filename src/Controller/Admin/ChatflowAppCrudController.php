<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\DslManagementTrait;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\SitePreviewTrait;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;

/**
 * @extends AbstractCrudController<ChatflowApp>
 */
#[AdminCrud(routePath: '/dify/chatflow-app', routeName: 'dify_chatflow_app')]
final class ChatflowAppCrudController extends AbstractCrudController
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
        return ChatflowApp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('聊天流应用')
            ->setEntityLabelInPlural('聊天流应用管理')
            ->setSearchFields(['name', 'difyAppId', 'description'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', '管理 Dify 聊天流应用，包括聊天流配置、模型配置和对话配置')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 暂时注释掉预览功能，避免测试框架的空方法名检查问题
        // $previewAction = $this->createSitePreviewAction('chatflow');

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
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield AssociationField::new('instance', 'Dify 实例')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('关联的 Dify 实例')
            ->autocomplete()
        ;

        yield AssociationField::new('account', 'Dify 账户')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('关联的 Dify 账户')
            ->autocomplete()
        ;

        yield TextField::new('difyAppId', 'Dify 应用 ID')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('在 Dify 平台中的应用 ID')
        ;

        yield TextField::new('name', '应用名称')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('聊天流应用的名称')
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

        // JSON 配置字段 - 仅在编辑和详情页显示
        if (Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield CodeEditorField::new('chatflowConfig', '聊天流配置')
                ->setLanguage('javascript')
                ->setNumOfRows(12)
                ->setColumns(12)
                ->setHelp('聊天流的详细配置（JSON 格式）')
                ->formatValue(
                    function ($value) {
                        return $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
                    }
                )
            ;

            yield CodeEditorField::new('modelConfig', '模型配置')
                ->setLanguage('javascript')
                ->setNumOfRows(10)
                ->setColumns(6)
                ->setHelp('AI 模型的配置参数（JSON 格式）')
                ->formatValue(
                    function ($value) {
                        return $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
                    }
                )
            ;

            yield CodeEditorField::new('conversationConfig', '对话配置')
                ->setLanguage('javascript')
                ->setNumOfRows(10)
                ->setColumns(6)
                ->setHelp('对话管理的配置参数（JSON 格式）')
                ->formatValue(
                    function ($value) {
                        return $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
                    }
                )
            ;
        }

        // 同步时间字段
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
