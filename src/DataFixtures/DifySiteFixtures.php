<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;

final class DifySiteFixtures extends Fixture
{
    public const SITE_MAIN = 'site_main';
    public const SITE_TEST = 'site_test';
    public const SITE_DISABLED = 'site_disabled';

    public function load(ObjectManager $manager): void
    {
        $mainSite = new DifySite();
        $mainSite->setSiteId('main-site-001');
        $mainSite->setTitle('主站点');
        $mainSite->setDescription('这是主要的Dify应用站点');
        $mainSite->setSiteUrl('https://images.unsplash.com/photo-1661956602116-aa6865609028');
        $mainSite->setIsEnabled(true);
        $mainSite->setDefaultLanguage('zh-CN');
        $mainSite->setTheme('default');
        $mainSite->setCopyright('© 2024 主站点');
        $mainSite->setPrivacyPolicy('主站点隐私政策内容...');
        $mainSite->setDisclaimer('主站点免责声明内容...');
        $mainSite->setCustomDomain(
            [
                'domain' => 'images.unsplash.com',
                'ssl_enabled' => true,
                'certificate_status' => 'valid',
            ]
        );
        $mainSite->setCustomConfig(
            [
                'analytics_enabled' => true,
                'seo_enabled' => true,
                'cache_ttl' => 3600,
            ]
        );
        $mainSite->setPublishTime(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $mainSite->setLastSyncTime(new \DateTimeImmutable());

        $manager->persist($mainSite);
        $this->addReference(self::SITE_MAIN, $mainSite);

        $testSite = new DifySite();
        $testSite->setSiteId('test-site-002');
        $testSite->setTitle('测试站点');
        $testSite->setDescription('用于测试的Dify应用站点');
        $testSite->setSiteUrl('https://images.unsplash.com/photo-1633356122544-f134324a6cee');
        $testSite->setIsEnabled(true);
        $testSite->setDefaultLanguage('en-US');
        $testSite->setTheme('test');
        $testSite->setCopyright('© 2024 测试站点');
        $testSite->setCustomDomain(
            [
                'domain' => 'images.unsplash.com',
                'ssl_enabled' => false,
            ]
        );
        $testSite->setCustomConfig(
            [
                'analytics_enabled' => false,
                'debug_mode' => true,
            ]
        );
        $testSite->setPublishTime(new \DateTimeImmutable('2024-02-01 14:30:00'));

        $manager->persist($testSite);
        $this->addReference(self::SITE_TEST, $testSite);

        $disabledSite = new DifySite();
        $disabledSite->setSiteId('disabled-site-003');
        $disabledSite->setTitle('已禁用站点');
        $disabledSite->setDescription('暂时禁用的站点');
        $disabledSite->setSiteUrl('https://images.unsplash.com/photo-1587620962725-abab7fe55159');
        $disabledSite->setIsEnabled(false);
        $disabledSite->setDefaultLanguage('zh-CN');
        $disabledSite->setTheme('minimal');

        $manager->persist($disabledSite);
        $this->addReference(self::SITE_DISABLED, $disabledSite);

        $manager->flush();
    }
}
