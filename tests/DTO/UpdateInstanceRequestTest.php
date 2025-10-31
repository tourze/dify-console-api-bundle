<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\UpdateInstanceRequest;

/**
 * UpdateInstanceRequest DTO 单元测试
 * 测试重点：readonly类的不可变性、构造函数参数、可选字段处理、部分更新场景
 * @internal
 */
#[CoversClass(UpdateInstanceRequest::class)]
class UpdateInstanceRequestTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $name = 'Updated Instance';
        $baseUrl = 'https://updated.dify.example.com';
        $description = 'Updated instance description';
        $isEnabled = false;

        $request = new UpdateInstanceRequest($name, $baseUrl, $description, $isEnabled);

        $this->assertSame($name, $request->name);
        $this->assertSame($baseUrl, $request->baseUrl);
        $this->assertSame($description, $request->description);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testConstructorWithAllDefaultValues(): void
    {
        $request = new UpdateInstanceRequest();

        $this->assertNull($request->name);
        $this->assertNull($request->baseUrl);
        $this->assertNull($request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testConstructorWithSomeValues(): void
    {
        $request = new UpdateInstanceRequest(
            name: 'Partial Instance',
            baseUrl: null,
            description: 'Partial description',
            isEnabled: null
        );

        $this->assertSame('Partial Instance', $request->name);
        $this->assertNull($request->baseUrl);
        $this->assertSame('Partial description', $request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testNameOnlyUpdate(): void
    {
        $request = new UpdateInstanceRequest(name: 'Name Only Instance');

        $this->assertSame('Name Only Instance', $request->name);
        $this->assertNull($request->baseUrl);
        $this->assertNull($request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testBaseUrlOnlyUpdate(): void
    {
        $request = new UpdateInstanceRequest(baseUrl: 'https://baseurl-only.example.com');

        $this->assertNull($request->name);
        $this->assertSame('https://baseurl-only.example.com', $request->baseUrl);
        $this->assertNull($request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testDescriptionOnlyUpdate(): void
    {
        $request = new UpdateInstanceRequest(description: 'Description only update');

        $this->assertNull($request->name);
        $this->assertNull($request->baseUrl);
        $this->assertSame('Description only update', $request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testIsEnabledOnlyUpdate(): void
    {
        $request = new UpdateInstanceRequest(isEnabled: false);

        $this->assertNull($request->name);
        $this->assertNull($request->baseUrl);
        $this->assertNull($request->description);
        $this->assertFalse($request->isEnabled);
    }

    #[TestWith(['Production Instance', 'https://prod.dify.com', 'Production environment', true], 'full_update_enabled')]
    #[TestWith(['Test Instance', 'https://test.dify.com', 'Test environment', false], 'full_update_disabled')]
    #[TestWith(['Name Only', null, null, null], 'name_only_update')]
    #[TestWith([null, 'https://url-only.com', null, null], 'baseurl_only_update')]
    #[TestWith([null, null, 'Description only', null], 'description_only_update')]
    #[TestWith([null, null, null, true], 'enable_only_update')]
    #[TestWith([null, null, null, false], 'disable_only_update')]
    #[TestWith(['Dev Instance', 'https://dev.dify.com', null, true], 'name_url_enable_update')]
    #[TestWith([null, 'https://stage.dify.com', 'Staging environment', null], 'url_description_update')]
    #[TestWith(['Disabled Instance', null, 'Disabled for maintenance', false], 'name_description_disable_update')]
    public function testVariousUpdateScenarios(
        ?string $name,
        ?string $baseUrl,
        ?string $description,
        ?bool $isEnabled,
    ): void {
        $request = new UpdateInstanceRequest($name, $baseUrl, $description, $isEnabled);

        $this->assertSame($name, $request->name);
        $this->assertSame($baseUrl, $request->baseUrl);
        $this->assertSame($description, $request->description);
        $this->assertSame($isEnabled, $request->isEnabled);
    }

    public function testNamedParametersConstructor(): void
    {
        $request = new UpdateInstanceRequest(
            isEnabled: true,
            description: 'Named parameters description',
            baseUrl: 'https://named.dify.com',
            name: 'Named Instance'
        );

        $this->assertSame('Named Instance', $request->name);
        $this->assertSame('https://named.dify.com', $request->baseUrl);
        $this->assertSame('Named parameters description', $request->description);
        $this->assertTrue($request->isEnabled);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $request = new UpdateInstanceRequest('Test Instance', 'https://test.com', 'Test description', true);

        // Readonly properties cannot be modified after construction
        // This test ensures the class is properly defined as readonly
        $this->assertSame('Test Instance', $request->name);
        $this->assertSame('https://test.com', $request->baseUrl);
        $this->assertSame('Test description', $request->description);
        $this->assertTrue($request->isEnabled);

        // The following would cause fatal errors if attempted:
        // $request->name = 'New Name';
        // $request->baseUrl = 'https://new.com';
        // $request->description = 'New description';
        // $request->isEnabled = false;
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(UpdateInstanceRequest::class);

        $this->assertTrue($reflection->isFinal(), 'UpdateInstanceRequest should be final');
        $this->assertTrue($reflection->isReadOnly(), 'UpdateInstanceRequest should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(UpdateInstanceRequest::class);
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

    public function testValidInstanceNames(): void
    {
        $validNames = [
            'Simple Instance',
            'Production Environment',
            'test-instance-123',
            'Dev_Environment_2024',
            'Instance with Special Characters !@#',
            'Multi Word Instance Name',
            'instance_with_underscores',
            'Instance-with-hyphens',
            'CamelCaseInstanceName',
            'instance123',
            '测试实例', // Unicode characters
            str_repeat('Long Instance Name ', 10), // Very long name
        ];

        foreach ($validNames as $name) {
            $request = new UpdateInstanceRequest($name);
            $this->assertSame($name, $request->name);
        }
    }

    public function testValidBaseUrls(): void
    {
        $validUrls = [
            'https://dify.example.com',
            'http://localhost:3000',
            'https://sub.domain.com',
            'https://dify-instance.company.org',
            'https://api.dify.dev',
            'https://192.168.1.100:8080',
            'https://dify.local',
            'https://very-long-subdomain-name.very-long-domain-name.example.com',
            'https://dify.ai/api/v1',
            'https://instance.dify.cloud/console',
        ];

        foreach ($validUrls as $url) {
            $request = new UpdateInstanceRequest(baseUrl: $url);
            $this->assertSame($url, $request->baseUrl);
        }
    }

    public function testVariousDescriptions(): void
    {
        $descriptions = [
            'Simple description',
            'Production environment for AI workflows',
            'Development instance used for testing new features',
            'Multi-line description\nwith line breaks\nand more text',
            'Description with special characters: !@#$%^&*()',
            '描述包含中文字符', // Unicode characters
            '', // Empty description
            str_repeat('Very long description text ', 50), // Very long description
            'Description with "quotes" and \'apostrophes\'',
            'Instance for customer-facing applications with high availability requirements',
        ];

        foreach ($descriptions as $description) {
            $request = new UpdateInstanceRequest(description: $description);
            $this->assertSame($description, $request->description);
        }
    }

    public function testEnabledAndDisabledStates(): void
    {
        // Test enabled update
        $enabledRequest = new UpdateInstanceRequest(isEnabled: true);
        $this->assertTrue($enabledRequest->isEnabled);

        // Test disabled update
        $disabledRequest = new UpdateInstanceRequest(isEnabled: false);
        $this->assertFalse($disabledRequest->isEnabled);
    }

    public function testAllParametersHaveDefaultValues(): void
    {
        $reflection = new \ReflectionClass(UpdateInstanceRequest::class);
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
        $reflection = new \ReflectionClass(UpdateInstanceRequest::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'Constructor should exist');

        $parameters = $constructor->getParameters();
        $this->assertCount(4, $parameters);

        // Check parameter types and nullability
        $this->assertSame('name', $parameters[0]->getName());
        $type0 = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type0);
        $this->assertTrue($type0->allowsNull());

        $this->assertSame('baseUrl', $parameters[1]->getName());
        $type1 = $parameters[1]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type1);
        $this->assertTrue($type1->allowsNull());

        $this->assertSame('description', $parameters[2]->getName());
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
            // Name change only
            ['name' => 'New Instance Name', 'baseUrl' => null, 'description' => null, 'isEnabled' => null],
            // URL change only
            ['name' => null, 'baseUrl' => 'https://new.dify.com', 'description' => null, 'isEnabled' => null],
            // Description change only
            ['name' => null, 'baseUrl' => null, 'description' => 'New description', 'isEnabled' => null],
            // Enable instance only
            ['name' => null, 'baseUrl' => null, 'description' => null, 'isEnabled' => true],
            // Disable instance only
            ['name' => null, 'baseUrl' => null, 'description' => null, 'isEnabled' => false],
            // Update name and URL
            ['name' => 'Updated Instance', 'baseUrl' => 'https://updated.com', 'description' => null, 'isEnabled' => null],
            // Update description and enable
            ['name' => null, 'baseUrl' => null, 'description' => 'Enabled with new description', 'isEnabled' => true],
            // Update name and disable
            ['name' => 'Disabled Instance', 'baseUrl' => null, 'description' => null, 'isEnabled' => false],
        ];

        foreach ($scenarios as $scenario) {
            $request = new UpdateInstanceRequest(
                $scenario['name'],
                $scenario['baseUrl'],
                $scenario['description'],
                $scenario['isEnabled']
            );

            $this->assertSame($scenario['name'], $request->name);
            $this->assertSame($scenario['baseUrl'], $request->baseUrl);
            $this->assertSame($scenario['description'], $request->description);
            $this->assertSame($scenario['isEnabled'], $request->isEnabled);
        }
    }

    public function testEmptyStringValues(): void
    {
        $request = new UpdateInstanceRequest(
            name: '',
            baseUrl: '',
            description: '',
            isEnabled: null
        );

        $this->assertSame('', $request->name);
        $this->assertSame('', $request->baseUrl);
        $this->assertSame('', $request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testMixedNullAndValueScenarios(): void
    {
        // Test various combinations of null and non-null values
        $combinations = [
            ['Instance1', null, 'Description1', null],
            [null, 'https://url1.com', null, true],
            ['Instance2', 'https://url2.com', null, null],
            [null, null, 'Description2', false],
            ['Instance3', null, null, true],
            [null, 'https://url3.com', 'Description3', null],
            ['Instance4', 'https://url4.com', 'Description4', false],
        ];

        foreach ($combinations as [$name, $baseUrl, $description, $isEnabled]) {
            $request = new UpdateInstanceRequest($name, $baseUrl, $description, $isEnabled);

            $this->assertSame($name, $request->name);
            $this->assertSame($baseUrl, $request->baseUrl);
            $this->assertSame($description, $request->description);
            $this->assertSame($isEnabled, $request->isEnabled);
        }
    }

    public function testNoParametersProvided(): void
    {
        $request = new UpdateInstanceRequest();

        $this->assertNull($request->name);
        $this->assertNull($request->baseUrl);
        $this->assertNull($request->description);
        $this->assertNull($request->isEnabled);
    }

    public function testBooleanEnabledValues(): void
    {
        // Test explicit true
        $enabledRequest = new UpdateInstanceRequest(isEnabled: true);
        $this->assertTrue($enabledRequest->isEnabled);

        // Test explicit false
        $disabledRequest = new UpdateInstanceRequest(isEnabled: false);
        $this->assertFalse($disabledRequest->isEnabled);

        // Test null (no change)
        $nullRequest = new UpdateInstanceRequest(isEnabled: null);
        $this->assertNull($nullRequest->isEnabled);
    }

    public function testComplexRealWorldScenarios(): void
    {
        // Production environment update
        $prodUpdate = new UpdateInstanceRequest(
            name: 'Production Dify Instance',
            baseUrl: 'https://dify.production.company.com',
            description: 'Production environment for customer-facing AI applications with 99.9% uptime SLA',
            isEnabled: true
        );

        $this->assertSame('Production Dify Instance', $prodUpdate->name);
        $this->assertSame('https://dify.production.company.com', $prodUpdate->baseUrl);
        $this->assertNotNull($prodUpdate->description);
        $this->assertStringContainsString('Production environment', $prodUpdate->description);
        $this->assertTrue($prodUpdate->isEnabled);

        // Maintenance mode update
        $maintenanceUpdate = new UpdateInstanceRequest(
            description: 'Instance temporarily disabled for scheduled maintenance - will be back online at 2AM UTC',
            isEnabled: false
        );

        $this->assertNull($maintenanceUpdate->name);
        $this->assertNull($maintenanceUpdate->baseUrl);
        $this->assertNotNull($maintenanceUpdate->description);
        $this->assertStringContainsString('maintenance', $maintenanceUpdate->description);
        $this->assertFalse($maintenanceUpdate->isEnabled);

        // URL migration update
        $migrationUpdate = new UpdateInstanceRequest(
            baseUrl: 'https://dify.newdomain.com',
            description: 'Migrated to new domain with improved infrastructure'
        );

        $this->assertNull($migrationUpdate->name);
        $this->assertSame('https://dify.newdomain.com', $migrationUpdate->baseUrl);
        $this->assertNotNull($migrationUpdate->description);
        $this->assertStringContainsString('Migrated', $migrationUpdate->description);
        $this->assertNull($migrationUpdate->isEnabled);
    }
}
