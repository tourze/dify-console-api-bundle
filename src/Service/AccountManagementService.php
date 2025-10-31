<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\DTO\CreateAccountRequest;
use Tourze\DifyConsoleApiBundle\DTO\UpdateAccountRequest;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;

/**
 * Dify账号管理服务
 *
 * 负责Dify账号的创建、更新、启用/禁用等管理操作
 */
#[WithMonologChannel(channel: 'dify_console_api')]
final class AccountManagementService implements AccountManagementServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifyAccountRepository $accountRepository,
        private readonly DifyInstanceRepository $instanceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createAccount(CreateAccountRequest $request): DifyAccount
    {
        $this->logger->info(
            '开始创建Dify账号',
            [
                'email' => $request->email,
                'instanceId' => $request->instanceId,
            ]
        );

        // 验证实例是否存在
        $instance = $this->instanceRepository->find($request->instanceId);
        if (null === $instance) {
            throw new \InvalidArgumentException("Dify实例不存在: {$request->instanceId}");
        }

        // 检查同一实例下邮箱是否重复
        $existingAccount = $this->accountRepository->findOneBy(
            [
                'email' => $request->email,
                'instance' => $instance,
            ]
        );

        if (null !== $existingAccount) {
            throw new \InvalidArgumentException("该实例下邮箱已存在: {$request->email}");
        }

        $account = new DifyAccount();
        $account->setEmail($request->email);
        $account->setPassword($request->password);
        $account->setInstance($instance);
        $account->setNickname($request->nickname);
        $account->setIsEnabled($request->isEnabled);

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                'Dify账号创建成功',
                [
                    'accountId' => $account->getId(),
                    'email' => $account->getEmail(),
                    'instanceId' => $instance->getId(),
                ]
            );

            return $account;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                'Dify账号创建失败',
                [
                    'email' => $request->email,
                    'instanceId' => $request->instanceId,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    public function updateAccount(int $accountId, UpdateAccountRequest $request): DifyAccount
    {
        $this->logger->info(
            '开始更新Dify账号',
            [
                'accountId' => $accountId,
            ]
        );

        $account = $this->accountRepository->find($accountId);
        if (null === $account) {
            throw new \InvalidArgumentException("Dify账号不存在: {$accountId}");
        }

        $hasChanges = false;

        if (null !== $request->email && $request->email !== $account->getEmail()) {
            // 检查新邮箱在同一实例下是否重复
            $existingAccount = $this->accountRepository->findOneBy(
                [
                    'email' => $request->email,
                    'instance' => $account->getInstance(),
                ]
            );

            if (null !== $existingAccount && $existingAccount->getId() !== $accountId) {
                throw new \InvalidArgumentException("该实例下邮箱已存在: {$request->email}");
            }

            $account->setEmail($request->email);
            $hasChanges = true;
        }

        if (null !== $request->password) {
            $account->setPassword($request->password);
            $hasChanges = true;
        }

        if (null !== $request->nickname) {
            $account->setNickname($request->nickname);
            $hasChanges = true;
        }

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                'Dify账号更新成功',
                [
                    'accountId' => $account->getId(),
                    'email' => $account->getEmail(),
                    'hasChanges' => $hasChanges,
                ]
            );

            return $account;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                'Dify账号更新失败',
                [
                    'accountId' => $accountId,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    public function enableAccount(int $accountId): bool
    {
        return $this->updateAccountStatus($accountId, true);
    }

    public function disableAccount(int $accountId): bool
    {
        return $this->updateAccountStatus($accountId, false);
    }

    /**
     * 更新账号状态的通用方法
     */
    private function updateAccountStatus(int $accountId, bool $isActive): bool
    {
        $action = $isActive ? '启用' : '禁用';
        $this->logger->info(
            "开始{$action}Dify账号",
            [
                'accountId' => $accountId,
                'isActive' => $isActive,
            ]
        );

        $account = $this->accountRepository->find($accountId);
        if (null === $account) {
            $this->logger->warning(
                "尝试{$action}不存在的Dify账号",
                [
                    'accountId' => $accountId,
                ]
            );

            return false;
        }

        if ($account->isEnabled() === $isActive) {
            $this->logger->info(
                'Dify账号状态无需改变',
                [
                    'accountId' => $accountId,
                    'currentStatus' => $isActive ? '已启用' : '已禁用',
                ]
            );

            return true;
        }

        $account->setIsEnabled($isActive);

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                "Dify账号{$action}成功",
                [
                    'accountId' => $accountId,
                    'newStatus' => $isActive ? '已启用' : '已禁用',
                ]
            );

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                "Dify账号{$action}失败",
                [
                    'accountId' => $accountId,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    public function getAccountsByInstance(int $instanceId): array
    {
        $this->logger->debug(
            '获取指定实例的所有Dify账号',
            [
                'instanceId' => $instanceId,
            ]
        );

        $instance = $this->instanceRepository->find($instanceId);
        if (null === $instance) {
            throw new \InvalidArgumentException("Dify实例不存在: {$instanceId}");
        }

        return $this->accountRepository->findBy(['instance' => $instance]);
    }

    public function getEnabledAccounts(?int $instanceId = null): array
    {
        $this->logger->debug(
            '获取启用的Dify账号',
            [
                'instanceId' => $instanceId,
            ]
        );

        $criteria = ['isEnabled' => true];

        if (null !== $instanceId) {
            $instance = $this->instanceRepository->find($instanceId);
            if (null === $instance) {
                throw new \InvalidArgumentException("Dify实例不存在: {$instanceId}");
            }
            $criteria['instance'] = $instance;
        }

        return $this->accountRepository->findBy($criteria);
    }

    /**
     * @return DifyAccount[]
     */
    public function getAllAccounts(): array
    {
        $this->logger->debug('获取所有Dify账号');

        return $this->accountRepository->findAll();
    }
}
