<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DifyConsoleApiBundle\DifyConsoleApiBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * DifyConsoleApiBundle 测试
 *
 * 测试重点：
 * - Bundle 基本功能和继承关系
 * - Bundle 依赖管理
 * - Bundle 类型和配置
 * - BundleDependencyInterface 实现
 *
 * @internal
 */
#[CoversClass(DifyConsoleApiBundle::class)]
#[RunTestsInSeparateProcesses]
final class DifyConsoleApiBundleTest extends AbstractBundleTestCase
{
    public function testGetBundleDependenciesContainsRequiredBundles(): void
    {
        $dependencies = DifyConsoleApiBundle::getBundleDependencies();

        // Verify required dependencies
        $this->assertArrayHasKey(DoctrineBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineTimestampBundle::class, $dependencies);
        $this->assertArrayHasKey(TwigBundle::class, $dependencies);
        $this->assertArrayHasKey(EasyAdminBundle::class, $dependencies);
    }

    public function testBundleDependenciesConfiguration(): void
    {
        $dependencies = DifyConsoleApiBundle::getBundleDependencies();

        // Verify specific bundle configurations
        $expectedBundles = [
            DoctrineBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            TwigBundle::class => ['all' => true],
            EasyAdminBundle::class => ['all' => true],
        ];

        foreach ($expectedBundles as $bundleClass => $expectedConfig) {
            $this->assertArrayHasKey($bundleClass, $dependencies);
            $this->assertEquals($expectedConfig, $dependencies[$bundleClass]);
        }
    }
}
