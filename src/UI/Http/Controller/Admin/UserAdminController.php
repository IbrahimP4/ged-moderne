<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Admin;

use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Command\ChangeUserRoleHandler;
use App\Application\User\Command\CreateUserCommand;
use App\Application\User\Command\CreateUserHandler;
use App\Application\User\Command\DeleteUserCommand;
use App\Application\User\Command\DeleteUserHandler;
use App\Application\User\Query\ListUsersHandler;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly ListUsersHandler $listUsersHandler,
        private readonly CreateUserHandler $createUserHandler,
        private readonly ChangeUserRoleHandler $changeRoleHandler,
        private readonly DeleteUserHandler $deleteUserHandler,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(($this->listUsersHandler)());
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return $this->json(['error' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $username = trim(\is_string($body['username'] ?? null) ? $body['username'] : '');
        $email = trim(\is_string($body['email'] ?? null) ? $body['email'] : '');
        $password = \is_string($body['password'] ?? null) ? $body['password'] : '';
        $isAdmin = (bool) ($body['is_admin'] ?? false);

        if ($username === '' || strlen($username) < 3) {
            return $this->json(['error' => 'Le nom d\'utilisateur doit contenir au moins 3 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($username) > 100) {
            return $this->json(['error' => 'Le nom d\'utilisateur ne peut pas dépasser 100 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (preg_match('/^[a-zA-Z0-9_.-]+$/', $username) !== 1) {
            return $this->json(['error' => 'Le nom d\'utilisateur contient des caractères non autorisés.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json(['error' => 'Adresse email invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins une majuscule.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (preg_match('/[0-9]/', $password) !== 1) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins un chiffre.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $userId = ($this->createUserHandler)(new CreateUserCommand(
                username: $username,
                email: $email,
                plainPassword: $password,
                isAdmin: $isAdmin,
                createdBy: $user->getDomainUser()->getId(),
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(
            ['id' => $userId->getValue(), 'message' => 'Utilisateur créé avec succès.'],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/role', name: 'change_role', methods: ['PATCH'])]
    public function changeRole(string $id, Request $request, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || !isset($body['make_admin'])) {
            return $this->json(['error' => 'Le champ make_admin est requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            ($this->changeRoleHandler)(new ChangeUserRoleCommand(
                targetUserId: UserId::fromString($id),
                makeAdmin:    (bool) $body['make_admin'],
                changedBy:    $user->getDomainUser()->getId(),
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['message' => 'Rôle mis à jour avec succès.']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SecurityUser $user): JsonResponse
    {
        try {
            ($this->deleteUserHandler)(new DeleteUserCommand(
                targetUserId: UserId::fromString($id),
                deletedBy:    $user->getDomainUser()->getId(),
            ));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['message' => 'Utilisateur supprimé avec succès.']);
    }
}
