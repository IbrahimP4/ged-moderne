<?php

declare(strict_types=1);

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

describe('UserAdminController', function (): void {

    beforeEach(function (): void {
        $this->userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->admin = User::create('admin', 'admin@example.com', 'pending', isAdmin: true);
        $this->admin->changePassword(
            $this->hasher->hashPassword(new SecurityUser($this->admin), 'admin1234'),
        );

        $this->user = User::create('alice', 'alice@example.com', 'pending');
        $this->user->changePassword(
            $this->hasher->hashPassword(new SecurityUser($this->user), 'alice1234'),
        );

        $this->userRepo->save($this->admin);
        $this->userRepo->save($this->user);
    });

    it('liste les utilisateurs pour un admin', function (): void {
        $this->loginAs($this->admin);
        $data = $this->jsonRequest('GET', '/api/admin/users');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($data)->toHaveCount(2)
            ->and($data[0])->toHaveKeys(['id', 'username', 'email', 'isAdmin']);
    });

    it('crée un utilisateur pour un admin', function (): void {
        $this->loginAs($this->admin);
        $data = $this->jsonRequest('POST', '/api/admin/users', [
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => 'Bob12345',
            'is_admin' => false,
        ]);

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
            ->and($data)->toHaveKey('id')
            ->and($this->userRepo->findByUsername('bob'))->not->toBeNull();
    });

    it('retourne 403 si un utilisateur simple tente d accéder à la liste', function (): void {
        $this->loginAs($this->user);
        $this->client->request('GET', '/api/admin/users');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    it('retourne 422 si le username existe déjà', function (): void {
        $this->loginAs($this->admin);
        $this->client->request(
            'POST',
            '/api/admin/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'alice',
                'email' => 'alice-2@example.com',
                'password' => 'alice1234',
                'is_admin' => false,
            ], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });
});
