<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\AppDetailResult;

/**
 * AppDetailResult DTO 单元测试
 * 测试重点：readonly类的不可变性、构造函数参数、数据完整性
 * @internal
 */
#[CoversClass(AppDetailResult::class)]
class AppDetailResultTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $success = true;
        $appData = [
            'id' => 'app_123',
            'name' => 'Test App',
            'description' => 'Test application',
            'status' => 'active',
        ];
        $errorMessage = null;

        $result = new AppDetailResult($success, $appData, $errorMessage);

        $this->assertSame($success, $result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testSuccessfulAppDetailResult(): void
    {
        $appData = [
            'id' => 'app_456',
            'name' => 'ChatBot App',
            'description' => 'AI-powered chatbot application',
            'type' => 'chatflow',
            'created_at' => '2024-01-15T10:30:00Z',
            'updated_at' => '2024-01-20T14:45:00Z',
            'status' => 'published',
            'model_config' => [
                'provider' => 'openai',
                'model' => 'gpt-3.5-turbo',
                'temperature' => 0.7,
            ],
        ];

        $result = new AppDetailResult(
            success: true,
            appData: $appData,
            errorMessage: null
        );

        $this->assertTrue($result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertNull($result->errorMessage);
    }

    public function testFailedAppDetailResult(): void
    {
        $errorMessage = 'App not found or access denied';

        $result = new AppDetailResult(
            success: false,
            appData: null,
            errorMessage: $errorMessage
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->appData);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    /**
     * @param array<string, mixed>|null $appData
     */
    #[TestWith([true, ['id' => 'app_1', 'name' => 'Test App'], null], 'successful_app_detail')]
    #[TestWith([false, null, 'App not found'], 'app_not_found')]
    #[TestWith([false, null, 'Insufficient permissions'], 'access_denied')]
    #[TestWith([false, null, 'Internal server error'], 'server_error')]
    #[TestWith([true, [], null], 'successful_with_empty_app_data')]
    #[TestWith([false, null, ''], 'failed_with_empty_error_message')]
    #[TestWith([true, ['complex' => ['nested' => ['data' => 'value']]], null], 'successful_with_nested_data')]
    #[TestWith([true, ['id' => 'app_2'], 'Warning: deprecated API version'], 'successful_with_warning_message')]
    public function testVariousAppDetailScenarios(
        bool $success,
        ?array $appData,
        ?string $errorMessage,
    ): void {
        $result = new AppDetailResult($success, $appData, $errorMessage);

        $this->assertSame($success, $result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testAppDataCanBeNullWhenSuccessIsFalse(): void
    {
        $result = new AppDetailResult(false, null, 'Resource not available');

        $this->assertFalse($result->success);
        $this->assertNull($result->appData);
        $this->assertSame('Resource not available', $result->errorMessage);
    }

    public function testAppDataCanBeEmptyArrayWhenSuccessIsTrue(): void
    {
        $result = new AppDetailResult(true, [], null);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->appData);
        $this->assertNull($result->errorMessage);
    }

    public function testErrorMessageCanBeNullWhenSuccessIsTrue(): void
    {
        $appData = ['id' => 'app_789', 'name' => 'Valid App'];

        $result = new AppDetailResult(true, $appData, null);

        $this->assertTrue($result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertNull($result->errorMessage);
    }

    public function testNamedParametersConstructor(): void
    {
        $result = new AppDetailResult(
            success: false,
            errorMessage: 'Database connection failed',
            appData: null
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->appData);
        $this->assertSame('Database connection failed', $result->errorMessage);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $appData = ['id' => 'test_app', 'status' => 'active'];
        $result = new AppDetailResult(true, $appData, null);

        // Readonly properties cannot be modified after construction
        // This test ensures the class is properly defined as readonly
        $this->assertTrue($result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertNull($result->errorMessage);

        // The following would cause fatal errors if attempted:
        // $result->success = false;
        // $result->appData = ['modified' => 'data'];
        // $result->errorMessage = 'new_error';
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AppDetailResult::class);

        $this->assertTrue($reflection->isFinal(), 'AppDetailResult should be final');
        $this->assertTrue($reflection->isReadOnly(), 'AppDetailResult should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(AppDetailResult::class);
        $properties = $reflection->getProperties();

        $this->assertCount(3, $properties, 'Should have exactly 3 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('success', $propertyNames);
        $this->assertContains('appData', $propertyNames);
        $this->assertContains('errorMessage', $propertyNames);
    }

    public function testComplexAppDataStructure(): void
    {
        $complexAppData = [
            'id' => 'complex_app_123',
            'name' => 'Advanced Workflow App',
            'description' => 'Complex multi-step workflow application',
            'type' => 'workflow',
            'metadata' => [
                'version' => '2.1.0',
                'author' => 'developer@example.com',
                'tags' => ['ai', 'automation', 'workflow'],
                'settings' => [
                    'timeout' => 300,
                    'retry_count' => 3,
                    'parallel_execution' => true,
                ],
            ],
            'workflow_config' => [
                'nodes' => [
                    [
                        'id' => 'node_1',
                        'type' => 'input',
                        'position' => ['x' => 100, 'y' => 200],
                    ],
                    [
                        'id' => 'node_2',
                        'type' => 'llm',
                        'position' => ['x' => 300, 'y' => 200],
                        'config' => [
                            'model' => 'gpt-4',
                            'temperature' => 0.8,
                            'max_tokens' => 2000,
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'node_1', 'target' => 'node_2'],
                ],
            ],
            'statistics' => [
                'total_runs' => 1250,
                'successful_runs' => 1180,
                'average_duration' => 45.7,
                'last_run' => '2024-01-20T15:30:00Z',
            ],
        ];

        $result = new AppDetailResult(true, $complexAppData, null);

        $this->assertTrue($result->success);
        $this->assertSame($complexAppData, $result->appData);
        $this->assertNull($result->errorMessage);

        // Verify specific nested data
        $this->assertSame('complex_app_123', $result->appData['id']);
        $this->assertSame('workflow', $result->appData['type']);
        $this->assertSame(1250, $result->appData['statistics']['total_runs']);
        $this->assertSame('gpt-4', $result->appData['workflow_config']['nodes'][1]['config']['model']);
    }

    public function testVariousErrorMessages(): void
    {
        $errorMessages = [
            'App not found in database',
            'User does not have permission to view this app',
            'App is currently being processed and details are not available',
            'API rate limit exceeded. Please try again later.',
            'App has been deleted and cannot be retrieved',
            'Invalid app ID format provided',
            '', // Empty error message
            'Error code: 404 - Resource not found',
            'Network timeout while fetching app details',
            str_repeat('Very long error message describing complex issue ', 20), // Very long error message
        ];

        foreach ($errorMessages as $errorMessage) {
            $result = new AppDetailResult(false, null, $errorMessage);
            $this->assertSame($errorMessage, $result->errorMessage);
        }
    }

    public function testEmptyAppDataIsValid(): void
    {
        $result = new AppDetailResult(true, [], null);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->appData);
        $this->assertIsArray($result->appData);
        $this->assertEmpty($result->appData);
        $this->assertNull($result->errorMessage);
    }

    public function testAppDataWithMixedTypes(): void
    {
        $mixedData = [
            'string_field' => 'text_value',
            'integer_field' => 42,
            'float_field' => 3.14159,
            'boolean_field' => true,
            'null_field' => null,
            'array_field' => [1, 2, 3],
            'nested_object' => [
                'inner_string' => 'inner_value',
                'inner_number' => 100,
            ],
        ];

        $result = new AppDetailResult(true, $mixedData, null);

        $this->assertTrue($result->success);
        $this->assertSame($mixedData, $result->appData);
        $this->assertSame('text_value', $result->appData['string_field']);
        $this->assertSame(42, $result->appData['integer_field']);
        $this->assertSame(3.14159, $result->appData['float_field']);
        $this->assertTrue($result->appData['boolean_field']);
        $this->assertArrayHasKey('null_field', $result->appData);
        $this->assertSame([1, 2, 3], $result->appData['array_field']);
        $this->assertSame('inner_value', $result->appData['nested_object']['inner_string']);
    }

    public function testSuccessfulResultWithWarningMessage(): void
    {
        $appData = ['id' => 'app_with_warning', 'status' => 'deprecated'];
        $warningMessage = 'This app uses a deprecated API version and should be updated';

        $result = new AppDetailResult(true, $appData, $warningMessage);

        $this->assertTrue($result->success);
        $this->assertSame($appData, $result->appData);
        $this->assertSame($warningMessage, $result->errorMessage);
    }
}
