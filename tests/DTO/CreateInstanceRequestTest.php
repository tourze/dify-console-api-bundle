<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\CreateInstanceRequest;

/**
 * CreateInstanceRequest DTO 单元测试
 * 测试重点：必需参数验证、可选参数默认值、参数完整性
 * @internal
 */
#[CoversClass(CreateInstanceRequest::class)]
class CreateInstanceRequestTest extends TestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $name = 'Production Dify Instance';
        $baseUrl = 'https://dify.example.com';

        $request = new CreateInstanceRequest($name, $baseUrl);

        $this->assertSame($name, $request->name);
        $this->assertSame($baseUrl, $request->baseUrl);
        $this->assertNull($request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testConstructorWithAllParameters(): void
    {
        $name = 'Development Dify Instance';
        $baseUrl = 'https://dev.dify.example.com';
        $description = 'Development environment for testing new features';
        $isEnabled = false;

        $request = new CreateInstanceRequest($name, $baseUrl, $description, $isEnabled);

        $this->assertSame($name, $request->name);
        $this->assertSame($baseUrl, $request->baseUrl);
        $this->assertSame($description, $request->description);
        $this->assertFalse($request->isEnabled);
    }

    public function testConstructorWithNamedParameters(): void
    {
        $request = new CreateInstanceRequest(
            name: 'Staging Instance',
            baseUrl: 'https://staging.dify.example.com',
            description: 'Staging environment for pre-production testing',
            isEnabled: true
        );

        $this->assertSame('Staging Instance', $request->name);
        $this->assertSame('https://staging.dify.example.com', $request->baseUrl);
        $this->assertSame('Staging environment for pre-production testing', $request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testDefaultValues(): void
    {
        $request = new CreateInstanceRequest('Test Instance', 'https://test.com');

        // Test default values
        $this->assertNull($request->description);
        $this->assertTrue($request->isEnabled);
    }

    #[TestWith(['Test Instance'])] // simple_name
    #[TestWith(['Dify Instance 2024'])] // name_with_numbers
    #[TestWith(['Test-Instance_v1.0'])] // name_with_special_chars
    #[TestWith(['测试实例'])] // chinese_name
    #[TestWith(['My Production Instance'])] // name_with_spaces
    #[TestWith(['My Instance 🚀'])] // name_with_emojis
    #[TestWith(['[PROD] Main Instance'])] // environment_prefix
    #[TestWith(['Engineering - AI Instance'])] // department_prefix
    public function testNameValues(string $name): void
    {
        $request = new CreateInstanceRequest($name, 'https://example.com');

        $this->assertSame($name, $request->name);
    }

    public function testLongNameValue(): void
    {
        $name = str_repeat('A', 100);
        $request = new CreateInstanceRequest($name, 'https://example.com');

        $this->assertSame($name, $request->name);
    }

    #[TestWith(['http://localhost:3000'])] // http_url
    #[TestWith(['https://dify.example.com'])] // https_url
    #[TestWith(['https://dify.example.com:8080'])] // url_with_port
    #[TestWith(['https://dify.example.com/api'])] // url_with_path
    #[TestWith(['https://api.dify.example.com'])] // url_with_subdomain
    #[TestWith(['http://192.168.1.100:8080'])] // ip_address
    #[TestWith(['http://localhost'])] // localhost
    #[TestWith(['https://dify.example.com?env=prod'])] // url_with_query
    #[TestWith(['https://dify.example.com#main'])] // url_with_fragment
    #[TestWith(['https://user:pass@dify.example.com:8443/v1/api?key=value#section'])] // complex_url
    public function testBaseUrlValues(string $baseUrl): void
    {
        $request = new CreateInstanceRequest('Test', $baseUrl);

        $this->assertSame($baseUrl, $request->baseUrl);
    }

    #[TestWith([null])] // null_description
    #[TestWith([''])] // empty_description
    #[TestWith(['Test instance for development'])] // simple_description
    #[TestWith(["Line 1\nLine 2\nLine 3"])] // description_with_newlines
    #[TestWith(['Instance for A&B testing @company.com'])] // description_with_special_chars
    #[TestWith(['实例描述：用于生产环境的AI应用'])] // unicode_description
    public function testDescriptionValues(?string $description): void
    {
        $request = new CreateInstanceRequest('Test', 'https://example.com', $description);

        $this->assertSame($description, $request->description);
    }

    public function testDetailedDescription(): void
    {
        $description = 'This is a comprehensive Dify instance configured for production use. ' .
            'It includes all necessary security settings, monitoring, and backup configurations.';
        $request = new CreateInstanceRequest('Test', 'https://example.com', $description);

        $this->assertSame($description, $request->description);
    }

    public function testLongDescription(): void
    {
        $description = str_repeat('This is a very long description. ', 50);
        $request = new CreateInstanceRequest('Test', 'https://example.com', $description);

        $this->assertSame($description, $request->description);
    }

    public function testMarkdownDescription(): void
    {
        $description = "# Production Instance\n\n- High availability\n- Auto-scaling enabled\n- 24/7 monitoring";
        $request = new CreateInstanceRequest('Test', 'https://example.com', $description);

        $this->assertSame($description, $request->description);
    }

    #[TestWith([true])] // enabled
    #[TestWith([false])] // disabled
    public function testIsEnabledValues(bool $isEnabled): void
    {
        $request = new CreateInstanceRequest('Test', 'https://example.com', null, $isEnabled);

        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testProductionInstanceRequest(): void
    {
        $request = new CreateInstanceRequest(
            name: 'Production AI Instance',
            baseUrl: 'https://ai.company.com',
            description: 'Main production instance for customer-facing AI applications',
            isEnabled: true
        );

        $this->assertSame('Production AI Instance', $request->name);
        $this->assertSame('https://ai.company.com', $request->baseUrl);
        $this->assertNotNull($request->description);
        $this->assertStringContainsString('production', $request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testDevelopmentInstanceRequest(): void
    {
        $request = new CreateInstanceRequest(
            name: 'Dev Environment',
            baseUrl: 'http://localhost:3000',
            description: 'Local development instance',
            isEnabled: false
        );

        $this->assertSame('Dev Environment', $request->name);
        $this->assertSame('http://localhost:3000', $request->baseUrl);
        $this->assertNotNull($request->description);
        $this->assertStringContainsString('development', $request->description);
        $this->assertFalse($request->isEnabled);
    }

    public function testStagingInstanceRequest(): void
    {
        $request = new CreateInstanceRequest(
            name: 'Staging Instance',
            baseUrl: 'https://staging-dify.company.com'
            // Using defaults for description (null) and isEnabled (true)
        );

        $this->assertSame('Staging Instance', $request->name);
        $this->assertSame('https://staging-dify.company.com', $request->baseUrl);
        $this->assertNull($request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testMinimalRequest(): void
    {
        $request = new CreateInstanceRequest('Minimal', 'https://min.example.com');

        $this->assertSame('Minimal', $request->name);
        $this->assertSame('https://min.example.com', $request->baseUrl);
        $this->assertNull($request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testCompleteRequest(): void
    {
        $request = new CreateInstanceRequest(
            'Complete Test Instance',
            'https://complete.test.example.com:8443',
            'Complete instance with all parameters specified for comprehensive testing purposes',
            false
        );

        $this->assertSame('Complete Test Instance', $request->name);
        $this->assertSame('https://complete.test.example.com:8443', $request->baseUrl);
        $this->assertNotNull($request->description);
        $this->assertStringContainsString('comprehensive', $request->description);
        $this->assertFalse($request->isEnabled);
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(CreateInstanceRequest::class);

        $this->assertTrue($reflection->isFinal(), 'CreateInstanceRequest should be final');
        $this->assertTrue($reflection->isReadOnly(), 'CreateInstanceRequest should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(CreateInstanceRequest::class);
        $properties = $reflection->getProperties();

        $this->assertCount(4, $properties, 'Should have exactly 4 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('name', $propertyNames);
        $this->assertContains('baseUrl', $propertyNames);
        $this->assertContains('description', $propertyNames);
        $this->assertContains('isEnabled', $propertyNames);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $request = new CreateInstanceRequest('Test', 'https://test.com', 'Description', false);

        // Readonly properties cannot be modified after construction
        $this->assertSame('Test', $request->name);
        $this->assertSame('https://test.com', $request->baseUrl);
        $this->assertSame('Description', $request->description);
        $this->assertFalse($request->isEnabled);

        // The following would cause fatal errors if attempted:
        // $request->name = 'New Name';
        // $request->baseUrl = 'https://new.com';
        // $request->description = 'New Description';
        // $request->isEnabled = true;
    }

    public function testEmptyStringValues(): void
    {
        // Test edge case with empty strings (may be invalid but DTO should accept them)
        $request = new CreateInstanceRequest('', '', '');

        $this->assertSame('', $request->name);
        $this->assertSame('', $request->baseUrl);
        $this->assertSame('', $request->description);
        $this->assertTrue($request->isEnabled); // Default value
    }

    public function testSpecialCharacterHandling(): void
    {
        $specialName = 'Instance "with" special \'characters\' & symbols @#$%';
        $specialUrl = 'https://special-chars.example.com/path?param=value&other=test';
        $specialDescription = 'Description with "quotes", \'apostrophes\', & symbols: @#$%^&*()';

        $request = new CreateInstanceRequest($specialName, $specialUrl, $specialDescription);

        $this->assertSame($specialName, $request->name);
        $this->assertSame($specialUrl, $request->baseUrl);
        $this->assertSame($specialDescription, $request->description);
    }

    public function testUnicodeSupport(): void
    {
        $unicodeName = '测试实例 🚀';
        $unicodeUrl = 'https://中文.example.com';
        $unicodeDescription = '这是一个支持中文的实例描述 🎯';

        $request = new CreateInstanceRequest($unicodeName, $unicodeUrl, $unicodeDescription);

        $this->assertSame($unicodeName, $request->name);
        $this->assertSame($unicodeUrl, $request->baseUrl);
        $this->assertSame($unicodeDescription, $request->description);
    }

    public function testVeryLongValues(): void
    {
        $longName = str_repeat('LongInstanceName', 20);
        $longUrl = 'https://' . str_repeat('very-long-subdomain-', 10) . '.example.com';
        $longDescription = str_repeat('This is a very detailed description. ', 100);

        $request = new CreateInstanceRequest($longName, $longUrl, $longDescription);

        $this->assertSame($longName, $request->name);
        $this->assertSame($longUrl, $request->baseUrl);
        $this->assertSame($longDescription, $request->description);
    }
}
