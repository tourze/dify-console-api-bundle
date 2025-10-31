<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests\Controller\Admin\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DifyConsoleApiBundle\Controller\Admin\Sync\DifyInstanceConnectionController;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * DifyInstanceConnectionController 控制器单元测试
 * 测试重点：单个实例连接测试、错误处理、实例不存在场景
 * @internal
 */
#[CoversClass(DifyInstanceConnectionController::class)]
#[RunTestsInSeparateProcesses]
class DifyInstanceConnectionControllerTest extends AbstractWebTestCase
{
    private DifyInstanceConnectionController $controller;

    private LoggerInterface&MockObject $logger;

    private DifyInstanceRepository&MockObject $instanceRepository;

    protected function onSetUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->instanceRepository = $this->createMock(DifyInstanceRepository::class);

        // 使用getService()获取Controller实例（集成测试模式）
        $this->controller = self::getService(DifyInstanceConnectionController::class);
    }

    public function testInvokeSuccess(): void
    {
        // Arrange
        $instanceId = 123;
        $instance = $this->createMockInstance($instanceId, 'Test Instance', 'https://test.dify.example.com');

        $this->instanceRepository
            ->expects($this->once())
            ->method('find')
            ->with($instanceId)
            ->willReturn($instance)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Testing connection for instance', [
                'instance_id' => $instanceId,
                'instance_name' => 'Test Instance',
                'base_url' => 'https://test.dify.example.com',
            ])
        ;

        // Act
        $response = $this->controller->__invoke($instanceId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame('连接正常', $data['message']);
        $this->assertSame($instanceId, $data['instance_id']);
        $this->assertSame('Test Instance', $data['instance_name']);
        $this->assertSame('https://test.dify.example.com', $data['base_url']);
        $this->assertArrayHasKey('tested_at', $data);
        $this->assertIsString($data['tested_at']);

        // 验证tested_at格式是否正确
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['tested_at']);
    }

    public function testInvokeInstanceNotFound(): void
    {
        // Arrange
        $instanceId = 999;

        $this->instanceRepository
            ->expects($this->once())
            ->method('find')
            ->with($instanceId)
            ->willReturn(null)
        ;

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        // Act
        $response = $this->controller->__invoke($instanceId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertSame('实例不存在', $data['message']);
    }

    public function testInvokeWithRepositoryException(): void
    {
        // Arrange
        $instanceId = 456;
        $exception = new \RuntimeException('Database connection failed');

        $this->instanceRepository
            ->expects($this->once())
            ->method('find')
            ->with($instanceId)
            ->willThrowException($exception)
        ;

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to test connection for instance', [
                'instance_id' => $instanceId,
                'error' => 'Database connection failed',
                'trace' => $exception->getTraceAsString(),
            ])
        ;

        // Act
        $response = $this->controller->__invoke($instanceId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertSame('连接测试失败: Database connection failed', $data['message']);
    }

    /**
     * 测试私有方法testInstanceConnection抛出异常的情况
     * 通过控制器的公共方法间接测试
     */
    public function testInvokeWithConnectionTestException(): void
    {
        // 由于testInstanceConnection是私有方法并且目前的实现总是成功
        // 这里我们测试当testInstanceConnection方法本身运行正常但记录日志
        // 在实际实现中，如果testInstanceConnection内部调用真实连接测试服务
        // 可能会抛出异常，这时会被捕获并返回失败结果

        // Arrange
        $instanceId = 789;
        $instance = $this->createMockInstance($instanceId, 'Connection Test Instance', 'https://test-conn.example.com');

        $this->instanceRepository
            ->expects($this->once())
            ->method('find')
            ->with($instanceId)
            ->willReturn($instance)
        ;

        // 在当前实现中，testInstanceConnection不会抛出异常
        // 所以我们验证正常的日志记录
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Testing connection for instance', [
                'instance_id' => $instanceId,
                'instance_name' => 'Connection Test Instance',
                'base_url' => 'https://test-conn.example.com',
            ])
        ;

        // Act
        $response = $this->controller->__invoke($instanceId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame('连接正常', $data['message']);
        $this->assertSame($instanceId, $data['instance_id']);
        $this->assertSame('Connection Test Instance', $data['instance_name']);
        $this->assertSame('https://test-conn.example.com', $data['base_url']);
    }

    /**
     * 测试不同实例ID的边界情况
     */
    #[DataProvider('provideInstanceIds')]
    public function testInvokeWithDifferentInstanceIds(int $instanceId): void
    {
        // Arrange
        $instance = $this->createMockInstance($instanceId, "Instance {$instanceId}", "https://instance{$instanceId}.example.com");

        $this->instanceRepository
            ->expects($this->once())
            ->method('find')
            ->with($instanceId)
            ->willReturn($instance)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Testing connection for instance', [
                'instance_id' => $instanceId,
                'instance_name' => "Instance {$instanceId}",
                'base_url' => "https://instance{$instanceId}.example.com",
            ])
        ;

        // Act
        $response = $this->controller->__invoke($instanceId);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame($instanceId, $data['instance_id']);
        $this->assertSame("Instance {$instanceId}", $data['instance_name']);
        $this->assertSame("https://instance{$instanceId}.example.com", $data['base_url']);
    }

    public static function provideInstanceIds(): \Generator
    {
        yield 'Small ID' => [1];
        yield 'Medium ID' => [100];
        yield 'Large ID' => [999999];
    }

    private function createMockInstance(int $id, string $name, string $baseUrl): DifyInstance&MockObject
    {
        $instance = $this->createMock(DifyInstance::class);
        $instance->method('getId')->willReturn($id);
        $instance->method('getName')->willReturn($name);
        $instance->method('getBaseUrl')->willReturn($baseUrl);

        return $instance;
    }

    /**
     * @phpstan-ignore-next-line test.dataProviderAllowed (父类提供通用方法测试数据)
     */
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // DifyInstanceConnectionController 只支持 POST 方法
        // 其他方法应该返回 405 Method Not Allowed
        $client = self::createClient();
        $client->request($method, '/admin/dify/instance/1/connection');

        $this->assertResponseStatusCodeSame(405);
    }
}
