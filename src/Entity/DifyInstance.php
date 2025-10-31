<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: DifyInstanceRepository::class)]
#[ORM\Table(name: 'dify_instance', options: ['comment' => 'Dify实例'])]
class DifyInstance implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键'])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 100, options: ['comment' => '实例名称'])]
    #[Assert\NotBlank(message: '实例名称不能为空')]
    #[Assert\Length(max: 100, maxMessage: '实例名称长度不能超过{{ limit }}个字符')]
    private string $name;

    #[ORM\Column(name: 'base_url', type: Types::STRING, length: 255, options: ['comment' => '基础URL'])]
    #[Assert\NotBlank(message: '基础URL不能为空')]
    #[Assert\Url(message: '基础URL格式不正确')]
    #[Assert\Length(max: 255, maxMessage: '基础URL长度不能超过{{ limit }}个字符')]
    private string $baseUrl;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 500, nullable: true, options: ['comment' => '描述'])]
    #[Assert\Length(max: 500, maxMessage: '描述长度不能超过{{ limit }}个字符')]
    private ?string $description = null;

    #[ORM\Column(name: 'is_enabled', type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull(message: '启用状态不能为空')]
    private bool $isEnabled = true;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updateTimestamp();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
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

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
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
        return $this->name ?? 'Dify Instance #' . ($this->id ?? 'new');
    }
}
