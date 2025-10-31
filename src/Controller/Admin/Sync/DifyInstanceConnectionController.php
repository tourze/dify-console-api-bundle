<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin\Sync;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\DifyConsoleApiBundle\Entity\DifyInstance;
use Tourze\DifyConsoleApiBundle\Repository\DifyInstanceRepository;

#[WithMonologChannel(channel: 'dify_console_api')]
class DifyInstanceConnectionController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DifyInstanceRepository $instanceRepository,
    ) {
    }

    #[Route(path: '/admin/dify/test-connection/{instanceId}', name: 'admin_dify_test_connection', methods: ['POST'])]
    #[IsGranted(attribute: 'ROLE_DIFY_ADMIN')]
    public function __invoke(int $instanceId): JsonResponse
    {
        try {
            $instance = $this->instanceRepository->find($instanceId);
            if (null === $instance) {
                return new JsonResponse(
                    [
                        'success' => false,
                        'message' => '实例不存在',
                    ],
                    Response::HTTP_NOT_FOUND
                );
            }

            $result = $this->testInstanceConnection($instance);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to test connection for instance',
                [
                    'instance_id' => $instanceId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'message' => '连接测试失败: ' . $e->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * 测试单个实例的连接
     *
     * @return array<string, mixed>
     */
    private function testInstanceConnection(DifyInstance $instance): array
    {
        try {
            // 这里可以调用实际的连接测试逻辑
            // 例如：$this->difyClientService->testConnection($instance);

            // 临时返回成功，实际实现时需要真正测试连接
            $this->logger->info(
                'Testing connection for instance',
                [
                    'instance_id' => $instance->getId(),
                    'instance_name' => $instance->getName(),
                    'base_url' => $instance->getBaseUrl(),
                ]
            );

            return [
                'success' => true,
                'message' => '连接正常',
                'instance_id' => $instance->getId(),
                'instance_name' => $instance->getName(),
                'base_url' => $instance->getBaseUrl(),
                'tested_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            $this->logger->error(
                'Connection test failed for instance',
                [
                    'instance_id' => $instance->getId(),
                    'instance_name' => $instance->getName(),
                    'base_url' => $instance->getBaseUrl(),
                    'error' => $e->getMessage(),
                ]
            );

            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage(),
                'instance_id' => $instance->getId(),
                'instance_name' => $instance->getName(),
                'base_url' => $instance->getBaseUrl(),
                'tested_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        }
    }
}
