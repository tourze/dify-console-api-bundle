<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

/**
 * HTTP客户端管理器
 *
 * 负责处理HTTP请求和响应的底层逻辑
 */
readonly class HttpClientManager
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * 执行登录请求
     */
    public function performLoginRequest(DifyInstance $instance, DifyAccount $account): ResponseInterface
    {
        $url = $instance->getBaseUrl() . '/console/api/login';

        return $this->httpClient->request(
            'POST',
            $url,
            [
                'json' => [
                    'email' => $account->getEmail(),
                    'password' => $account->getPassword(),
                ],
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]
        );
    }

    /**
     * 执行应用列表请求
     */
    public function performAppsListRequest(DifyInstance $instance, DifyAccount $account, string $url): ResponseInterface
    {
        return $this->httpClient->request(
            'GET',
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $account->getAccessToken(),
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]
        );
    }

    /**
     * 执行应用详情请求
     */
    public function performAppDetailRequest(DifyInstance $instance, DifyAccount $account, string $url): ResponseInterface
    {
        return $this->httpClient->request(
            'GET',
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $account->getAccessToken(),
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]
        );
    }

    /**
     * 执行DSL导出请求
     */
    public function performDslExportRequest(DifyAccount $account, string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $account->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * 执行应用DSL导出请求
     */
    public function performAppDslExportRequest(DifyInstance $instance, DifyAccount $account, string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $account->getAccessToken(),
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }
}
