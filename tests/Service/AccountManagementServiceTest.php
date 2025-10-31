<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\DTO\CreateAccountRequest;
use Tourze\DifyConsoleApiBundle\DTO\UpdateAccountRequest;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\DifyConsoleApiBundle\Service\AccountManagementService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccountManagementService::class)]
#[RunTestsInSeparateProcesses]
final class AccountManagementServiceTest extends AbstractIntegrationTestCase
{
    private DifyAccountRepository $accountRepository;

    private AccountManagementService $service;

    /**
     * @var array<object>
     */
    private array $testEntities = [];

    protected function onSetUp(): void
    {
        $this->accountRepository = self::getService(DifyAccountRepository::class);
        $this->service = self::getService(AccountManagementService::class);
    }

    protected function onTearDown(): void
    {
        $em = self::getEntityManager();

        // 清理所有测试实体
        foreach (array_reverse($this->testEntities) as $entity) {
            if ($em->contains($entity)) {
                $em->remove($entity);
            }
        }

        if ([] !== $this->testEntities) {
            $em->flush();
        }

        $this->testEntities = [];
    }

    public function testCreateAccountSuccess(): void
    {
        // 创建测试用的 DifyInstance
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId);

        $request = new CreateAccountRequest(
            email: 'test@example.com',
            password: 'password123',
            instanceId: $instanceId,
            nickname: 'Test User',
            isEnabled: true
        );

        $result = $this->service->createAccount($request);
        $this->testEntities[] = $result;

        $this->assertInstanceOf(DifyAccount::class, $result);
        $this->assertSame('test@example.com', $result->getEmail());
        $this->assertSame($instance->getId(), $result->getInstance()->getId());
        $this->assertSame('Test User', $result->getNickname());
        $this->assertTrue($result->isEnabled());

        // 验证已保存到数据库
        $this->assertNotNull($result->getId());

        // 验证可以从数据库中查询到
        $em = self::getEntityManager();
        $em->clear();
        $foundAccount = $this->accountRepository->find($result->getId());
        $this->assertNotNull($foundAccount);
        $this->assertSame('test@example.com', $foundAccount->getEmail());
    }

    public function testCreateAccountThrowsExceptionWhenInstanceNotFound(): void
    {
        $request = new CreateAccountRequest(
            email: 'test@example.com',
            password: 'password123',
            instanceId: 999999,
            nickname: 'Test User',
            isEnabled: true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dify实例不存在: 999999');

        $this->service->createAccount($request);
    }

    public function testCreateAccountThrowsExceptionWhenEmailExists(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');

        // 先创建一个已存在的账号
        $existingAccount = $this->createTestAccount($instance, 'existing@example.com', 'password123');

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId);

        $request = new CreateAccountRequest(
            email: 'existing@example.com',
            password: 'password123',
            instanceId: $instanceId,
            nickname: 'Test User',
            isEnabled: true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该实例下邮箱已存在: existing@example.com');

        $this->service->createAccount($request);
    }

    public function testUpdateAccountSuccess(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'old@example.com', 'password123', 'Old User');

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $request = new UpdateAccountRequest(
            email: 'updated@example.com',
            password: 'newpassword',
            nickname: 'Updated User'
        );

        $result = $this->service->updateAccount($accountId, $request);

        $this->assertInstanceOf(DifyAccount::class, $result);
        $this->assertSame('updated@example.com', $result->getEmail());
        $this->assertSame('Updated User', $result->getNickname());

        // 验证数据库中已更新
        $em = self::getEntityManager();
        $em->clear();
        $foundAccount = $this->accountRepository->find($accountId);
        $this->assertNotNull($foundAccount);
        $this->assertSame('updated@example.com', $foundAccount->getEmail());
        $this->assertSame('Updated User', $foundAccount->getNickname());
    }

    public function testUpdateAccountThrowsExceptionWhenAccountNotFound(): void
    {
        $request = new UpdateAccountRequest(
            email: 'updated@example.com',
            password: 'newpassword',
            nickname: 'Updated User'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dify账号不存在: 999999');

        $this->service->updateAccount(999999, $request);
    }

    public function testUpdateAccountThrowsExceptionWhenEmailExists(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'old@example.com', 'password123');
        $existingAccount = $this->createTestAccount($instance, 'existing@example.com', 'password123');

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $request = new UpdateAccountRequest(
            email: 'existing@example.com',
            password: 'newpassword',
            nickname: 'Updated User'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该实例下邮箱已存在: existing@example.com');

        $this->service->updateAccount($accountId, $request);
    }

    public function testEnableAccount(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'test@example.com', 'password123');
        $account->setIsEnabled(false);

        $em = self::getEntityManager();
        $em->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $result = $this->service->enableAccount($accountId);

        $this->assertTrue($result);

        // 验证数据库中已更新
        $em->clear();
        $foundAccount = $this->accountRepository->find($accountId);
        $this->assertNotNull($foundAccount);
        $this->assertTrue($foundAccount->isEnabled());
    }

    public function testDisableAccount(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'test@example.com', 'password123');
        $account->setIsEnabled(true);

        $em = self::getEntityManager();
        $em->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $result = $this->service->disableAccount($accountId);

        $this->assertTrue($result);

        // 验证数据库中已更新
        $em->clear();
        $foundAccount = $this->accountRepository->find($accountId);
        $this->assertNotNull($foundAccount);
        $this->assertFalse($foundAccount->isEnabled());
    }

    public function testEnableAccountWhenAccountNotFound(): void
    {
        $result = $this->service->enableAccount(999999);

        $this->assertFalse($result);
    }

    public function testDisableAccountWhenAccountNotFound(): void
    {
        $result = $this->service->disableAccount(999999);

        $this->assertFalse($result);
    }

    public function testEnableAccountWhenAlreadyEnabled(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'test@example.com', 'password123');
        $account->setIsEnabled(true);

        $em = self::getEntityManager();
        $em->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $result = $this->service->enableAccount($accountId);

        $this->assertTrue($result);
        $this->assertTrue($account->isEnabled());
    }

    public function testDisableAccountWhenAlreadyDisabled(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account = $this->createTestAccount($instance, 'test@example.com', 'password123');
        $account->setIsEnabled(false);

        $em = self::getEntityManager();
        $em->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $result = $this->service->disableAccount($accountId);

        $this->assertTrue($result);
        $this->assertFalse($account->isEnabled());
    }

    public function testGetAccountsByInstance(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account1 = $this->createTestAccount($instance, 'test1@example.com', 'password123');
        $account2 = $this->createTestAccount($instance, 'test2@example.com', 'password123');

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId);

        $result = $this->service->getAccountsByInstance($instanceId);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $result);

        $emails = array_map(static fn (DifyAccount $account): string => $account->getEmail(), $result);
        $this->assertContains('test1@example.com', $emails);
        $this->assertContains('test2@example.com', $emails);
    }

    public function testGetAccountsByInstanceThrowsExceptionWhenInstanceNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dify实例不存在: 999999');

        $this->service->getAccountsByInstance(999999);
    }

    public function testGetEnabledAccounts(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $enabledAccount1 = $this->createTestAccount($instance, 'enabled1@example.com', 'password123');
        $enabledAccount1->setIsEnabled(true);
        $enabledAccount2 = $this->createTestAccount($instance, 'enabled2@example.com', 'password123');
        $enabledAccount2->setIsEnabled(true);
        $disabledAccount = $this->createTestAccount($instance, 'disabled@example.com', 'password123');
        $disabledAccount->setIsEnabled(false);

        $em = self::getEntityManager();
        $em->flush();

        $result = $this->service->getEnabledAccounts();

        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $result);

        foreach ($result as $account) {
            $this->assertTrue($account->isEnabled());
        }

        $emails = array_map(static fn (DifyAccount $account): string => $account->getEmail(), $result);
        $this->assertContains('enabled1@example.com', $emails);
        $this->assertContains('enabled2@example.com', $emails);
        $this->assertNotContains('disabled@example.com', $emails);
    }

    public function testGetEnabledAccountsWithInstanceId(): void
    {
        $instance1 = $this->createTestInstance('Instance 1', 'https://test1.example.com');
        $instance2 = $this->createTestInstance('Instance 2', 'https://test2.example.com');

        $enabledAccount1 = $this->createTestAccount($instance1, 'enabled1@example.com', 'password123');
        $enabledAccount1->setIsEnabled(true);
        $disabledAccount1 = $this->createTestAccount($instance1, 'disabled1@example.com', 'password123');
        $disabledAccount1->setIsEnabled(false);
        $enabledAccount2 = $this->createTestAccount($instance2, 'enabled2@example.com', 'password123');
        $enabledAccount2->setIsEnabled(true);

        $em = self::getEntityManager();
        $em->flush();

        $instance1Id = $instance1->getId();
        $this->assertNotNull($instance1Id);

        $result = $this->service->getEnabledAccounts($instance1Id);

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $result);

        $account = reset($result);
        $this->assertSame('enabled1@example.com', $account->getEmail());
        $this->assertTrue($account->isEnabled());
        $this->assertSame($instance1Id, $account->getInstance()->getId());
    }

    public function testGetEnabledAccountsThrowsExceptionWhenInstanceNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dify实例不存在: 999999');

        $this->service->getEnabledAccounts(999999);
    }

    public function testGetAllAccounts(): void
    {
        $instance = $this->createTestInstance('Test Instance', 'https://test.example.com');
        $account1 = $this->createTestAccount($instance, 'test1@example.com', 'password123');
        $account2 = $this->createTestAccount($instance, 'test2@example.com', 'password123');
        $account3 = $this->createTestAccount($instance, 'test3@example.com', 'password123');

        $result = $this->service->getAllAccounts();

        $this->assertGreaterThanOrEqual(3, count($result));
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $result);

        $emails = array_map(static fn (DifyAccount $account): string => $account->getEmail(), $result);
        $this->assertContains('test1@example.com', $emails);
        $this->assertContains('test2@example.com', $emails);
        $this->assertContains('test3@example.com', $emails);
    }

    private function createTestInstance(string $name, string $baseUrl): DifyInstance
    {
        $instance = new DifyInstance();
        $instance->setName($name);
        $instance->setBaseUrl($baseUrl);
        $instance->setIsEnabled(true);

        $em = self::getEntityManager();
        $em->persist($instance);
        $em->flush();

        $this->testEntities[] = $instance;

        return $instance;
    }

    private function createTestAccount(
        DifyInstance $instance,
        string $email,
        string $password,
        ?string $nickname = null,
    ): DifyAccount {
        $account = new DifyAccount();
        $account->setInstance($instance);
        $account->setEmail($email);
        $account->setPassword($password);

        if (null !== $nickname) {
            $account->setNickname($nickname);
        }

        $account->setIsEnabled(true);

        $em = self::getEntityManager();
        $em->persist($account);
        $em->flush();

        $this->testEntities[] = $account;

        return $account;
    }
}
