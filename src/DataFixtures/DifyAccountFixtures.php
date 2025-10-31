<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

final class DifyAccountFixtures extends Fixture implements DependentFixtureInterface
{
    public const ACCOUNT_ADMIN = 'account_admin';
    public const ACCOUNT_USER = 'account_user';
    public const ACCOUNT_TEST = 'account_test';

    public function load(ObjectManager $manager): void
    {
        /**
         * @var DifyInstance $mainInstance
         */
        $mainInstance = $this->getReference(DifyInstanceFixtures::INSTANCE_MAIN, DifyInstance::class);
        /**
         * @var DifyInstance $devInstance
         */
        $devInstance = $this->getReference(DifyInstanceFixtures::INSTANCE_DEV, DifyInstance::class);

        $adminAccount = new DifyAccount();
        $adminAccount->setInstance($mainInstance);
        $adminAccount->setEmail('admin@example.com');
        $adminAccount->setPassword('hashed_password_admin');
        $adminAccount->setNickname('系统管理员');
        $adminAccount->setIsEnabled(true);

        $userAccount = new DifyAccount();
        $userAccount->setInstance($mainInstance);
        $userAccount->setEmail('user@example.com');
        $userAccount->setPassword('hashed_password_user');
        $userAccount->setNickname('普通用户');
        $userAccount->setIsEnabled(true);

        $testAccount = new DifyAccount();
        $testAccount->setInstance($devInstance);
        $testAccount->setEmail('felix@umworks.com');
        $testAccount->setPassword('Ladder19');
        $testAccount->setNickname('测试用户');
        $testAccount->setIsEnabled(true);

        $manager->persist($adminAccount);
        $manager->persist($userAccount);
        $manager->persist($testAccount);

        $this->addReference(self::ACCOUNT_ADMIN, $adminAccount);
        $this->addReference(self::ACCOUNT_USER, $userAccount);
        $this->addReference(self::ACCOUNT_TEST, $testAccount);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DifyInstanceFixtures::class,
        ];
    }
}
