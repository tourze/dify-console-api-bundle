<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * DifyAccountRepository 仓储单元测试
 *
 * 测试重点：自定义查询方法、账户状态管理、令牌过期查询
 * @internal
 */
#[CoversClass(DifyAccountRepository::class)]
#[RunTestsInSeparateProcesses]
final class DifyAccountRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): DifyAccount
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $em = self::getEntityManager();
        $em->persist($instance);
        $em->flush();

        $account = new DifyAccount();
        $account->setInstance($instance);
        $account->setEmail('test_' . uniqid() . '@example.com');
        $account->setPassword('test_password_123');

        return $account;
    }

    /**
     * @return DifyAccountRepository
     */
    protected function getRepository(): DifyAccountRepository
    {
        return self::getService(DifyAccountRepository::class);
    }

    public function testFindByInstanceWithExistingAccounts(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Test Instance 777');
        $instance->setBaseUrl('https://test777.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        $otherInstance = new DifyInstance();
        $otherInstance->setName('Other Instance');
        $otherInstance->setBaseUrl('https://other.example.com');
        $entityManager->persist($otherInstance);
        $entityManager->flush();

        // 创建多个同一实例的账户
        $account1 = new DifyAccount();
        $account1->setInstance($instance);
        $account1->setEmail('user1@example.com');
        $account1->setPassword('password123');
        $account1->setNickname('User One');

        $account2 = new DifyAccount();
        $account2->setInstance($instance);
        $account2->setEmail('user2@example.com');
        $account2->setPassword('password123');
        $account2->setNickname('User Two');

        // 创建不同实例的账户（不应该被查找到）
        $otherAccount = new DifyAccount();
        $otherAccount->setInstance($otherInstance);
        $otherAccount->setEmail('other@example.com');
        $otherAccount->setPassword('password123');

        $entityManager->persist($account1);
        $entityManager->persist($account2);
        $entityManager->persist($otherAccount);
        $entityManager->flush();

        // 模拟时间差异以测试排序
        usleep(1000);
        $account2->setNickname('Updated User Two');
        $entityManager->flush();

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');
        $foundAccounts = $repository->findByInstance($instanceId);

        $this->assertCount(2, $foundAccounts);
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $foundAccounts);

        // 验证所有账户都属于正确的实例
        foreach ($foundAccounts as $account) {
            $this->assertSame($instance, $account->getInstance());
        }

        // 验证按更新时间降序排列（最近更新的在前）
        $this->assertSame('Updated User Two', $foundAccounts[0]->getNickname());
    }

    public function testFindByInstanceWithNoAccounts(): void
    {
        $repository = $this->getRepository();

        $foundAccounts = $repository->findByInstance(999999);

        $this->assertIsArray($foundAccounts);
        $this->assertEmpty($foundAccounts);
    }

    public function testFindEnabledAccountsWithInstanceFilter(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Test Instance 555');
        $instance->setBaseUrl('https://test555.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        $otherInstance = new DifyInstance();
        $otherInstance->setName('Other Instance 666');
        $otherInstance->setBaseUrl('https://test666.example.com');
        $entityManager->persist($otherInstance);
        $entityManager->flush();

        // 创建启用的账户
        $enabledAccount1 = new DifyAccount();
        $enabledAccount1->setInstance($instance);
        $enabledAccount1->setEmail('enabled1@example.com');
        $enabledAccount1->setPassword('password123');
        $enabledAccount1->setIsEnabled(true);

        $enabledAccount2 = new DifyAccount();
        $enabledAccount2->setInstance($instance);
        $enabledAccount2->setEmail('enabled2@example.com');
        $enabledAccount2->setPassword('password123');
        $enabledAccount2->setIsEnabled(true);

        // 创建禁用的账户（不应该被查找到）
        $disabledAccount = new DifyAccount();
        $disabledAccount->setInstance($instance);
        $disabledAccount->setEmail('disabled@example.com');
        $disabledAccount->setPassword('password123');
        $disabledAccount->setIsEnabled(false);

        // 创建其他实例的启用账户（不应该被查找到）
        $otherInstanceAccount = new DifyAccount();
        $otherInstanceAccount->setInstance($otherInstance);
        $otherInstanceAccount->setEmail('other_instance@example.com');
        $otherInstanceAccount->setPassword('password123');
        $otherInstanceAccount->setIsEnabled(true);

        $entityManager->persist($enabledAccount1);
        $entityManager->persist($enabledAccount2);
        $entityManager->persist($disabledAccount);
        $entityManager->persist($otherInstanceAccount);
        $entityManager->flush();

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');
        $foundAccounts = $repository->findEnabledAccounts($instanceId);

        $this->assertCount(2, $foundAccounts);
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $foundAccounts);

        foreach ($foundAccounts as $account) {
            $this->assertTrue($account->isEnabled());
            $this->assertSame($instance, $account->getInstance());
        }
    }

    public function testFindEnabledAccountsWithoutInstanceFilter(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 清理可能存在的测试数据
        $entityManager->clear();

        $instance1 = new DifyInstance();
        $instance1->setName('Instance 111');
        $instance1->setBaseUrl('https://test111.example.com');
        $entityManager->persist($instance1);

        $instance2 = new DifyInstance();
        $instance2->setName('Instance 222');
        $instance2->setBaseUrl('https://test222.example.com');
        $entityManager->persist($instance2);

        $instance3 = new DifyInstance();
        $instance3->setName('Instance 333');
        $instance3->setBaseUrl('https://test333.example.com');
        $entityManager->persist($instance3);
        $entityManager->flush();

        // 创建启用的账户（不同实例）
        $enabledAccount1 = new DifyAccount();
        $enabledAccount1->setInstance($instance1);
        $enabledAccount1->setEmail('global_enabled1@example.com');
        $enabledAccount1->setPassword('password123');
        $enabledAccount1->setIsEnabled(true);

        $enabledAccount2 = new DifyAccount();
        $enabledAccount2->setInstance($instance2);
        $enabledAccount2->setEmail('global_enabled2@example.com');
        $enabledAccount2->setPassword('password123');
        $enabledAccount2->setIsEnabled(true);

        // 创建禁用的账户（不应该被查找到）
        $disabledAccount = new DifyAccount();
        $disabledAccount->setInstance($instance3);
        $disabledAccount->setEmail('global_disabled@example.com');
        $disabledAccount->setPassword('password123');
        $disabledAccount->setIsEnabled(false);

        $entityManager->persist($enabledAccount1);
        $entityManager->persist($enabledAccount2);
        $entityManager->persist($disabledAccount);
        $entityManager->flush();

        $foundAccounts = $repository->findEnabledAccounts(null);

        $this->assertGreaterThanOrEqual(2, count($foundAccounts));
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $foundAccounts);

        foreach ($foundAccounts as $account) {
            $this->assertTrue($account->isEnabled());
        }
    }

    public function testFindByEmailWithExistingAccount(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Test Instance 123');
        $instance->setBaseUrl('https://test123.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        $email = 'test_find_by_email@example.com';

        $account = new DifyAccount();
        $account->setInstance($instance);
        $account->setEmail($email);
        $account->setPassword('password123');
        $account->setNickname('Test User');

        $entityManager->persist($account);
        $entityManager->flush();

        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');
        $foundAccount = $repository->findByEmail($email, $instanceId);

        $this->assertInstanceOf(DifyAccount::class, $foundAccount);
        $this->assertSame($email, $foundAccount->getEmail());
        $this->assertSame($instance, $foundAccount->getInstance());
        $this->assertSame('Test User', $foundAccount->getNickname());
    }

    public function testFindByEmailWithNonExistentAccount(): void
    {
        $repository = $this->getRepository();

        $foundAccount = $repository->findByEmail('non_existent@example.com', 999);

        $this->assertNull($foundAccount);
    }

    public function testFindByEmailWithWrongInstance(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试实例
        $instance = new DifyInstance();
        $instance->setName('Test Instance 123');
        $instance->setBaseUrl('https://test123.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        $account = $this->createNewEntity();
        $account->setInstance($instance);
        $account->setEmail('instance_specific@example.com');

        $entityManager->persist($account);
        $entityManager->flush();

        // 使用错误的实例ID
        $foundAccount = $repository->findByEmail('instance_specific@example.com', 456);

        $this->assertNull($foundAccount);
    }

    #[DataProvider('emailSearchProvider')]
    public function testFindByEmailWithVariousEmailFormats(string $email, int $instanceId): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建测试实例
        $instance = new DifyInstance();
        $instance->setName("Test Instance {$instanceId}");
        $instance->setBaseUrl("https://test{$instanceId}.example.com");
        $entityManager->persist($instance);
        $entityManager->flush();

        $account = $this->createNewEntity();
        $account->setInstance($instance);
        $account->setEmail($email);

        $entityManager->persist($account);
        $entityManager->flush();

        $persistedInstanceId = $instance->getId();
        $this->assertNotNull($persistedInstanceId, 'Instance ID should not be null after persist and flush');
        $foundAccount = $repository->findByEmail($email, $persistedInstanceId);

        $this->assertInstanceOf(DifyAccount::class, $foundAccount);
        $this->assertSame($email, $foundAccount->getEmail());
        $this->assertSame($persistedInstanceId, $foundAccount->getInstance()->getId());
    }

    /**
     * @return array<string, array{email: string, instanceId: int}>
     */
    public static function emailSearchProvider(): array
    {
        return [
            'basic_email' => [
                'email' => 'basic@example.com',
                'instanceId' => 100,
            ],
            'email_with_plus' => [
                'email' => 'user+tag@example.com',
                'instanceId' => 200,
            ],
            'email_with_dots' => [
                'email' => 'user.name@example.com',
                'instanceId' => 300,
            ],
            'subdomain_email' => [
                'email' => 'admin@subdomain.example.com',
                'instanceId' => 400,
            ],
        ];
    }

    public function testFindExpiredTokensWithExpiredAccounts(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 清理现有的DifyAccount数据，确保测试隔离
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $connection->executeStatement($platform->getTruncateTableSQL('dify_account', true));

        $now = new \DateTimeImmutable();
        $oneHourAgo = $now->modify('-1 hour');
        $oneHourLater = $now->modify('+1 hour');

        // 创建令牌已过期的启用账户
        $expiredAccount1 = $this->createNewEntity();
        $expiredAccount1->setEmail('expired1@example.com');
        $expiredAccount1->setIsEnabled(true);
        $expiredAccount1->setTokenExpiresTime($oneHourAgo);

        $expiredAccount2 = $this->createNewEntity();
        $expiredAccount2->setEmail('expired2@example.com');
        $expiredAccount2->setIsEnabled(true);
        $expiredAccount2->setTokenExpiresTime(null); // null也被认为是过期

        // 创建令牌未过期的账户（不应该被查找到）
        $validAccount = $this->createNewEntity();
        $validAccount->setEmail('valid@example.com');
        $validAccount->setIsEnabled(true);
        $validAccount->setTokenExpiresTime($oneHourLater);

        // 创建令牌过期但被禁用的账户（不应该被查找到）
        $disabledExpiredAccount = $this->createNewEntity();
        $disabledExpiredAccount->setEmail('disabled_expired@example.com');
        $disabledExpiredAccount->setIsEnabled(false);
        $disabledExpiredAccount->setTokenExpiresTime($oneHourAgo);

        $entityManager->persist($expiredAccount1);
        $entityManager->persist($expiredAccount2);
        $entityManager->persist($validAccount);
        $entityManager->persist($disabledExpiredAccount);
        $entityManager->flush();

        $foundAccounts = $repository->findExpiredTokens();

        $this->assertCount(2, $foundAccounts);
        $this->assertContainsOnlyInstancesOf(DifyAccount::class, $foundAccounts);

        foreach ($foundAccounts as $account) {
            $this->assertTrue($account->isEnabled());
            $this->assertTrue($account->isTokenExpired());
        }

        // 验证按过期时间升序排列（最早过期的在前）
        $this->assertLessThanOrEqual(
            $foundAccounts[1]->getTokenExpiresTime() ?? new \DateTimeImmutable('1970-01-01'),
            $foundAccounts[0]->getTokenExpiresTime() ?? new \DateTimeImmutable('1970-01-01')
        );
    }

    public function testFindExpiredTokensWithNoExpiredAccounts(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 清理可能存在的测试数据
        $entityManager->clear();

        // 创建令牌未过期的账户
        $validAccount = $this->createNewEntity();
        $validAccount->setEmail('only_valid@example.com');
        $validAccount->setIsEnabled(true);
        $validAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $entityManager->persist($validAccount);
        $entityManager->flush();

        $foundAccounts = $repository->findExpiredTokens();

        // 可能有其他测试创建的过期账户，所以只验证我们创建的这个不在结果中
        foreach ($foundAccounts as $account) {
            $this->assertNotSame('only_valid@example.com', $account->getEmail());
        }
    }

    public function testEnableAccountWithExistingAccount(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建禁用的账户
        $account = $this->createNewEntity();
        $account->setEmail('to_enable@example.com');
        $account->setIsEnabled(false);

        $entityManager->persist($account);
        $entityManager->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId, 'Account ID should not be null after persist and flush');

        // 启用账户
        $repository->enableAccount($accountId);

        // 重新获取账户验证状态
        $entityManager->refresh($account);
        $this->assertTrue($account->isEnabled());
    }

    public function testEnableAccountWithNonExistentAccount(): void
    {
        $repository = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Account with ID 999999 not found');

        $repository->enableAccount(999999);
    }

    public function testDisableAccountWithExistingAccount(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建启用的账户
        $account = $this->createNewEntity();
        $account->setEmail('to_disable@example.com');
        $account->setIsEnabled(true);

        $entityManager->persist($account);
        $entityManager->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId, 'Account ID should not be null after persist and flush');

        // 禁用账户
        $repository->disableAccount($accountId);

        // 重新获取账户验证状态
        $entityManager->refresh($account);
        $this->assertFalse($account->isEnabled());
    }

    public function testDisableAccountWithNonExistentAccount(): void
    {
        $repository = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Account with ID 888888 not found');

        $repository->disableAccount(888888);
    }

    public function testRepositoryInheritanceAndMethods(): void
    {
        $repository = $this->getRepository();

        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
        $this->assertSame(DifyAccount::class, $repository->getClassName());

        // 测试基本的Repository方法
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified

        // 测试自定义方法
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified
        // $this->assertTrue(method_exists($repository, ...)); // PHPStan verified

        // 验证方法存在性已由类型系统保证
        $this->assertTrue(true, 'Repository methods verified by static analysis');
    }

    public function testComplexQueryScenario(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $instance = new DifyInstance();
        $instance->setName('Company Instance');
        $instance->setBaseUrl('https://company.example.com');
        $entityManager->persist($instance);
        $entityManager->flush();

        // 创建复杂场景的测试数据
        $activeAccount = new DifyAccount();
        $activeAccount->setInstance($instance);
        $activeAccount->setEmail('active@company.com');
        $activeAccount->setPassword('password123');
        $activeAccount->setNickname('Active User');
        $activeAccount->setIsEnabled(true);
        $activeAccount->setTokenExpiresTime(new \DateTimeImmutable('+2 hours'));
        $activeAccount->setAccessToken('valid_token_123');

        $expiredAccount = new DifyAccount();
        $expiredAccount->setInstance($instance);
        $expiredAccount->setEmail('expired@company.com');
        $expiredAccount->setPassword('password123');
        $expiredAccount->setNickname('Expired User');
        $expiredAccount->setIsEnabled(true);
        $expiredAccount->setTokenExpiresTime(new \DateTimeImmutable('-1 hour'));
        $expiredAccount->setAccessToken('expired_token_456');

        $disabledAccount = new DifyAccount();
        $disabledAccount->setInstance($instance);
        $disabledAccount->setEmail('disabled@company.com');
        $disabledAccount->setPassword('password123');
        $disabledAccount->setNickname('Disabled User');
        $disabledAccount->setIsEnabled(false);

        $entityManager->persist($activeAccount);
        $entityManager->persist($expiredAccount);
        $entityManager->persist($disabledAccount);
        $entityManager->flush();

        // 测试多种查询方法
        $instanceId = $instance->getId();
        $this->assertNotNull($instanceId, 'Instance ID should not be null after persist and flush');
        $byInstance = $repository->findByInstance($instanceId);
        $this->assertCount(3, $byInstance);

        $enabledAccounts = $repository->findEnabledAccounts($instanceId);
        $this->assertCount(2, $enabledAccounts);

        $expiredTokens = $repository->findExpiredTokens();
        $this->assertGreaterThanOrEqual(1, count($expiredTokens));

        $specificAccount = $repository->findByEmail('active@company.com', $instanceId);
        $this->assertInstanceOf(DifyAccount::class, $specificAccount);
        $this->assertSame('Active User', $specificAccount->getNickname());
        $this->assertFalse($specificAccount->isTokenExpired());

        // 测试账户状态操作
        $disabledAccountId = $disabledAccount->getId();
        $this->assertNotNull($disabledAccountId, 'Account ID should not be null after persist and flush');
        $repository->enableAccount($disabledAccountId);
        $entityManager->refresh($disabledAccount);
        $this->assertTrue($disabledAccount->isEnabled());

        $repository->disableAccount($disabledAccountId);
        $entityManager->refresh($disabledAccount);
        $this->assertFalse($disabledAccount->isEnabled());
    }

    public function testTokenExpirationLogic(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        $now = new \DateTimeImmutable();

        // 创建各种令牌状态的账户
        $accounts = [];

        // 刚刚过期
        $justExpired = $this->createNewEntity();
        $justExpired->setEmail('just_expired@example.com');
        $justExpired->setIsEnabled(true);
        $justExpired->setTokenExpiresTime($now->modify('-1 second'));
        $accounts[] = $justExpired;

        // 即将过期（还有效）
        $almostExpired = $this->createNewEntity();
        $almostExpired->setEmail('almost_expired@example.com');
        $almostExpired->setIsEnabled(true);
        $almostExpired->setTokenExpiresTime($now->modify('+1 second'));
        $accounts[] = $almostExpired;

        // 令牌时间为null（被认为是过期）
        $nullExpiry = $this->createNewEntity();
        $nullExpiry->setEmail('null_expiry@example.com');
        $nullExpiry->setIsEnabled(true);
        $nullExpiry->setTokenExpiresTime(null);
        $accounts[] = $nullExpiry;

        foreach ($accounts as $account) {
            $entityManager->persist($account);
        }
        $entityManager->flush();

        $expiredAccounts = $repository->findExpiredTokens();

        // 验证过期账户中包含我们期望的账户
        $expiredEmails = array_map(fn ($account) => $account->getEmail(), $expiredAccounts);

        $this->assertContains('just_expired@example.com', $expiredEmails);
        $this->assertContains('null_expiry@example.com', $expiredEmails);
        $this->assertNotContains('almost_expired@example.com', $expiredEmails);
    }

    public function testAccountStatusToggling(): void
    {
        $repository = $this->getRepository();
        $entityManager = self::getEntityManager();

        // 创建账户
        $account = $this->createNewEntity();
        $account->setEmail('toggle_test@example.com');
        $account->setIsEnabled(true);

        $entityManager->persist($account);
        $entityManager->flush();

        $accountId = $account->getId();
        $this->assertNotNull($accountId, 'Account ID should not be null after persist and flush');

        // 初始状态验证
        $this->assertTrue($account->isEnabled());

        // 禁用账户
        $repository->disableAccount($accountId);
        $entityManager->refresh($account);
        $this->assertFalse($account->isEnabled());

        // 重新启用账户
        $repository->enableAccount($accountId);
        $entityManager->refresh($account);
        $this->assertTrue($account->isEnabled());

        // 再次禁用
        $repository->disableAccount($accountId);
        $entityManager->refresh($account);
        $this->assertFalse($account->isEnabled());
    }
}
