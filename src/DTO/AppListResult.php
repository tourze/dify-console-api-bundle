<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class AppListResult
{
    /**
     * @param array<int, array<string, mixed>|string> $apps 允许包含非法数据用于错误处理测试
     */
    public function __construct(
        public bool $success,
        public array $apps,
        public int $total,
        public int $page,
        public int $limit,
        public ?string $errorMessage = null,
    ) {
    }
}
