<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\DifySite;
use Tourze\DifyConsoleApiBundle\Repository\DifySiteRepository;

/**
 * 站点数据处理器
 *
 * 负责处理应用站点数据的同步和更新
 */
#[WithMonologChannel(channel: 'dify_console_api')]
readonly class SiteDataProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DifySiteRepository $siteRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理应用站点数据并更新统计信息
     *
     * @param  array<string, mixed>                                                                                                                                                                                                                                  $appData
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    public function processAppSiteData(BaseApp $app, array $appData, array $syncStats): array
    {
        $siteData = $appData['site'] ?? null;

        if (!$this->isValidSiteData($siteData)) {
            $app->setSite(null);

            return $syncStats;
        }

        /** @var array<string, mixed> $validSiteData */
        $validSiteData = $siteData;
        $siteId = $this->extractSiteId($validSiteData);
        if (null === $siteId) {
            return $syncStats;
        }

        $site = $this->findOrCreateSite($siteId);
        $isNewSite = null === $this->siteRepository->findBySiteId($siteId);
        $appType = $this->extractAppType($appData);

        $this->configureSite($site, $validSiteData, $appType);
        $this->setAppSite($app, $site);

        return $this->updateSiteStatistics($syncStats, $isNewSite);
    }

    /**
     * 验证站点数据是否有效
     */
    private function isValidSiteData(mixed $siteData): bool
    {
        return is_array($siteData) && [] !== $siteData;
    }

    /**
     * 提取站点ID
     *
     * @param array<string, mixed> $siteData
     */
    private function extractSiteId(array $siteData): ?string
    {
        // Dify API中site的唯一标识符是'code'字段，不是'id'
        $siteId = $siteData['code'] ?? $siteData['access_token'] ?? $siteData['id'] ?? null;

        return is_string($siteId) && '' !== $siteId ? $siteId : null;
    }

    /**
     * 查找或创建站点
     */
    private function findOrCreateSite(string $siteId): DifySite
    {
        $existingSite = $this->siteRepository->findBySiteId($siteId);

        if (null !== $existingSite) {
            return $existingSite;
        }

        $site = new DifySite();
        $site->setSiteId($siteId);

        return $site;
    }

    /**
     * 提取应用类型
     *
     * @param array<string, mixed> $appData
     */
    private function extractAppType(array $appData): string
    {
        return is_string($appData['mode'] ?? null) ? $appData['mode'] : 'chat';
    }

    /**
     * 配置站点
     *
     * @param array<string, mixed> $siteData
     */
    private function configureSite(DifySite $site, array $siteData, string $appType): void
    {
        $this->updateSiteFromData($site, $siteData, $appType);
        $this->entityManager->persist($site);
    }

    /**
     * 设置应用的站点关联
     */
    private function setAppSite(BaseApp $app, DifySite $site): void
    {
        $app->setSite($site);
    }

    /**
     * 更新站点统计
     *
     * @param  array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>} $syncStats
     * @return array{processed_instances: int, processed_accounts: int, synced_apps: int, created_apps: int, updated_apps: int, synced_sites: int, created_sites: int, updated_sites: int, errors: int, app_types: array<string, int>, error_details: array<string>}
     */
    private function updateSiteStatistics(array $syncStats, bool $isNewSite): array
    {
        ++$syncStats['synced_sites'];
        if ($isNewSite) {
            ++$syncStats['created_sites'];
        } else {
            ++$syncStats['updated_sites'];
        }

        return $syncStats;
    }

    /**
     * 从API数据更新站点信息
     *
     * @param array<string, mixed> $siteData
     */
    private function updateSiteFromData(DifySite $site, array $siteData, string $appType): void
    {
        $this->updateAllSiteFields($site, $siteData, $appType);
        $site->setLastSyncTime(new \DateTimeImmutable());
    }

    /**
     * 更新所有站点字段
     *
     * @param array<string, mixed> $siteData
     */
    private function updateAllSiteFields(DifySite $site, array $siteData, string $appType): void
    {
        $this->setSiteBasicInfo($site, $siteData, $appType);
        $this->setSiteConfiguration($site, $siteData);
        $this->setSitePolicyInfo($site, $siteData);
        $this->setSiteCustomConfig($site, $siteData);
        $this->setSiteTimestamp($site, 'created_at', 'setPublishTime', $siteData);
    }

    /**
     * 设置站点基本信息
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteBasicInfo(DifySite $site, array $siteData, string $appType): void
    {
        $this->setSiteTitleAndDescription($site, $siteData);
        $this->setSiteUrl($site, $siteData, $appType);
        $this->setSiteEnabledStatus($site, $siteData);
    }

    /**
     * 设置站点标题和描述
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteTitleAndDescription(DifySite $site, array $siteData): void
    {
        $title = $siteData['title'] ?? $siteData['name'] ?? '未命名站点';
        $site->setTitle(is_string($title) ? $title : '未命名站点');

        $description = $siteData['description'] ?? null;
        $site->setDescription(null === $description ? null : (is_string($description) ? $description : null));
    }

    /**
     * 设置站点URL
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteUrl(DifySite $site, array $siteData, string $appType): void
    {
        $siteUrl = $siteData['url'] ?? $siteData['site_url'] ?? '';

        // 如果没有直接的URL，根据app_base_url和code构建站点URL
        if (!is_string($siteUrl) || '' === $siteUrl) {
            $siteUrl = $this->buildSiteUrlFromComponents($siteData, $appType);
        }

        if ('' !== $siteUrl) {
            $site->setSiteUrl($siteUrl);
        }
    }

    /**
     * 从组件构建站点URL
     *
     * @param array<string, mixed> $siteData
     */
    private function buildSiteUrlFromComponents(array $siteData, string $appType): string
    {
        $appBaseUrl = $siteData['app_base_url'] ?? '';
        $code = $siteData['code'] ?? $siteData['access_token'] ?? '';

        if (!is_string($appBaseUrl) || !is_string($code) || '' === $appBaseUrl || '' === $code) {
            return '';
        }

        $urlPath = $this->getUrlPathByAppType($appType);

        return rtrim($appBaseUrl, '/') . $urlPath . $code;
    }

    /**
     * 根据应用类型获取URL路径
     */
    private function getUrlPathByAppType(string $appType): string
    {
        return match ($appType) {
            'workflow' => '/workflow/',
            'agent-chat', 'advanced-chat', 'chat' => '/chatbot/',
            default => '/chatbot/',
        };
    }

    /**
     * 设置站点启用状态
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteEnabledStatus(DifySite $site, array $siteData): void
    {
        $code = $siteData['code'] ?? $siteData['access_token'] ?? '';
        $isEnabled = is_string($code) && '' !== $code;
        $site->setIsEnabled($isEnabled);
    }

    /**
     * 设置站点配置信息
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteConfiguration(DifySite $site, array $siteData): void
    {
        $defaultLanguage = $siteData['default_language'] ?? null;
        $site->setDefaultLanguage(null === $defaultLanguage ? null : (is_string($defaultLanguage) ? $defaultLanguage : null));

        $theme = $siteData['theme'] ?? null;
        $site->setTheme(null === $theme ? null : (is_string($theme) ? $theme : null));

        $copyright = $siteData['copyright'] ?? null;
        $site->setCopyright(null === $copyright ? null : (is_string($copyright) ? $copyright : null));
    }

    /**
     * 设置站点政策信息
     *
     * @param array<string, mixed> $siteData
     */
    private function setSitePolicyInfo(DifySite $site, array $siteData): void
    {
        $privacyPolicy = $siteData['privacy_policy'] ?? null;
        $site->setPrivacyPolicy(null === $privacyPolicy ? null : (is_string($privacyPolicy) ? $privacyPolicy : null));

        $disclaimer = $siteData['custom_disclaimer'] ?? $siteData['disclaimer'] ?? null;
        $site->setDisclaimer(null === $disclaimer ? null : (is_string($disclaimer) ? $disclaimer : null));
    }

    /**
     * 设置站点自定义配置
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteCustomConfig(DifySite $site, array $siteData): void
    {
        $customDomain = $siteData['customize_domain'] ?? $siteData['custom_domain'] ?? null;
        $customDomainArray = $this->ensureStringKeysArray($customDomain);
        $site->setCustomDomain($customDomainArray);

        $customConfig = $siteData['custom_config'] ?? $siteData['config'] ?? null;
        $customConfigArray = $this->ensureStringKeysArray($customConfig);
        $site->setCustomConfig($customConfigArray);
    }

    /**
     * 确保数组具有字符串键
     *
     * @param mixed $data
     * @return array<string, mixed>|null
     */
    private function ensureStringKeysArray(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return [] === $result ? null : $result;
    }

    /**
     * 设置站点时间戳
     *
     * @param array<string, mixed> $siteData
     */
    private function setSiteTimestamp(DifySite $site, string $dataKey, string $method, array $siteData): void
    {
        if (!isset($siteData[$dataKey])) {
            return;
        }

        $timestamp = $siteData[$dataKey];

        try {
            // 处理Unix时间戳（数字）或字符串格式
            if (is_int($timestamp) || (is_string($timestamp) && is_numeric($timestamp))) {
                $dateTime = new \DateTimeImmutable('@' . $timestamp);
            } elseif (is_string($timestamp)) {
                $dateTime = new \DateTimeImmutable($timestamp);
            } else {
                return;
            }
            match ($method) {
                'setPublishTime' => $site->setPublishTime($dateTime),
                default => throw new \InvalidArgumentException('Unknown method: ' . $method),
            };
        } catch (\Exception $e) {
            $this->logger->warning('无法解析站点时间戳', [
                'siteId' => $site->getSiteId(),
                'field' => $dataKey,
                'value' => $timestamp,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
