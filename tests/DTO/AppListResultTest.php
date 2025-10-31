<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\AppListResult;

/**
 * AppListResult DTO 单元测试
 * 测试重点：数据完整性、分页信息、成功和失败状态处理
 * @internal
 */
#[CoversClass(AppListResult::class)]
class AppListResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $apps = [
            [
                'id' => 'app_1',
                'name' => 'Test App 1',
                'mode' => 'chatflow',
                'created_at' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => 'app_2',
                'name' => 'Test App 2',
                'mode' => 'workflow',
                'created_at' => '2024-01-15T11:00:00Z',
            ],
        ];

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: 25,
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(25, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(30, $result->limit);
        $this->assertNull($result->errorMessage);
    }

    public function testFailedResult(): void
    {
        $errorMessage = 'Failed to fetch applications: API timeout';

        $result = new AppListResult(
            success: false,
            apps: [],
            total: 0,
            page: 1,
            limit: 30,
            errorMessage: $errorMessage
        );

        $this->assertFalse($result->success);
        $this->assertSame([], $result->apps);
        $this->assertSame(0, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(30, $result->limit);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testEmptySuccessfulResult(): void
    {
        $result = new AppListResult(
            success: true,
            apps: [],
            total: 0,
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame([], $result->apps);
        $this->assertSame(0, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(30, $result->limit);
        $this->assertNull($result->errorMessage);
    }

    #[TestWith([100, 1, 30, 30])] // first_page_full
    #[TestWith([15, 1, 30, 15])] // first_page_partial
    #[TestWith([100, 2, 30, 30])] // middle_page_full
    #[TestWith([85, 3, 30, 25])] // last_page_partial
    #[TestWith([1, 1, 30, 1])] // single_item
    #[TestWith([0, 1, 30, 0])] // empty_page
    #[TestWith([1000, 10, 100, 100])] // large_page
    public function testPaginationData(int $total, int $page, int $limit, int $expectedAppsCount): void
    {
        $apps = array_fill(0, $expectedAppsCount, ['id' => 'app_test', 'name' => 'Test App']);

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: $total,
            page: $page,
            limit: $limit
        );

        $this->assertTrue($result->success);
        $this->assertCount($expectedAppsCount, $result->apps);
        $this->assertSame($total, $result->total);
        $this->assertSame($page, $result->page);
        $this->assertSame($limit, $result->limit);
    }

    /**
     * @param array<int, array<string, mixed>|string> $apps
     */
    #[TestWith([[]])] // empty_apps
    public function testVariousAppDataStructures(array $apps): void
    {
        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: count($apps),
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(count($apps), $result->total);
    }

    public function testSingleAppDataStructure(): void
    {
        $apps = [
            [
                'id' => 'app_123',
                'name' => 'My Application',
                'mode' => 'chatflow',
                'status' => 'active',
            ],
        ];

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: count($apps),
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(count($apps), $result->total);
    }

    public function testMultipleAppsDataStructure(): void
    {
        $apps = [
            [
                'id' => 'app_1',
                'name' => 'Customer Support Bot',
                'mode' => 'chatflow',
                'created_by' => 'user_123',
                'created_at' => '2024-01-15T10:00:00Z',
                'updated_at' => '2024-01-15T12:00:00Z',
            ],
            [
                'id' => 'app_2',
                'name' => 'Document Processor',
                'mode' => 'workflow',
                'created_by' => 'user_456',
                'created_at' => '2024-01-14T15:30:00Z',
                'updated_at' => '2024-01-15T09:45:00Z',
            ],
            [
                'id' => 'app_3',
                'name' => 'Code Assistant',
                'mode' => 'assistant',
                'created_by' => 'user_123',
                'created_at' => '2024-01-13T08:20:00Z',
                'updated_at' => '2024-01-15T14:10:00Z',
            ],
        ];

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: count($apps),
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(count($apps), $result->total);
    }

    public function testComplexAppDataStructure(): void
    {
        $apps = [
            [
                'id' => 'app_complex',
                'name' => 'Advanced Analytics Bot',
                'mode' => 'workflow',
                'metadata' => [
                    'tags' => ['analytics', 'reporting', 'dashboard'],
                    'version' => '2.1.0',
                    'features' => [
                        'real_time_data' => true,
                        'export_formats' => ['pdf', 'excel', 'csv'],
                        'integrations' => ['salesforce', 'hubspot'],
                    ],
                ],
                'statistics' => [
                    'total_conversations' => 1250,
                    'avg_response_time' => 1.8,
                    'satisfaction_score' => 4.7,
                ],
                'config' => [
                    'max_conversation_length' => 50,
                    'response_timeout' => 30,
                    'enable_logging' => true,
                ],
            ],
        ];

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: count($apps),
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(count($apps), $result->total);
    }

    public function testNestedArrayAppDataStructure(): void
    {
        $apps = [
            [
                'id' => 'nested_app',
                'permissions' => [
                    'read' => ['user_123', 'user_456'],
                    'write' => ['user_123'],
                    'admin' => ['user_123'],
                ],
                'workflows' => [
                    [
                        'name' => 'onboarding',
                        'steps' => ['welcome', 'setup', 'tutorial'],
                    ],
                    [
                        'name' => 'support',
                        'steps' => ['categorize', 'route', 'resolve'],
                    ],
                ],
            ],
        ];

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: count($apps),
            page: 1,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(count($apps), $result->total);
    }

    #[TestWith([null])] // null_error
    #[TestWith([''])] // empty_error
    #[TestWith(['Request failed'])] // simple_error
    #[TestWith(['Failed to connect to Dify API: Connection timeout after 30 seconds'])] // detailed_error
    #[TestWith(['API Error 429: Rate limit exceeded. Please try again later.'])] // api_error
    #[TestWith(['Authentication failed: Invalid or expired access token'])] // auth_error
    #[TestWith(['Validation failed: Invalid page parameter (must be positive integer)'])] // validation_error
    #[TestWith(['Network error: Unable to resolve hostname dify.example.com'])] // network_error
    public function testErrorMessages(?string $errorMessage): void
    {
        $result = new AppListResult(
            success: false,
            apps: [],
            total: 0,
            page: 1,
            limit: 30,
            errorMessage: $errorMessage
        );

        $this->assertFalse($result->success);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testLongErrorMessage(): void
    {
        $errorMessage = str_repeat('Error details: ', 20);
        $result = new AppListResult(
            success: false,
            apps: [],
            total: 0,
            page: 1,
            limit: 30,
            errorMessage: $errorMessage
        );

        $this->assertFalse($result->success);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testConstructorWithAllParameters(): void
    {
        $apps = [['id' => 'test', 'name' => 'Test App']];
        $total = 100;
        $page = 5;
        $limit = 20;
        $errorMessage = 'Warning: Some data may be stale';

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: $total,
            page: $page,
            limit: $limit,
            errorMessage: $errorMessage
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame($total, $result->total);
        $this->assertSame($page, $result->page);
        $this->assertSame($limit, $result->limit);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function testNamedParameterConstructor(): void
    {
        $apps = [
            ['id' => 'app1', 'name' => 'First App'],
            ['id' => 'app2', 'name' => 'Second App'],
        ];

        $result = new AppListResult(
            total: 50,
            limit: 25,
            apps: $apps,
            page: 2,
            success: true
        );

        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(50, $result->total);
        $this->assertSame(2, $result->page);
        $this->assertSame(25, $result->limit);
        $this->assertNull($result->errorMessage);
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AppListResult::class);

        $this->assertTrue($reflection->isFinal(), 'AppListResult should be final');
        $this->assertTrue($reflection->isReadOnly(), 'AppListResult should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(AppListResult::class);
        $properties = $reflection->getProperties();

        $this->assertCount(6, $properties, 'Should have exactly 6 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('success', $propertyNames);
        $this->assertContains('apps', $propertyNames);
        $this->assertContains('total', $propertyNames);
        $this->assertContains('page', $propertyNames);
        $this->assertContains('limit', $propertyNames);
        $this->assertContains('errorMessage', $propertyNames);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $apps = [['id' => 'test']];
        $result = new AppListResult(true, $apps, 10, 1, 30);

        // Readonly properties cannot be modified after construction
        $this->assertTrue($result->success);
        $this->assertSame($apps, $result->apps);
        $this->assertSame(10, $result->total);

        // The following would cause fatal errors if attempted:
        // $result->success = false;
        // $result->apps = [];
        // $result->total = 0;
        // $result->page = 2;
        // $result->limit = 50;
        // $result->errorMessage = 'New error';
    }

    public function testLargePaginationScenario(): void
    {
        $apps = array_fill(0, 100, ['id' => 'app_bulk', 'name' => 'Bulk App']);

        $result = new AppListResult(
            success: true,
            apps: $apps,
            total: 10000,
            page: 50,
            limit: 100
        );

        $this->assertTrue($result->success);
        $this->assertCount(100, $result->apps);
        $this->assertSame(10000, $result->total);
        $this->assertSame(50, $result->page);
        $this->assertSame(100, $result->limit);
    }

    public function testPartialFailureScenario(): void
    {
        // Scenario where some apps are retrieved but with warnings
        $partialApps = [
            ['id' => 'app_1', 'name' => 'Working App', 'status' => 'active'],
        ];

        $result = new AppListResult(
            success: true,
            apps: $partialApps,
            total: 5, // Total is higher than retrieved
            page: 1,
            limit: 30,
            errorMessage: 'Warning: 4 apps could not be loaded due to permission restrictions'
        );

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->apps);
        $this->assertSame(5, $result->total);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('permission restrictions', $result->errorMessage);
    }

    public function testZeroPageScenario(): void
    {
        // Edge case: page 0 (may be invalid but DTO should accept it)
        $result = new AppListResult(
            success: true,
            apps: [],
            total: 0,
            page: 0,
            limit: 30
        );

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->page);
        $this->assertSame([], $result->apps);
    }

    public function testNegativeValuesScenario(): void
    {
        // Edge case: negative values (may be invalid but DTO should accept them)
        $result = new AppListResult(
            success: false,
            apps: [],
            total: -1,
            page: -1,
            limit: -1,
            errorMessage: 'Invalid request parameters'
        );

        $this->assertFalse($result->success);
        $this->assertSame(-1, $result->total);
        $this->assertSame(-1, $result->page);
        $this->assertSame(-1, $result->limit);
    }
}
