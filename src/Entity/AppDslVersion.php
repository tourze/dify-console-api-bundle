<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\AppDslVersionRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: AppDslVersionRepository::class)]
#[ORM\Table(name: 'app_dsl_version', options: ['comment' => 'DSL版本记录表'])]
class AppDslVersion implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BaseApp::class)]
    #[ORM\JoinColumn(name: 'app_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private BaseApp $app;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '版本号'])]
    #[Assert\NotNull(message: '版本号不能为空')]
    #[Assert\Positive(message: '版本号必须为正整数')]
    private int $version;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => 'DSL内容JSON数据'])]
    #[Assert\NotNull(message: 'DSL内容不能为空')]
    #[Assert\Type(type: 'array', message: 'DSL内容必须是数组格式')]
    private array $dslContent = [];

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => 'DSL原始文本内容'])]
    #[Assert\Length(max: 65535, maxMessage: 'DSL原始内容长度不能超过{{ limit }}个字符')]
    private ?string $dslRawContent = null;

    #[IndexColumn(name: 'app_dsl_version_idx_dsl_hash')]
    #[ORM\Column(name: 'dsl_hash', type: Types::STRING, length: 64, options: ['comment' => 'DSL内容哈希值'])]
    #[Assert\NotBlank(message: 'DSL哈希值不能为空')]
    #[Assert\Length(max: 64, maxMessage: 'DSL哈希值长度不能超过{{ limit }}个字符')]
    private string $dslHash;

    #[IndexColumn(name: 'app_dsl_version_idx_sync_time')]
    #[ORM\Column(name: 'sync_time', type: Types::DATETIME_IMMUTABLE, options: ['comment' => '同步时间'])]
    #[Assert\NotNull(message: '同步时间不能为空')]
    #[Assert\Type(type: '\DateTimeImmutable', message: '同步时间必须是有效的日期时间')]
    private \DateTimeImmutable $syncTime;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createTime = $now;
        $this->updateTime = $now;
        $this->syncTime = $now;
        // 注意：app和version必须在persist前设置
    }

    /**
     * 创建新的AppDslVersion实例的工厂方法
     */
    public static function create(BaseApp $app, int $version = 1): self
    {
        $instance = new self();
        $instance->app = $app;
        $instance->version = $version;
        return $instance;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApp(): BaseApp
    {
        return $this->app;
    }

    public function setApp(BaseApp $app): void
    {
        $this->app = $app;
        $this->updateTimestamp();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
        $this->updateTimestamp();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDslContent(): array
    {
        return $this->dslContent;
    }

    /**
     * @param array<string, mixed> $dslContent
     */
    public function setDslContent(array $dslContent): void
    {
        $this->dslContent = $dslContent;
        $this->updateTimestamp();
    }

    public function getDslRawContent(): ?string
    {
        return $this->dslRawContent;
    }

    public function setDslRawContent(?string $dslRawContent): void
    {
        $this->dslRawContent = $dslRawContent;
        $this->updateTimestamp();
    }

    public function getDslHash(): string
    {
        return $this->dslHash;
    }

    public function setDslHash(string $dslHash): void
    {
        $this->dslHash = $dslHash;
        $this->updateTimestamp();
    }

    public function getSyncTime(): \DateTimeImmutable
    {
        return $this->syncTime;
    }

    public function setSyncTime(\DateTimeImmutable $syncTime): void
    {
        $this->syncTime = $syncTime;
        $this->updateTimestamp();
    }

    /**
     * 更新时间戳便利方法
     */
    private function updateTimestamp(): void
    {
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return sprintf('DSL Version %d for App #%d', $this->version, $this->app->getId() ?? 0);
    }
}
