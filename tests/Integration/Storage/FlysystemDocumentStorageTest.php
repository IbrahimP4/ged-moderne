<?php

declare(strict_types=1);

use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Storage\Port\DocumentStorageInterface;
use App\Domain\Storage\ValueObject\StoragePath;
use Tests\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('FlysystemDocumentStorage', function (): void {

    beforeEach(function (): void {
        $this->storage = self::getContainer()->get(DocumentStorageInterface::class);
    });

    it('stocke et relit un fichier', function (): void {
        $contents = 'Contenu du fichier PDF de test';
        $mime     = MimeType::fromString('application/pdf');

        $path = $this->storage->store($contents, $mime, 'rapport.pdf');

        expect($path)->toBeInstanceOf(StoragePath::class)
            ->and($path->getValue())->toContain('.pdf');

        $stream = $this->storage->read($path);
        expect(is_resource($stream))->toBeTrue();

        fclose($stream);
    });

    it('génère un chemin unique à chaque stockage', function (): void {
        $mime = MimeType::fromString('application/pdf');

        $path1 = $this->storage->store('contenu 1', $mime, 'fichier.pdf');
        $path2 = $this->storage->store('contenu 2', $mime, 'fichier.pdf');

        expect($path1->getValue())->not->toBe($path2->getValue());
    });

    it('détecte l\'existence d\'un fichier stocké', function (): void {
        $mime = MimeType::fromString('image/png');
        $path = $this->storage->store('fake png data', $mime, 'image.png');

        expect($this->storage->exists($path))->toBeTrue();
    });

    it('retourne false pour un fichier inexistant', function (): void {
        $path = StoragePath::fromString('documents/2026/01/inexistant.pdf');

        expect($this->storage->exists($path))->toBeFalse();
    });

    it('supprime un fichier existant', function (): void {
        $mime = MimeType::fromString('application/pdf');
        $path = $this->storage->store('à supprimer', $mime, 'delete.pdf');

        expect($this->storage->exists($path))->toBeTrue();

        $this->storage->delete($path);

        expect($this->storage->exists($path))->toBeFalse();
    });

    it('force l\'extension canonique si l\'extension est suspecte', function (): void {
        $mime = MimeType::fromString('application/pdf');

        // Fichier PHP renommé en .pdf — l'extension originale ne compte pas
        $path = $this->storage->store('<?php system($_GET["cmd"]); ?>', $mime, 'evil.php');

        expect($path->getValue())->toEndWith('.pdf');
    });

    it('lève une exception si le fichier est introuvable en lecture', function (): void {
        $path = StoragePath::fromString('documents/2026/01/ghost.pdf');

        expect(fn () => $this->storage->read($path))->toThrow(\RuntimeException::class);
    });
});
