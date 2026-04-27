<?php

declare(strict_types=1);

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

uses(FunctionalTestCase::class);

describe('PATCH /api/documents/{id}/tags', function (): void {

    beforeEach(function (): void {
        $this->userRepo     = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo   = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->documentRepo = self::getContainer()->get(DocumentRepositoryInterface::class);

        $this->owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);

        $this->userRepo->save($this->owner);
        $this->folderRepo->save($this->folder);

        $this->document = Document::upload(
            title: 'Contrat test',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(1024),
            originalFilename: 'contrat.pdf',
            storagePath: StoragePath::fromString('documents/contrat.pdf'),
        );
        $this->documentRepo->save($this->document);
        $this->em->clear();
    });

    it('met à jour les tags avec succès', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => ['contrat', 'rh', 'urgent']], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

        $this->em->clear();
        $doc = $this->documentRepo->findById($this->document->getId());
        expect($doc?->getTags())->toBe(['contrat', 'rh', 'urgent']);
    });

    it('accepte un tableau vide (supprime tous les tags)', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => []], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);
    });

    it('retourne 422 si un tag dépasse 50 caractères', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => [str_repeat('x', 51)]], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('retourne 400 si le champ "tags" est absent', function (): void {
        $this->loginAs($this->owner);
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['other' => 'data'], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
    });

    it('retourne 401 sans authentification', function (): void {
        $this->client->request(
            'PATCH',
            '/api/documents/' . $this->document->getId()->getValue() . '/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['tags' => ['test']], JSON_THROW_ON_ERROR),
        );

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });
});

describe('GET /api/search', function (): void {

    beforeEach(function (): void {
        $this->userRepo     = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo   = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->documentRepo = self::getContainer()->get(DocumentRepositoryInterface::class);

        $this->owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->folder = Folder::createRoot('DRH', $this->owner);
        $this->userRepo->save($this->owner);
        $this->folderRepo->save($this->folder);

        foreach (['Contrat CDI', 'Facture Juin', 'Contrat CDD'] as $title) {
            $doc = Document::upload(
                title: $title,
                folder: $this->folder,
                owner: $this->owner,
                mimeType: MimeType::fromString('application/pdf'),
                fileSize: FileSize::fromBytes(512),
                originalFilename: strtolower(str_replace(' ', '-', $title)) . '.pdf',
                storagePath: StoragePath::fromString('documents/' . uniqid() . '.pdf'),
            );
            $this->documentRepo->save($doc);
        }

        $this->em->clear();
    });

    it('retourne les résultats correspondant à la requête', function (): void {
        $this->loginAs($this->owner);
        $data = $this->jsonRequest('GET', '/api/search?q=contrat');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($data['total'])->toBe(2)
            ->and($data['results'])->toHaveCount(2);
    });

    it('retourne 0 résultats si aucun document ne correspond', function (): void {
        $this->loginAs($this->owner);
        $data = $this->jsonRequest('GET', '/api/search?q=inexistant');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($data['total'])->toBe(0);
    });

    it('retourne un tableau vide si la requête est vide', function (): void {
        $this->loginAs($this->owner);
        $data = $this->jsonRequest('GET', '/api/search?q=');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($data['total'])->toBe(0);
    });

    it('retourne 401 sans authentification', function (): void {
        $this->client->request('GET', '/api/search?q=contrat');

        expect($this->client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });
});
