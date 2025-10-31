<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class UpdateInstanceRequest
{
    public function __construct(
        public ?string $name = null,
        public ?string $baseUrl = null,
        public ?string $description = null,
        public ?bool $isEnabled = null,
    ) {
    }
}
