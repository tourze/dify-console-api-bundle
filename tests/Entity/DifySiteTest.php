<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DifySite::class)]
class DifySiteTest extends AbstractEntityTestCase
{
    private DifySite $site;

    protected function createEntity(): object
    {
        return new DifySite();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            'siteId' => ['siteId', 'test-site-123'],
            'title' => ['title', 'Test Site Title'],
            'description' => ['description', 'Test site description'],
            'siteUrl' => ['siteUrl', 'https://example.dify.ai/app/123'],
            'defaultLanguage' => ['defaultLanguage', 'zh-CN'],
            'theme' => ['theme', 'dark'],
            'copyright' => ['copyright', '© 2025 Test Company'],
            'privacyPolicy' => ['privacyPolicy', 'Privacy policy content'],
            'disclaimer' => ['disclaimer', 'Disclaimer content'],
            'customDomain' => ['customDomain', ['domain' => 'custom.example.com', 'ssl_enabled' => true]],
            'customConfig' => ['customConfig', ['analytics_enabled' => true, 'custom_css' => '.test { color: red; }']],
            'publishTime' => ['publishTime', new \DateTimeImmutable('2025-01-01 12:00:00')],
            'lastSyncTime' => ['lastSyncTime', new \DateTimeImmutable('2025-01-01 13:00:00')],
        ];
    }

    protected function setUp(): void
    {
        $this->site = new DifySite();
    }

    public function testConstructor(): void
    {
        $site = new DifySite();

        self::assertNull($site->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $site->getCreateTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $site->getUpdateTime());
    }

    public function testSetSiteId(): void
    {
        $siteId = 'test-site-123';

        $this->site->setSiteId($siteId);

        self::assertSame($siteId, $this->site->getSiteId());
    }

    public function testSetTitle(): void
    {
        $title = 'Test Site Title';

        $this->site->setTitle($title);

        self::assertSame($title, $this->site->getTitle());
    }

    public function testSetDescription(): void
    {
        $description = 'Test site description';

        $this->site->setDescription($description);

        self::assertSame($description, $this->site->getDescription());

        // Test null value
        $this->site->setDescription(null);
        self::assertNull($this->site->getDescription());
    }

    public function testSetSiteUrl(): void
    {
        $siteUrl = 'https://example.dify.ai/app/123';

        $this->site->setSiteUrl($siteUrl);

        self::assertSame($siteUrl, $this->site->getSiteUrl());
    }

    public function testSetIsEnabled(): void
    {
        // Test default value
        self::assertFalse($this->site->isEnabled());

        $this->site->setIsEnabled(true);
        self::assertTrue($this->site->isEnabled());

        $this->site->setIsEnabled(false);
        self::assertFalse($this->site->isEnabled());
    }

    public function testSetDefaultLanguage(): void
    {
        $language = 'zh-CN';

        $this->site->setDefaultLanguage($language);

        self::assertSame($language, $this->site->getDefaultLanguage());

        // Test null value
        $this->site->setDefaultLanguage(null);
        self::assertNull($this->site->getDefaultLanguage());
    }

    public function testSetTheme(): void
    {
        $theme = 'dark';

        $this->site->setTheme($theme);

        self::assertSame($theme, $this->site->getTheme());

        // Test null value
        $this->site->setTheme(null);
        self::assertNull($this->site->getTheme());
    }

    public function testSetCopyright(): void
    {
        $copyright = '© 2025 Test Company';

        $this->site->setCopyright($copyright);

        self::assertSame($copyright, $this->site->getCopyright());

        // Test null value
        $this->site->setCopyright(null);
        self::assertNull($this->site->getCopyright());
    }

    public function testSetPrivacyPolicy(): void
    {
        $privacyPolicy = 'Privacy policy content';

        $this->site->setPrivacyPolicy($privacyPolicy);

        self::assertSame($privacyPolicy, $this->site->getPrivacyPolicy());

        // Test null value
        $this->site->setPrivacyPolicy(null);
        self::assertNull($this->site->getPrivacyPolicy());
    }

    public function testSetDisclaimer(): void
    {
        $disclaimer = 'Disclaimer content';

        $this->site->setDisclaimer($disclaimer);

        self::assertSame($disclaimer, $this->site->getDisclaimer());

        // Test null value
        $this->site->setDisclaimer(null);
        self::assertNull($this->site->getDisclaimer());
    }

    public function testSetCustomDomain(): void
    {
        $customDomain = [
            'domain' => 'custom.example.com',
            'ssl_enabled' => true,
        ];

        $this->site->setCustomDomain($customDomain);

        self::assertSame($customDomain, $this->site->getCustomDomain());

        // Test null value
        $this->site->setCustomDomain(null);
        self::assertNull($this->site->getCustomDomain());
    }

    public function testSetCustomConfig(): void
    {
        $customConfig = [
            'analytics_enabled' => true,
            'custom_css' => '.test { color: red; }',
        ];

        $this->site->setCustomConfig($customConfig);

        self::assertSame($customConfig, $this->site->getCustomConfig());

        // Test null value
        $this->site->setCustomConfig(null);
        self::assertNull($this->site->getCustomConfig());
    }

    public function testSetPublishTime(): void
    {
        $publishTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->site->setPublishTime($publishTime);

        self::assertSame($publishTime, $this->site->getPublishTime());

        // Test null value
        $this->site->setPublishTime(null);
        self::assertNull($this->site->getPublishTime());
    }

    public function testSetLastSyncTime(): void
    {
        $lastSyncTime = new \DateTimeImmutable('2025-01-01 13:00:00');

        $this->site->setLastSyncTime($lastSyncTime);

        self::assertSame($lastSyncTime, $this->site->getLastSyncTime());

        // Test null value
        $this->site->setLastSyncTime(null);
        self::assertNull($this->site->getLastSyncTime());
    }

    public function testUpdateTimeIsModifiedOnPropertyChange(): void
    {
        $originalUpdateTime = $this->site->getUpdateTime();

        // Wait to ensure time difference
        usleep(1000);

        $this->site->setTitle('New Title');

        self::assertGreaterThan($originalUpdateTime, $this->site->getUpdateTime());
    }
}
