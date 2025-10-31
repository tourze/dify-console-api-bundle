<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\DTO\AppDetailResult;
use Tourze\DifyConsoleApiBundle\DTO\AppDslExportResult;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\DTO\AppListResult;
use Tourze\DifyConsoleApiBundle\DTO\AuthenticationResult;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Exception\DifyApiException;
use Tourze\DifyConsoleApiBundle\Exception\DifyGenericException;
use Tourze\DifyConsoleApiBundle\Exception\DifyInstanceUnavailableException;
use Tourze\DifyConsoleApiBundle\Service\Helper\AuthenticationProcessor;
use Tourze\DifyConsoleApiBundle\Service\Helper\HttpClientManager;
use Tourze\DifyConsoleApiBundle\Service\Helper\ResponseProcessor;

/**
 * Dify Console API 客户端服务
 *
 * 提供与 Dify Console API 交互的核心功能
 * 包括认证、应用管理、Token 自动刷新等
 */
#[WithMonologChannel(channel: 'dify_console_api')]
final readonly class DifyClientService implements DifyClientServiceInterface
{
    public function __construct(
        private HttpClientManager $httpManager,
        private AuthenticationProcessor $authProcessor,
        private ResponseProcessor $responseProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function login(DifyAccount $account): AuthenticationResult
    {
        try {
            $instance = $this->getInstance($account);
            $response = $this->httpManager->performLoginRequest($instance, $account);
            $data = $this->authProcessor->validateLoginResponse($response, $instance->getBaseUrl());
            $authData = $this->authProcessor->extractAuthenticationData($data);

            $this->authProcessor->logSuccessfulLogin($account);

            return new AuthenticationResult(
                success: true,
                token: $authData['token'],
                expiresTime: $authData['expiresAt'],
                errorMessage: null
            );
        } catch (DifyApiException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            $this->logNetworkError($account, $e);
            throw DifyInstanceUnavailableException::connectionFailed($this->getInstance($account)->getBaseUrl(), $e);
        } catch (\Throwable $e) {
            $this->logUnknownError($account, $e);
            throw DifyGenericException::create('登录失败: ' . $e->getMessage(), 0, null, $e);
        }
    }

    public function getApps(DifyAccount $account, AppListQuery $query): AppListResult
    {
        try {
            $this->authProcessor->ensureValidToken($account, fn () => $this->refreshToken($account));
            $instance = $this->getInstance($account);
            $url = $this->buildAppsListUrl($instance, $query);

            $response = $this->httpManager->performAppsListRequest($instance, $account, $url);

            return $this->responseProcessor->processAppsListResponse($response, $query, $instance->getBaseUrl());
        } catch (DifyApiException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            throw DifyInstanceUnavailableException::connectionFailed($this->getInstance($account)->getBaseUrl(), $e);
        } catch (\Throwable $e) {
            throw DifyGenericException::create('获取应用列表失败: ' . $e->getMessage(), 0, null, $e);
        }
    }

    public function getAppDetail(DifyAccount $account, string $appId): AppDetailResult
    {
        try {
            $this->authProcessor->ensureValidToken($account, fn () => $this->refreshToken($account));
            $instance = $this->getInstance($account);
            $url = $instance->getBaseUrl() . '/console/api/apps/' . $appId;

            $response = $this->httpManager->performAppDetailRequest($instance, $account, $url);

            return $this->responseProcessor->processAppDetailResponse($response, $instance->getBaseUrl());
        } catch (DifyApiException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            throw DifyInstanceUnavailableException::connectionFailed($this->getInstance($account)->getBaseUrl(), $e);
        } catch (\Throwable $e) {
            throw DifyGenericException::create('获取应用详情失败: ' . $e->getMessage(), 0, null, $e);
        }
    }

    public function refreshToken(DifyAccount $account): AuthenticationResult
    {
        // 使用登录方式重新获取token
        return $this->login($account);
    }

    public function exportAppDsl(DifyAccount $account, string $appId): AppDslExportResult
    {
        try {
            $tokenResult = $this->ensureValidTokenForExport($account);
            if (null !== $tokenResult) {
                return $tokenResult;
            }

            $response = $this->performDslExportRequest($account, $appId);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent();

            if (200 === $statusCode) {
                return $this->responseProcessor->processDslExportResponse($responseBody, $appId);
            }

            return $this->responseProcessor->handleDslExportError($appId, $statusCode, $responseBody);
        } catch (TransportExceptionInterface $e) {
            return $this->handleDslTransportException($appId, $e);
        } catch (\Exception $e) {
            return $this->handleDslGenericException($appId, $e);
        }
    }

    /**
     * 构建应用列表URL
     */
    private function buildAppsListUrl(DifyInstance $instance, AppListQuery $query): string
    {
        $url = $instance->getBaseUrl() . '/console/api/apps';

        // 构建查询参数
        $queryParams = [];
        if ($query->page > 1) {
            $queryParams['page'] = $query->page;
        }
        if (30 !== $query->limit) {
            $queryParams['limit'] = $query->limit;
        }
        if (null !== $query->name) {
            $queryParams['name'] = $query->name;
        }

        if ([] !== $queryParams) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * 确保导出时token有效
     */
    private function ensureValidTokenForExport(DifyAccount $account): ?AppDslExportResult
    {
        if (!$account->isTokenExpired()) {
            return null;
        }

        $refreshResult = $this->refreshToken($account);
        if ($refreshResult->success) {
            return null;
        }

        return new AppDslExportResult(
            success: false,
            errorMessage: '账号授权已过期，且刷新失败: ' . $refreshResult->errorMessage
        );
    }

    /**
     * 执行DSL导出请求
     */
    private function performDslExportRequest(DifyAccount $account, string $appId): ResponseInterface
    {
        $instance = $this->getInstance($account);
        $url = $instance->getBaseUrl() . '/console/api/apps/' . $appId . '/export?include_secret=false';

        return $this->httpManager->performDslExportRequest($account, $url);
    }

    /**
     * 处理DSL传输异常
     */
    private function handleDslTransportException(string $appId, TransportExceptionInterface $e): AppDslExportResult
    {
        $this->logger->error('DSL导出网络请求失败', [
            'app_id' => $appId,
            'error' => $e->getMessage(),
        ]);

        return new AppDslExportResult(
            success: false,
            errorMessage: '网络请求失败: ' . $e->getMessage()
        );
    }

    /**
     * 处理DSL通用异常
     */
    private function handleDslGenericException(string $appId, \Exception $e): AppDslExportResult
    {
        $this->logger->error('DSL导出发生未知错误', [
            'app_id' => $appId,
            'error' => $e->getMessage(),
        ]);

        return new AppDslExportResult(
            success: false,
            errorMessage: '未知错误: ' . $e->getMessage()
        );
    }

    /**
     * 获取账户对应的Dify实例
     */
    private function getInstance(DifyAccount $account): DifyInstance
    {
        $instance = $account->getInstance();

        // 由于DifyAccount->getInstance()总是返回DifyInstance，这个检查理论上不会失败
        // 但为了防御性编程，保留这个检查

        if (!$instance->isEnabled()) {
            throw DifyInstanceUnavailableException::configurationError($instance->getBaseUrl(), '实例已禁用');
        }

        return $instance;
    }

    /**
     * 记录网络错误
     */
    private function logNetworkError(DifyAccount $account, TransportExceptionInterface $e): void
    {
        $this->logger->error('Dify登录网络错误', [
            'account_id' => $account->getId(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 记录未知错误
     */
    private function logUnknownError(DifyAccount $account, \Throwable $e): void
    {
        $this->logger->error('Dify登录未知错误', [
            'account_id' => $account->getId(),
            'error' => $e->getMessage(),
        ]);
    }
}
