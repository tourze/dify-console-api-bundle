<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\ChatflowApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

final class ChatflowAppFixtures extends Fixture implements DependentFixtureInterface
{
    public const APP_SALES_FLOW = 'chatflow_sales_flow';
    public const APP_ONBOARDING_FLOW = 'chatflow_onboarding_flow';

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

        $salesFlowApp = new ChatflowApp();
        $salesFlowApp->setInstance($mainInstance);
        $salesFlowApp->setAccount($adminAccount);
        $salesFlowApp->setDifyAppId('chatflow-sales-001');
        $salesFlowApp->setName('销售对话流');
        $salesFlowApp->setDescription('智能销售对话流程，引导客户完成购买');
        $salesFlowApp->setIcon('https://images.unsplash.com/photo-1556740738-b6a63e27c4df?w=64&h=64&fit=crop&crop=face');
        $salesFlowApp->setIsPublic(true);
        $salesFlowApp->setCreatedByDifyUser('sales_manager');
        $salesFlowApp->setChatflowConfig(
            [
                'flow_type' => 'sales_funnel',
                'stages' => ['greeting', 'qualification', 'presentation', 'closing'],
                'fallback_enabled' => true,
                'max_turns' => 20,
            ]
        );
        $salesFlowApp->setModelConfig(
            [
                'provider' => 'openai',
                'model' => 'gpt-3.5-turbo',
                'parameters' => [
                    'temperature' => 0.8,
                    'max_tokens' => 1500,
                    'presence_penalty' => 0.1,
                ],
            ]
        );
        $salesFlowApp->setConversationConfig(
            [
                'opening_statement' => '您好！我是您的专属销售顾问，很高兴为您服务。',
                'suggested_questions' => [
                    '我想了解产品功能',
                    '价格是多少？',
                    '有什么优惠活动吗？',
                ],
                'conversation_starters_enabled' => true,
            ]
        );

        $onboardingFlowApp = new ChatflowApp();
        $onboardingFlowApp->setInstance($mainInstance);
        $onboardingFlowApp->setAccount($userAccount);
        $onboardingFlowApp->setDifyAppId('chatflow-onboard-001');
        $onboardingFlowApp->setName('用户引导流');
        $onboardingFlowApp->setDescription('新用户引导对话流程，帮助用户快速上手');
        $onboardingFlowApp->setIcon('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=64&h=64&fit=crop&crop=face');
        $onboardingFlowApp->setIsPublic(false);
        $onboardingFlowApp->setCreatedByDifyUser('product_manager');
        $onboardingFlowApp->setChatflowConfig(
            [
                'flow_type' => 'onboarding',
                'stages' => ['welcome', 'profile_setup', 'feature_tour', 'completion'],
                'fallback_enabled' => true,
                'max_turns' => 15,
            ]
        );
        $onboardingFlowApp->setModelConfig(
            [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'parameters' => [
                    'temperature' => 0.5,
                    'max_tokens' => 1200,
                    'frequency_penalty' => 0.2,
                ],
            ]
        );
        $onboardingFlowApp->setConversationConfig(
            [
                'opening_statement' => '欢迎加入我们！让我来帮助您快速熟悉产品功能。',
                'suggested_questions' => [
                    '如何开始使用？',
                    '有什么核心功能？',
                    '如何设置个人资料？',
                ],
                'conversation_starters_enabled' => true,
            ]
        );

        $manager->persist($salesFlowApp);
        $manager->persist($onboardingFlowApp);

        $this->addReference(self::APP_SALES_FLOW, $salesFlowApp);
        $this->addReference(self::APP_ONBOARDING_FLOW, $onboardingFlowApp);

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
