<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;
use Tourze\DifyConsoleApiBundle\Entity\DifyAccount;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;

final class ChatAssistantAppFixtures extends Fixture implements DependentFixtureInterface
{
    public const APP_CUSTOMER_SERVICE = 'chat_assistant_customer_service';
    public const APP_TECH_SUPPORT = 'chat_assistant_tech_support';
    public const CHAT_ASSISTANT_APP_REFERENCE = 'chat-assistant-app-default';

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

        $customerServiceApp = new ChatAssistantApp();
        $customerServiceApp->setInstance($mainInstance);
        $customerServiceApp->setAccount($adminAccount);
        $customerServiceApp->setDifyAppId('app-cs-001');
        $customerServiceApp->setName('客服助手');
        $customerServiceApp->setDescription('智能客服助手，用于处理客户咨询');
        $customerServiceApp->setIcon('https://images.unsplash.com/photo-1553484771-371a605b060b?w=64&h=64&fit=crop&crop=face');
        $customerServiceApp->setIsPublic(true);
        $customerServiceApp->setCreatedByDifyUser('admin');
        $customerServiceApp->setAssistantConfig(
            [
                'model' => 'gpt-3.5-turbo',
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'system_role' => 'customer_service',
            ]
        );
        $customerServiceApp->setPromptTemplate('你是一个专业的客服助手，请耐心、友好地回答客户的问题。');
        $customerServiceApp->setKnowledgeBase(
            [
                'enabled' => true,
                'datasets' => ['faq-dataset', 'product-info-dataset'],
                'retrieval_mode' => 'semantic',
                'top_k' => 5,
            ]
        );

        $techSupportApp = new ChatAssistantApp();
        $techSupportApp->setInstance($mainInstance);
        $techSupportApp->setAccount($userAccount);
        $techSupportApp->setDifyAppId('app-tech-001');
        $techSupportApp->setName('技术支持助手');
        $techSupportApp->setDescription('专业的技术支持助手，提供技术问题解答');
        $techSupportApp->setIcon('https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=64&h=64&fit=crop&crop=face');
        $techSupportApp->setIsPublic(false);
        $techSupportApp->setCreatedByDifyUser('tech_user');
        $techSupportApp->setAssistantConfig(
            [
                'model' => 'gpt-4',
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'system_role' => 'technical_expert',
            ]
        );
        $techSupportApp->setPromptTemplate('你是一个专业的技术支持专家，请提供准确、详细的技术解决方案。');
        $techSupportApp->setKnowledgeBase(
            [
                'enabled' => true,
                'datasets' => ['technical-docs', 'troubleshooting-guide'],
                'retrieval_mode' => 'keyword',
                'top_k' => 3,
            ]
        );

        $manager->persist($customerServiceApp);
        $manager->persist($techSupportApp);

        $this->addReference(self::APP_CUSTOMER_SERVICE, $customerServiceApp);
        $this->addReference(self::APP_TECH_SUPPORT, $techSupportApp);
        $this->addReference(self::CHAT_ASSISTANT_APP_REFERENCE, $customerServiceApp);

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
