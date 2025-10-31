<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;
use Tourze\DifyConsoleApiBundle\Repository\DifySiteRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * DifySiteRepository 测试
 * @internal
 */
#[CoversClass(DifySiteRepository::class)]
#[RunTestsInSeparateProcesses]
final class DifySiteRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): DifySite
    {
        $site = new DifySite();
        $site->setSiteId('test-site-' . uniqid());
        $site->setTitle('Test Site');
        $site->setSiteUrl('https://example.com');
        $site->setIsEnabled(true);

        return $site;
    }

    /**
     * @return DifySiteRepository
     */
    protected function getRepository(): DifySiteRepository
    {
        return self::getService(DifySiteRepository::class);
    }

    public function testFindBySiteId(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试数据
        $site = $this->createNewEntity();
        $entityManager->persist($site);
        $entityManager->flush();

        // 测试查找存在的站点
        $foundSite = $repository->findBySiteId($site->getSiteId());
        $this->assertInstanceOf(DifySite::class, $foundSite);
        $this->assertSame($site->getSiteId(), $foundSite->getSiteId());

        // 测试查找不存在的站点
        $notFound = $repository->findBySiteId('non-existent-site');
        $this->assertNull($notFound);
    }

    public function testFindEnabledSites(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建启用的站点
        $enabledSite1 = $this->createNewEntity();
        $enabledSite1->setIsEnabled(true);

        $enabledSite2 = $this->createNewEntity();
        $enabledSite2->setIsEnabled(true);

        // 创建禁用的站点
        $disabledSite = $this->createNewEntity();
        $disabledSite->setIsEnabled(false);

        $entityManager->persist($enabledSite1);
        $entityManager->persist($enabledSite2);
        $entityManager->persist($disabledSite);
        $entityManager->flush();

        // 测试查找启用的站点
        $enabledSites = $repository->findEnabledSites();
        $this->assertIsArray($enabledSites);

        // 验证所有返回的站点都是启用的
        foreach ($enabledSites as $site) {
            $this->assertTrue($site->isEnabled());
        }

        // 验证至少包含我们创建的启用站点
        $siteIds = array_map(fn ($s) => $s->getSiteId(), $enabledSites);
        $this->assertContains($enabledSite1->getSiteId(), $siteIds);
        $this->assertContains($enabledSite2->getSiteId(), $siteIds);
    }

    public function testFindSitesNeedingSync(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $now = new \DateTimeImmutable();
        $twoDaysAgo = $now->modify('-2 days');

        // 创建需要同步的站点（最后同步时间较早）
        $siteNeedingSync1 = $this->createNewEntity();
        $siteNeedingSync1->setLastSyncTime($twoDaysAgo);

        $siteNeedingSync2 = $this->createNewEntity();
        $siteNeedingSync2->setLastSyncTime($twoDaysAgo->modify('-12 hours'));

        // 创建不需要同步的站点（最近同步过）
        $recentSite = $this->createNewEntity();
        $recentSite->setLastSyncTime($now->modify('-1 hour'));

        // 创建从未同步的站点
        $neverSyncedSite = $this->createNewEntity();
        // lastSyncTime 保持为 null

        $entityManager->persist($siteNeedingSync1);
        $entityManager->persist($siteNeedingSync2);
        $entityManager->persist($recentSite);
        $entityManager->persist($neverSyncedSite);
        $entityManager->flush();

        // 测试查找需要同步的站点（1天前）
        $since = new \DateTimeImmutable('-1 day');
        $sitesNeedingSync = $repository->findSitesNeedingSync($since);

        $this->assertIsArray($sitesNeedingSync);
        $this->assertGreaterThanOrEqual(2, count($sitesNeedingSync)); // 至少包含两个需要同步的站点

        // 验证从未同步的站点也在结果中
        $siteIds = array_map(fn ($s) => $s->getSiteId(), $sitesNeedingSync);
        $this->assertContains($neverSyncedSite->getSiteId(), $siteIds);
    }

    public function testBasicPersistence(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建新站点
        $site = $this->createNewEntity();
        $this->assertNull($site->getId());

        // 保存站点
        $entityManager->persist($site);
        $entityManager->flush();
        $this->assertNotNull($site->getId());

        // 验证站点可以被找到
        $foundSite = $repository->find($site->getId());
        $this->assertInstanceOf(DifySite::class, $foundSite);
        $this->assertSame($site->getSiteId(), $foundSite->getSiteId());

        // 删除站点
        $entityManager->remove($site);
        $entityManager->flush();

        // 验证站点已被删除
        $deletedSite = $repository->find($site->getId());
        $this->assertNull($deletedSite);
    }
}
