<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;

/**
 * AppListQuery DTO 单元测试
 * 测试重点：默认值处理、参数验证、readonly不可变性
 * @internal
 */
#[CoversClass(AppListQuery::class)]
class AppListQueryTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $query = new AppListQuery();

        $this->assertSame(1, $query->page);
        $this->assertSame(30, $query->limit);
        $this->assertNull($query->name);
        $this->assertNull($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    public function testConstructorWithAllParameters(): void
    {
        $page = 2;
        $limit = 50;
        $name = 'Test App';
        $isCreatedByMe = true;
        $mode = 'chatflow';

        $query = new AppListQuery($page, $limit, $name, $isCreatedByMe, $mode);

        $this->assertSame($page, $query->page);
        $this->assertSame($limit, $query->limit);
        $this->assertSame($name, $query->name);
        $this->assertSame($isCreatedByMe, $query->isCreatedByMe);
        $this->assertSame($mode, $query->mode);
    }

    public function testConstructorWithNamedParameters(): void
    {
        $query = new AppListQuery(
            page: 3,
            limit: 20,
            name: 'My Application',
            isCreatedByMe: false,
            mode: 'workflow'
        );

        $this->assertSame(3, $query->page);
        $this->assertSame(20, $query->limit);
        $this->assertSame('My Application', $query->name);
        $this->assertFalse($query->isCreatedByMe);
        $this->assertSame('workflow', $query->mode);
    }

    public function testPartialParameterOverride(): void
    {
        // Only override some parameters, others should use defaults
        $query = new AppListQuery(page: 5, limit: 100);

        $this->assertSame(5, $query->page);
        $this->assertSame(100, $query->limit);
        $this->assertNull($query->name);
        $this->assertNull($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    #[TestWith([1])] // first_page
    #[TestWith([2])] // second_page
    #[TestWith([10])] // middle_page
    #[TestWith([100])] // large_page
    #[TestWith([9999])] // very_large_page
    #[TestWith([0])] // zero_page - Edge case: may be invalid but DTO should accept it
    public function testPageValues(int $page): void
    {
        $query = new AppListQuery(page: $page);

        $this->assertSame($page, $query->page);
        $this->assertSame(30, $query->limit); // Default value should remain
    }

    #[TestWith([1])] // small_limit
    #[TestWith([10])] // small_batch
    #[TestWith([30])] // default_limit
    #[TestWith([50])] // medium_batch
    #[TestWith([100])] // large_batch
    #[TestWith([1000])] // very_large_batch
    #[TestWith([0])] // zero_limit - Edge case: may be invalid but DTO should accept it
    public function testLimitValues(int $limit): void
    {
        $query = new AppListQuery(limit: $limit);

        $this->assertSame(1, $query->page); // Default value should remain
        $this->assertSame($limit, $query->limit);
    }

    #[TestWith([null])] // null_name
    #[TestWith([''])] // empty_name
    #[TestWith(['MyApp'])] // simple_name
    #[TestWith(['My Application'])] // name_with_spaces
    #[TestWith(['App v2.1'])] // name_with_numbers
    #[TestWith(['My-App_v1.0'])] // name_with_special_chars
    #[TestWith(['我的应用'])] // chinese_name
    #[TestWith(['My App 🚀'])] // name_with_emojis
    public function testNameValues(?string $name): void
    {
        $query = new AppListQuery(name: $name);

        $this->assertSame($name, $query->name);
    }

    public function testNameWithVeryLongValue(): void
    {
        $name = str_repeat('A', 200);
        $query = new AppListQuery(name: $name);

        $this->assertSame($name, $query->name);
    }

    #[TestWith([null])] // null_value
    #[TestWith([true])] // true_value
    #[TestWith([false])] // false_value
    public function testIsCreatedByMeValues(?bool $isCreatedByMe): void
    {
        $query = new AppListQuery(isCreatedByMe: $isCreatedByMe);

        $this->assertSame($isCreatedByMe, $query->isCreatedByMe);
    }

    #[TestWith([null])] // null_mode
    #[TestWith([''])] // empty_mode
    #[TestWith(['chatflow'])] // chatflow_mode
    #[TestWith(['workflow'])] // workflow_mode
    #[TestWith(['assistant'])] // assistant_mode
    #[TestWith(['chat'])] // chat_mode
    #[TestWith(['completion'])] // completion_mode
    #[TestWith(['unknown'])] // unknown_mode - DTO should accept any string
    #[TestWith(['CHATFLOW'])] // uppercase_mode
    #[TestWith(['ChatFlow'])] // mixed_case_mode
    public function testModeValues(?string $mode): void
    {
        $query = new AppListQuery(mode: $mode);

        $this->assertSame($mode, $query->mode);
    }

    public function testComplexSearchQuery(): void
    {
        $query = new AppListQuery(
            page: 2,
            limit: 25,
            name: 'Customer Support',
            isCreatedByMe: true,
            mode: 'chatflow'
        );

        // Verify all properties are set correctly
        $this->assertSame(2, $query->page);
        $this->assertSame(25, $query->limit);
        $this->assertSame('Customer Support', $query->name);
        $this->assertTrue($query->isCreatedByMe);
        $this->assertSame('chatflow', $query->mode);
    }

    public function testFilterOnlyQuery(): void
    {
        $query = new AppListQuery(
            name: 'API Documentation',
            isCreatedByMe: false
        );

        // Should use default page and limit
        $this->assertSame(1, $query->page);
        $this->assertSame(30, $query->limit);
        $this->assertSame('API Documentation', $query->name);
        $this->assertFalse($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    public function testPaginationOnlyQuery(): void
    {
        $query = new AppListQuery(page: 5, limit: 10);

        // Should use null for filter parameters
        $this->assertSame(5, $query->page);
        $this->assertSame(10, $query->limit);
        $this->assertNull($query->name);
        $this->assertNull($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AppListQuery::class);

        $this->assertTrue($reflection->isFinal(), 'AppListQuery should be final');
        $this->assertTrue($reflection->isReadOnly(), 'AppListQuery should be readonly');
    }

    public function testAllPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(AppListQuery::class);
        $properties = $reflection->getProperties();

        $this->assertCount(5, $properties, 'Should have exactly 5 properties');

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "Property {$property->getName()} should be public");
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }

        // Verify specific property names
        $propertyNames = array_map(fn ($prop) => $prop->getName(), $properties);
        $this->assertContains('page', $propertyNames);
        $this->assertContains('limit', $propertyNames);
        $this->assertContains('name', $propertyNames);
        $this->assertContains('isCreatedByMe', $propertyNames);
        $this->assertContains('mode', $propertyNames);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $query = new AppListQuery(page: 1, limit: 30, name: 'Test');

        // Readonly properties cannot be modified after construction
        $this->assertSame(1, $query->page);
        $this->assertSame(30, $query->limit);
        $this->assertSame('Test', $query->name);

        // The following would cause fatal errors if attempted:
        // $query->page = 2;
        // $query->limit = 50;
        // $query->name = 'New Name';
        // $query->isCreatedByMe = true;
        // $query->mode = 'workflow';
    }

    public function testQueryForMyAppsOnly(): void
    {
        $query = new AppListQuery(isCreatedByMe: true);

        $this->assertSame(1, $query->page);
        $this->assertSame(30, $query->limit);
        $this->assertNull($query->name);
        $this->assertTrue($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    public function testQueryForAllApps(): void
    {
        $query = new AppListQuery(isCreatedByMe: false);

        $this->assertSame(1, $query->page);
        $this->assertSame(30, $query->limit);
        $this->assertNull($query->name);
        $this->assertFalse($query->isCreatedByMe);
        $this->assertNull($query->mode);
    }

    public function testQueryByMode(): void
    {
        $workflowQuery = new AppListQuery(mode: 'workflow');
        $this->assertSame('workflow', $workflowQuery->mode);

        $chatflowQuery = new AppListQuery(mode: 'chatflow');
        $this->assertSame('chatflow', $chatflowQuery->mode);

        $assistantQuery = new AppListQuery(mode: 'assistant');
        $this->assertSame('assistant', $assistantQuery->mode);
    }

    public function testSearchByNamePattern(): void
    {
        $patterns = [
            'exact match' => 'MyApp',
            'partial match' => 'App',
            'case sensitive' => 'MYAPP',
            'with wildcards' => 'My*App',
            'with regex chars' => 'App[v1]',
        ];

        foreach ($patterns as $description => $pattern) {
            $query = new AppListQuery(name: $pattern);
            $this->assertSame($pattern, $query->name, "Failed for {$description}");
        }
    }

    public function testLargePaginationValues(): void
    {
        $query = new AppListQuery(page: 1000, limit: 500);

        $this->assertSame(1000, $query->page);
        $this->assertSame(500, $query->limit);
    }

    public function testBoundaryValues(): void
    {
        // Test minimum values
        $minQuery = new AppListQuery(page: 1, limit: 1);
        $this->assertSame(1, $minQuery->page);
        $this->assertSame(1, $minQuery->limit);

        // Test zero values (edge case)
        $zeroQuery = new AppListQuery(page: 0, limit: 0);
        $this->assertSame(0, $zeroQuery->page);
        $this->assertSame(0, $zeroQuery->limit);

        // Test negative values (edge case - DTO accepts but may be invalid logically)
        $negativeQuery = new AppListQuery(page: -1, limit: -1);
        $this->assertSame(-1, $negativeQuery->page);
        $this->assertSame(-1, $negativeQuery->limit);
    }
}
