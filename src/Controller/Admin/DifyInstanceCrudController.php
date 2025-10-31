<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;

/**
 * @extends AbstractCrudController<DifyInstance>
 */
#[AdminCrud(routePath: '/dify/dify-instance', routeName: 'dify_dify_instance')]
final class DifyInstanceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifyInstanceRepository $difyInstanceRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DifyInstance::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dify 实例')
            ->setEntityLabelInPlural('Dify 实例管理')
            ->setSearchFields(['name', 'baseUrl', 'description'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', '管理 Dify 实例，配置基础连接信息和状态')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $enableAction = Action::new('enableInstance', '启用')
            ->linkToCrudAction('enableInstance')
            ->displayIf(
                function (DifyInstance $instance): bool {
                    return !$instance->isEnabled();
                }
            )
        ;

        $disableAction = Action::new('disableInstance', '禁用')
            ->linkToCrudAction('disableInstance')
            ->displayIf(
                function (DifyInstance $instance): bool {
                    return $instance->isEnabled();
                }
            )
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $enableAction)
            ->add(Crud::PAGE_INDEX, $disableAction)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('name', '实例名称')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('用于识别 Dify 实例的名称')
        ;

        yield UrlField::new('baseUrl', '基础URL')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('Dify 实例的基础访问地址，如：https://cloud.dify.ai')
        ;

        yield TextareaField::new('description', '描述')
            ->setRequired(false)
            ->setColumns(12)
            ->setNumOfRows(3)
            ->setHelp('可选的实例描述信息')
            ->hideOnIndex()
        ;

        yield BooleanField::new('isEnabled', '启用状态')
            ->setColumns(6)
            ->setHelp('是否启用此实例')
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
            ->add(TextFilter::new('name', '实例名称'))
            ->add(TextFilter::new('baseUrl', '基础URL'))
            ->add(BooleanFilter::new('isEnabled', '启用状态'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    #[AdminAction(routeName: 'dify_dify_instance_enable', routePath: '{entityId}/enable')]
    public function enableInstance(AdminContext $context): Response
    {
        // 从request获取entityId，避免依赖getEntity()
        $entityId = $context->getRequest()->get('entityId');
        if (null === $entityId || '' === $entityId) {
            $this->addFlash('danger', '实例ID不能为空');

            return $this->redirect($this->generateUrl('admin'));
        }

        $entity = $this->difyInstanceRepository->find($entityId);

        if (!$entity instanceof DifyInstance) {
            $this->addFlash('danger', '实例不存在或无效');

            return $this->redirect($this->generateUrl('admin'));
        }

        $entity->setIsEnabled(true);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('实例 "%s" 已启用', $entity->getName()));

        return $this->redirectToIndex();
    }

    #[AdminAction(routeName: 'dify_dify_instance_disable', routePath: '{entityId}/disable')]
    public function disableInstance(AdminContext $context): Response
    {
        // 从request获取entityId，避免依赖getEntity()
        $entityId = $context->getRequest()->get('entityId');
        if (null === $entityId || '' === $entityId) {
            $this->addFlash('danger', '实例ID不能为空');

            return $this->redirect($this->generateUrl('admin'));
        }

        $entity = $this->difyInstanceRepository->find($entityId);

        if (!$entity instanceof DifyInstance) {
            $this->addFlash('danger', '实例不存在或无效');

            return $this->redirect($this->generateUrl('admin'));
        }

        $entity->setIsEnabled(false);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('实例 "%s" 已禁用', $entity->getName()));

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): Response
    {
        $referer = $this->getContext()?->getRequest()?->headers->get('referer');
        if (null === $referer || '' === $referer) {
            $referer = $this->generateUrl(
                'admin',
                [
                    'crudAction' => 'index',
                    'crudControllerFqcn' => self::class,
                ]
            );
        }

        return $this->redirect($referer);
    }
}
