<?php

declare(strict_types=1);

use App\Domain\Document\ValueObject\MimeType;

describe('MimeType', function (): void {

    it('crée un type MIME valide depuis une string', function (): void {
        $mime = MimeType::fromString('application/pdf');

        expect($mime->getValue())->toBe('application/pdf');
    });

    it('normalise en minuscules', function (): void {
        $mime = MimeType::fromString('Application/PDF');

        expect($mime->getValue())->toBe('application/pdf');
    });

    it('lève une exception pour un format invalide', function (): void {
        MimeType::fromString('pas-un-mime-type');
    })->throws(InvalidArgumentException::class, 'Format MIME invalide');

    it('lève une exception pour un type non autorisé', function (): void {
        MimeType::fromString('application/x-php');
    })->throws(InvalidArgumentException::class, 'Type MIME non autorisé');

    it('détecte les images', function (): void {
        expect(MimeType::fromString('image/png')->isImage())->toBeTrue();
        expect(MimeType::fromString('application/pdf')->isImage())->toBeFalse();
    });

    it('détecte les PDFs', function (): void {
        expect(MimeType::fromString('application/pdf')->isPdf())->toBeTrue();
        expect(MimeType::fromString('image/jpeg')->isPdf())->toBeFalse();
    });

    it('détecte les documents Office', function (): void {
        $docx = MimeType::fromString('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        expect($docx->isOfficeDocument())->toBeTrue();
    });

    dataset('extensions_valides', [
        'PDF'  => ['document.pdf',  'application/pdf'],
        'DOCX' => ['rapport.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'XLSX' => ['data.xlsx',    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'PNG'  => ['image.png',    'image/png'],
        'JPEG' => ['photo.jpeg',   'image/jpeg'],
    ]);

    it('crée depuis un nom de fichier', function (string $filename, string $expectedMime): void {
        $mime = MimeType::fromFilename($filename);
        expect($mime->getValue())->toBe($expectedMime);
    })->with('extensions_valides');

    it('lève une exception pour une extension inconnue', function (): void {
        MimeType::fromFilename('script.php');
    })->throws(InvalidArgumentException::class, 'Extension non supportée');
});
