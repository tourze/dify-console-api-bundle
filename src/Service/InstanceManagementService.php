<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\DTO\CreateInstanceRequest;
use Tourze\DifyConsoleApiBundle\DTO\UpdateInstanceRequest;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;

/**
 * Dify实例管理服务
 *
 * 负责Dify实例的创建、更新、启用/禁用等管理操作
 */
#[WithMonologChannel(channel: 'dify_console_api')]
final class InstanceManagementService implements InstanceManagementServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DifyInstanceRepository $instanceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createInstance(CreateInstanceRequest $request): DifyInstance
    {
        $this->logger->info(
            '开始创建Dify实例',
            [
                'name' => $request->name,
                'baseUrl' => $request->baseUrl,
            ]
        );

        $instance = new DifyInstance();
        $instance->setName($request->name);
        $instance->setBaseUrl($request->baseUrl);
        $instance->setDescription($request->description);
        $instance->setIsEnabled(true);

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->persist($instance);
            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                'Dify实例创建成功',
                [
                    'instanceId' => $instance->getId(),
                    'name' => $instance->getName(),
                ]
            );

            return $instance;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                'Dify实例创建失败',
                [
                    'name' => $request->name,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    public function updateInstance(int $instanceId, UpdateInstanceRequest $request): DifyInstance
    {
        $this->logger->info(
            '开始更新Dify实例',
            [
                'instanceId' => $instanceId,
            ]
        );

        $instance = $this->instanceRepository->find($instanceId);
        if (null === $instance) {
            throw new \InvalidArgumentException("Dify实例不存在: {$instanceId}");
        }

        if (null !== $request->name) {
            $instance->setName($request->name);
        }

        if (null !== $request->baseUrl) {
            $instance->setBaseUrl($request->baseUrl);
        }

        if (null !== $request->description) {
            $instance->setDescription($request->description);
        }

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                'Dify实例更新成功',
                [
                    'instanceId' => $instance->getId(),
                    'name' => $instance->getName(),
                ]
            );

            return $instance;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                'Dify实例更新失败',
                [
                    'instanceId' => $instanceId,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    public function enableInstance(int $instanceId): bool
    {
        return $this->updateInstanceStatus($instanceId, true);
    }

    public function disableInstance(int $instanceId): bool
    {
        return $this->updateInstanceStatus($instanceId, false);
    }

    /**
     * 更新实例状态的通用方法
     */
    private function updateInstanceStatus(int $instanceId, bool $isActive): bool
    {
        $action = $isActive ? '启用' : '禁用';
        $this->logger->info(
            "开始{$action}Dify实例",
            [
                'instanceId' => $instanceId,
                'isActive' => $isActive,
            ]
        );

        $instance = $this->instanceRepository->find($instanceId);
        if (null === $instance) {
            $this->logger->warning(
                "尝试{$action}不存在的Dify实例",
                [
                    'instanceId' => $instanceId,
                ]
            );

            return false;
        }

        if ($instance->isEnabled() === $isActive) {
            $this->logger->info(
                'Dify实例状态无需改变',
                [
                    'instanceId' => $instanceId,
                    'currentStatus' => $isActive ? '已启用' : '已禁用',
                ]
            );

            return true;
        }

        $instance->setIsEnabled($isActive);

        try {
            $this->entityManager->beginTransaction();

            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info(
                "Dify实例{$action}成功",
                [
                    'instanceId' => $instanceId,
                    'newStatus' => $isActive ? '已启用' : '已禁用',
                ]
            );

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error(
                "Dify实例{$action}失败",
                [
                    'instanceId' => $instanceId,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * @return DifyInstance[]
     */
    public function getEnabledInstances(): array
    {
        $this->logger->debug('获取所有启用的Dify实例');

        return $this->instanceRepository->findBy(['isEnabled' => true]);
    }

    /**
     * @return DifyInstance[]
     */
    public function getAllInstances(): array
    {
        $this->logger->debug('获取所有Dify实例');

        return $this->instanceRepository->findAll();
    }
}
