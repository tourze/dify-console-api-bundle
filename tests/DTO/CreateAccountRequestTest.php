<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\CreateAccountRequest;

/**
 * CreateAccountRequest DTO 单元测试
 * 测试重点：readonly类的不可变性、构造函数参数、数据完整性、默认值处理
 * @internal
 */
#[CoversClass(CreateAccountRequest::class)]
class CreateAccountRequestTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $instanceId = 123;
        $email = 'user@example.com';
        $password = 'secure_password_123';
        $nickname = 'TestUser';
        $isEnabled = true;

        $request = new CreateAccountRequest($instanceId, $email, $password, $nickname, $isEnabled);

        $this->assertSame($instanceId, $request->instanceId);
        $this->assertSame($email, $request->email);
        $this->assertSame($password, $request->password);
        $this->assertSame($nickname, $request->nickname);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $instanceId = 456;
        $email = 'test@domain.com';
        $password = 'mypassword';

        $request = new CreateAccountRequest($instanceId, $email, $password);

        $this->assertSame($instanceId, $request->instanceId);
        $this->assertSame($email, $request->email);
        $this->assertSame($password, $request->password);
        $this->assertNull($request->nickname);
        $this->assertTrue($request->isEnabled); // Default value
    }

    public function testConstructorWithExplicitDefaults(): void
    {
        $request = new CreateAccountRequest(
            instanceId: 789,
            email: 'explicit@test.com',
            password: 'explicit_pass',
            nickname: null,
            isEnabled: true
        );

        $this->assertSame(789, $request->instanceId);
        $this->assertSame('explicit@test.com', $request->email);
        $this->assertSame('explicit_pass', $request->password);
        $this->assertNull($request->nickname);
        $this->assertTrue($request->isEnabled);
    }

    public function testConstructorWithDisabledAccount(): void
    {
        $request = new CreateAccountRequest(
            instanceId: 999,
            email: 'disabled@test.com',
            password: 'disabled_pass',
            nickname: 'DisabledUser',
            isEnabled: false
        );

        $this->assertSame(999, $request->instanceId);
        $this->assertSame('disabled@test.com', $request->email);
        $this->assertSame('disabled_pass', $request->password);
        $this->assertSame('DisabledUser', $request->nickname);
        $this->assertFalse($request->isEnabled);
    }

    #[TestWith([1, 'user1@example.com', 'password123', 'User1', true], 'basic_enabled_account')]
    #[TestWith([2, 'user2@example.com', 'password456', 'User2', false], 'basic_disabled_account')]
    #[TestWith([3, 'user3@example.com', 'password789', null, true], 'account_without_nickname')]
    #[TestWith([4, 'admin@company.org', 'admin_secure_pass', 'Administrator', true], 'admin_account')]
    #[TestWith([5, 'test.user+tag@domain.co.uk', 'complex_password_!@#', 'Test User', true], 'complex_email_and_password')]
    #[TestWith([999999, 'very.long.email.address.with.many.dots@very-long-domain-name.example.com', 'very_long_password_with_special_chars_!@#$%^&*()', 'Very Long Nickname With Spaces', false], 'edge_case_long_values')]
    #[TestWith([0, 'minimal@test.com', 'min', '', true], 'minimal_values_with_empty_nickname')]
    #[TestWith([2147483647, 'max.int@test.com', 'max_int_test', 'MaxInt', true], 'maximum_integer_instance_id')]
    public function testVariousCreateAccountScenarios(
        int $instanceId,
        string $email,
        string $password,
        ?string $nickname,
        bool $isEnabled,
    ): void {
        $request = new CreateAccountRequest($instanceId, $email, $password, $nickname, $isEnabled);

        $this->assertSame($instanceId, $request->instanceId);
        $this->assertSame($email, $request->email);
        $this->assertSame($password, $request->password);
        $this->assertSame($nickname, $request->nickname);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testNamedParametersConstructor(): void
    {
        $request = new CreateAccountRequest(
            password: 'named_password',
            email: 'named@email.com',
            instanceId: 555,
            isEnabled: false,
            nickname: 'NamedUser'
        );

        $this->assertSame(555, $request->instanceId);
        $this->assertSame('named@email.com', $request->email);
        $this->assertSame('named_password', $request->password);
        $this->assertSame('NamedUser', $request->nickname);
        $this->assertFalse($request->isEnabled);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $request = new CreateAccountRequest(100, 'test@example.com', 'test_pass', 'TestUser', true);

        // Readonly properties cannot be modified after construction
        // This test ensures the class is properly defined as readonly
        $this->assertSame(100, $request->instanceId);
        $this->assertSame('test@example.com', $request->email);
        $this->assertSame('test_pass', $request->password);
        $this->assertSame('TestUser', $request->nickname);
        $this->assertTrue($request->isEnabled);

        // The following would cause fatal errors if attempted:
        // $request->instanceId = 200;
        // $request->email = 'new@email.com';
        // $request->password = 'new_password';
        // $request->nickname = 'NewUser';
        // $request->isEnabled = false;
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(CreateAccountRequest::class);

        $this->assertTrue($reflection->isFinal(), 'CreateAccountRequest should be final');
        $this->assertTrue($reflection->isReadOnly(), 'CreateAccountRequest should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(CreateAccountRequest::class);
        $properties = $reflection->getProperties();

        $this->assertCount(5, $properties, 'Should have exactly 5 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('instanceId', $propertyNames);
        $this->assertContains('email', $propertyNames);
        $this->assertContains('password', $propertyNames);
        $this->assertContains('nickname', $propertyNames);
        $this->assertContains('isEnabled', $propertyNames);
    }

    public function testValidEmailFormats(): void
    {
        $validEmails = [
            'simple@example.com',
            'user.name@domain.org',
            'user+tag@domain.co.uk',
            'user_name@sub.domain.com',
            'firstname.lastname@company.travel',
            'test123@123domain.com',
            'a@b.co',
            'very.long.email.address@very-long-domain-name.example.com',
            'user@domain-with-hyphens.com',
            'user@subdomain.domain.museum',
        ];

        foreach ($validEmails as $email) {
            $request = new CreateAccountRequest(1, $email, 'password', 'User', true);
            $this->assertSame($email, $request->email);
        }
    }

    public function testVariousPasswordFormats(): void
    {
        $passwords = [
            'simple123',
            'Complex_Password_123!',
            'very_long_password_with_many_characters_and_symbols_!@#$%^&*()',
            'P@ssw0rd',
            'short',
            '12345678',
            'NoSpecialChars123',
            'special!@#$%^&*()chars',
            'unicode密码测试',
            str_repeat('a', 100), // Very long password
        ];

        foreach ($passwords as $password) {
            $request = new CreateAccountRequest(1, 'test@example.com', $password, 'User', true);
            $this->assertSame($password, $request->password);
        }
    }

    public function testVariousNicknameFormats(): void
    {
        $nicknames = [
            'Simple',
            'User Name',
            'user_name',
            'User-Name',
            'User123',
            '用户名', // Unicode characters
            'VeryLongNicknameWithManyCharacters',
            'Special!@#Chars',
            'Mixed123_User-Name',
            '', // Empty string
        ];

        foreach ($nicknames as $nickname) {
            $nicknameValue = '' === $nickname ? null : $nickname;
            $request = new CreateAccountRequest(1, 'test@example.com', 'password', $nicknameValue, true);
            $this->assertSame($nicknameValue, $request->nickname);
        }
    }

    public function testVariousInstanceIds(): void
    {
        $instanceIds = [
            1,
            100,
            999,
            1000,
            999999,
            2147483647, // Maximum 32-bit integer
        ];

        foreach ($instanceIds as $instanceId) {
            $request = new CreateAccountRequest($instanceId, 'test@example.com', 'password', 'User', true);
            $this->assertSame($instanceId, $request->instanceId);
        }
    }

    public function testEnabledAndDisabledStates(): void
    {
        // Test enabled account
        $enabledRequest = new CreateAccountRequest(1, 'enabled@test.com', 'password', 'EnabledUser', true);
        $this->assertTrue($enabledRequest->isEnabled);

        // Test disabled account
        $disabledRequest = new CreateAccountRequest(2, 'disabled@test.com', 'password', 'DisabledUser', false);
        $this->assertFalse($disabledRequest->isEnabled);
    }

    public function testDefaultIsEnabledValue(): void
    {
        $request = new CreateAccountRequest(1, 'test@example.com', 'password');
        $this->assertTrue($request->isEnabled, 'isEnabled should default to true');
    }

    public function testNullNicknameIsValid(): void
    {
        $request = new CreateAccountRequest(1, 'test@example.com', 'password', null, true);
        $this->assertNull($request->nickname);
    }

    public function testConstructorParameterTypes(): void
    {
        $reflection = new \ReflectionClass(CreateAccountRequest::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'Constructor should exist');

        $parameters = $constructor->getParameters();
        $this->assertCount(5, $parameters);

        // Check parameter types
        $this->assertSame('instanceId', $parameters[0]->getName());
        $type0 = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type0);
        $this->assertSame('int', $type0->getName());

        $this->assertSame('email', $parameters[1]->getName());
        $type1 = $parameters[1]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type1);
        $this->assertSame('string', $type1->getName());

        $this->assertSame('password', $parameters[2]->getName());
        $type2 = $parameters[2]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type2);
        $this->assertSame('string', $type2->getName());

        $this->assertSame('nickname', $parameters[3]->getName());
        $type3 = $parameters[3]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type3);
        $this->assertTrue($type3->allowsNull());

        $this->assertSame('isEnabled', $parameters[4]->getName());
        $type4 = $parameters[4]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type4);
        $this->assertSame('bool', $type4->getName());
        $this->assertTrue($parameters[4]->isDefaultValueAvailable());
        $this->assertTrue($parameters[4]->getDefaultValue());
    }

    public function testRequiredParametersOnly(): void
    {
        $request = new CreateAccountRequest(
            instanceId: 42,
            email: 'required@only.com',
            password: 'required_pass'
        );

        $this->assertSame(42, $request->instanceId);
        $this->assertSame('required@only.com', $request->email);
        $this->assertSame('required_pass', $request->password);
        $this->assertNull($request->nickname);
        $this->assertTrue($request->isEnabled);
    }
}
