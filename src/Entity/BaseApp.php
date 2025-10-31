<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity]
#[ORM\Table(name: 'dify_app', options: ['comment' => 'Dify应用基础表'])]
#[ORM\InheritanceType(value: 'SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'app_type', type: 'string')]
#[ORM\DiscriminatorMap(value: [
    'chat_assistant' => 'Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp',
    'chatflow' => 'Tourze\DifyConsoleApiBundle\Entity\ChatflowApp',
    'workflow' => 'Tourze\DifyConsoleApiBundle\Entity\WorkflowApp',
])]
abstract class BaseApp implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键ID'])]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DifyInstance::class)]
    #[ORM\JoinColumn(name: 'instance_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', options: ['comment' => '关联的Dify实例ID'])]
    #[Assert\NotNull(message: 'Dify实例不能为空')]
    protected DifyInstance $instance;

    #[ORM\ManyToOne(targetEntity: DifyAccount::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', options: ['comment' => '关联的Dify账户ID'])]
    #[Assert\NotNull(message: 'Dify账户不能为空')]
    protected DifyAccount $account;

    #[ORM\Column(name: 'dify_app_id', type: Types::STRING, length: 100, options: ['comment' => 'Dify应用ID'])]
    #[Assert\NotBlank(message: 'Dify应用ID不能为空')]
    #[Assert\Length(max: 100, maxMessage: 'Dify应用ID长度不能超过{{ limit }}个字符')]
    protected string $difyAppId;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 200, options: ['comment' => '应用名称'])]
    #[Assert\NotBlank(message: '应用名称不能为空')]
    #[Assert\Length(max: 200, maxMessage: '应用名称长度不能超过{{ limit }}个字符')]
    protected string $name;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true, options: ['comment' => '应用描述'])]
    #[Assert\Length(max: 65535, maxMessage: '应用描述长度不能超过{{ limit }}个字符')]
    protected ?string $description = null;

    #[ORM\Column(name: 'icon', type: Types::STRING, length: 255, nullable: true, options: ['comment' => '应用图标URL'])]
    #[Assert\Length(max: 255, maxMessage: '应用图标URL长度不能超过{{ limit }}个字符')]
    #[Assert\Url(message: '应用图标必须是有效的URL')]
    protected ?string $icon = null;

    #[ORM\Column(name: 'is_public', type: Types::BOOLEAN, options: ['comment' => '是否公开'])]
    #[Assert\Type(type: 'bool', message: '公开状态必须是布尔值')]
    protected bool $isPublic = false;

    #[ORM\Column(name: 'created_by_dify_user', type: Types::STRING, length: 100, nullable: true, options: ['comment' => 'Dify创建用户'])]
    #[Assert\Length(max: 100, maxMessage: 'Dify创建用户长度不能超过{{ limit }}个字符')]
    protected ?string $createdByDifyUser = null;

    #[ORM\Column(name: 'dify_create_time', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => 'Dify创建时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: 'Dify创建时间必须是有效的日期时间')]
    protected ?\DateTimeImmutable $difyCreateTime = null;

    #[ORM\Column(name: 'dify_update_time', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => 'Dify更新时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: 'Dify更新时间必须是有效的日期时间')]
    protected ?\DateTimeImmutable $difyUpdateTime = null;

    #[ORM\Column(name: 'last_sync_time', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后同步时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '最后同步时间必须是有效的日期时间')]
    protected ?\DateTimeImmutable $lastSyncTime = null;

    #[ORM\OneToOne(targetEntity: DifySite::class)]
    #[ORM\JoinColumn(name: 'site_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL', options: ['comment' => '关联的Dify站点ID'])]
    protected ?DifySite $site = null;

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

    public function getInstance(): DifyInstance
    {
        return $this->instance;
    }

    public function setInstance(DifyInstance $instance): void
    {
        $this->instance = $instance;
        $this->updateTimestamp();
    }

    public function getAccount(): DifyAccount
    {
        return $this->account;
    }

    public function setAccount(DifyAccount $account): void
    {
        $this->account = $account;
        $this->updateTimestamp();
    }

    public function getDifyAppId(): string
    {
        return $this->difyAppId;
    }

    public function setDifyAppId(string $difyAppId): void
    {
        $this->difyAppId = $difyAppId;
        $this->updateTimestamp();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
        $this->updateTimestamp();
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
        $this->updateTimestamp();
    }

    public function getCreatedByDifyUser(): ?string
    {
        return $this->createdByDifyUser;
    }

    public function setCreatedByDifyUser(?string $createdByDifyUser): void
    {
        $this->createdByDifyUser = $createdByDifyUser;
        $this->updateTimestamp();
    }

    public function getDifyCreateTime(): ?\DateTimeImmutable
    {
        return $this->difyCreateTime;
    }

    public function setDifyCreateTime(?\DateTimeImmutable $difyCreateTime): void
    {
        $this->difyCreateTime = $difyCreateTime;
        $this->updateTimestamp();
    }

    public function getDifyUpdateTime(): ?\DateTimeImmutable
    {
        return $this->difyUpdateTime;
    }

    public function setDifyUpdateTime(?\DateTimeImmutable $difyUpdateTime): void
    {
        $this->difyUpdateTime = $difyUpdateTime;
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

    public function getSite(): ?DifySite
    {
        return $this->site;
    }

    public function setSite(?DifySite $site): void
    {
        $this->site = $site;
        $this->updateTimestamp();
    }

    // createTime and updateTime getters are provided by TimestampableAware trait

    /**
     * 更新时间戳便利方法
     */
    protected function updateTimestamp(): void
    {
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return $this->name ?? $this->getClassName() . ' #' . ($this->id ?? 'new');
    }

    protected function getClassName(): string
    {
        $reflection = new \ReflectionClass($this);

        return $reflection->getShortName();
    }
}
