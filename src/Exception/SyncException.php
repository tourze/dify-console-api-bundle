<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * 同步异常
 *
 * 当数据同步过程中发生错误时抛出此异常
 * 包括 Dify 应用同步、账户同步等操作的错误
 */
class SyncException extends \Exception
{
    private string $syncType;

    private ?string $entityId;

    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $syncType,
        string $message,
        ?string $entityId = null,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->syncType = $syncType;
        $this->entityId = $entityId;
        $this->context = $context;
    }

    public function getSyncType(): string
    {
        return $this->syncType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public static function appSyncFailed(string $appId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            'app_sync',
            "应用同步失败: {$reason}",
            $appId,
            ['app_id' => $appId, 'reason' => $reason],
            $previous
        );
    }

    public static function accountSyncFailed(string $accountId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            'account_sync',
            "账户同步失败: {$reason}",
            $accountId,
            ['account_id' => $accountId, 'reason' => $reason],
            $previous
        );
    }

    public static function instanceSyncFailed(string $instanceId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            'instance_sync',
            "实例同步失败: {$reason}",
            $instanceId,
            ['instance_id' => $instanceId, 'reason' => $reason],
            $previous
        );
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function dataValidationFailed(string $syncType, string $entityId, array $errors): self
    {
        return new self(
            $syncType,
            '数据验证失败',
            $entityId,
            ['validation_errors' => $errors]
        );
    }

    public static function conflictResolutionFailed(string $syncType, string $entityId, string $conflictType): self
    {
        return new self(
            $syncType,
            "冲突解决失败: {$conflictType}",
            $entityId,
            ['conflict_type' => $conflictType]
        );
    }
}
