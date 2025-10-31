<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Service\Helper;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;

/**
 * 应用数据处理器
 *
 * 负责处理应用实体的数据设置和验证
 */
#[WithMonologChannel(channel: 'dify_console_api')]
readonly class AppDataProcessor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 更新应用基本字段
     *
     * @param array<string, mixed> $appData
     */
    public function updateAppBasicFields(BaseApp $app, DifyInstance $instance, array $appData): void
    {
        $this->setBasicAppData($app, $instance, $appData);
        $this->setAppTextFields($app, $appData);
        $this->setAppFlags($app, $appData);
        $this->setAppTimestamps($app, $appData);
    }

    /**
     * 设置应用基本数据
     *
     * @param array<string, mixed> $appData
     */
    private function setBasicAppData(BaseApp $app, DifyInstance $instance, array $appData): void
    {
        $difyAppId = $appData['id'] ?? '';
        $instanceId = $instance->getId();

        if (!is_string($difyAppId) || null === $instanceId) {
            throw new \InvalidArgumentException('必要的字段缺失或类型不正确');
        }

        $app->setDifyAppId($difyAppId);
        $app->setInstance($instance);
        $app->setLastSyncTime(new \DateTimeImmutable());
    }

    /**
     * 设置应用文本字段
     *
     * @param array<string, mixed> $appData
     */
    private function setAppTextFields(BaseApp $app, array $appData): void
    {
        $this->setAppName($app, $appData);
        $this->setAppDescription($app, $appData);
        $this->setAppIcon($app, $appData);
        $this->setAppCreatedBy($app, $appData);
    }

    /**
     * 设置应用名称
     *
     * @param array<string, mixed> $appData
     */
    private function setAppName(BaseApp $app, array $appData): void
    {
        $name = $appData['name'] ?? '';
        $app->setName(is_string($name) ? $name : '');
    }

    /**
     * 设置应用描述
     *
     * @param array<string, mixed> $appData
     */
    private function setAppDescription(BaseApp $app, array $appData): void
    {
        $description = $appData['description'] ?? null;
        $app->setDescription(null === $description ? null : (is_string($description) ? $description : ''));
    }

    /**
     * 设置应用图标
     *
     * @param array<string, mixed> $appData
     */
    private function setAppIcon(BaseApp $app, array $appData): void
    {
        $icon = $appData['icon'] ?? null;
        $app->setIcon(null === $icon ? null : (is_string($icon) ? $icon : ''));
    }

    /**
     * 设置应用创建者
     *
     * @param array<string, mixed> $appData
     */
    private function setAppCreatedBy(BaseApp $app, array $appData): void
    {
        $createdBy = $appData['created_by'] ?? null;
        $app->setCreatedByDifyUser(null === $createdBy ? null : (is_string($createdBy) ? $createdBy : ''));
    }

    /**
     * 设置应用标志
     *
     * @param array<string, mixed> $appData
     */
    private function setAppFlags(BaseApp $app, array $appData): void
    {
        $isPublic = $appData['is_public'] ?? false;
        $app->setIsPublic(is_bool($isPublic) ? $isPublic : false);
    }

    /**
     * 设置应用时间戳
     *
     * @param array<string, mixed> $appData
     */
    private function setAppTimestamps(BaseApp $app, array $appData): void
    {
        $this->setDifyTimestamp($app, 'created_at', 'setDifyCreateTime', $appData);
        $this->setDifyTimestamp($app, 'updated_at', 'setDifyUpdateTime', $appData);
    }

    /**
     * 设置 Dify 时间戳
     *
     * @param array<string, mixed> $appData
     */
    private function setDifyTimestamp(BaseApp $app, string $dataKey, string $method, array $appData): void
    {
        if (!isset($appData[$dataKey])) {
            return;
        }

        $timestamp = $appData[$dataKey];
        if (!is_string($timestamp)) {
            return;
        }

        try {
            $dateTime = new \DateTimeImmutable($timestamp);
            match ($method) {
                'setDifyCreateTime' => $app->setDifyCreateTime($dateTime),
                'setDifyUpdateTime' => $app->setDifyUpdateTime($dateTime),
                default => throw new \InvalidArgumentException('Unknown method: ' . $method),
            };
        } catch (\Exception $e) {
            $this->logger->warning('无法解析Dify时间戳', [
                'appId' => $appData['id'] ?? 'unknown',
                'field' => $dataKey,
                'value' => $timestamp,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 设置应用特定字段
     *
     * @param array<string, mixed> $appData
     */
    public function setAppSpecificFields(BaseApp $app, array $appData): void
    {
        match (true) {
            $app instanceof ChatAssistantApp => $this->setChatAssistantFields($app, $appData),
            $app instanceof ChatflowApp => $this->setChatflowFields($app, $appData),
            $app instanceof WorkflowApp => $this->setWorkflowFields($app, $appData),
            default => null,
        };
    }

    /**
     * 设置ChatAssistant应用特定字段
     *
     * @param array<string, mixed> $appData
     */
    private function setChatAssistantFields(ChatAssistantApp $app, array $appData): void
    {
        $promptTemplate = $appData['prompt_template'] ?? $appData['description'] ?? '默认提示模板';
        $app->setPromptTemplate(is_string($promptTemplate) ? $promptTemplate : '默认提示模板');

        $this->setArrayConfig($app, 'setAssistantConfig', $appData, 'model_config');
        $this->setArrayConfig($app, 'setKnowledgeBase', $appData, 'retrieval_setting');
    }

    /**
     * 设置Chatflow应用特定字段
     *
     * @param array<string, mixed> $appData
     */
    private function setChatflowFields(ChatflowApp $app, array $appData): void
    {
        $this->setArrayConfig($app, 'setChatflowConfig', $appData, 'workflow_config');
        $this->setArrayConfig($app, 'setModelConfig', $appData, 'model_config');
        $this->setArrayConfig($app, 'setConversationConfig', $appData, 'conversation_config');
    }

    /**
     * 设置Workflow应用特定字段
     *
     * @param array<string, mixed> $appData
     */
    private function setWorkflowFields(WorkflowApp $app, array $appData): void
    {
        $this->setArrayConfig($app, 'setWorkflowConfig', $appData, 'workflow_config');
        $this->setArrayConfig($app, 'setInputSchema', $appData, 'input_schema');
        $this->setArrayConfig($app, 'setOutputSchema', $appData, 'output_schema');
    }

    /**
     * 设置数组配置
     *
     * @param array<string, mixed> $appData
     */
    private function setArrayConfig(BaseApp $app, string $method, array $appData, string $key): void
    {
        $configArray = $this->extractArrayConfig($appData, $key);
        $this->applyConfigByMethod($app, $method, $configArray);
    }

    /**
     * 提取数组配置
     *
     * @param  array<string, mixed> $appData
     * @return array<string, mixed>
     */
    private function extractArrayConfig(array $appData, string $key): array
    {
        $config = $appData[$key] ?? [];

        if (!is_array($config)) {
            return [];
        }

        // Ensure we return array<string, mixed>
        $result = [];
        foreach ($config as $k => $v) {
            if (is_string($k)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * 根据方法名应用配置
     *
     * @param array<string, mixed> $configArray
     */
    private function applyConfigByMethod(BaseApp $app, string $method, array $configArray): void
    {
        match ($method) {
            'setAssistantConfig' => $this->setAssistantConfig($app, $configArray),
            'setKnowledgeBase' => $this->setKnowledgeBase($app, $configArray),
            'setChatflowConfig' => $this->setChatflowConfig($app, $configArray),
            'setModelConfig' => $this->setModelConfig($app, $configArray),
            'setConversationConfig' => $this->setConversationConfig($app, $configArray),
            'setWorkflowConfig' => $this->setWorkflowConfig($app, $configArray),
            'setInputSchema' => $this->setInputSchema($app, $configArray),
            'setOutputSchema' => $this->setOutputSchema($app, $configArray),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setAssistantConfig(BaseApp $app, array $configArray): void
    {
        if ($app instanceof ChatAssistantApp) {
            $app->setAssistantConfig($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setKnowledgeBase(BaseApp $app, array $configArray): void
    {
        if ($app instanceof ChatAssistantApp) {
            $app->setKnowledgeBase($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setChatflowConfig(BaseApp $app, array $configArray): void
    {
        if ($app instanceof ChatflowApp) {
            $app->setChatflowConfig($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setModelConfig(BaseApp $app, array $configArray): void
    {
        if ($app instanceof ChatflowApp) {
            $app->setModelConfig($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setConversationConfig(BaseApp $app, array $configArray): void
    {
        if ($app instanceof ChatflowApp) {
            $app->setConversationConfig($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setWorkflowConfig(BaseApp $app, array $configArray): void
    {
        if ($app instanceof WorkflowApp) {
            $app->setWorkflowConfig($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setInputSchema(BaseApp $app, array $configArray): void
    {
        if ($app instanceof WorkflowApp) {
            $app->setInputSchema($configArray);
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function setOutputSchema(BaseApp $app, array $configArray): void
    {
        if ($app instanceof WorkflowApp) {
            $app->setOutputSchema($configArray);
        }
    }
}
