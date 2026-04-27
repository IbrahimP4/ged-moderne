<?php

declare(strict_types=1);

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

describe('FolderController', function (): void {

    beforeEach(function (): void {
        $this->userRepo   = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo = self::getContainer()->get(FolderRepositoryInterface::class);

        $this->owner = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->admin = User::create('admin', 'admin@example.com', '$2y$13$fakehash', isAdmin: true);
        $this->userRepo->save($this->owner);
        $this->userRepo->save($this->admin);
    });

    // ── GET /api/folders ──────────────────────────────────────────────────────

    describe('GET /api/folders', function (): void {

        it('retourne 404 si aucun dossier racine n\'existe', function (): void {
            $this->loginAs($this->owner);
            $this->client->request('GET', '/api/folders');

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
        });

        it('retourne le dossier racine', function (): void {
            $root = Folder::createRoot('GED', $this->owner);
            $this->folderRepo->save($root);
            $this->em->clear();

            $this->loginAs($this->owner);
            $data = $this->jsonRequest('GET', '/api/folders');

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
                ->and($data['folder']['name'])->toBe('GED');
        });

        it('retourne 401 sans authentification', function (): void {
            $this->client->request('GET', '/api/folders');

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        });
    });

    // ── GET /api/folders/{id} ─────────────────────────────────────────────────

    describe('GET /api/folders/{id}', function (): void {

        it('retourne le contenu d\'un dossier', function (): void {
            $root  = Folder::createRoot('GED', $this->owner);
            $child = Folder::create('RH', $this->owner, $root);
            $this->folderRepo->save($root);
            $this->folderRepo->save($child);
            $this->em->clear();

            $this->loginAs($this->owner);
            $data = $this->jsonRequest('GET', '/api/folders/' . $root->getId()->getValue());

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
                ->and($data['folder']['name'])->toBe('GED');
        });

        it('retourne 400 pour un UUID invalide', function (): void {
            $this->loginAs($this->owner);
            $this->client->request('GET', '/api/folders/not-a-uuid');

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
        });

        it('retourne 404 pour un dossier inexistant', function (): void {
            $this->loginAs($this->owner);
            $this->client->request('GET', '/api/folders/00000000-0000-0000-0000-000000000000');

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
        });
    });

    // ── POST /api/folders ─────────────────────────────────────────────────────

    describe('POST /api/folders', function (): void {

        it('crée un dossier racine avec succès', function (): void {
            $this->loginAs($this->owner);
            $data = $this->jsonRequest('POST', '/api/folders', ['name' => 'Archive']);

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
                ->and($data)->toHaveKey('id')
                ->and($data['message'])->toBe('Dossier créé avec succès.');
        });

        it('crée un sous-dossier avec parent_id', function (): void {
            $root = Folder::createRoot('GED', $this->owner);
            $this->folderRepo->save($root);
            $this->em->clear();

            $this->loginAs($this->owner);
            $data = $this->jsonRequest('POST', '/api/folders', [
                'name'      => 'Contrats',
                'parent_id' => $root->getId()->getValue(),
            ]);

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED);
        });

        it('retourne 422 si "name" est absent', function (): void {
            $this->loginAs($this->owner);
            $this->client->request(
                'POST',
                '/api/folders',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => '']),
            );

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('retourne 422 si "name" dépasse 255 caractères', function (): void {
            $this->loginAs($this->owner);
            $this->client->request(
                'POST',
                '/api/folders',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => str_repeat('x', 256)]),
            );

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('retourne 422 si "parent_id" n\'est pas un UUID valide', function (): void {
            $this->loginAs($this->owner);
            $this->client->request(
                'POST',
                '/api/folders',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => 'Test', 'parent_id' => 'invalid-uuid']),
            );

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('retourne 401 sans authentification', function (): void {
            $this->client->request(
                'POST',
                '/api/folders',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => 'Test']),
            );

            expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        });
    });
});
