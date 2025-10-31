<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * 聊天流应用仓储类
 *
 * @extends ServiceEntityRepository<ChatflowApp>
 * @phpstan-method ChatflowApp|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method ChatflowApp|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method ChatflowApp[] findAll()
 * @phpstan-method ChatflowApp[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: ChatflowApp::class)]
class ChatflowAppRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatflowApp::class);
    }

    /**
     * 根据 Dify 应用 ID、实例 ID 和账户 ID 查找应用
     *
     * @param  string $difyAppId  Dify应用 ID
     * @param  int    $instanceId 实例 ID
     * @param  int    $accountId  账户 ID
     * @return ChatflowApp|null 应用实体，未找到时返回 null
     */
    public function findByDifyAppId(string $difyAppId, int $instanceId, int $accountId): ?ChatflowApp
    {
        /**
         * @var ChatflowApp|null
         */
        return $this->createQueryBuilder('c')
            ->where('c.difyAppId = :difyAppId')
            ->andWhere('c.instance = :instanceId')
            ->andWhere('c.account = :accountId')
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
     * @return array<ChatflowApp> 应用实体数组
     */
    public function findByInstance(int $instanceId): array
    {
        /**
         * @var array<ChatflowApp>
         */
        return $this->createQueryBuilder('c')
            ->where('c.instance = :instanceId')
            ->setParameter('instanceId', $instanceId)
            ->orderBy('c.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据账户 ID 查找所有应用
     *
     * @param  int $accountId 账户 ID
     * @return array<ChatflowApp> 应用实体数组
     */
    public function findByAccount(int $accountId): array
    {
        /**
         * @var array<ChatflowApp>
         */
        return $this->createQueryBuilder('c')
            ->where('c.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('c.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找最近同步的应用
     *
     * @param  \DateTimeImmutable $since 开始时间
     * @return array<ChatflowApp> 应用实体数组
     */
    public function findRecentlySynced(\DateTimeImmutable $since): array
    {
        /**
         * @var array<ChatflowApp>
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
     * 根据模型配置查找应用
     *
     * @param  array<string, mixed> $config 模型配置
     * @return array<ChatflowApp> 匹配的聊天流应用实体数组
     */
    public function findByModelConfig(array $config): array
    {
        if ([] === $config) {
            return [];
        }

        // 暂时简化实现：在PHP中进行JSON过滤，避免Doctrine DQL兼容性问题
        // TODO: 后续可以考虑注册自定义DQL函数或使用原生SQL查询
        $qb = $this->createQueryBuilder('c');

        /**
         * @var array<ChatflowApp>
         */
        $allApps = $qb->orderBy('c.updateTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        // 在PHP中过滤结果
        $filteredApps = [];
        foreach ($allApps as $app) {
            $modelConfig = $app->getModelConfig();
            if (!is_array($modelConfig)) {
                continue;
            }

            if ($this->isConfigMatching($config, $modelConfig)) {
                $filteredApps[] = $app;
            }
        }

        return $filteredApps;
    }

    /**
     * 检查配置是否匹配
     *
     * @param  array<string, mixed> $expectedConfig 期望的配置
     * @param  array<string, mixed> $actualConfig   实际的配置
     * @return bool 是否匹配
     */
    private function isConfigMatching(array $expectedConfig, array $actualConfig): bool
    {
        foreach ($expectedConfig as $key => $expectedValue) {
            if (!array_key_exists($key, $actualConfig)) {
                return false;
            }

            if (!$this->isValueMatching($expectedValue, $actualConfig[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查值是否匹配
     *
     * @param mixed $expectedValue 期望的值
     * @param mixed $actualValue   实际的值
     * @return bool 是否匹配
     */
    private function isValueMatching(mixed $expectedValue, mixed $actualValue): bool
    {
        if (is_scalar($expectedValue)) {
            return $actualValue === $expectedValue;
        }

        return json_encode($actualValue) === json_encode($expectedValue);
    }

    /**
     * 保存实体
     */
    public function save(ChatflowApp $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(ChatflowApp $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
