<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\DslManagementTrait;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Trait\SitePreviewTrait;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;

/**
 * @extends AbstractCrudController<DifySite>
 */
#[AdminCrud(routePath: '/dify/dify-site', routeName: 'dify_dify_site')]
final class DifySiteCrudController extends AbstractCrudController
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
        return DifySite::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dify站点')
            ->setEntityLabelInPlural('Dify站点管理')
            ->setDefaultSort(['updateTime' => 'DESC'])
            ->setPageTitle('index', 'Dify站点管理')
            ->setPageTitle('new', '新建Dify站点')
            ->setPageTitle('edit', '编辑Dify站点')
            ->setPageTitle('detail', 'Dify站点详情')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $previewAction = Action::new('preview', '预览站点', 'fas fa-external-link-alt')
            ->linkToUrl(
                function (DifySite $entity): string {
                    $siteUrl = $entity->getSiteUrl();
                    $title = $entity->getTitle();

                    return "javascript:openSitePreview('{$siteUrl}', '{$title}', 'site')";
                }
            )
            ->setHtmlAttributes(
                [
                    'onclick' => 'return false',
                ]
            )
            ->displayIf(
                function (DifySite $entity): bool {
                    $siteUrl = trim($entity->getSiteUrl());

                    return $entity->isEnabled() && '' !== $siteUrl;
                }
            )
        ;

        // 添加DSL管理功能
        $actions = $this->configureDslActions($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_DETAIL, $previewAction)
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
        ;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $this->configureSitePreviewAssets($assets);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
            ->add('isEnabled')
            ->add('publishTime')
            ->add('createTime')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
        ;

        yield TextField::new('siteId', '站点ID')
            ->setColumns(4)
            ->setHelp('Dify系统中的站点唯一标识')
        ;

        yield TextField::new('title', '站点标题')
            ->setColumns(6)
            ->setRequired(true)
        ;

        yield TextareaField::new('description', '站点描述')
            ->setColumns(12)
            ->hideOnIndex()
        ;

        yield UrlField::new('siteUrl', '站点URL')
            ->setColumns(8)
            ->setRequired(true)
        ;

        yield BooleanField::new('isEnabled', '启用状态')
            ->setColumns(2)
        ;

        yield TextField::new('defaultLanguage', '默认语言')
            ->setColumns(3)
            ->hideOnIndex()
        ;

        yield TextField::new('theme', '主题')
            ->setColumns(3)
            ->hideOnIndex()
        ;

        yield TextField::new('copyright', '版权信息')
            ->setColumns(6)
            ->hideOnIndex()
        ;

        yield TextareaField::new('privacyPolicy', '隐私政策')
            ->setColumns(6)
            ->hideOnIndex()
            ->onlyOnDetail()
        ;

        yield TextareaField::new('disclaimer', '免责声明')
            ->setColumns(6)
            ->hideOnIndex()
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('customDomain', '自定义域名配置')
            ->setLanguage('javascript')
            ->setColumns(6)
            ->hideOnIndex()
            ->onlyOnDetail()
        ;

        yield CodeEditorField::new('customConfig', '自定义配置')
            ->setLanguage('javascript')
            ->setColumns(6)
            ->hideOnIndex()
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('publishTime', '发布时间')
            ->setColumns(4)
            ->hideOnForm()
        ;

        yield DateTimeField::new('lastSyncTime', '最后同步时间')
            ->setColumns(4)
            ->hideOnForm()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setColumns(4)
            ->hideOnForm()
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setColumns(4)
            ->hideOnForm()
            ->onlyOnDetail()
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
