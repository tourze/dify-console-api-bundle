<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

final class DifyInstanceFixtures extends Fixture
{
    public const INSTANCE_MAIN = 'instance_main';
    public const INSTANCE_TEST = 'instance_test';
    public const INSTANCE_DEV = 'instance_dev';

    public function load(ObjectManager $manager): void
    {
        $mainInstance = new DifyInstance();
        $mainInstance->setName('主实例');
        $mainInstance->setBaseUrl('https://api.dify.ai');
        $mainInstance->setDescription('生产环境Dify实例');
        $mainInstance->setIsEnabled(true);

        $testInstance = new DifyInstance();
        $testInstance->setName('测试实例');
        $testInstance->setBaseUrl('https://test.dify.ai');
        $testInstance->setDescription('测试环境Dify实例');
        $testInstance->setIsEnabled(true);

        $devInstance = new DifyInstance();
        $devInstance->setName('开发实例');
        $devInstance->setBaseUrl('http://dify-demo.umworks.com');
        $devInstance->setDescription('开发环境Dify实例');
        $devInstance->setIsEnabled(true);

        $manager->persist($mainInstance);
        $manager->persist($testInstance);
        $manager->persist($devInstance);

        $this->addReference(self::INSTANCE_MAIN, $mainInstance);
        $this->addReference(self::INSTANCE_TEST, $testInstance);
        $this->addReference(self::INSTANCE_DEV, $devInstance);

        $manager->flush();
    }
}
