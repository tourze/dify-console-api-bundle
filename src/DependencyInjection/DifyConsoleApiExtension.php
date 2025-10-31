<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class DifyConsoleApiExtension extends AutoExtension implements PrependExtensionInterface, CompilerPassInterface
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        parent::load($configs, $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // 注册模板路径到Twig
        $templatePath = __DIR__ . '/../../templates';
        if (is_dir($templatePath)) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $templatePath => 'DifyConsoleApi',
                ],
            ]);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        // 为 ObjectManager 接口创建别名，指向默认的实体管理器
        if ($container->hasDefinition('doctrine.orm.default_entity_manager')) {
            $container->setAlias('Doctrine\Persistence\ObjectManager', 'doctrine.orm.default_entity_manager');
        }
    }
}
