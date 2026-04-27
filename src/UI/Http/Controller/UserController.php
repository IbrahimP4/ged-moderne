<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints publics (pour les utilisateurs connectés) sur les profils utilisateurs.
 * Utilisé notamment par la messagerie pour lister les interlocuteurs possibles.
 */
#[Route('/api/users', name: 'api_users_')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Liste tous les utilisateurs actifs (id + username uniquement).
     * Exclu l'utilisateur connecté de la liste.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $myId  = $user->getDomainUser()->getId()->getValue();
        $users = $this->userRepository->findAll();

        $result = [];
        foreach ($users as $u) {
            if ($u->getId()->getValue() === $myId) {
                continue; // exclut soi-même
            }
            $result[] = [
                'id'       => $u->getId()->getValue(),
                'username' => $u->getUsername(),
                'isAdmin'  => $u->isAdmin(),
            ];
        }

        return $this->json($result);
    }
}
