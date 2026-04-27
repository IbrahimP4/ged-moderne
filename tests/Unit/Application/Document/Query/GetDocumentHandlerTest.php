<?php

declare(strict_types=1);

use App\Application\Document\Query\DocumentDTO;
use App\Application\Document\Query\GetDocumentHandler;
use App\Application\Document\Query\GetDocumentQuery;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;

function makeDocumentForQuery(): Document
{
    $owner  = User::create('alice', 'alice@test.com', '$2y$fakehash');
    $folder = Folder::createRoot('DRH', $owner);

    return Document::upload(
        title: 'Contrat 2026',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromMegabytes(1.5),
        originalFilename: 'contrat.pdf',
        storagePath: StoragePath::fromString('documents/2026/04/uuid.pdf'),
    );
}

describe('GetDocumentHandler', function (): void {

    beforeEach(function (): void {
        $this->repo    = Mockery::mock(DocumentRepositoryInterface::class);
        $this->handler = new GetDocumentHandler($this->repo);
    });

    afterEach(fn () => Mockery::close());

    it('retourne un DocumentDTO quand le document existe', function (): void {
        $doc = makeDocumentForQuery();
        $id  = $doc->getId();

        $this->repo->shouldReceive('findById')->once()->with(
            Mockery::on(fn ($arg) => $arg->equals($id)),
        )->andReturn($doc);

        $dto = ($this->handler)(new GetDocumentQuery($id));

        expect($dto)->toBeInstanceOf(DocumentDTO::class);
        expect($dto->title)->toBe('Contrat 2026');
        expect($dto->status)->toBe('draft');
        expect($dto->statusLabel)->toBe('Brouillon');
        expect($dto->versionCount)->toBe(1);
        expect($dto->latestVersion)->not->toBeNull();
        expect($dto->latestVersion->versionNumber)->toBe(1);
        expect($dto->latestVersion->fileSizeHuman)->toContain('MB');
    });

    it('inclut toutes les versions si demandé', function (): void {
        $doc = makeDocumentForQuery();
        $id  = $doc->getId();

        $this->repo->shouldReceive('findById')->andReturn($doc);

        $dto = ($this->handler)(new GetDocumentQuery($id, withAllVersions: true));

        expect($dto->versions)->toHaveCount(1);
    });

    it('lève DocumentNotFoundException si le document n\'existe pas', function (): void {
        $this->repo->shouldReceive('findById')->andReturn(null);

        ($this->handler)(new GetDocumentQuery(DocumentId::generate()));
    })->throws(DocumentNotFoundException::class);
});
