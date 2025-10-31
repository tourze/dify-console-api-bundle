<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\ChatflowAppRepository;

#[ORM\Entity(repositoryClass: ChatflowAppRepository::class)]
class ChatflowApp extends BaseApp implements \Stringable
{
    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'chatflow_config', type: Types::JSON, nullable: true, options: ['comment' => '聊天流配置'])]
    #[Assert\Type(type: 'array', message: '聊天流配置必须是数组格式')]
    private ?array $chatflowConfig = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'model_config', type: Types::JSON, nullable: true, options: ['comment' => '模型配置'])]
    #[Assert\Type(type: 'array', message: '模型配置必须是数组格式')]
    private ?array $modelConfig = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'conversation_config', type: Types::JSON, nullable: true, options: ['comment' => '对话配置'])]
    #[Assert\Type(type: 'array', message: '对话配置必须是数组格式')]
    private ?array $conversationConfig = null;

    /**
     * @return array<string, mixed>|null
     */
    public function getChatflowConfig(): ?array
    {
        return $this->chatflowConfig;
    }

    /**
     * @param array<string, mixed>|null $chatflowConfig
     */
    public function setChatflowConfig(?array $chatflowConfig): void
    {
        $this->chatflowConfig = $chatflowConfig;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModelConfig(): ?array
    {
        return $this->modelConfig;
    }

    /**
     * @param array<string, mixed>|null $modelConfig
     */
    public function setModelConfig(?array $modelConfig): void
    {
        $this->modelConfig = $modelConfig;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConversationConfig(): ?array
    {
        return $this->conversationConfig;
    }

    /**
     * @param array<string, mixed>|null $conversationConfig
     */
    public function setConversationConfig(?array $conversationConfig): void
    {
        $this->conversationConfig = $conversationConfig;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return $this->name ?? 'Chatflow App #' . ($this->id ?? 'new');
    }
}
