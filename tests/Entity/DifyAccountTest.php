<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * DifyAccount 实体单元测试
 * 测试重点：Token过期逻辑、密码字段处理、状态管理、时间戳追踪
 * @internal
 */
#[CoversClass(DifyAccount::class)]
class DifyAccountTest extends AbstractEntityTestCase
{
    private DifyAccount $account;

    protected function createEntity(): object
    {
        return new DifyAccount();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        return [
            'instance' => ['instance', $instance],
            'email' => ['email', 'test@example.com'],
            'password' => ['password', 'secure_password_123'],
            'nickname' => ['nickname', 'TestUser'],
            'accessToken' => ['accessToken', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'],
            'tokenExpiresTime' => ['tokenExpiresTime', new \DateTimeImmutable('+1 hour')],
            'lastLoginTime' => ['lastLoginTime', new \DateTimeImmutable()],
        ];
    }

    protected function setUp(): void
    {
        $this->account = new DifyAccount();
    }

    public function testConstructorSetsCreateAndUpdateTimeToCurrentTime(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $entity = new DifyAccount();
        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getCreateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getCreateTime());
        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getUpdateTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getUpdateTime());
    }

    public function testIdIsNullByDefault(): void
    {
        $this->assertNull($this->account->getId());
    }

    public function testInstanceSetterAndGetter(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setInstance($instance);

        $this->assertSame($instance, $this->account->getInstance());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testEmailSetterAndGetter(): void
    {
        $email = 'test@example.com';

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setEmail($email);

        $this->assertSame($email, $this->account->getEmail());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    #[TestWith(['user@example.com'])] // simple_email
    #[TestWith(['user@mail.example.com'])] // email_with_subdomain
    #[TestWith(['user123@example.com'])] // email_with_numbers
    #[TestWith(['user.name@example.com'])] // email_with_dots
    #[TestWith(['user+tag@example.com'])] // email_with_plus
    #[TestWith(['user-name@example-domain.com'])] // email_with_hyphen
    public function testEmailWithVariousValues(string $email): void
    {
        $this->account->setEmail($email);
        $this->assertSame($email, $this->account->getEmail());
    }

    public function testPasswordSetterAndGetter(): void
    {
        $password = 'secure_password_123';

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setPassword($password);

        $this->assertSame($password, $this->account->getPassword());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testNicknameSetterAndGetter(): void
    {
        $nickname = 'TestUser';

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setNickname($nickname);

        $this->assertSame($nickname, $this->account->getNickname());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testNicknameCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->account->getNickname());

        // 测试设置为null
        $this->account->setNickname('SomeNickname');
        $this->account->setNickname(null);
        $this->assertNull($this->account->getNickname());
    }

    public function testAccessTokenSetterAndGetter(): void
    {
        $accessToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setAccessToken($accessToken);

        $this->assertSame($accessToken, $this->account->getAccessToken());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testAccessTokenCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->account->getAccessToken());

        // 测试设置为null
        $this->account->setAccessToken('some_token');
        $this->account->setAccessToken(null);
        $this->assertNull($this->account->getAccessToken());
    }

    public function testTokenExpiresTimeSetterAndGetter(): void
    {
        $expiresTime = new \DateTimeImmutable('+1 hour');

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setTokenExpiresTime($expiresTime);

        $this->assertSame($expiresTime, $this->account->getTokenExpiresTime());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testTokenExpiresTimeCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->account->getTokenExpiresTime());

        // 测试设置为null
        $this->account->setTokenExpiresTime(new \DateTimeImmutable());
        $this->account->setTokenExpiresTime(null);
        $this->assertNull($this->account->getTokenExpiresTime());
    }

    public function testIsEnabledDefaultsToTrue(): void
    {
        $this->assertTrue($this->account->isEnabled());
    }

    public function testIsEnabledSetterAndGetter(): void
    {
        $beforeUpdate = $this->account->getUpdateTime();

        // 测试设置为false
        $this->account->setIsEnabled(false);
        $this->assertFalse($this->account->isEnabled());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());

        $beforeUpdate = $this->account->getUpdateTime();

        // 测试设置为true
        $this->account->setIsEnabled(true);
        $this->assertTrue($this->account->isEnabled());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testLastLoginTimeSetterAndGetter(): void
    {
        $loginTime = new \DateTimeImmutable();

        $beforeUpdate = $this->account->getUpdateTime();
        $this->account->setLastLoginTime($loginTime);

        $this->assertSame($loginTime, $this->account->getLastLoginTime());
        $this->assertGreaterThan($beforeUpdate, $this->account->getUpdateTime());
    }

    public function testLastLoginTimeCanBeNull(): void
    {
        // 测试初始值
        $this->assertNull($this->account->getLastLoginTime());

        // 测试设置为null
        $this->account->setLastLoginTime(new \DateTimeImmutable());
        $this->account->setLastLoginTime(null);
        $this->assertNull($this->account->getLastLoginTime());
    }

    #[TestWith([null, true])] // null_expiration
    #[TestWith(['-1 hour', true])] // expired_token
    #[TestWith(['-1 second', true])] // just_expired
    #[TestWith(['+1 hour', false])] // valid_token
    #[TestWith(['+1 second', false])] // just_valid
    #[TestWith(['+1 year', false])] // far_future
    public function testIsTokenExpired(?string $expirationTime, bool $expectedExpired): void
    {
        if (null !== $expirationTime) {
            $this->account->setTokenExpiresTime(new \DateTimeImmutable($expirationTime));
        }

        $this->assertSame($expectedExpired, $this->account->isTokenExpired());
    }

    public function testTokenExpirationLogicWithCurrentTime(): void
    {
        $now = new \DateTimeImmutable();

        // 测试当前时间之前的token（已过期）
        $this->account->setTokenExpiresTime($now->modify('-1 second'));
        $this->assertTrue($this->account->isTokenExpired());

        // 测试当前时间之后的token（未过期）
        $this->account->setTokenExpiresTime($now->modify('+1 second'));
        $this->assertFalse($this->account->isTokenExpired());

        // 测试正好等于当前时间的token（已过期）
        $this->account->setTokenExpiresTime($now);
        $this->assertTrue($this->account->isTokenExpired());
    }

    public function testCreateTimeIsImmutable(): void
    {
        $createTime = $this->account->getCreateTime();

        // 任何操作都不应改变createTime
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');

        $this->account->setInstance($instance);
        $this->account->setEmail('test@example.com');
        $this->account->setPassword('password');
        $this->account->setNickname('nickname');
        $this->account->setAccessToken('token');
        $this->account->setIsEnabled(false);

        $this->assertSame($createTime, $this->account->getCreateTime());
    }

    public function testUpdateTimeChangesOnAllSetters(): void
    {
        $initialUpdateTime = $this->account->getUpdateTime();

        // 等待确保时间差异
        usleep(1000);

        // 测试每个setter都会更新updateTime
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');
        $this->account->setInstance($instance);
        $updateTime1 = $this->account->getUpdateTime();
        $this->assertGreaterThan($initialUpdateTime, $updateTime1);

        usleep(1000);
        $this->account->setEmail('test@example.com');
        $updateTime2 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime1, $updateTime2);

        usleep(1000);
        $this->account->setPassword('password');
        $updateTime3 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime2, $updateTime3);

        usleep(1000);
        $this->account->setNickname('nickname');
        $updateTime4 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime3, $updateTime4);

        usleep(1000);
        $this->account->setAccessToken('token');
        $updateTime5 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime4, $updateTime5);

        usleep(1000);
        $this->account->setTokenExpiresTime(new \DateTimeImmutable());
        $updateTime6 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime5, $updateTime6);

        usleep(1000);
        $this->account->setIsEnabled(false);
        $updateTime7 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime6, $updateTime7);

        usleep(1000);
        $this->account->setLastLoginTime(new \DateTimeImmutable());
        $updateTime8 = $this->account->getUpdateTime();
        $this->assertGreaterThan($updateTime7, $updateTime8);
    }

    public function testCompleteAccountConfiguration(): void
    {
        $instance = new DifyInstance();
        $instance->setName('Test Instance');
        $instance->setBaseUrl('https://test.example.com');
        $email = 'user@example.com';
        $password = 'secure_password';
        $nickname = 'TestUser';
        $accessToken = 'jwt_token_here';
        $tokenExpiresTime = new \DateTimeImmutable('+1 hour');
        $isEnabled = true;
        $lastLoginTime = new \DateTimeImmutable();

        $this->account->setInstance($instance);
        $this->account->setEmail($email);
        $this->account->setPassword($password);
        $this->account->setNickname($nickname);
        $this->account->setAccessToken($accessToken);
        $this->account->setTokenExpiresTime($tokenExpiresTime);
        $this->account->setIsEnabled($isEnabled);
        $this->account->setLastLoginTime($lastLoginTime);

        // 验证所有属性都正确设置
        $this->assertSame($instance, $this->account->getInstance());
        $this->assertSame($email, $this->account->getEmail());
        $this->assertSame($password, $this->account->getPassword());
        $this->assertSame($nickname, $this->account->getNickname());
        $this->assertSame($accessToken, $this->account->getAccessToken());
        $this->assertSame($tokenExpiresTime, $this->account->getTokenExpiresTime());
        $this->assertSame($isEnabled, $this->account->isEnabled());
        $this->assertSame($lastLoginTime, $this->account->getLastLoginTime());

        // 验证token未过期
        $this->assertFalse($this->account->isTokenExpired());

        // 验证时间戳
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->account->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->account->getUpdateTime());
        $this->assertGreaterThanOrEqual($this->account->getCreateTime(), $this->account->getUpdateTime());
    }

    public function testTokenLifecycleScenario(): void
    {
        // 模拟token生命周期
        $this->account->setEmail('user@example.com');
        $this->account->setPassword('password');

        // 初始状态：无token
        $this->assertNull($this->account->getAccessToken());
        $this->assertNull($this->account->getTokenExpiresTime());
        $this->assertTrue($this->account->isTokenExpired());

        // 设置有效token
        $this->account->setAccessToken('valid_token');
        $this->account->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));
        $this->assertFalse($this->account->isTokenExpired());

        // 模拟登录
        $this->account->setLastLoginTime(new \DateTimeImmutable());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->account->getLastLoginTime());

        // 模拟token过期
        $this->account->setTokenExpiresTime(new \DateTimeImmutable('-1 hour'));
        $this->assertTrue($this->account->isTokenExpired());

        // 清除token
        $this->account->setAccessToken(null);
        $this->account->setTokenExpiresTime(null);
        $this->assertNull($this->account->getAccessToken());
        $this->assertNull($this->account->getTokenExpiresTime());
        $this->assertTrue($this->account->isTokenExpired());
    }
}
