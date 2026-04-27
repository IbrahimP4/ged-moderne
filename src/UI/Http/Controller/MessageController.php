<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Messaging\Command\SendMessageCommand;
use App\Application\Messaging\Command\SendMessageHandler;
use App\Domain\Messaging\Repository\MessageRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/messages', name: 'api_messages_')]
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    public function __construct(
        private readonly SendMessageHandler       $sendMessageHandler,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly UserRepositoryInterface  $userRepository,
    ) {}

    /**
     * Résumé des conversations de l'utilisateur connecté.
     * Retourne la liste des interlocuteurs + dernier message + nb non-lus.
     */
    #[Route('/conversations', name: 'conversations', methods: ['GET'])]
    public function conversations(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $summaries = $this->messageRepository->findConversationSummaries(
            $user->getDomainUser()->getId(),
        );

        return $this->json(array_map(
            static fn ($s) => [
                'partner'      => [
                    'id'       => $s['partner']->getId()->getValue(),
                    'username' => $s['partner']->getUsername(),
                ],
                'lastMessage'  => $s['lastMessage']->toArray(),
                'unreadCount'  => $s['unreadCount'],
            ],
            $summaries,
        ));
    }

    /**
     * Messages échangés avec un utilisateur spécifique.
     * Marque automatiquement les messages reçus comme lus.
     */
    #[Route('/conversation/{partnerId}', name: 'conversation', methods: ['GET'])]
    public function conversation(
        string $partnerId,
        #[CurrentUser] SecurityUser $user,
    ): JsonResponse {
        try {
            $partnerUserId = UserId::fromString($partnerId);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $myId     = $user->getDomainUser()->getId();
        $messages = $this->messageRepository->findConversation($myId, $partnerUserId);

        // Marque les messages reçus comme lus
        $this->messageRepository->markConversationRead($myId, $partnerUserId);

        return $this->json(array_reverse(
            array_map(static fn ($m) => $m->toArray(), $messages),
        ));
    }

    /**
     * Envoie un message à un utilisateur.
     * Body: { recipient_id, content, document_id?, document_title? }
     */
    #[Route('', name: 'send', methods: ['POST'])]
    public function send(Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (! is_array($body)) {
            return $this->json(['error' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $recipientIdRaw = is_string($body['recipient_id'] ?? null) ? $body['recipient_id'] : '';
        $content        = is_string($body['content'] ?? null) ? trim($body['content']) : '';
        $documentId     = is_string($body['document_id'] ?? null) ? $body['document_id'] : null;
        $documentTitle  = is_string($body['document_title'] ?? null) ? $body['document_title'] : null;

        if ($content === '') {
            return $this->json(['error' => 'Le message ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $recipientId = UserId::fromString($recipientIdRaw);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de destinataire invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $message = ($this->sendMessageHandler)(new SendMessageCommand(
                senderId:      $user->getDomainUser()->getId(),
                recipientId:   $recipientId,
                content:       $content,
                documentId:    $documentId,
                documentTitle: $documentTitle,
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($message->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Nombre total de messages non lus (badge dans la sidebar).
     */
    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $count = $this->messageRepository->countUnreadFor(
            $user->getDomainUser()->getId(),
        );

        return $this->json(['count' => $count]);
    }
}
