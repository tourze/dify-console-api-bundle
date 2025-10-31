<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class AuthenticationResult
{
    public function __construct(
        public bool $success,
        public ?string $token,
        public ?\DateTimeImmutable $expiresTime,
        public ?string $errorMessage,
    ) {
    }
}
