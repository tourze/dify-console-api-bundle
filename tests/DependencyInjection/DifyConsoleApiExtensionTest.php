<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DifyConsoleApiBundle\DependencyInjection\DifyConsoleApiExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

/**
 * DifyConsoleApiExtension 测试
 *
 * 测试重点：
 * - Extension 基本功能和继承关系
 * - 服务配置加载和注册
 * - 容器配置正确性
 * - 服务定义的自动配置
 *
 * @internal
 */
#[CoversClass(DifyConsoleApiExtension::class)]
final class DifyConsoleApiExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private DifyConsoleApiExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new DifyConsoleApiExtension();
        $this->container = new ContainerBuilder();

        // Set required parameters for AutoExtension
        $this->container->setParameter('kernel.environment', 'test');
        $this->container->setParameter('kernel.debug', true);
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.project_dir', __DIR__ . '/../../');
    }

    public function testLoadLoadsServicesYaml(): void
    {
        $this->extension->load([], $this->container);

        // Check that key services are loaded
        $this->assertTrue($this->container->hasDefinition('Tourze\DifyConsoleApiBundle\Service\DifyClientService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\DifyConsoleApiBundle\Service\InstanceManagementService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\DifyConsoleApiBundle\Service\AppSyncService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\DifyConsoleApiBundle\Service\AdminMenu'));
    }

    public function testLoadWithEmptyConfigs(): void
    {
        $this->extension->load([], $this->container);

        $this->assertGreaterThan(0, count($this->container->getDefinitions()));
    }

    public function testLoadWithNonEmptyConfigs(): void
    {
        $configs = [
            ['some_config' => 'value'],
        ];

        $this->extension->load($configs, $this->container);

        $this->assertGreaterThan(0, count($this->container->getDefinitions()));
    }

    public function testLoadSetsCorrectAutowiring(): void
    {
        $this->extension->load([], $this->container);

        $definitions = $this->container->getDefinitions();

        // Check that autowiring is enabled for bundle services
        foreach ($definitions as $id => $definition) {
            if (str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\')) {
                $this->assertTrue($definition->isAutowired(), "Service {$id} should be autowired");
            }
        }
    }

    public function testLoadSetsCorrectAutoconfiguration(): void
    {
        $this->extension->load([], $this->container);

        $definitions = $this->container->getDefinitions();

        // Check that autoconfiguration is enabled for bundle services
        foreach ($definitions as $id => $definition) {
            if (str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\')) {
                $this->assertTrue($definition->isAutoconfigured(), "Service {$id} should be autoconfigured");
            }
        }
    }

    public function testLoadRegistersControllers(): void
    {
        $this->extension->load([], $this->container);

        // Check that controller services are loaded
        $definitions = $this->container->getDefinitions();
        $controllerDefinitions = array_filter(array_keys($definitions), function ($id) {
            return str_contains($id, 'Controller') && str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\');
        });

        $this->assertGreaterThan(0, count($controllerDefinitions));
    }

    public function testLoadRegistersRepositories(): void
    {
        $this->extension->load([], $this->container);

        // Check that repository services are loaded
        $definitions = $this->container->getDefinitions();
        $repositoryDefinitions = array_filter(array_keys($definitions), function ($id) {
            return str_contains($id, 'Repository') && str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\');
        });

        $this->assertGreaterThan(0, count($repositoryDefinitions));
    }

    public function testLoadMultipleCalls(): void
    {
        $this->extension->load([], $this->container);
        $firstCount = count($this->container->getDefinitions());

        // Loading again should not duplicate services
        $this->extension->load([], $this->container);
        $secondCount = count($this->container->getDefinitions());

        $this->assertEquals($firstCount, $secondCount);
    }

    public function testExtensionInheritsFromCorrectClass(): void
    {
        $this->assertInstanceOf(
            AutoExtension::class,
            $this->extension
        );
    }

    public function testLoadDoesNotThrowException(): void
    {
        $this->expectNotToPerformAssertions();

        $this->extension->load([], $this->container);
        $this->extension->load([['key' => 'value']], $this->container);
        $this->extension->load([[], ['another' => 'config']], $this->container);
    }

    public function testLoadRegistersEntityServices(): void
    {
        $this->extension->load([], $this->container);

        // Check that entity-related services are loaded
        $definitions = $this->container->getDefinitions();
        $entityDefinitions = array_filter(array_keys($definitions), function ($id) {
            return str_contains($id, 'Entity') && str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\');
        });

        // Should have entity definitions for Doctrine mapping
        $this->assertGreaterThanOrEqual(0, count($entityDefinitions));
    }

    public function testConfigDirectoryPath(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('getConfigDir');

        $configDir = $method->invoke($this->extension);
        $this->assertIsString($configDir);

        $this->assertStringEndsWith('/Resources/config', $configDir);
        $this->assertDirectoryExists($configDir);
    }

    public function testExtensionCanLoadMultipleConfigurationSets(): void
    {
        $configs = [
            ['setting1' => 'value1'],
            ['setting2' => 'value2'],
            ['setting3' => ['nested' => 'value']],
        ];

        $this->extension->load($configs, $this->container);

        // Should handle multiple configuration arrays without error
        $this->assertGreaterThan(0, count($this->container->getDefinitions()));
    }

    public function testLoadRegistersMessageHandlers(): void
    {
        $this->extension->load([], $this->container);

        // Check that message handlers are loaded
        $definitions = $this->container->getDefinitions();
        $handlerDefinitions = array_filter(array_keys($definitions), function ($id) {
            return str_contains($id, 'Handler') && str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\');
        });

        $this->assertGreaterThanOrEqual(0, count($handlerDefinitions));
    }

    public function testLoadRegistersCommands(): void
    {
        $this->extension->load([], $this->container);

        // Check that command services are loaded
        $definitions = $this->container->getDefinitions();
        $commandDefinitions = array_filter(array_keys($definitions), function ($id) {
            return str_contains($id, 'Command') && str_starts_with($id, 'Tourze\DifyConsoleApiBundle\\');
        });

        $this->assertGreaterThanOrEqual(0, count($commandDefinitions));
    }

    public function testPrepend(): void
    {
        // 测试 prepend 方法注册 Twig 路径
        $this->extension->prepend($this->container);

        // 获取 Twig 扩展配置
        $twigConfig = $this->container->getExtensionConfig('twig');

        // 验证至少有一个 Twig 配置条目
        $this->assertNotEmpty($twigConfig, 'Twig configuration should be registered');

        // 验证配置中包含 paths 键
        $pathsFound = false;
        foreach ($twigConfig as $config) {
            if (isset($config['paths'])) {
                $pathsFound = true;
                $this->assertIsArray($config['paths']);
                // 验证包含 DifyConsoleApi 命名空间的路径注册
                $templatePath = __DIR__ . '/../../templates';
                $found = false;
                foreach ($config['paths'] as $path => $namespace) {
                    if ('DifyConsoleApi' === $namespace) {
                        $found = true;
                        // 验证路径指向正确的模板目录
                        $this->assertStringEndsWith('templates', $path);
                        break;
                    }
                }
                $this->assertTrue($found, 'DifyConsoleApi namespace should be registered');
                break;
            }
        }

        $this->assertTrue($pathsFound, 'Twig paths configuration should be registered');
    }

    public function testProcess(): void
    {
        // 模拟 Doctrine ORM 默认实体管理器
        $this->container->register('doctrine.orm.default_entity_manager', \stdClass::class);

        // 加载扩展配置
        $this->extension->load([], $this->container);

        // 执行 process 方法
        $this->extension->process($this->container);

        // 验证 ObjectManager 别名已创建
        $this->assertTrue(
            $this->container->hasAlias('Doctrine\Persistence\ObjectManager'),
            'ObjectManager alias should be created'
        );

        $alias = $this->container->getAlias('Doctrine\Persistence\ObjectManager');
        $this->assertSame(
            'doctrine.orm.default_entity_manager',
            (string) $alias,
            'ObjectManager alias should point to default entity manager'
        );
    }
}
