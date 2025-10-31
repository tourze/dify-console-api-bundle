<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Service\AppSyncServiceInterface;

/**
 * @extends AbstractCrudController<DifyAccount>
 */
#[AdminCrud(routePath: '/dify/dify-account', routeName: 'dify_dify_account')]
final class DifyAccountCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifyAccountRepository $accountRepository,
        private readonly AppSyncServiceInterface $appSyncService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DifyAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dify 账号')
            ->setEntityLabelInPlural('Dify 账号管理')
            ->setSearchFields(['email', 'nickname'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', '管理 Dify 账号信息，包括登录凭据和访问令牌')
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // 由于 instanceId 是数字字段，不是关联字段，这里不需要 JOIN
    }

    public function configureActions(Actions $actions): Actions
    {
        $enableAction = Action::new('enableAccount', '启用')
            ->linkToCrudAction('enableAccount')
            ->setIcon('fa fa-check-circle')
            ->displayIf(
                function (DifyAccount $account): bool {
                    return !$account->isEnabled();
                }
            )
        ;

        $disableAction = Action::new('disableAccount', '禁用')
            ->linkToCrudAction('disableAccount')
            ->setIcon('fa fa-ban')
            ->displayIf(
                function (DifyAccount $account): bool {
                    return $account->isEnabled();
                }
            )
        ;

        $syncAction = Action::new('syncAccount', '手动同步')
            ->linkToCrudAction('syncAccount')
            ->setIcon('fa fa-sync')
            ->displayIf(
                function (DifyAccount $account): bool {
                    return $account->isEnabled();
                }
            )
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $enableAction)
            ->add(Crud::PAGE_INDEX, $disableAction)
            ->add(Crud::PAGE_INDEX, $syncAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                function (Action $action) {
                    return $action->setIcon('fa fa-eye')->setLabel('查看');
                }
            )
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->configureBasicFields();
        yield from $this->configureInstanceField();
        yield from $this->configureAccountFields($pageName);
        yield from $this->configureTokenFields($pageName);
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
    private function configureInstanceField(): iterable
    {
        yield AssociationField::new('instance', 'Dify 实例')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('选择关联的 Dify 实例')
            ->autocomplete()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureAccountFields(string $pageName): iterable
    {
        yield EmailField::new('email', '邮箱')
            ->setRequired(true)
            ->setColumns(6)
            ->setHelp('Dify 登录邮箱地址')
        ;

        if (Crud::PAGE_NEW === $pageName) {
            yield TextField::new('password', '密码')
                ->setFormType(PasswordType::class)
                ->setRequired(true)
                ->setColumns(6)
                ->setHelp('Dify 登录密码')
            ;
        }

        yield TextField::new('nickname', '昵称')
            ->setRequired(false)
            ->setColumns(6)
            ->setHelp('可选的用户昵称')
        ;

        yield BooleanField::new('isEnabled', '启用状态')
            ->setColumns(6)
            ->setHelp('是否启用此账号')
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureTokenFields(string $pageName): iterable
    {
        if (Crud::PAGE_DETAIL === $pageName) {
            yield TextField::new('accessToken', '访问令牌')
                ->formatValue(
                    function ($value) {
                        if (null === $value || '' === $value) {
                            return '未设置';
                        }

                        if (!is_string($value)) {
                            return '令牌格式错误';
                        }

                        return substr($value, 0, 20) . '...';
                    }
                )
                ->setHelp('用于访问 Dify API 的令牌（已隐藏）')
            ;

            yield DateTimeField::new('tokenExpiresTime', '令牌过期时间')
                ->setFormat('yyyy-MM-dd HH:mm:ss')
                ->setHelp('访问令牌的过期时间')
            ;
        }
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function configureTimestampFields(string $pageName): iterable
    {
        yield DateTimeField::new('lastLoginTime', '最后登录时间')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('最后一次成功登录的时间')
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
            ->add('email')
            ->add('nickname')
            ->add(BooleanFilter::new('isEnabled', '启用状态'))
            ->add(DateTimeFilter::new('lastLoginTime', '最后登录时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    #[AdminAction(routeName: 'dify_dify_account_enable', routePath: '{entityId}/enable')]
    public function enableAccount(): Response
    {
        // 从路径参数获取实体ID
        $entityId = $this->getContext()?->getRequest()?->attributes->get('entityId');

        if (null === $entityId) {
            $this->addFlash('danger', '缺少实体ID参数');

            return $this->redirectToIndex();
        }

        // 直接从数据库查找实体
        $entity = $this->accountRepository->find($entityId);

        if (null === $entity) {
            $this->addFlash('danger', '无法找到指定的账号记录');

            return $this->redirectToIndex();
        }

        $entity->setIsEnabled(true);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('账号 "%s" 已启用', $entity->getEmail()));

        return $this->redirectToIndex();
    }

    #[AdminAction(routeName: 'dify_dify_account_disable', routePath: '{entityId}/disable')]
    public function disableAccount(): Response
    {
        // 从路径参数获取实体ID
        $entityId = $this->getContext()?->getRequest()?->attributes->get('entityId');

        if (null === $entityId) {
            $this->addFlash('danger', '缺少实体ID参数');

            return $this->redirectToIndex();
        }

        // 直接从数据库查找实体
        $entity = $this->accountRepository->find($entityId);

        if (null === $entity) {
            $this->addFlash('danger', '无法找到指定的账号记录');

            return $this->redirectToIndex();
        }

        $entity->setIsEnabled(false);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('账号 "%s" 已禁用', $entity->getEmail()));

        return $this->redirectToIndex();
    }

    #[AdminAction(routeName: 'dify_dify_account_sync', routePath: '{entityId}/sync')]
    public function syncAccount(): Response
    {
        $entity = $this->getEntityFromRequest();
        if (null === $entity) {
            return $this->redirectToIndex();
        }

        if (!$entity->isEnabled()) {
            $this->addFlash('warning', '只能同步已启用的账号');

            return $this->redirectToIndex();
        }

        return $this->performSync($entity);
    }

    private function getEntityFromRequest(): ?DifyAccount
    {
        $entityId = $this->getContext()?->getRequest()?->attributes->get('entityId');

        if (null === $entityId) {
            $this->addFlash('danger', '缺少实体ID参数');

            return null;
        }

        $entity = $this->accountRepository->find($entityId);

        if (null === $entity) {
            $this->addFlash('danger', '无法找到指定的账号记录');

            return null;
        }

        return $entity;
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

    private function performSync(DifyAccount $entity): Response
    {
        try {
            $startTime = microtime(true);

            $instanceId = $entity->getInstance()->getId();
            if (null === $instanceId) {
                throw new \InvalidArgumentException('关联的 Dify 实例ID不能为空');
            }

            $syncStats = $this->appSyncService->syncApps(
                instanceId: $instanceId,
                accountId: $entity->getId(),
                appType: null
            );

            $processingTime = microtime(true) - $startTime;
            $this->handleSyncResult($entity, $syncStats, $processingTime);
        } catch (\Exception $e) {
            $this->addFlash('danger', '同步失败: ' . $e->getMessage());
        }

        return $this->redirectToIndex();
    }

    /**
     * @param array<string, mixed> $syncStats
     */
    private function handleSyncResult(DifyAccount $entity, array $syncStats, float $processingTime): void
    {
        $errorCount = is_numeric($syncStats['errors'] ?? 0) ? (int) ($syncStats['errors'] ?? 0) : 0;
        $syncedApps = is_numeric($syncStats['synced_apps'] ?? 0) ? (int) ($syncStats['synced_apps'] ?? 0) : 0;
        $syncedSites = is_numeric($syncStats['synced_sites'] ?? 0) ? (int) ($syncStats['synced_sites'] ?? 0) : 0;
        $createdSites = is_numeric($syncStats['created_sites'] ?? 0) ? (int) ($syncStats['created_sites'] ?? 0) : 0;
        $updatedSites = is_numeric($syncStats['updated_sites'] ?? 0) ? (int) ($syncStats['updated_sites'] ?? 0) : 0;

        if ($errorCount > 0) {
            $this->addFlash('warning', sprintf(
                '账号 "%s" 同步完成，但有 %d 个错误。同步了 %d 个应用、%d 个站点，处理时间: %.2f 秒',
                $entity->getEmail(),
                $errorCount,
                $syncedApps,
                $syncedSites,
                $processingTime
            ));

            $this->displayErrorDetails($syncStats);
        } else {
            $siteDetailsMessage = $syncedSites > 0
                ? sprintf('，同步了 %d 个站点（新建 %d 个，更新 %d 个）', $syncedSites, $createdSites, $updatedSites)
                : '，无站点数据';

            $this->addFlash('success', sprintf(
                '账号 "%s" 同步成功！同步了 %d 个应用%s，处理时间: %.2f 秒',
                $entity->getEmail(),
                $syncedApps,
                $siteDetailsMessage,
                $processingTime
            ));
        }
    }

    /**
     * 显示错误详情
     *
     * @param array<string, mixed> $syncStats
     */
    private function displayErrorDetails(array $syncStats): void
    {
        $errorDetails = $syncStats['error_details'] ?? [];
        if (!is_array($errorDetails) || 0 === count($errorDetails)) {
            return;
        }

        foreach ($errorDetails as $errorDetail) {
            if (is_string($errorDetail)) {
                $this->addFlash('danger', '错误详情: ' . $errorDetail);
            }
        }
    }
}
