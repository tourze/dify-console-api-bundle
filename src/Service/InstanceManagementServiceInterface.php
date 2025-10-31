<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Tourze\DifyConsoleApiBundle\DTO\CreateInstanceRequest;
use Tourze\DifyConsoleApiBundle\DTO\UpdateInstanceRequest;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

interface InstanceManagementServiceInterface
{
    public function createInstance(CreateInstanceRequest $request): DifyInstance;

    public function updateInstance(int $instanceId, UpdateInstanceRequest $request): DifyInstance;

    public function enableInstance(int $instanceId): bool;

    public function disableInstance(int $instanceId): bool;

    /**
     * @return DifyInstance[]
     */
    public function getEnabledInstances(): array;

    /**
     * @return DifyInstance[]
     */
    public function getAllInstances(): array;
}
