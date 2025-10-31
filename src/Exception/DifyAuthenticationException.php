<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Exception;

/**
 * Dify 认证异常
 *
 * 当 Dify Console API 认证失败时抛出此异常
 * 包括登录失败、token 过期、权限不足等情况
 */
class DifyAuthenticationException extends DifyApiException
{
    public static function loginFailed(string $responseBody = ''): self
    {
        return new self('Dify 登录失败', 401, $responseBody);
    }

    public static function tokenExpired(): self
    {
        return new self('Dify token 已过期', 401);
    }

    public static function tokenInvalid(string $responseBody = ''): self
    {
        return new self('Dify token 无效', 401, $responseBody);
    }

    public static function insufficientPermissions(): self
    {
        return new self('权限不足', 403);
    }
}
