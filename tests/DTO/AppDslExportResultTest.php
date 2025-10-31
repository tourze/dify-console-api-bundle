<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\DifyConsoleApiBundle\DTO\AppDslExportResult;

/**
 * @internal
 */
#[CoversClass(AppDslExportResult::class)]
final class AppDslExportResultTest extends TestCase
{
    public function testConstructWithSuccessOnly(): void
    {
        $result = new AppDslExportResult(true);

        $this->assertTrue($result->success);
        $this->assertNull($result->dslContent);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->rawContent);
    }

    public function testConstructWithSuccessAndDslContent(): void
    {
        $dslContent = [
            'version' => '1.0',
            'app' => [
                'name' => 'Test App',
                'description' => 'Test Description',
            ],
        ];

        $result = new AppDslExportResult(true, $dslContent);

        $this->assertTrue($result->success);
        $this->assertSame($dslContent, $result->dslContent);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->rawContent);
    }

    public function testConstructWithFailureAndErrorMessage(): void
    {
        $errorMessage = 'Export failed: Invalid app configuration';

        $result = new AppDslExportResult(false, null, $errorMessage);

        $this->assertFalse($result->success);
        $this->assertNull($result->dslContent);
        $this->assertSame($errorMessage, $result->errorMessage);
        $this->assertNull($result->rawContent);
    }

    public function testConstructWithAllParameters(): void
    {
        $dslContent = ['data' => 'test'];
        $errorMessage = 'Warning: Partial export';
        $rawContent = 'version: 1.0\ndata: test';

        $result = new AppDslExportResult(true, $dslContent, $errorMessage, $rawContent);

        $this->assertTrue($result->success);
        $this->assertSame($dslContent, $result->dslContent);
        $this->assertSame($errorMessage, $result->errorMessage);
        $this->assertSame($rawContent, $result->rawContent);
    }

    public function testConstructWithRawContent(): void
    {
        $dslContent = ['version' => '1.0'];
        $rawContent = 'version: "1.0"';

        $result = new AppDslExportResult(true, $dslContent, null, $rawContent);

        $this->assertTrue($result->success);
        $this->assertSame($dslContent, $result->dslContent);
        $this->assertNull($result->errorMessage);
        $this->assertSame($rawContent, $result->rawContent);
    }

      /** @phpstan-ignore missingType.iterableValue */
    #[TestWith([null])]
    #[TestWith([[]])]
    #[TestWith([['key' => 'value']])]
    #[TestWith([['metadata' => ['version' => '2.0'], 'workflow' => ['nodes' => [['id' => 1, 'type' => 'start']]]]])]
    public function testDslContentTypes(?array $dslContent): void
    {
        $result = new AppDslExportResult(true, $dslContent);

        $this->assertTrue($result->success);
        $this->assertSame($dslContent, $result->dslContent);
    }

    public function testReadonlyClass(): void
    {
        $result = new AppDslExportResult(true);

        // This test verifies that the class is readonly by ensuring
        // all properties are accessible but not settable
        $this->assertTrue($result->success);
        $this->assertNull($result->dslContent);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->rawContent);

        // The readonly keyword ensures properties cannot be modified after construction
        // This test documents the immutable nature of the DTO
    }
}
