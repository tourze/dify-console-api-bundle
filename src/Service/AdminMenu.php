<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Knp\Menu\ItemInterface;
use Tourze\DifyConsoleApiBundle\Controller\Admin\ChatAssistantAppCrudController;
use Tourze\DifyConsoleApiBundle\Controller\Admin\ChatflowAppCrudController;
use Tourze\DifyConsoleApiBundle\Controller\Admin\DifyAccountCrudController;
use Tourze\DifyConsoleApiBundle\Controller\Admin\DifyInstanceCrudController;
use Tourze\DifyConsoleApiBundle\Controller\Admin\DifySiteCrudController;
use Tourze\DifyConsoleApiBundle\Controller\Admin\WorkflowAppCrudController;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('Dify 控制台')) {
            $item->addChild('Dify 控制台')->setExtra('permission', 'ROLE_DIFY_USER');
        }

        $difyConsole = $item->getChild('Dify 控制台');
        if (null !== $difyConsole) {
            $difyConsole->addChild('Dify 实例')
                ->setUri($this->linkGenerator->getCurdListPage(DifyInstanceCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_ADMIN')
            ;

            $difyConsole->addChild('Dify 账号')
                ->setUri($this->linkGenerator->getCurdListPage(DifyAccountCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_ADMIN')
            ;

            $difyConsole->addChild('工作流应用')
                ->setUri($this->linkGenerator->getCurdListPage(WorkflowAppCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_USER')
            ;

            $difyConsole->addChild('聊天流应用')
                ->setUri($this->linkGenerator->getCurdListPage(ChatflowAppCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_USER')
            ;

            $difyConsole->addChild('聊天助手应用')
                ->setUri($this->linkGenerator->getCurdListPage(ChatAssistantAppCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_USER')
            ;

            $difyConsole->addChild('应用站点')
                ->setUri($this->linkGenerator->getCurdListPage(DifySiteCrudController::class))
                ->setExtra('permission', 'ROLE_DIFY_USER')
            ;
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function getMenuItems(): array
    {
        return [
            MenuItem::section('Dify 控制台管理', 'fas fa-robot')->setPermission('ROLE_DIFY_USER'),

            MenuItem::linkToCrud('Dify 实例', 'fas fa-server', DifyInstanceCrudController::getEntityFqcn())
                ->setController(DifyInstanceCrudController::class)
                ->setPermission('ROLE_DIFY_ADMIN')
                ->setBadge(
                    function () {
                        return null; // 可以在这里添加动态计数逻辑
                    }
                ),

            MenuItem::linkToCrud('Dify 账号', 'fas fa-user-cog', DifyAccountCrudController::getEntityFqcn())
                ->setController(DifyAccountCrudController::class)
                ->setPermission('ROLE_DIFY_ADMIN'),

            MenuItem::linkToCrud('工作流应用', 'fas fa-project-diagram', WorkflowAppCrudController::getEntityFqcn())
                ->setController(WorkflowAppCrudController::class)
                ->setPermission('ROLE_DIFY_USER'),

            MenuItem::linkToCrud('聊天流应用', 'fas fa-comments', ChatflowAppCrudController::getEntityFqcn())
                ->setController(ChatflowAppCrudController::class)
                ->setPermission('ROLE_DIFY_USER'),

            MenuItem::linkToCrud('聊天助手应用', 'fas fa-comment-dots', ChatAssistantAppCrudController::getEntityFqcn())
                ->setController(ChatAssistantAppCrudController::class)
                ->setPermission('ROLE_DIFY_USER'),

            MenuItem::linkToCrud('应用站点', 'fas fa-globe', DifySiteCrudController::getEntityFqcn())
                ->setController(DifySiteCrudController::class)
                ->setPermission('ROLE_DIFY_USER'),
        ];
    }

    /**
     * 获取子菜单项（用于嵌套菜单结构）
     *
     * @return array<int, mixed>
     */
    public function getSubMenuItems(): array
    {
        return [
            MenuItem::subMenu('系统配置', 'fas fa-cog')->setSubItems(
                [
                    MenuItem::linkToCrud('Dify 实例', 'fas fa-server', DifyInstanceCrudController::getEntityFqcn())
                        ->setController(DifyInstanceCrudController::class),
                    MenuItem::linkToCrud('Dify 账号', 'fas fa-user-cog', DifyAccountCrudController::getEntityFqcn())
                        ->setController(DifyAccountCrudController::class),
                ]
            )->setPermission('ROLE_DIFY_ADMIN'),

            MenuItem::subMenu('应用管理', 'fas fa-apps')->setSubItems(
                [
                    MenuItem::linkToCrud('工作流应用', 'fas fa-project-diagram', WorkflowAppCrudController::getEntityFqcn())
                        ->setController(WorkflowAppCrudController::class),
                    MenuItem::linkToCrud('聊天流应用', 'fas fa-comments', ChatflowAppCrudController::getEntityFqcn())
                        ->setController(ChatflowAppCrudController::class),
                    MenuItem::linkToCrud('聊天助手应用', 'fas fa-comment-dots', ChatAssistantAppCrudController::getEntityFqcn())
                        ->setController(ChatAssistantAppCrudController::class),
                    MenuItem::linkToCrud('应用站点', 'fas fa-globe', DifySiteCrudController::getEntityFqcn())
                        ->setController(DifySiteCrudController::class),
                ]
            )->setPermission('ROLE_DIFY_USER'),
        ];
    }

    /**
     * 获取仪表盘菜单项
     *
     * @return array<int, mixed>
     */
    public function getDashboardItems(): array
    {
        return [
            MenuItem::linkToRoute('Dify 概览', 'fas fa-chart-pie', 'dify_dashboard')
                ->setPermission('ROLE_DIFY_MANAGER'),
        ];
    }

    /**
     * 获取快捷操作菜单项
     *
     * @return array<int, mixed>
     */
    public function getQuickActionItems(): array
    {
        return [
            MenuItem::linkToCrud('新建 Dify 实例', 'fas fa-plus', DifyInstanceCrudController::getEntityFqcn())
                ->setController(DifyInstanceCrudController::class)
                ->setAction('new')
                ->setPermission('ROLE_DIFY_ADMIN'),

            MenuItem::linkToCrud('新建 Dify 账号', 'fas fa-plus', DifyAccountCrudController::getEntityFqcn())
                ->setController(DifyAccountCrudController::class)
                ->setAction('new')
                ->setPermission('ROLE_DIFY_ADMIN'),
        ];
    }

    /**
     * 获取同步操作菜单项
     *
     * @return array<int, mixed>
     */
    public function getSyncActionItems(): array
    {
        return [
            MenuItem::linkToRoute('同步所有应用', 'fas fa-sync', 'admin_dify_sync_apps')
                ->setPermission('ROLE_DIFY_ADMIN'),

            MenuItem::linkToRoute('测试实例连接', 'fas fa-network-wired', 'admin_dify_test_connections')
                ->setPermission('ROLE_DIFY_ADMIN'),
        ];
    }
}
