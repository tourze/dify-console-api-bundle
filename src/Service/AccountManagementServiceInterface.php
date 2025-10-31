<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Tourze\DifyConsoleApiBundle\DTO\CreateAccountRequest;
use Tourze\DifyConsoleApiBundle\DTO\UpdateAccountRequest;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;

interface AccountManagementServiceInterface
{
    public function createAccount(CreateAccountRequest $request): DifyAccount;

    public function updateAccount(int $accountId, UpdateAccountRequest $request): DifyAccount;

    public function enableAccount(int $accountId): bool;

    public function disableAccount(int $accountId): bool;

    /**
     * @return DifyAccount[]
     */
    public function getAccountsByInstance(int $instanceId): array;

    /**
     * @return DifyAccount[]
     */
    public function getEnabledAccounts(?int $instanceId = null): array;

    /**
     * @return DifyAccount[]
     */
    public function getAllAccounts(): array;
}
