<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Message;

/**
 * Dify应用同步异步消息
 *
 * 用于通过Symfony Messenger异步处理Dify应用同步任务
 */
final readonly class DifySyncMessage
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ?int $instanceId = null,
        public ?int $accountId = null,
        public ?string $appType = null,
        public array $metadata = [],
    ) {
    }

    /**
     * 获取消息的唯一标识
     */
    public function getMessageId(): string
    {
        return md5(
            serialize(
                [
                    $this->instanceId,
                    $this->accountId,
                    $this->appType,
                    $this->metadata['request_id'] ?? null,
                ]
            )
        );
    }

    /**
     * 获取消息类型标识（用于路由和监控）
     */
    public function getMessageType(): string
    {
        return 'dify_sync';
    }

    /**
     * 检查是否指定了实例
     */
    public function hasInstance(): bool
    {
        return null !== $this->instanceId;
    }

    /**
     * 检查是否指定了账号
     */
    public function hasAccount(): bool
    {
        return null !== $this->accountId;
    }

    /**
     * 检查是否指定了应用类型
     */
    public function hasAppType(): bool
    {
        return null !== $this->appType;
    }

    /**
     * 获取实例ID
     */
    public function getInstanceId(): ?int
    {
        return $this->instanceId;
    }

    /**
     * 获取账号ID
     */
    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * 获取应用类型
     */
    public function getAppType(): ?string
    {
        return $this->appType;
    }

    /**
     * 获取消息优先级（用于队列排序）
     */
    public function getPriority(): int
    {
        // 有具体过滤条件的同步优先级更高
        if ($this->hasInstance() || $this->hasAccount() || $this->hasAppType()) {
            return 10;
        }

        return 5; // 全量同步默认优先级
    }

    /**
     * 转换为数组格式（用于序列化和调试）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->getMessageId(),
            'message_type' => $this->getMessageType(),
            'instance_id' => $this->instanceId,
            'account_id' => $this->accountId,
            'app_type' => $this->appType,
            'metadata' => $this->metadata,
            'priority' => $this->getPriority(),
        ];
    }

    /**
     * 获取同步范围描述
     */
    public function getScopeDescription(): string
    {
        $scopes = [];

        if ($this->hasInstance()) {
            $scopes[] = "实例:{$this->instanceId}";
        }

        if ($this->hasAccount()) {
            $scopes[] = "账号:{$this->accountId}";
        }

        if ($this->hasAppType()) {
            $scopes[] = "类型:{$this->appType}";
        }

        if (0 === count($scopes)) {
            return '全量同步';
        }

        return implode(', ', $scopes);
    }
}
