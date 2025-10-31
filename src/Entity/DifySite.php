<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\DifySiteRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * Dify应用站点实体
 *
 * 存储Dify应用发布后的站点信息
 * 每个应用可以有一个发布的站点，站点信息会随发布状态变化
 */
#[ORM\Entity(repositoryClass: DifySiteRepository::class)]
#[ORM\Table(name: 'dify_site', options: ['comment' => 'Dify应用站点信息表'])]
class DifySite implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '站点唯一标识符'])]
    #[Assert\NotBlank(message: '站点ID不能为空')]
    #[Assert\Length(max: 100, maxMessage: '站点ID不能超过100个字符')]
    private string $siteId;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '站点标题'])]
    #[Assert\NotBlank(message: '站点标题不能为空')]
    #[Assert\Length(max: 255, maxMessage: '站点标题不能超过255个字符')]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '站点描述'])]
    #[Assert\Length(max: 2000, maxMessage: '站点描述不能超过2000个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '站点访问URL'])]
    #[Assert\NotBlank(message: '站点URL不能为空')]
    #[Assert\Url(message: '请输入有效的URL格式')]
    #[Assert\Length(max: 500, maxMessage: '站点URL不能超过500个字符')]
    private string $siteUrl;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用站点'])]
    #[Assert\Type(type: 'boolean', message: '启用状态必须是布尔值')]
    private bool $isEnabled = false;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '默认语言'])]
    #[Assert\Length(max: 50, maxMessage: '默认语言不能超过50个字符')]
    private ?string $defaultLanguage = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '站点主题'])]
    #[Assert\Length(max: 100, maxMessage: '站点主题不能超过100个字符')]
    private ?string $theme = null;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true, options: ['comment' => '版权信息'])]
    #[Assert\Length(max: 200, maxMessage: '版权信息不能超过200个字符')]
    private ?string $copyright = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '隐私政策'])]
    #[Assert\Length(max: 5000, maxMessage: '隐私政策不能超过5000个字符')]
    private ?string $privacyPolicy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '免责声明'])]
    #[Assert\Length(max: 5000, maxMessage: '免责声明不能超过5000个字符')]
    private ?string $disclaimer = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '自定义域名配置'])]
    #[Assert\Type(type: 'array', message: '自定义域名配置必须是数组格式')]
    private ?array $customDomain = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '自定义配置信息'])]
    #[Assert\Type(type: 'array', message: '自定义配置信息必须是数组格式')]
    private ?array $customConfig = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '发布时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '发布时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $publishTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后同步时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '最后同步时间必须是有效的日期时间格式')]
    private ?\DateTimeImmutable $lastSyncTime = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createTime = $now;
        $this->updateTime = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function setSiteId(string $siteId): void
    {
        $this->siteId = $siteId;
        $this->updateTimestamp();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->updateTimestamp();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updateTimestamp();
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    public function setSiteUrl(string $siteUrl): void
    {
        $this->siteUrl = $siteUrl;
        $this->updateTimestamp();
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
        $this->updateTimestamp();
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(?string $defaultLanguage): void
    {
        $this->defaultLanguage = $defaultLanguage;
        $this->updateTimestamp();
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): void
    {
        $this->theme = $theme;
        $this->updateTimestamp();
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(?string $copyright): void
    {
        $this->copyright = $copyright;
        $this->updateTimestamp();
    }

    public function getPrivacyPolicy(): ?string
    {
        return $this->privacyPolicy;
    }

    public function setPrivacyPolicy(?string $privacyPolicy): void
    {
        $this->privacyPolicy = $privacyPolicy;
        $this->updateTimestamp();
    }

    public function getDisclaimer(): ?string
    {
        return $this->disclaimer;
    }

    public function setDisclaimer(?string $disclaimer): void
    {
        $this->disclaimer = $disclaimer;
        $this->updateTimestamp();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomDomain(): ?array
    {
        return $this->customDomain;
    }

    /**
     * @param array<string, mixed>|null $customDomain
     */
    public function setCustomDomain(?array $customDomain): void
    {
        $this->customDomain = $customDomain;
        $this->updateTimestamp();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomConfig(): ?array
    {
        return $this->customConfig;
    }

    /**
     * @param array<string, mixed>|null $customConfig
     */
    public function setCustomConfig(?array $customConfig): void
    {
        $this->customConfig = $customConfig;
        $this->updateTimestamp();
    }

    public function getPublishTime(): ?\DateTimeImmutable
    {
        return $this->publishTime;
    }

    public function setPublishTime(?\DateTimeImmutable $publishTime): void
    {
        $this->publishTime = $publishTime;
        $this->updateTimestamp();
    }

    public function getLastSyncTime(): ?\DateTimeImmutable
    {
        return $this->lastSyncTime;
    }

    public function setLastSyncTime(?\DateTimeImmutable $lastSyncTime): void
    {
        $this->lastSyncTime = $lastSyncTime;
        $this->updateTimestamp();
    }

    /**
     * 更新时间戳便利方法
     */
    protected function updateTimestamp(): void
    {
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->title, $this->siteId);
    }
}
