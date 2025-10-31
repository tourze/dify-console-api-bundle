<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class CreateInstanceRequest
{
    public function __construct(
        public string $name,
        public string $baseUrl,
        public ?string $description = null,
        public bool $isEnabled = true,
    ) {
    }
}
