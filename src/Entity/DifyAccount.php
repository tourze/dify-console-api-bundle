<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: DifyAccountRepository::class)]
#[ORM\Table(name: 'dify_account', options: ['comment' => 'Dify账户'])]
class DifyAccount implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DifyInstance::class)]
    #[ORM\JoinColumn(name: 'instance_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', options: ['comment' => '关联的Dify实例'])]
    #[Assert\NotNull(message: 'Dify实例不能为空')]
    private DifyInstance $instance;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '邮箱地址'])]
    #[Assert\NotBlank(message: '邮箱不能为空')]
    #[Assert\Email(message: '邮箱格式不正确')]
    #[Assert\Length(max: 255, maxMessage: '邮箱长度不能超过{{ limit }}个字符')]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '密码'])]
    #[Assert\NotBlank(message: '密码不能为空')]
    #[Assert\Length(max: 255, maxMessage: '密码长度不能超过{{ limit }}个字符')]
    private string $password;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '昵称'])]
    #[Assert\Length(max: 100, maxMessage: '昵称长度不能超过{{ limit }}个字符')]
    private ?string $nickname = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '访问令牌'])]
    #[Assert\Length(max: 65535, maxMessage: '访问令牌长度不能超过{{ limit }}个字符')]
    private ?string $accessToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '令牌过期时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '令牌过期时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $tokenExpiresTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull(message: '启用状态不能为空')]
    private bool $isEnabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后登录时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '最后登录时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $lastLoginTime = null;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
        $this->updateTimestamp();
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
        $this->updateTimestamp();
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
        $this->updateTimestamp();
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->updateTimestamp();
    }

    public function getTokenExpiresTime(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresTime;
    }

    public function setTokenExpiresTime(?\DateTimeImmutable $tokenExpiresTime): void
    {
        $this->tokenExpiresTime = $tokenExpiresTime;
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

    public function getLastLoginTime(): ?\DateTimeImmutable
    {
        return $this->lastLoginTime;
    }

    public function setLastLoginTime(?\DateTimeImmutable $lastLoginTime): void
    {
        $this->lastLoginTime = $lastLoginTime;
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

    public function getName(): string
    {
        return $this->nickname ?? $this->email;
    }

    public function isTokenExpired(): bool
    {
        if (null === $this->tokenExpiresTime) {
            return true;
        }

        return $this->tokenExpiresTime <= new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
