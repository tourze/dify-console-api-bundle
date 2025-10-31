<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<AppDslVersion>
 * @phpstan-method AppDslVersion|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method AppDslVersion|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method AppDslVersion[] findAll()
 * @phpstan-method AppDslVersion[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: AppDslVersion::class)]
class AppDslVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppDslVersion::class);
    }

    /**
     * 获取应用的最新 DSL 版本
     */
    public function findLatestVersionByApp(BaseApp $app): ?AppDslVersion
    {
        /** @var AppDslVersion|null */
        return $this->createQueryBuilder('v')
            ->where('v.app = :app')
            ->setParameter('app', $app)
            ->orderBy('v.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据应用和版本号查找 DSL 版本
     */
    public function findByAppAndVersion(BaseApp $app, int $version): ?AppDslVersion
    {
        return $this->findOneBy([
            'app' => $app,
            'version' => $version,
        ]);
    }

    /**
     * 获取应用的所有 DSL 版本历史
     *
     * @return AppDslVersion[]
     */
    public function findVersionHistoryByApp(BaseApp $app): array
    {
        /** @var array<AppDslVersion> */
        return $this->createQueryBuilder('v')
            ->where('v.app = :app')
            ->setParameter('app', $app)
            ->orderBy('v.version', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据哈希值查找 DSL 版本
     */
    public function findByAppAndHash(BaseApp $app, string $hash): ?AppDslVersion
    {
        return $this->findOneBy([
            'app' => $app,
            'dslHash' => $hash,
        ]);
    }

    /**
     * 获取应用的下一个版本号
     */
    public function getNextVersionNumber(BaseApp $app): int
    {
        $latestVersion = $this->findLatestVersionByApp($app);

        return null === $latestVersion ? 1 : $latestVersion->getVersion() + 1;
    }

    /**
     * 获取指定时间之后的 DSL 版本
     *
     * @return AppDslVersion[]
     */
    public function findVersionsSince(\DateTimeImmutable $since): array
    {
        /** @var array<AppDslVersion> */
        return $this->createQueryBuilder('v')
            ->where('v.syncTime >= :since')
            ->setParameter('since', $since)
            ->orderBy('v.syncTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 获取包含密钥的版本
     *
     * @return AppDslVersion[]
     */
    public function findVersionsWithSecret(BaseApp $app): array
    {
        /** @var array<AppDslVersion> */
        return $this->createQueryBuilder('v')
            ->where('v.app = :app')
            ->andWhere('v.includeSecret = :includeSecret')
            ->setParameter('app', $app)
            ->setParameter('includeSecret', true)
            ->orderBy('v.version', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 删除应用的旧版本（保留最新的 N 个版本）
     */
    public function deleteOldVersions(BaseApp $app, int $keepCount = 10): int
    {
        $versionsToKeep = $this->createQueryBuilder('v')
            ->select('v.id')
            ->where('v.app = :app')
            ->setParameter('app', $app)
            ->orderBy('v.version', 'DESC')
            ->setMaxResults($keepCount)
            ->getQuery()
            ->getArrayResult()
        ;

        if ([] === $versionsToKeep) {
            return 0;
        }

        $idsToKeep = array_column($versionsToKeep, 'id');

        /** @var int */
        return $this->createQueryBuilder('v')
            ->delete()
            ->where('v.app = :app')
            ->andWhere('v.id NOT IN (:idsToKeep)')
            ->setParameter('app', $app)
            ->setParameter('idsToKeep', $idsToKeep)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * 统计应用的版本数量
     */
    public function countVersionsByApp(BaseApp $app): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.app = :app')
            ->setParameter('app', $app)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
