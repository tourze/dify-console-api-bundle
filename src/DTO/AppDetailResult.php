<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class AppDetailResult
{
    /**
     * @param array<string, mixed>|null $appData
     */
    public function __construct(
        public bool $success,
        public ?array $appData,
        public ?string $errorMessage = null,
    ) {
    }
}
