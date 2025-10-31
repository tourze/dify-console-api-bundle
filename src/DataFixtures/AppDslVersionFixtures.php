<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DifyConsoleApiBundle\Entity\AppDslVersion;
use Tourze\DifyConsoleApiBundle\Entity\ChatAssistantApp;

class AppDslVersionFixtures extends Fixture implements DependentFixtureInterface
{
    public const DSL_VERSION_REFERENCE = 'dsl-version';

    public function load(ObjectManager $manager): void
    {
        // 获取测试应用
        /** @var ChatAssistantApp $app */
        $app = $this->getReference(ChatAssistantAppFixtures::CHAT_ASSISTANT_APP_REFERENCE, ChatAssistantApp::class);

        // 创建 DSL 版本记录
        $dslVersion = AppDslVersion::create($app, 1);
        $dslVersion->setDslContent([
            'system_prompt' => 'You are a helpful AI assistant.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]);
        $dslVersion->setDslRawContent('{"system_prompt": "You are a helpful AI assistant.", "model": "gpt-3.5-turbo", "temperature": 0.7, "max_tokens": 2000}');
        $dslContent = json_encode($dslVersion->getDslContent());
        if (false === $dslContent) {
            throw new \InvalidArgumentException('Failed to encode DSL content to JSON');
        }
        $dslVersion->setDslHash(hash('sha256', $dslContent));
        $dslVersion->setSyncTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $manager->persist($dslVersion);

        // 创建第二个版本
        $dslVersion2 = AppDslVersion::create($app, 2);
        $dslVersion2->setDslContent([
            'system_prompt' => 'You are a helpful and knowledgeable AI assistant.',
            'model' => 'gpt-4',
            'temperature' => 0.5,
            'max_tokens' => 4000,
        ]);
        $dslVersion2->setDslRawContent('{"system_prompt": "You are a helpful and knowledgeable AI assistant.", "model": "gpt-4", "temperature": 0.5, "max_tokens": 4000}');
        $dslContent2 = json_encode($dslVersion2->getDslContent());
        if (false === $dslContent2) {
            throw new \InvalidArgumentException('Failed to encode DSL content to JSON');
        }
        $dslVersion2->setDslHash(hash('sha256', $dslContent2));
        $dslVersion2->setSyncTime(new \DateTimeImmutable('2024-01-02 15:30:00'));

        $manager->persist($dslVersion2);

        $manager->flush();

        // 添加引用供其他 Fixtures 使用
        $this->addReference(self::DSL_VERSION_REFERENCE, $dslVersion);
    }

    public function getDependencies(): array
    {
        return [
            ChatAssistantAppFixtures::class,
        ];
    }
}
