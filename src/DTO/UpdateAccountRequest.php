<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class UpdateAccountRequest
{
    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
        public ?string $nickname = null,
        public ?bool $isEnabled = null,
    ) {
    }
}
