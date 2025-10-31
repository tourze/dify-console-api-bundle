<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Repository\DifySiteRepository;
use Tourze\DifyConsoleApiBundle\Service\Helper\SiteDataProcessor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SiteDataProcessor::class)]
#[RunTestsInSeparateProcesses]
class SiteDataProcessorTest extends AbstractIntegrationTestCase
{
    private SiteDataProcessor $processor;

    protected function onSetUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $siteRepository = $this->createMock(DifySiteRepository::class);
        $this->processor = self::getService(SiteDataProcessor::class);
    }

    public function testProcessAppSiteDataWithNoSite(): void
    {
        $app = new ChatAssistantApp();
        $appData = ['id' => 'test-app'];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $result = $this->processor->processAppSiteData($app, $appData, $syncStats);

        $this->assertSame($syncStats, $result);
        $this->assertNull($app->getSite());
    }

    public function testProcessAppSiteDataWithInvalidSite(): void
    {
        $app = new ChatAssistantApp();
        $appData = ['id' => 'test-app', 'site' => []];
        $syncStats = [
            'processed_instances' => 0,
            'processed_accounts' => 0,
            'synced_apps' => 0,
            'created_apps' => 0,
            'updated_apps' => 0,
            'synced_sites' => 0,
            'created_sites' => 0,
            'updated_sites' => 0,
            'errors' => 0,
            'error_details' => [],
            'app_types' => [],
        ];

        $result = $this->processor->processAppSiteData($app, $appData, $syncStats);

        $this->assertSame($syncStats, $result);
        $this->assertNull($app->getSite());
    }
}
