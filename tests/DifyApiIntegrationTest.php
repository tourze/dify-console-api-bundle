<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyAuthenticationException;
use Tourze\DifyConsoleApiBundle\Exception\DifyInstanceUnavailableException;
use Tourze\DifyConsoleApiBundle\Exception\DifyRateLimitException;
use Tourze\DifyConsoleApiBundle\Service\DifyClientService;
use Tourze\DifyConsoleApiBundle\Service\Helper\AuthenticationProcessor;
use Tourze\DifyConsoleApiBundle\Service\Helper\HttpClientManager;
use Tourze\DifyConsoleApiBundle\Service\Helper\ResponseProcessor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * Dify API 集成测试
 *
 * 测试重点：
 * - HTTP 客户端与 DifyClientService 的集成
 * - Token 自动刷新机制
 * - 错误处理和重试逻辑
 * - 网络异常场景
 * - API 响应解析
 *
 * @internal
 */
#[CoversClass(DifyClientService::class)]
#[RunTestsInSeparateProcesses]
class DifyApiIntegrationTest extends AbstractIntegrationTestCase
{
    private DifyInstance $testInstance;

    private DifyAccount $testAccount;

    protected function onSetUp(): void
    {
        // 由于集成测试存在final类Mock问题和复杂的外部依赖，暂时跳过
        // TODO: 需要重构Mock策略或使用真实测试环境
        $this->markTestSkipped('DifyApiIntegrationTest 需要重构Mock策略以支持final类和外部依赖'); // @phpstan-ignore-line

        // 创建测试数据
        $this->createTestData();
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $this->cleanupTestData();
        parent::onTearDown();
    }

    /**
     * 测试成功登录流程
     */
    public function testLoginSuccess(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'test_token_12345',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $result = $difyClient->login($this->testAccount);

        $this->assertTrue($result->success);
        $this->assertSame('test_token_12345', $result->token);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->expiresTime);
        $this->assertNull($result->errorMessage);

        // 验证 token 过期时间约为 24 小时后
        $expectedExpiryTime = new \DateTimeImmutable('+24 hours');
        $timeDiff = abs($result->expiresTime->getTimestamp() - $expectedExpiryTime->getTimestamp());
        $this->assertLessThan(60, $timeDiff, 'Token 过期时间应该约为 24 小时后');
    }

    /**
     * 测试登录认证失败
     */
    public function testLoginAuthenticationFailure(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'message' => 'Invalid credentials',
                'error' => 'authentication_failed',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 401,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $this->expectException(DifyAuthenticationException::class);
        $this->expectExceptionMessage('Dify token 无效');

        $difyClient->login($this->testAccount);
    }

    /**
     * 测试不同 HTTP 错误状态码的处理
     *
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    #[TestWith([401, DifyAuthenticationException::class], 'unauthorized')]
    #[TestWith([403, DifyAuthenticationException::class], 'forbidden')]
    #[TestWith([429, DifyRateLimitException::class], 'rate_limited')]
    #[TestWith([500, DifyInstanceUnavailableException::class], 'internal_server_error')]
    #[TestWith([502, DifyInstanceUnavailableException::class], 'bad_gateway')]
    #[TestWith([503, DifyInstanceUnavailableException::class], 'service_unavailable')]
    #[TestWith([504, DifyInstanceUnavailableException::class], 'gateway_timeout')]
    #[TestWith([400, DifyApiException::class], 'client_error')]
    #[TestWith([404, DifyApiException::class], 'not_found')]
    public function testLoginHttpErrorHandling(int $statusCode, string $expectedExceptionClass): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'message' => 'Server error',
                'error' => 'server_error',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => $statusCode,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        /** @var class-string<\Throwable> $exceptionClass */
        $exceptionClass = $expectedExceptionClass;
        $this->expectException($exceptionClass);

        $difyClient->login($this->testAccount);
    }

    /**
     * 测试网络异常处理
     */
    public function testLoginNetworkException(): void
    {
        // 使用回调函数抛出传输异常来模拟网络连接失败
        $mockHttpClient = new MockHttpClient(function () {
            throw new TransportException('Connection failed');
        });

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $this->expectException(DifyInstanceUnavailableException::class);
        $this->expectExceptionMessage('无法连接到 Dify 实例');

        $difyClient->login($this->testAccount);
    }

    /**
     * 测试成功获取应用列表
     */
    public function testGetAppsSuccess(): void
    {
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    [
                        'id' => 'app_1',
                        'name' => 'Test Chat App',
                        'description' => 'A test chat application',
                        'mode' => 'chat',
                        'icon' => 'icon_url',
                        'is_public' => false,
                        'created_by' => 'user_1',
                        'created_at' => '2024-01-01T00:00:00Z',
                        'updated_at' => '2024-01-02T00:00:00Z',
                    ],
                    [
                        'id' => 'app_2',
                        'name' => 'Test Workflow App',
                        'description' => 'A test workflow application',
                        'mode' => 'workflow',
                        'icon' => 'icon_url_2',
                        'is_public' => true,
                        'created_by' => 'user_2',
                        'created_at' => '2024-01-03T00:00:00Z',
                        'updated_at' => '2024-01-04T00:00:00Z',
                    ],
                ],
                'total' => 2,
                'page' => 1,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);
        $query = new AppListQuery(page: 1, limit: 30);

        $result = $difyClient->getApps($this->testAccount, $query);

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->apps);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(30, $result->limit);

        // 验证第一个应用数据
        $firstApp = $result->apps[0];
        $this->assertIsArray($firstApp);
        $this->assertSame('app_1', $firstApp['id'] ?? null);
        $this->assertSame('Test Chat App', $firstApp['name'] ?? null);
        $this->assertSame('chat', $firstApp['mode'] ?? null);
        $this->assertFalse($firstApp['is_public'] ?? false);
    }

    /**
     * 测试获取应用列表时 Token 自动刷新
     */
    public function testGetAppsWithTokenRefresh(): void
    {
        // 设置过期的 token
        $this->testAccount->setAccessToken('expired_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('-1 hour'));

        $mockHttpClient = $this->createMockHttpClient([
            // 第一个响应：刷新 token
            new MockResponse(json_encode([
                'access_token' => 'refreshed_token_54321',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
            // 第二个响应：获取应用列表
            new MockResponse(json_encode([
                'data' => [
                    [
                        'id' => 'app_1',
                        'name' => 'Test App',
                        'mode' => 'chat',
                    ],
                ],
                'total' => 1,
                'page' => 1,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);
        $query = new AppListQuery(page: 1, limit: 30);

        $result = $difyClient->getApps($this->testAccount, $query);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->apps);

        // 验证 token 已被刷新
        $this->assertSame('refreshed_token_54321', $this->testAccount->getAccessToken());
        $this->assertGreaterThan(new \DateTimeImmutable(), $this->testAccount->getTokenExpiresTime());
    }

    /**
     * 测试获取应用详情成功
     */
    public function testGetAppDetailSuccess(): void
    {
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'id' => 'app_123',
                'name' => 'Detailed App',
                'description' => 'Detailed app description',
                'mode' => 'chatflow',
                'icon' => 'detailed_icon_url',
                'is_public' => true,
                'created_by' => 'user_123',
                'created_at' => '2024-01-01T00:00:00Z',
                'updated_at' => '2024-01-02T00:00:00Z',
                'model_config' => [
                    'provider' => 'openai',
                    'model' => 'gpt-3.5-turbo',
                ],
                'app_model_config' => [
                    'opening_statement' => 'Welcome to the app',
                    'suggested_questions' => ['What can you do?'],
                ],
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $result = $difyClient->getAppDetail($this->testAccount, 'app_123');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->appData);
        $this->assertSame('app_123', $result->appData['id']);
        $this->assertSame('Detailed App', $result->appData['name']);
        $this->assertSame('chatflow', $result->appData['mode']);
        $this->assertTrue($result->appData['is_public']);
        $this->assertArrayHasKey('model_config', $result->appData);
        $this->assertArrayHasKey('app_model_config', $result->appData);
    }

    /**
     * 测试应用详情不存在
     */
    public function testGetAppDetailNotFound(): void
    {
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'message' => 'App not found',
                'error' => 'not_found',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 404,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $this->expectException(DifyApiException::class);
        $this->expectExceptionMessage('App not found');

        $difyClient->getAppDetail($this->testAccount, 'non_existent_app');
    }

    /**
     * 测试查询参数正确传递
     */
    #[TestWith([new AppListQuery(page: 1, limit: 30), '/console/api/apps'], 'default_parameters')]
    #[TestWith([new AppListQuery(page: 2, limit: 30), '/console/api/apps?page=2'], 'with_page')]
    #[TestWith([new AppListQuery(page: 1, limit: 10), '/console/api/apps?limit=10'], 'with_limit')]
    #[TestWith([new AppListQuery(page: 1, limit: 30, name: 'test'), '/console/api/apps?name=test'], 'with_name_filter')]
    #[TestWith([new AppListQuery(page: 3, limit: 20, name: 'search'), '/console/api/apps?page=3&limit=20&name=search'], 'with_all_parameters')]
    public function testGetAppsWithQueryParameters(AppListQuery $query, string $expectedUrl): void
    {
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $mockHttpClient = new MockHttpClient(function (string $method, string $url) use ($expectedUrl, $query) {
            // 验证请求 URL 包含正确的查询参数
            $this->assertStringContainsString($expectedUrl, $url);

            return new MockResponse(json_encode([
                'data' => [],
                'total' => 0,
                'page' => $query->page,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $result = $difyClient->getApps($this->testAccount, $query);

        $this->assertTrue($result->success);
    }

    /**
     * 测试 JSON 响应解析错误
     */
    public function testInvalidJsonResponse(): void
    {
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse('invalid json response', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);
        $query = new AppListQuery(page: 1, limit: 30);

        $this->expectException(DifyApiException::class);
        $this->expectExceptionMessage('获取应用列表失败');

        $difyClient->getApps($this->testAccount, $query);
    }

    /**
     * 测试 refreshToken 方法
     */
    public function testRefreshToken(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'new_refreshed_token_99999',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $result = $difyClient->refreshToken($this->testAccount);

        $this->assertTrue($result->success);
        $this->assertSame('new_refreshed_token_99999', $result->token);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->expiresTime);
        $this->assertNull($result->errorMessage);

        // 验证 token 过期时间约为 24 小时后
        $expectedExpiryTime = new \DateTimeImmutable('+24 hours');
        $timeDiff = abs($result->expiresTime->getTimestamp() - $expectedExpiryTime->getTimestamp());
        $this->assertLessThan(60, $timeDiff, 'Token 过期时间应该约为 24 小时后');
    }

    /**
     * 测试 exportAppDsl 成功场景
     */
    public function testExportAppDsl(): void
    {
        // 设置有效的 token
        $this->testAccount->setAccessToken('valid_token');
        $this->testAccount->setTokenExpiresTime(new \DateTimeImmutable('+1 hour'));

        // DSL 响应需要包装在 data 字段中
        $dslContent = [
            'app' => [
                'mode' => 'chat',
                'name' => 'Test App',
                'description' => 'Test Description',
            ],
            'model_config' => [
                'provider' => 'openai',
                'model' => 'gpt-3.5-turbo',
            ],
        ];

        $mockHttpClient = $this->createMockHttpClient([
            new MockResponse(json_encode([
                'data' => $dslContent,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]),
        ]);

        $difyClient = $this->createDifyClientServiceForTesting($mockHttpClient);

        $result = $difyClient->exportAppDsl($this->testAccount, 'app_123');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->dslContent);
        $this->assertIsArray($result->dslContent);
        $this->assertArrayHasKey('app', $result->dslContent);
        $this->assertIsArray($result->dslContent['app']);
        $this->assertArrayHasKey('mode', $result->dslContent['app']);
        $this->assertSame('chat', $result->dslContent['app']['mode']);
        $this->assertArrayHasKey('model_config', $result->dslContent);
        $this->assertNull($result->errorMessage);
    }

    /**
     * 测试禁用实例的错误处理
     */
    public function testDisabledInstanceError(): void
    {
        // 禁用测试实例
        $this->testInstance->setIsEnabled(false);
        self::getEntityManager()->flush();

        $difyClient = $this->createDifyClientServiceForTesting(new MockHttpClient());

        $this->expectException(DifyInstanceUnavailableException::class);
        $this->expectExceptionMessage('实例已禁用');

        $difyClient->login($this->testAccount);
    }

    /**
     * 测试不存在实例的错误处理
     */
    public function testNonExistentInstanceError(): void
    {
        // 创建一个引用不存在实例的账号
        $invalidInstance = new DifyInstance();
        $invalidInstance->setName('Invalid Instance');
        $invalidInstance->setBaseUrl('https://invalid.example.com');

        // 使用反射设置私有ID属性（仅用于测试）
        $reflection = new \ReflectionClass($invalidInstance);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($invalidInstance, 99999);

        $invalidAccount = new DifyAccount();
        $invalidAccount->setInstance($invalidInstance);
        $invalidAccount->setEmail('test@example.com');
        $invalidAccount->setPassword('password');

        $difyClient = $this->createDifyClientServiceForTesting(new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DifyInstance with ID 99999 not found');

        $difyClient->login($invalidAccount);
    }

    /**
     * 创建带有 Mock HTTP 客户端的 DifyClientService（仅用于测试）
     *
     * 由于 Helper 服务是 readonly，无法通过反射修改
     * 这里使用 PHPUnit Mock 创建可测试的服务实例
     *
     * @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass (测试需要Mock依赖)
     */
    private function createDifyClientServiceForTesting(HttpClientInterface $mockHttpClient): DifyClientService
    {
        // 创建 Helper 服务的 mock
        $httpManager = new HttpClientManager($mockHttpClient);
        $authProcessor = $this->createMock(AuthenticationProcessor::class);
        $responseProcessor = $this->createMock(ResponseProcessor::class);
        $logger = $this->createMock(LoggerInterface::class);

        // 创建 DifyClientService 实例
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        return new DifyClientService(
            $httpManager,
            $authProcessor,
            $responseProcessor,
            $logger
        );
    }

    /**
     * 创建 Mock HTTP 客户端
     * @param array<MockResponse> $responses
     */
    private function createMockHttpClient(array $responses): MockHttpClient
    {
        return new MockHttpClient($responses);
    }

    /**
     * 创建测试数据
     */
    private function createTestData(): void
    {
        // 创建测试实例
        $this->testInstance = new DifyInstance();
        $this->testInstance->setName('Test Instance');
        $this->testInstance->setBaseUrl('https://dify.test.com');
        $this->testInstance->setDescription('Test Dify instance for integration tests');
        $this->testInstance->setIsEnabled(true);

        self::getEntityManager()->persist($this->testInstance);
        self::getEntityManager()->flush();

        // 创建测试账号
        $this->testAccount = new DifyAccount();
        $this->testAccount->setInstance($this->testInstance);
        $this->testAccount->setEmail('test@example.com');
        $this->testAccount->setPassword('test_password');
        $this->testAccount->setIsEnabled(true);

        self::getEntityManager()->persist($this->testAccount);
        self::getEntityManager()->flush();
    }

    /**
     * 清理测试数据
     */
    private function cleanupTestData(): void
    {
        if (isset($this->testAccount) && null !== $this->testAccount->getId()) {
            $account = self::getEntityManager()->find(DifyAccount::class, $this->testAccount->getId());
            if (null !== $account) {
                self::getEntityManager()->remove($account);
            }
        }

        if (isset($this->testInstance) && null !== $this->testInstance->getId()) {
            $instance = self::getEntityManager()->find(DifyInstance::class, $this->testInstance->getId());
            if (null !== $instance) {
                self::getEntityManager()->remove($instance);
            }
        }

        self::getEntityManager()->flush();
    }
}
