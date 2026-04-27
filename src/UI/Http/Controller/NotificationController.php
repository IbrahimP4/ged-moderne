<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
    ) {}

    /** Liste les 30 dernières notifications de l'utilisateur connecté. */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $userId        = $user->getDomainUser()->getId();
        $notifications = $this->notificationRepository->findRecentFor($userId);
        $unread        = $this->notificationRepository->countUnreadFor($userId);

        return $this->json([
            'notifications' => array_map(
                static fn ($n) => $n->toArray(),
                $notifications,
            ),
            'unreadCount' => $unread,
        ]);
    }

    /** Marque toutes les notifications comme lues. */
    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $this->notificationRepository->markAllReadFor(
            $user->getDomainUser()->getId(),
        );

        return $this->json(['ok' => true]);
    }

    /** Marque une notification spécifique comme lue. */
    #[Route('/{id}/read', name: 'read_one', methods: ['PATCH'])]
    public function readOne(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            $notifId = NotificationId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $this->notificationRepository->markRead(
            $notifId,
            $user->getDomainUser()->getId(),
        );

        return $this->json(['ok' => true]);
    }

    /** Nombre de notifications non lues (pour le badge). */
    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $count = $this->notificationRepository->countUnreadFor(
            $user->getDomainUser()->getId(),
        );

        return $this->json(['count' => $count]);
    }
}
