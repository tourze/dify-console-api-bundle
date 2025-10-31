<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\DifyConsoleApiBundle\DTO\AppDetailResult;
use Tourze\DifyConsoleApiBundle\DTO\AppDslExportResult;
use Tourze\DifyConsoleApiBundle\DTO\AppListQuery;
use Tourze\DifyConsoleApiBundle\DTO\AppListResult;
use Tourze\DifyConsoleApiBundle\Exception\DifyAuthenticationException;
use Tourze\DifyConsoleApiBundle\Exception\DifyGenericException;
use Tourze\DifyConsoleApiBundle\Exception\DifyInstanceUnavailableException;
use Tourze\DifyConsoleApiBundle\Exception\DifyRateLimitException;

/**
 * 响应处理器
 *
 * 负责处理API响应数据的解析和转换
 */
#[WithMonologChannel(channel: 'dify_console_api')]
readonly class ResponseProcessor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理应用列表响应
     */
    public function processAppsListResponse(ResponseInterface $response, AppListQuery $query, string $instanceUrl): AppListResult
    {
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            $responseBody = $response->getContent(false); // false 表示不抛出异常
            $this->handleApiError($statusCode, $responseBody, $instanceUrl);
        }

        $data = $response->toArray();
        $normalizedData = $this->ensureStringKeys($data);
        $apps = $this->extractAppsData($normalizedData['data'] ?? []);

        return new AppListResult(
            success: true,
            apps: $apps,
            total: $this->extractIntValue($normalizedData, 'total', 0),
            page: $this->extractIntValue($normalizedData, 'page', 1),
            limit: $query->limit
        );
    }

    /**
     * 提取应用数据
     *
     * @param mixed $appsData
     * @return array<int, array<string, mixed>|string>
     */
    private function extractAppsData(mixed $appsData): array
    {
        if (!is_array($appsData)) {
            return [];
        }

        $apps = [];
        foreach ($appsData as $index => $appData) {
            if (is_int($index)) {
                $apps[$index] = $this->normalizeAppData($appData);
            }
        }

        return $apps;
    }

    /**
     * 标准化应用数据
     *
     * @param mixed $appData
     * @return array<string, mixed>|string
     */
    private function normalizeAppData(mixed $appData): array|string
    {
        if (is_string($appData)) {
            return $appData;
        }

        if (is_array($appData)) {
            return $this->ensureStringKeys($appData);
        }

        return [];
    }

    /**
     * 确保数组具有字符串键
     *
     * @param array<mixed, mixed> $data
     * @return array<string, mixed>
     */
    private function ensureStringKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 提取整数值
     *
     * @param array<string, mixed> $data
     */
    private function extractIntValue(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * 处理应用详情响应
     */
    public function processAppDetailResponse(ResponseInterface $response, string $instanceUrl): AppDetailResult
    {
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            $responseBody = $response->getContent(false); // false 表示不抛出异常
            $this->handleApiError($statusCode, $responseBody, $instanceUrl);
        }

        $data = $response->toArray();
        $appData = $this->normalizeAppDetailData($data);

        return new AppDetailResult(
            success: true,
            appData: $appData
        );
    }

    /**
     * 标准化应用详情数据
     *
     * @param array<mixed, mixed> $data
     * @return array<string, mixed>|null
     */
    private function normalizeAppDetailData(array $data): ?array
    {
        $stringKeyData = $this->ensureStringKeys($data);

        return [] === $stringKeyData ? null : $stringKeyData;
    }

    /**
     * 处理DSL导出响应（字符串形式）
     */
    public function processDslExportResponse(string $responseBody, string $appId): AppDslExportResult
    {
        try {
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                return $this->createDslErrorResult('响应数据格式错误');
            }

            $dslRawData = $data['data'] ?? null;
            if (null === $dslRawData) {
                return $this->createDslErrorResult('响应中未找到 DSL 数据');
            }

            return $this->processDslRawData($dslRawData);
        } catch (\JsonException $e) {
            $this->logger->error('DSL导出响应解析失败', [
                'app_id' => $appId,
                'error' => $e->getMessage(),
            ]);

            return $this->createDslErrorResult('响应数据解析失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理DSL导出响应（ResponseInterface形式）
     */
    public function processAppDslExportResponse(ResponseInterface $response, string $instanceUrl): AppDslExportResult
    {
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            $responseBody = $response->getContent(false);

            return $this->handleDslExportError('unknown', $statusCode, $responseBody);
        }

        try {
            $data = $response->toArray();

            $dslRawData = $data['data'] ?? null;
            if (null === $dslRawData) {
                return $this->createDslErrorResult('响应中未找到 DSL 数据');
            }

            return $this->processDslRawData($dslRawData);
        } catch (\Exception $e) {
            $this->logger->error('DSL导出响应解析失败', [
                'instance_url' => $instanceUrl,
                'error' => $e->getMessage(),
            ]);

            return $this->createDslErrorResult('响应数据解析失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理DSL导出错误
     */
    public function handleDslExportError(string $appId, int $statusCode, string $responseBody): AppDslExportResult
    {
        $errorMessage = $this->extractErrorMessage($responseBody);
        $this->logger->warning('DSL导出失败', [
            'app_id' => $appId,
            'status_code' => $statusCode,
            'error' => $errorMessage,
        ]);

        return $this->createDslErrorResult($errorMessage);
    }

    /**
     * 处理DSL原始数据
     */
    private function processDslRawData(mixed $dslRawData): AppDslExportResult
    {
        if (is_string($dslRawData)) {
            return $this->processStringDslData($dslRawData);
        }

        if (is_array($dslRawData)) {
            // 确保数组具有字符串键
            $stringKeyData = [];
            foreach ($dslRawData as $key => $value) {
                if (is_string($key)) {
                    $stringKeyData[$key] = $value;
                }
            }

            return $this->processArrayDslData($stringKeyData);
        }

        return $this->createDslErrorResult('DSL 数据格式无效');
    }

    /**
     * 处理字符串格式DSL数据
     */
    private function processStringDslData(string $dslRawData): AppDslExportResult
    {
        try {
            $parsedContent = Yaml::parse($dslRawData);
            if (!is_array($parsedContent)) {
                return $this->createDslErrorResult('DSL YAML 解析结果不是数组格式');
            }

            /** @var array<string, mixed> $dslContent */
            $dslContent = $parsedContent;

            return new AppDslExportResult(
                success: true,
                dslContent: $dslContent,
                rawContent: $dslRawData
            );
        } catch (ParseException $e) {
            return $this->createDslErrorResult('DSL YAML 解析失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理数组格式DSL数据
     *
     * @param array<string, mixed> $dslRawData
     */
    private function processArrayDslData(array $dslRawData): AppDslExportResult
    {
        try {
            $rawContent = Yaml::dump($dslRawData, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

            return new AppDslExportResult(
                success: true,
                dslContent: $dslRawData,
                rawContent: $rawContent
            );
        } catch (\Exception $e) {
            return $this->createDslErrorResult('DSL 数组转换为 YAML 失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建DSL错误结果
     */
    private function createDslErrorResult(string $errorMessage): AppDslExportResult
    {
        return new AppDslExportResult(
            success: false,
            errorMessage: $errorMessage
        );
    }

    /**
     * 处理API错误响应
     *
     * @throws DifyAuthenticationException
     * @throws DifyRateLimitException
     * @throws DifyInstanceUnavailableException
     * @throws DifyGenericException
     */
    private function handleApiError(int $statusCode, string $responseBody, string $instanceUrl = ''): never
    {
        // 尝试解析错误信息
        $errorMessage = $this->extractErrorMessage($responseBody);

        match ($statusCode) {
            401 => throw DifyAuthenticationException::tokenInvalid($responseBody),
            403 => throw DifyAuthenticationException::insufficientPermissions(),
            429 => throw new DifyRateLimitException('请求频率过高，请稍后重试', null, 0, 0, $responseBody),
            500, 502, 503, 504 => throw DifyInstanceUnavailableException::serviceUnavailable($instanceUrl, $responseBody),
            default => throw DifyGenericException::create('' !== $errorMessage ? $errorMessage : "API请求失败 (HTTP {$statusCode})", $statusCode, $responseBody),
        };
    }

    /**
     * 从响应体中提取错误信息
     */
    private function extractErrorMessage(string $responseBody): string
    {
        try {
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($data)) {
                $message = $data['message'] ?? $data['error'] ?? $data['detail'] ?? '未知错误';

                return is_string($message) ? $message : '';
            }

            return '未知错误';
        } catch (\JsonException) {
            return '' !== $responseBody ? $responseBody : '未知错误';
        }
    }
}
