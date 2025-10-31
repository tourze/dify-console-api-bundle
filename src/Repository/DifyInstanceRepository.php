<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * DifyInstance实体的仓储类
 * 提供CRUD操作和业务特定的查询方法
 *
 * @extends ServiceEntityRepository<DifyInstance>
 * @phpstan-method DifyInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method DifyInstance|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method DifyInstance[] findAll()
 * @phpstan-method DifyInstance[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: DifyInstance::class)]
class DifyInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DifyInstance::class);
    }

    /**
     * 查找所有启用的Dify实例
     *
     * @return array<DifyInstance>
     */
    public function findEnabledInstances(): array
    {
        /**
         * @var array<DifyInstance>
         */
        return $this->createQueryBuilder('d')
            ->where('d.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据基础URL查找Dify实例
     */
    public function findByBaseUrl(string $baseUrl): ?DifyInstance
    {
        /**
         * @var DifyInstance|null
         */
        return $this->createQueryBuilder('d')
            ->where('d.baseUrl = :baseUrl')
            ->setParameter('baseUrl', $baseUrl)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据ID启用Dify实例
     *
     * @throws \InvalidArgumentException 当实例不存在时
     */
    public function enableInstance(int $id): void
    {
        $instance = $this->find($id);
        if (null === $instance) {
            throw new \InvalidArgumentException(sprintf('DifyInstance with ID %d not found', $id));
        }

        $instance->setIsEnabled(true);
        $this->getEntityManager()->flush();
    }

    /**
     * 根据ID禁用Dify实例
     *
     * @throws \InvalidArgumentException 当实例不存在时
     */
    public function disableInstance(int $id): void
    {
        $instance = $this->find($id);
        if (null === $instance) {
            throw new \InvalidArgumentException(sprintf('DifyInstance with ID %d not found', $id));
        }

        $instance->setIsEnabled(false);
        $this->getEntityManager()->flush();
    }

    /**
     * 查找所有实例并按名称排序
     *
     * @return array<DifyInstance>
     */
    public function findAllOrderedByName(): array
    {
        /**
         * @var array<DifyInstance>
         */
        return $this->createQueryBuilder('d')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据启用状态查找实例
     *
     * @return array<DifyInstance>
     */
    public function findByEnabledStatus(bool $isEnabled): array
    {
        /**
         * @var array<DifyInstance>
         */
        return $this->createQueryBuilder('d')
            ->where('d.isEnabled = :enabled')
            ->setParameter('enabled', $isEnabled)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计启用的实例数量
     */
    public function countEnabledInstances(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * 根据启用状态统计实例数量
     */
    public function countByEnabledStatus(bool $isEnabled): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isEnabled = :enabled')
            ->setParameter('enabled', $isEnabled)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * 查找在指定日期范围内创建的实例
     *
     * @return array<DifyInstance>
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /**
         * @var array<DifyInstance>
         */
        return $this->createQueryBuilder('d')
            ->where('d.createTime >= :from')
            ->andWhere('d.createTime <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('d.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据名称搜索实例
     *
     * @return array<DifyInstance>
     */
    public function searchByName(string $searchTerm): array
    {
        /**
         * @var array<DifyInstance>
         */
        return $this->createQueryBuilder('d')
            ->where('d.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 保存实体，可选择是否立即刷新
     */
    public function save(DifyInstance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体，可选择是否立即刷新
     */
    public function remove(DifyInstance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
