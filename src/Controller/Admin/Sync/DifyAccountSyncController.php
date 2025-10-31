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
final class DifyAccountSyncController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly DifyAccountRepository $accountRepository,
    ) {
    }

    #[Route(path: '/admin/dify/sync-apps/{accountId}', name: 'admin_dify_sync_apps_for_account', methods: ['POST'])]
    #[IsGranted(attribute: 'ROLE_DIFY_ADMIN')]
    public function __invoke(int $accountId): JsonResponse
    {
        try {
            $account = $this->accountRepository->find($accountId);
            if (null === $account) {
                return new JsonResponse(
                    [
                        'success' => false,
                        'message' => '账号不存在',
                    ],
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->messageBus->dispatch(new SyncApplicationsMessage($accountId));

            $this->logger->info(
                'Dispatched sync applications message for account',
                [
                    'account_id' => $accountId,
                    'account_name' => $account->getName(),
                ]
            );

            return new JsonResponse(
                [
                    'success' => true,
                    'message' => "已启动账号 '{$account->getName()}' 的应用同步任务",
                    'data' => [
                        'account_id' => $accountId,
                        'account_name' => $account->getName(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to dispatch sync applications message for account',
                [
                    'account_id' => $accountId,
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
