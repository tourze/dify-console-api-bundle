<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DTO;

final readonly class AppListQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 30,
        public ?string $name = null,
        public ?bool $isCreatedByMe = null,
        public ?string $mode = null,
    ) {
    }
}
