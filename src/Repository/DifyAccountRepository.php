<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * Dify 账户仓储类
 *
 * @extends ServiceEntityRepository<DifyAccount>
 * @phpstan-method DifyAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @phpstan-method DifyAccount|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @phpstan-method DifyAccount[] findAll()
 * @phpstan-method DifyAccount[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: DifyAccount::class)]
class DifyAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DifyAccount::class);
    }

    /**
     * 根据实例 ID 查找所有账户
     *
     * @param  int $instanceId 实例 ID
     * @return array<DifyAccount> 账户实体数组
     */
    public function findByInstance(int $instanceId): array
    {
        /**
         * @var array<DifyAccount>
         */
        return $this->createQueryBuilder('account')
            ->innerJoin('account.instance', 'inst')
            ->andWhere('inst.id = :instanceId')
            ->setParameter('instanceId', $instanceId)
            ->orderBy('account.updateTime', 'DESC')
            ->addOrderBy('account.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找启用的账户
     *
     * @param  int|null $instanceId 实例 ID，为 null 时查找所有实例
     * @return array<DifyAccount> 启用的账户实体数组
     */
    public function findEnabledAccounts(?int $instanceId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('account')
            ->andWhere('account.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('account.updateTime', 'DESC')
            ->addOrderBy('account.id', 'DESC')
        ;

        if (null !== $instanceId) {
            $queryBuilder
                ->innerJoin('account.instance', 'inst')
                ->andWhere('inst.id = :instanceId')
                ->setParameter('instanceId', $instanceId)
            ;
        }

        /**
         * @var array<DifyAccount>
         */
        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据邮箱和实例 ID 查找账户
     *
     * @param  string $email      邮箱地址
     * @param  int    $instanceId 实例
     *                            ID
     * @return DifyAccount|null 账户实体，未找到时返回 null
     */
    public function findByEmail(string $email, int $instanceId): ?DifyAccount
    {
        /**
         * @var DifyAccount|null
         */
        return $this->createQueryBuilder('account')
            ->innerJoin('account.instance', 'inst')
            ->andWhere('account.email = :email')
            ->andWhere('inst.id = :instanceId')
            ->setParameter('email', $email)
            ->setParameter('instanceId', $instanceId)
            ->orderBy('account.updateTime', 'DESC')
            ->addOrderBy('account.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 查找令牌已过期的账户
     *
     * @return array<DifyAccount> 令牌已过期的账户实体数组
     */
    public function findExpiredTokens(): array
    {
        $now = new \DateTimeImmutable();

        /**
         * @var array<DifyAccount>
         */
        return $this->createQueryBuilder('a')
            ->where('a.tokenExpiresTime IS NULL OR a.tokenExpiresTime <= :now')
            ->andWhere('a.isEnabled = true')
            ->setParameter('now', $now)
            ->orderBy('a.tokenExpiresTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 启用账户
     *
     * @param  int $id 账户 ID
     * @throws EntityNotFoundException 当账户不存在时抛出
     */
    public function enableAccount(int $id): void
    {
        $account = $this->find($id);
        if (null === $account) {
            throw new EntityNotFoundException("Account with ID {$id} not found");
        }

        $account->setIsEnabled(true);
        $this->getEntityManager()->flush();
    }

    /**
     * 禁用账户
     *
     * @param  int $id 账户 ID
     * @throws EntityNotFoundException 当账户不存在时抛出
     */
    public function disableAccount(int $id): void
    {
        $account = $this->find($id);
        if (null === $account) {
            throw new EntityNotFoundException("Account with ID {$id} not found");
        }

        $account->setIsEnabled(false);
        $this->getEntityManager()->flush();
    }

    /**
     * 保存实体
     */
    public function save(DifyAccount $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(DifyAccount $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
