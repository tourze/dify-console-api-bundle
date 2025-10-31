<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\UpdateAccountRequest;

/**
 * UpdateAccountRequest DTO 单元测试
 * 测试重点：readonly类的不可变性、构造函数参数、可选字段处理、部分更新场景
 * @internal
 */
#[CoversClass(UpdateAccountRequest::class)]
class UpdateAccountRequestTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $email = 'updated@example.com';
        $password = 'new_secure_password';
        $nickname = 'UpdatedUser';
        $isEnabled = false;

        $request = new UpdateAccountRequest($email, $password, $nickname, $isEnabled);

        $this->assertSame($email, $request->email);
        $this->assertSame($password, $request->password);
        $this->assertSame($nickname, $request->nickname);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testConstructorWithAllDefaultValues(): void
    {
        $request = new UpdateAccountRequest();

        $this->assertNull($request->email);
        $this->assertNull($request->password);
        $this->assertNull($request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testConstructorWithSomeValues(): void
    {
        $request = new UpdateAccountRequest(
            email: 'partial@update.com',
            password: null,
            nickname: 'PartialUser',
            isEnabled: null
        );

        $this->assertSame('partial@update.com', $request->email);
        $this->assertNull($request->password);
        $this->assertSame('PartialUser', $request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testEmailOnlyUpdate(): void
    {
        $request = new UpdateAccountRequest(email: 'email-only@update.com');

        $this->assertSame('email-only@update.com', $request->email);
        $this->assertNull($request->password);
        $this->assertNull($request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testPasswordOnlyUpdate(): void
    {
        $request = new UpdateAccountRequest(password: 'new_password_only');

        $this->assertNull($request->email);
        $this->assertSame('new_password_only', $request->password);
        $this->assertNull($request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testNicknameOnlyUpdate(): void
    {
        $request = new UpdateAccountRequest(nickname: 'NicknameOnly');

        $this->assertNull($request->email);
        $this->assertNull($request->password);
        $this->assertSame('NicknameOnly', $request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testIsEnabledOnlyUpdate(): void
    {
        $request = new UpdateAccountRequest(isEnabled: false);

        $this->assertNull($request->email);
        $this->assertNull($request->password);
        $this->assertNull($request->nickname);
        $this->assertFalse($request->isEnabled);
    }

    #[TestWith(['user@example.com', 'password123', 'User', true], 'full_update_enabled')]
    #[TestWith(['admin@company.org', 'admin_pass', 'Administrator', false], 'full_update_disabled')]
    #[TestWith(['test@domain.com', null, null, null], 'email_only_update')]
    #[TestWith([null, 'new_password', null, null], 'password_only_update')]
    #[TestWith([null, null, 'NewNickname', null], 'nickname_only_update')]
    #[TestWith([null, null, null, true], 'enable_only_update')]
    #[TestWith([null, null, null, false], 'disable_only_update')]
    #[TestWith(['change@email.com', 'change_pass', null, true], 'email_password_enable_update')]
    #[TestWith([null, 'secure_pass', 'SecureUser', null], 'password_nickname_update')]
    #[TestWith(['partial@test.com', null, 'PartialUser', false], 'email_nickname_disable_update')]
    public function testVariousUpdateScenarios(
        ?string $email,
        ?string $password,
        ?string $nickname,
        ?bool $isEnabled,
    ): void {
        $request = new UpdateAccountRequest($email, $password, $nickname, $isEnabled);

        $this->assertSame($email, $request->email);
        $this->assertSame($password, $request->password);
        $this->assertSame($nickname, $request->nickname);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testNamedParametersConstructor(): void
    {
        $request = new UpdateAccountRequest(
            isEnabled: true,
            nickname: 'NamedUser',
            password: 'named_password',
            email: 'named@email.com'
        );

        $this->assertSame('named@email.com', $request->email);
        $this->assertSame('named_password', $request->password);
        $this->assertSame('NamedUser', $request->nickname);
        $this->assertTrue($request->isEnabled);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $request = new UpdateAccountRequest('test@example.com', 'test_pass', 'TestUser', true);

        // Readonly properties cannot be modified after construction
        // This test ensures the class is properly defined as readonly
        $this->assertSame('test@example.com', $request->email);
        $this->assertSame('test_pass', $request->password);
        $this->assertSame('TestUser', $request->nickname);
        $this->assertTrue($request->isEnabled);

        // The following would cause fatal errors if attempted:
        // $request->email = 'new@email.com';
        // $request->password = 'new_password';
        // $request->nickname = 'NewUser';
        // $request->isEnabled = false;
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(UpdateAccountRequest::class);

        $this->assertTrue($reflection->isFinal(), 'UpdateAccountRequest should be final');
        $this->assertTrue($reflection->isReadOnly(), 'UpdateAccountRequest should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(UpdateAccountRequest::class);
        $properties = $reflection->getProperties();

        $this->assertCount(4, $properties, 'Should have exactly 4 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
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
            $request = new UpdateAccountRequest($email);
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
            $request = new UpdateAccountRequest(password: $password);
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
            $request = new UpdateAccountRequest(nickname: $nickname);
            $this->assertSame($nickname, $request->nickname);
        }
    }

    public function testEnabledAndDisabledStates(): void
    {
        // Test enabled update
        $enabledRequest = new UpdateAccountRequest(isEnabled: true);
        $this->assertTrue($enabledRequest->isEnabled);

        // Test disabled update
        $disabledRequest = new UpdateAccountRequest(isEnabled: false);
        $this->assertFalse($disabledRequest->isEnabled);
    }

    public function testAllParametersHaveDefaultValues(): void
    {
        $reflection = new \ReflectionClass(UpdateAccountRequest::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'Constructor should exist');

        $parameters = $constructor->getParameters();
        $this->assertCount(4, $parameters);

        // All parameters should have default values (null)
        foreach ($parameters as $parameter) {
            $this->assertTrue($parameter->isDefaultValueAvailable(), "Parameter {$parameter->getName()} should have a default value");
            $this->assertNull($parameter->getDefaultValue(), "Parameter {$parameter->getName()} should default to null");
        }
    }

    public function testConstructorParameterTypes(): void
    {
        $reflection = new \ReflectionClass(UpdateAccountRequest::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'Constructor should exist');

        $parameters = $constructor->getParameters();
        $this->assertCount(4, $parameters);

        // Check parameter types and nullability
        $this->assertSame('email', $parameters[0]->getName());
        $type0 = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type0);
        $this->assertTrue($type0->allowsNull());

        $this->assertSame('password', $parameters[1]->getName());
        $type1 = $parameters[1]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type1);
        $this->assertTrue($type1->allowsNull());

        $this->assertSame('nickname', $parameters[2]->getName());
        $type2 = $parameters[2]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type2);
        $this->assertTrue($type2->allowsNull());

        $this->assertSame('isEnabled', $parameters[3]->getName());
        $type3 = $parameters[3]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type3);
        $this->assertTrue($type3->allowsNull());
    }

    public function testPartialUpdateScenarios(): void
    {
        // Test common partial update scenarios
        $scenarios = [
            // Email change only
            ['email' => 'new@email.com', 'password' => null, 'nickname' => null, 'isEnabled' => null],
            // Password change only
            ['email' => null, 'password' => 'new_password', 'nickname' => null, 'isEnabled' => null],
            // Enable account only
            ['email' => null, 'password' => null, 'nickname' => null, 'isEnabled' => true],
            // Disable account only
            ['email' => null, 'password' => null, 'nickname' => null, 'isEnabled' => false],
            // Update email and nickname
            ['email' => 'updated@test.com', 'password' => null, 'nickname' => 'UpdatedUser', 'isEnabled' => null],
            // Update password and enable
            ['email' => null, 'password' => 'secure_new_pass', 'nickname' => null, 'isEnabled' => true],
            // Update nickname and disable
            ['email' => null, 'password' => null, 'nickname' => 'DisabledUser', 'isEnabled' => false],
        ];

        foreach ($scenarios as $scenario) {
            $request = new UpdateAccountRequest(
                $scenario['email'],
                $scenario['password'],
                $scenario['nickname'],
                $scenario['isEnabled']
            );

            $this->assertSame($scenario['email'], $request->email);
            $this->assertSame($scenario['password'], $request->password);
            $this->assertSame($scenario['nickname'], $request->nickname);
            $this->assertSame($scenario['isEnabled'], $request->isEnabled);
        }
    }

    public function testEmptyStringValues(): void
    {
        $request = new UpdateAccountRequest(
            email: '',
            password: '',
            nickname: '',
            isEnabled: null
        );

        $this->assertSame('', $request->email);
        $this->assertSame('', $request->password);
        $this->assertSame('', $request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testMixedNullAndValueScenarios(): void
    {
        // Test various combinations of null and non-null values
        $combinations = [
            ['test1@example.com', null, 'User1', null],
            [null, 'password1', null, true],
            ['test2@example.com', 'password2', null, null],
            [null, null, 'User2', false],
            ['test3@example.com', null, null, true],
            [null, 'password3', 'User3', null],
            ['test4@example.com', 'password4', 'User4', false],
        ];

        foreach ($combinations as [$email, $password, $nickname, $isEnabled]) {
            $request = new UpdateAccountRequest($email, $password, $nickname, $isEnabled);

            $this->assertSame($email, $request->email);
            $this->assertSame($password, $request->password);
            $this->assertSame($nickname, $request->nickname);
            $this->assertSame($isEnabled, $request->isEnabled);
        }
    }

    public function testNoParametersProvided(): void
    {
        $request = new UpdateAccountRequest();

        $this->assertNull($request->email);
        $this->assertNull($request->password);
        $this->assertNull($request->nickname);
        $this->assertNull($request->isEnabled);
    }

    public function testBooleanEnabledValues(): void
    {
        // Test explicit true
        $enabledRequest = new UpdateAccountRequest(isEnabled: true);
        $this->assertTrue($enabledRequest->isEnabled);

        // Test explicit false
        $disabledRequest = new UpdateAccountRequest(isEnabled: false);
        $this->assertFalse($disabledRequest->isEnabled);

        // Test null (no change)
        $nullRequest = new UpdateAccountRequest(isEnabled: null);
        $this->assertNull($nullRequest->isEnabled);
    }
}
