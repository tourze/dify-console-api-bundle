<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;

/**
 * @extends AbstractCrudController<AppDslVersion>
 */
#[AdminCrud(routePath: '/dify/app-dsl-version', routeName: 'dify_app_dsl_version')]
final class AppDslVersionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AppDslVersion::class;
    }

    /**
     * @return iterable<FieldInterface>
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();

        yield AssociationField::new('app', '应用')
            ->setCrudController(BaseApp::class)
            ->setRequired(true)
            ->setHelp('关联的应用实例')
        ;

        yield IntegerField::new('version', '版本号')
            ->setHelp('DSL版本号，自动递增')
        ;

        yield TextField::new('dslHash', 'DSL哈希')
            ->hideOnIndex()
            ->setHelp('DSL内容的SHA256哈希值，用于检测变更')
        ;

        yield CodeEditorField::new('dslContent', 'DSL内容')
            ->hideOnIndex()
            ->hideOnDetail()
            ->onlyOnForms()
            ->setLanguage('javascript')
            ->setHelp('DSL配置内容（JSON格式）')
            ->formatValue(function ($value) {
                if (is_array($value)) {
                    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }

                return $value;
            })
        ;

        yield DateTimeField::new('syncTime', '同步时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('从Dify平台同步的时间')
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Crud::PAGE_DETAIL)
            // 禁止创建和编辑，DSL版本只能通过同步创建
            ->disable(Crud::PAGE_NEW, Crud::PAGE_EDIT)
            // 禁止删除操作，保留版本历史
            ->disable(Action::DELETE)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('DSL版本')
            ->setEntityLabelInPlural('DSL版本管理')
            ->setSearchFields(['app.name', 'version', 'dslHash'])
            ->setDefaultSort(['syncTime' => 'DESC'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('app', '应用'))
            ->add(TextFilter::new('version', '版本号'))
            ->add(DateTimeFilter::new('syncTime', '同步时间'))
        ;
    }
}
