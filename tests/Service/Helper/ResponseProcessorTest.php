<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Service\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\Service\Helper\ResponseProcessor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ResponseProcessor::class)]
#[RunTestsInSeparateProcesses]
class ResponseProcessorTest extends AbstractIntegrationTestCase
{
    private ResponseProcessor $processor;

    protected function onSetUp(): void
    {
        $this->processor = self::getService(ResponseProcessor::class);
    }

    public function testProcessAppsListResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'data' => ['app1', 'app2'],
            'total' => 2,
            'page' => 1,
        ]);

        $query = new AppListQuery();
        $result = $this->processor->processAppsListResponse($response, $query, 'http://test.com');

        $this->assertTrue($result->success);
        $this->assertSame(['app1', 'app2'], $result->apps);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->page);
    }

    public function testProcessDslExportResponse(): void
    {
        $responseBody = json_encode(['data' => ['version' => '1.0']]);
        $responseBodyString = false !== $responseBody ? $responseBody : '';
        $result = $this->processor->processDslExportResponse($responseBodyString, 'test-app');

        $this->assertTrue($result->success);
        $this->assertSame(['version' => '1.0'], $result->dslContent);
    }

    public function testProcessAppDetailResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['id' => 'test-app', 'name' => 'Test App']);

        $result = $this->processor->processAppDetailResponse($response, 'http://test.com');

        $this->assertTrue($result->success);
        $this->assertSame(['id' => 'test-app', 'name' => 'Test App'], $result->appData);
    }

    public function testHandleDslExportError(): void
    {
        $result = $this->processor->handleDslExportError('test-app', 400, '{"error": "Bad request"}');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Bad request', $result->errorMessage ?? '');
    }

    public function testProcessAppDslExportResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'data' => ['version' => '1.0', 'name' => 'Test DSL'],
        ]);

        $result = $this->processor->processAppDslExportResponse($response, 'http://test.com');

        $this->assertTrue($result->success);
        $this->assertSame(['version' => '1.0', 'name' => 'Test DSL'], $result->dslContent);
    }
}
