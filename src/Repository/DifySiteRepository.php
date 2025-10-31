<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DifySite>
 * @phpstan-method DifySite|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method DifySite|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method DifySite[] findAll()
 * @phpstan-method DifySite[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: DifySite::class)]
class DifySiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DifySite::class);
    }

    /**
     * 根据站点ID查找站点
     */
    public function findBySiteId(string $siteId): ?DifySite
    {
        return $this->findOneBy(['siteId' => $siteId]);
    }

    /**
     * 获取所有启用的站点
     *
     * @return DifySite[]
     */
    public function findEnabledSites(): array
    {
        return $this->findBy(['isEnabled' => true], ['updateTime' => 'DESC']);
    }

    /**
     * 获取需要同步的站点（最近同步时间超过指定时间间隔）
     *
     * @return DifySite[]
     */
    public function findSitesNeedingSync(\DateTimeImmutable $sinceBefore): array
    {
        /**
         * @var array<DifySite>
         */
        return $this->createQueryBuilder('s')
            ->where('s.lastSyncTime IS NULL OR s.lastSyncTime < :sinceBefore')
            ->setParameter('sinceBefore', $sinceBefore)
            ->orderBy('s.lastSyncTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 保存实体
     */
    public function save(DifySite $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(DifySite $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
