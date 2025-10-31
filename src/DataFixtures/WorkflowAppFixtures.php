<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Entity\WorkflowApp;

final class WorkflowAppFixtures extends Fixture implements DependentFixtureInterface
{
    public const APP_DATA_PROCESSING = 'workflow_data_processing';
    public const APP_CONTENT_GENERATION = 'workflow_content_generation';

    public function load(ObjectManager $manager): void
    {
        /**
         * @var DifyInstance $mainInstance
         */
        $mainInstance = $this->getReference(DifyInstanceFixtures::INSTANCE_MAIN, DifyInstance::class);
        /**
         * @var DifyAccount $adminAccount
         */
        $adminAccount = $this->getReference(DifyAccountFixtures::ACCOUNT_ADMIN, DifyAccount::class);
        /**
         * @var DifyAccount $userAccount
         */
        $userAccount = $this->getReference(DifyAccountFixtures::ACCOUNT_USER, DifyAccount::class);

        $dataProcessingApp = new WorkflowApp();
        $dataProcessingApp->setInstance($mainInstance);
        $dataProcessingApp->setAccount($adminAccount);
        $dataProcessingApp->setDifyAppId('workflow-data-001');
        $dataProcessingApp->setName('数据处理工作流');
        $dataProcessingApp->setDescription('自动化数据处理和分析工作流');
        $dataProcessingApp->setIcon('https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=64&h=64&fit=crop&crop=face');
        $dataProcessingApp->setIsPublic(true);
        $dataProcessingApp->setCreatedByDifyUser('data_engineer');
        $dataProcessingApp->setWorkflowConfig(
            [
                'workflow_type' => 'data_processing',
                'steps' => [
                    ['name' => 'data_validation', 'type' => 'validator'],
                    ['name' => 'data_transformation', 'type' => 'transformer'],
                    ['name' => 'data_analysis', 'type' => 'analyzer'],
                    ['name' => 'report_generation', 'type' => 'generator'],
                ],
                'parallel_execution' => false,
                'error_handling' => 'stop_on_error',
            ]
        );
        $dataProcessingApp->setInputSchema(
            [
                'type' => 'object',
                'properties' => [
                    'data_source' => [
                        'type' => 'string',
                        'description' => '数据源路径或URL',
                        'required' => true,
                    ],
                    'processing_options' => [
                        'type' => 'object',
                        'properties' => [
                            'format' => ['type' => 'string', 'enum' => ['csv', 'json', 'xml']],
                            'encoding' => ['type' => 'string', 'default' => 'utf-8'],
                        ],
                    ],
                ],
            ]
        );
        $dataProcessingApp->setOutputSchema(
            [
                'type' => 'object',
                'properties' => [
                    'processed_data' => [
                        'type' => 'object',
                        'description' => '处理后的数据结果',
                    ],
                    'analysis_report' => [
                        'type' => 'string',
                        'description' => '分析报告内容',
                    ],
                    'statistics' => [
                        'type' => 'object',
                        'properties' => [
                            'total_records' => ['type' => 'integer'],
                            'valid_records' => ['type' => 'integer'],
                            'processing_time' => ['type' => 'number'],
                        ],
                    ],
                ],
            ]
        );

        $contentGenerationApp = new WorkflowApp();
        $contentGenerationApp->setInstance($mainInstance);
        $contentGenerationApp->setAccount($userAccount);
        $contentGenerationApp->setDifyAppId('workflow-content-001');
        $contentGenerationApp->setName('内容生成工作流');
        $contentGenerationApp->setDescription('自动化内容创作和优化工作流');
        $contentGenerationApp->setIcon('https://images.unsplash.com/photo-1455849318743-b2233052fcff?w=64&h=64&fit=crop&crop=face');
        $contentGenerationApp->setIsPublic(false);
        $contentGenerationApp->setCreatedByDifyUser('content_creator');
        $contentGenerationApp->setWorkflowConfig(
            [
                'workflow_type' => 'content_generation',
                'steps' => [
                    ['name' => 'topic_research', 'type' => 'research'],
                    ['name' => 'outline_creation', 'type' => 'planning'],
                    ['name' => 'content_writing', 'type' => 'generation'],
                    ['name' => 'content_review', 'type' => 'validation'],
                    ['name' => 'seo_optimization', 'type' => 'optimization'],
                ],
                'parallel_execution' => true,
                'error_handling' => 'continue_on_error',
            ]
        );
        $contentGenerationApp->setInputSchema(
            [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'description' => '内容主题',
                        'required' => true,
                    ],
                    'content_type' => [
                        'type' => 'string',
                        'enum' => ['article', 'blog_post', 'social_media', 'product_description'],
                        'required' => true,
                    ],
                    'target_audience' => [
                        'type' => 'string',
                        'description' => '目标受众描述',
                    ],
                    'keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'SEO关键词列表',
                    ],
                    'word_count' => [
                        'type' => 'integer',
                        'minimum' => 100,
                        'maximum' => 5000,
                        'default' => 1000,
                    ],
                ],
            ]
        );
        $contentGenerationApp->setOutputSchema(
            [
                'type' => 'object',
                'properties' => [
                    'generated_content' => [
                        'type' => 'string',
                        'description' => '生成的内容文本',
                    ],
                    'content_outline' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => '内容大纲',
                    ],
                    'seo_analysis' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword_density' => ['type' => 'number'],
                            'readability_score' => ['type' => 'number'],
                            'seo_score' => ['type' => 'number'],
                        ],
                    ],
                    'metadata' => [
                        'type' => 'object',
                        'properties' => [
                            'word_count' => ['type' => 'integer'],
                            'reading_time' => ['type' => 'integer'],
                            'creation_time' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                ],
            ]
        );

        $manager->persist($dataProcessingApp);
        $manager->persist($contentGenerationApp);

        $this->addReference(self::APP_DATA_PROCESSING, $dataProcessingApp);
        $this->addReference(self::APP_CONTENT_GENERATION, $contentGenerationApp);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DifyInstanceFixtures::class,
            DifyAccountFixtures::class,
        ];
    }
}
