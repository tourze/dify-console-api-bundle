<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\DTO\AuthenticationResult;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Exception\DifyAuthenticationException;
use Tourze\DifyConsoleApiBundle\Exception\DifyGenericException;
use Tourze\DifyConsoleApiBundle\Exception\DifyInstanceUnavailableException;
use Tourze\DifyConsoleApiBundle\Exception\DifyRateLimitException;

/**
 * 认证处理器
 *
 * 负责处理登录认证和Token管理
 */
#[WithMonologChannel(channel: 'dify_console_api')]
readonly class AuthenticationProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 验证登录响应
     *
     * @return array<string, mixed>
     */
    public function validateLoginResponse(ResponseInterface $response, string $baseUrl): array
    {
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            $responseBody = $response->getContent(false);
            $this->handleHttpError($statusCode, $responseBody);
        }

        /** @var array<string, mixed> */
        return $response->toArray();
    }

    /**
     * 处理HTTP错误并抛出相应的异常
     */
    private function handleHttpError(int $statusCode, string $responseBody): never
    {
        // 401/403: 认证/授权失败
        if (401 === $statusCode || 403 === $statusCode) {
            throw DifyAuthenticationException::loginFailed($responseBody);
        }

        // 429: 速率限制
        if (429 === $statusCode) {
            throw DifyRateLimitException::rateLimitExceeded($responseBody);
        }

        // 5xx: 服务端错误，实例不可用
        if ($statusCode >= 500 && $statusCode < 600) {
            throw DifyInstanceUnavailableException::instanceUnavailable($responseBody);
        }

        // 4xx: 其他客户端错误
        if ($statusCode >= 400 && $statusCode < 500) {
            throw DifyGenericException::create("HTTP {$statusCode} 错误", $statusCode, $responseBody);
        }

        // 其他错误
        throw DifyGenericException::create('未知HTTP错误', $statusCode, $responseBody);
    }

    /**
     * 提取认证数据
     *
     * @param  array<string, mixed> $data
     * @return array{token: string, expiresAt: \DateTimeImmutable}
     */
    public function extractAuthenticationData(array $data): array
    {
        $accessToken = $this->extractAccessToken($data);
        $this->validateAccessToken($accessToken, $data);

        if (!is_string($accessToken)) {
            throw DifyAuthenticationException::loginFailed('Access token must be a string');
        }

        $expiresAt = $this->calculateTokenExpiry($accessToken, $data);

        return [
            'token' => $accessToken,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * 提取访问令牌
     *
     * @param array<string, mixed> $data
     */
    private function extractAccessToken(array $data): mixed
    {
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        if (isset($data['data']) && is_array($data['data']) && isset($data['data']['access_token'])) {
            return $data['data']['access_token'];
        }

        return null;
    }

    /**
     * 验证访问令牌
     *
     * @param array<string, mixed> $data
     */
    private function validateAccessToken(mixed $accessToken, array $data): void
    {
        if (is_string($accessToken) && '' !== $accessToken) {
            return;
        }

        $jsonData = json_encode($data);
        throw DifyAuthenticationException::loginFailed(false !== $jsonData ? $jsonData : 'Invalid JSON data');
    }

    /**
     * 计算Token过期时间
     *
     * @param array<string, mixed> $data
     */
    private function calculateTokenExpiry(string $accessToken, array $data): \DateTimeImmutable
    {
        $expiresAt = $this->extractExpiryFromJwt($accessToken);

        if (null !== $expiresAt) {
            return $expiresAt;
        }

        return $this->calculateExpiryFromResponseData($data);
    }

    /**
     * 从响应数据计算过期时间
     *
     * @param array<string, mixed> $data
     */
    private function calculateExpiryFromResponseData(array $data): \DateTimeImmutable
    {
        $expiresIn = null;
        if (isset($data['expires_in'])) {
            $expiresIn = $data['expires_in'];
        } elseif (isset($data['data']) && is_array($data['data']) && isset($data['data']['expires_in'])) {
            $expiresIn = $data['data']['expires_in'];
        }

        if (null !== $expiresIn && is_int($expiresIn)) {
            return new \DateTimeImmutable(sprintf('+%d seconds', $expiresIn));
        }

        return new \DateTimeImmutable('+24 hours');
    }

    /**
     * 从JWT token中提取过期时间
     */
    private function extractExpiryFromJwt(string $token): ?\DateTimeImmutable
    {
        try {
            $parts = explode('.', $token);
            if (3 !== count($parts)) {
                return null;
            }

            // 解码JWT payload（第二部分）
            $payload = $parts[1];
            // 添加base64 padding
            $payload .= str_repeat('=', 4 - strlen($payload) % 4);

            $decoded = base64_decode($payload, true);
            if (false === $decoded) {
                return null;
            }

            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['exp'])) {
                return null;
            }

            $exp = $data['exp'];
            if (!is_int($exp)) {
                return null;
            }

            return new \DateTimeImmutable('@' . $exp);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * 确保账户有有效的token，如果过期则自动刷新
     */
    public function ensureValidToken(DifyAccount $account, callable $refreshTokenCallback): void
    {
        if (null === $account->getAccessToken() || $account->isTokenExpired()) {
            $result = $refreshTokenCallback();

            // 验证 result 是 AuthenticationResult 对象
            if (!$result instanceof AuthenticationResult || !$result->success) {
                throw DifyAuthenticationException::tokenExpired();
            }

            // 更新账户的token信息并持久化到数据库
            $account->setAccessToken($result->token);
            $account->setTokenExpiresTime($result->expiresTime);
            $account->setLastLoginTime(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->logger->info('Token自动刷新成功', [
                'account_id' => $account->getId(),
                'email' => $account->getEmail(),
                'expires_time' => $result->expiresTime?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * 记录成功登录
     */
    public function logSuccessfulLogin(DifyAccount $account): void
    {
        $this->logger->info('Dify登录成功', [
            'account_id' => $account->getId(),
            'email' => $account->getEmail(),
            'instance_id' => $account->getInstance()->getName(),
        ]);
    }
}
