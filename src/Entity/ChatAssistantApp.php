<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\ChatAssistantAppRepository;

#[ORM\Entity(repositoryClass: ChatAssistantAppRepository::class)]
class ChatAssistantApp extends BaseApp implements \Stringable
{
    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'assistant_config', type: Types::JSON, nullable: true, options: ['comment' => '助手配置'])]
    #[Assert\Type(type: 'array', message: '助手配置必须是数组格式')]
    private ?array $assistantConfig = null;

    #[ORM\Column(name: 'prompt_template', type: Types::TEXT, nullable: true, options: ['comment' => '提示模板'])]
    #[Assert\Length(max: 65535, maxMessage: '提示模板长度不能超过{{ limit }}个字符')]
    private ?string $promptTemplate = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'knowledge_base', type: Types::JSON, nullable: true, options: ['comment' => '知识库配置'])]
    #[Assert\Type(type: 'array', message: '知识库配置必须是数组格式')]
    private ?array $knowledgeBase = null;

    /**
     * @return array<string, mixed>|null
     */
    public function getAssistantConfig(): ?array
    {
        return $this->assistantConfig;
    }

    /**
     * @param array<string, mixed>|null $assistantConfig
     */
    public function setAssistantConfig(?array $assistantConfig): void
    {
        $this->assistantConfig = $assistantConfig;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function getPromptTemplate(): ?string
    {
        return $this->promptTemplate;
    }

    public function setPromptTemplate(?string $promptTemplate): void
    {
        $this->promptTemplate = $promptTemplate;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getKnowledgeBase(): ?array
    {
        return $this->knowledgeBase;
    }

    /**
     * @param array<string, mixed>|null $knowledgeBase
     */
    public function setKnowledgeBase(?array $knowledgeBase): void
    {
        $this->knowledgeBase = $knowledgeBase;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return $this->name ?? 'Chat Assistant App #' . ($this->id ?? 'new');
    }
}
