<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

/**
 * 应用 DSL 导出结果
 */
readonly class AppDslExportResult
{
    /**
     * @param array<string, mixed>|null $dslContent
     */
    public function __construct(
        public bool $success,
        public ?array $dslContent = null,
        public ?string $errorMessage = null,
        public ?string $rawContent = null,
    ) {
    }
}
