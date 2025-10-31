<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Message;

/**
 * 同步应用消息
 * 用于触发特定账号的应用同步任务
 */
final readonly class SyncApplicationsMessage
{
    public function __construct(
        public int $accountId,
        /**
         * @var array<string, mixed>
         */
        public array $metadata = [],
    ) {
    }

    /**
     * 获取消息唯一标识
     */
    public function getMessageId(): string
    {
        return md5(sprintf('sync_apps_%d_%s', $this->accountId, serialize($this->metadata)));
    }

    /**
     * 获取消息类型
     */
    public function getMessageType(): string
    {
        return 'sync_applications';
    }

    /**
     * 获取消息优先级
     * 针对特定账号的同步任务优先级为10
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->getMessageId(),
            'message_type' => $this->getMessageType(),
            'account_id' => $this->accountId,
            'metadata' => $this->metadata,
            'priority' => $this->getPriority(),
        ];
    }

    /**
     * 获取同步范围描述
     */
    public function getScopeDescription(): string
    {
        return "账号:{$this->accountId}的应用同步";
    }
}
