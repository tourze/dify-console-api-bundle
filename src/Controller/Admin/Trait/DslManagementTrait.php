<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin\Trait;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;
use Tourze\DifyConsoleApiBundle\Service\DslSyncServiceInterface;

/**
 * DSL管理Trait - 为应用控制器提供DSL管理功能
 */
trait DslManagementTrait
{
    private AppDslVersionRepository $appDslVersionRepository;

    public function setAppDslVersionRepository(AppDslVersionRepository $repository): void
    {
        $this->appDslVersionRepository = $repository;
    }

    abstract protected function getDslSyncService(): DslSyncServiceInterface;

    /**
     * 创建DSL同步Action
     */
    protected function createSyncDslAction(): Action
    {
        return Action::new('syncDsl', '同步DSL', 'fa fa-sync')
            ->linkToCrudAction('syncDsl')
            ->displayIf(static function ($entity) {
                return $entity instanceof BaseApp;
            })
        ;
    }

    /**
     * 创建查看DSL版本历史Action
     */
    protected function createViewDslVersionsAction(): Action
    {
        return Action::new('viewDslVersions', 'DSL版本', 'fa fa-history')
            ->linkToCrudAction('viewDslVersions')
            ->displayIf(static function ($entity) {
                return $entity instanceof BaseApp;
            })
        ;
    }

    /**
     * 处理DSL同步
     */
    #[AdminAction(routePath: '{entityId}/sync-dsl', routeName: 'sync_dsl')]
    public function syncDsl(AdminContext $context): Response
    {
        $entityDto = $context->getEntity();
        $app = $entityDto->getInstance();

        // 检查应用实例是否存在且类型正确
        if (!($app instanceof BaseApp)) {
            $this->addFlash('danger', '未找到指定的应用或应用类型错误');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => $this->getCrudControllerFqcn(),
            ]);
        }

        try {
            // 获取Dify账号（这里需要根据实际情况获取）
            $account = $this->getDifyAccount($app);

            // 执行同步
            $result = $this->getDslSyncService()->syncAppDsl($app, $account);

            if ($result['success'] && isset($result['version'])) {
                /** @var AppDslVersion $version */
                $version = $result['version'];
                $this->addFlash('success', sprintf(
                    'DSL同步成功！版本: %d, 哈希: %s',
                    $version->getVersion(),
                    substr($version->getDslHash(), 0, 8) . '...'
                ));
            } else {
                $this->addFlash('warning', $result['message'] ?? 'DSL同步完成，但未创建新版本');
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'DSL同步失败: ' . $e->getMessage());
        }

        $url = $context->getRequest()->headers->get('referer')
            ?? $this->generateUrl('admin', [
                'crudAction' => $context->getCrud()?->getCurrentPage() ?? 'index',
                'crudControllerFqcn' => $this->getCrudControllerFqcn(),
            ]);

        return $this->redirect($url);
    }

    /**
     * 查看DSL版本历史
     */
    #[AdminAction(routePath: '{entityId}/view-dsl-versions', routeName: 'view_dsl_versions')]
    public function viewDslVersions(AdminContext $context): Response
    {
        $entityDto = $context->getEntity();
        $app = $entityDto->getInstance();

        // 检查应用实例是否存在且类型正确
        if (!($app instanceof BaseApp)) {
            $this->addFlash('danger', '未找到指定的应用或应用类型错误');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => $this->getCrudControllerFqcn(),
            ]);
        }

        $versions = $this->appDslVersionRepository->findVersionHistoryByApp($app);

        return $this->render('@DifyConsoleApi/admin/dsl/version_list.html.twig', [
            'app' => $app,
            'versions' => $versions,
        ]);
    }

    /**
     * 配置Actions，添加DSL管理功能
     */
    protected function configureDslActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, $this->createSyncDslAction())
            ->add(Crud::PAGE_INDEX, $this->createViewDslVersionsAction())
            ->add(Crud::PAGE_DETAIL, $this->createSyncDslAction())
            ->add(Crud::PAGE_DETAIL, $this->createViewDslVersionsAction())
        ;
    }

    /**
     * 获取Dify账号
     * 需要在使用该Trait的控制器中实现此方法或注入DifyAccountRepository
     */
    abstract protected function getDifyAccount(BaseApp $app): DifyAccount;

    /**
     * 获取控制器FQCN
     */
    protected function getCrudControllerFqcn(): string
    {
        return $this::class;
    }
}
