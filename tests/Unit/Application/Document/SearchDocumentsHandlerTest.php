<?php

declare(strict_types=1);

use App\Application\Document\Query\SearchDocumentsHandler;
use App\Application\Document\Query\SearchDocumentsQuery;
use App\Application\Document\Query\SearchResultDTO;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;

describe('SearchDocumentsHandler', function (): void {

    beforeEach(function (): void {
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->folderRepo   = Mockery::mock(FolderRepositoryInterface::class);

        $this->handler = new SearchDocumentsHandler(
            documentRepository: $this->documentRepo,
            folderRepository: $this->folderRepo,
        );

        $this->owner    = User::create('alice', 'alice@ged.test', '$2y$10$fakehash');
        $this->folder   = Folder::createRoot('DRH', $this->owner);

        $this->document = Document::upload(
            title: 'Contrat 2024',
            folder: $this->folder,
            owner: $this->owner,
            mimeType: MimeType::fromString('application/pdf'),
            fileSize: FileSize::fromBytes(2048),
            originalFilename: 'contrat-2024.pdf',
            storagePath: StoragePath::fromString('documents/contrat-2024.pdf'),
        );
        $this->document->releaseEvents();
    });

    afterEach(fn () => Mockery::close());

    it('retourne une liste de SearchResultDTO pour une requête non vide', function (): void {
        // search() retourne maintenant des tableaux enrichis
        $enrichedResult = [
            'document'         => $this->document,
            'snippet'          => null,
            'matchedInContent' => false,
        ];

        $this->documentRepo
            ->shouldReceive('search')
            ->once()
            ->with('contrat', null, 50, null, [])
            ->andReturn([$enrichedResult]);

        $results = ($this->handler)(new SearchDocumentsQuery(q: 'contrat'));

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(SearchResultDTO::class)
            ->and($results[0]->title)->toBe('Contrat 2024')
            ->and($results[0]->snippet)->toBeNull()
            ->and($results[0]->matchedInContent)->toBeFalse();
    });

    it('inclut le snippet quand le match vient du contenu', function (): void {
        $enrichedResult = [
            'document'         => $this->document,
            'snippet'          => '…le contrat stipule que les parties…',
            'matchedInContent' => true,
        ];

        $this->documentRepo
            ->shouldReceive('search')
            ->once()
            ->andReturn([$enrichedResult]);

        $results = ($this->handler)(new SearchDocumentsQuery(q: 'contrat'));

        expect($results[0]->snippet)->toBe('…le contrat stipule que les parties…')
            ->and($results[0]->matchedInContent)->toBeTrue();
    });

    it('retourne un tableau vide si la requête est vide', function (): void {
        $this->documentRepo->shouldReceive('search')->never();

        $results = ($this->handler)(new SearchDocumentsQuery(q: '   '));

        expect($results)->toBe([]);
    });

    it('retourne un tableau vide si le folderId est un UUID invalide', function (): void {
        $this->documentRepo->shouldReceive('search')->never();

        $results = ($this->handler)(new SearchDocumentsQuery(q: 'test', folderId: 'not-a-uuid'));

        expect($results)->toBe([]);
    });
});

