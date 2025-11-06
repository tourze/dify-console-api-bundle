<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin\Sync;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\DifyConsoleApiBundle\Message\SyncApplicationsMessage;
use Tourze\DifyConsoleApiBundle\Repository\DifyAccountRepository;

#[WithMonologChannel(channel: 'dify_console_api')]
final class DifyAppSyncController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly DifyAccountRepository $accountRepository,
    ) {
    }

    #[Route(path: '/admin/dify/sync-apps', name: 'admin_dify_sync_apps', methods: ['POST'])]
    #[IsGranted(attribute: 'ROLE_DIFY_ADMIN')]
    public function __invoke(): JsonResponse
    {
        try {
            $accounts = $this->accountRepository->findAll();
            $syncCount = 0;

            foreach ($accounts as $account) {
                $accountId = $account->getId();
                if (null !== $accountId) {
                    $this->messageBus->dispatch(new SyncApplicationsMessage($accountId));
                    ++$syncCount;
                }
            }

            $this->logger->info(
                'Dispatched sync applications messages',
                [
                    'account_count' => $syncCount,
                ]
            );

            return new JsonResponse(
                [
                    'success' => true,
                    'message' => "已启动 {$syncCount} 个账号的应用同步任务",
                    'data' => [
                        'account_count' => $syncCount,
                    ],
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to dispatch sync applications messages',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'message' => '同步失败: ' . $e->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
