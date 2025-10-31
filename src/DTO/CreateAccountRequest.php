<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class CreateAccountRequest
{
    public function __construct(
        public int $instanceId,
        public string $email,
        public string $password,
        public ?string $nickname = null,
        public bool $isEnabled = true,
    ) {
    }
}
