<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * 聊天助手应用仓储类
 *
 * @extends ServiceEntityRepository<ChatAssistantApp>
 * @phpstan-method ChatAssistantApp|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method ChatAssistantApp|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method ChatAssistantApp[] findAll()
 * @phpstan-method ChatAssistantApp[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: ChatAssistantApp::class)]
class ChatAssistantAppRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatAssistantApp::class);
    }

    /**
     * 根据 Dify 应用 ID、实例 ID 和账户 ID 查找应用
     *
     * @param  string $difyAppId  Dify应用 ID
     * @param  int    $instanceId 实例 ID
     * @param  int    $accountId  账户 ID
     * @return ChatAssistantApp|null 应用实体，未找到时返回 null
     */
    public function findByDifyAppId(string $difyAppId, int $instanceId, int $accountId): ?ChatAssistantApp
    {
        /** @var ChatAssistantApp|null */
        return $this->createQueryBuilder('a')
            ->where('a.difyAppId = :difyAppId')
            ->andWhere('a.instance = :instanceId')
            ->andWhere('a.account = :accountId')
            ->setParameter('difyAppId', $difyAppId)
            ->setParameter('instanceId', $instanceId)
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据实例 ID 查找所有应用
     *
     * @param  int $instanceId 实例 ID
     * @return array<ChatAssistantApp> 应用实体数组
     */
    public function findByInstance(int $instanceId): array
    {
        /** @var array<ChatAssistantApp> */
        return $this->createQueryBuilder('a')
            ->where('a.instance = :instanceId')
            ->setParameter('instanceId', $instanceId)
            ->orderBy('a.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据账户 ID 查找所有应用
     *
     * @param  int $accountId 账户 ID
     * @return array<ChatAssistantApp> 应用实体数组
     */
    public function findByAccount(int $accountId): array
    {
        /** @var array<ChatAssistantApp> */
        return $this->createQueryBuilder('a')
            ->where('a.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('a.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找最近同步的应用
     *
     * @param  \DateTimeImmutable $since 开始时间
     * @return array<ChatAssistantApp> 应用实体数组
     */
    public function findRecentlySynced(\DateTimeImmutable $since): array
    {
        /**
         * @var array<ChatAssistantApp>
         */
        return $this->createQueryBuilder('a')
            ->where('a.lastSyncTime >= :since')
            ->setParameter('since', $since)
            ->orderBy('a.lastSyncTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据提示模板查找应用
     *
     * @param  string $template 提示模板内容
     * @return array<ChatAssistantApp> 匹配的聊天助手应用实体数组
     */
    public function findByPromptTemplate(string $template): array
    {
        if ('' === $template) {
            return [];
        }

        /**
         * @var array<ChatAssistantApp>
         */
        return $this->createQueryBuilder('c')
            ->where('c.promptTemplate LIKE :template')
            ->setParameter('template', "%{$template}%")
            ->orderBy('c.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 保存实体
     */
    public function save(ChatAssistantApp $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(ChatAssistantApp $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
