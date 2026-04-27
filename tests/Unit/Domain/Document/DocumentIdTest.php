<?php

declare(strict_types=1);

use App\Domain\Document\ValueObject\DocumentId;

describe('DocumentId', function (): void {

    it('génère un UUID v7 valide', function (): void {
        $id = DocumentId::generate();

        expect($id->getValue())
            ->toBeString()
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('crée depuis une string UUID valide', function (): void {
        $uuid = '018e4c6a-1b2c-7d3e-8f4a-5b6c7d8e9f0a';
        $id   = DocumentId::fromString($uuid);

        expect($id->getValue())->toBe($uuid);
    });

    it('lève une exception pour un UUID invalide', function (): void {
        DocumentId::fromString('pas-un-uuid');
    })->throws(InvalidArgumentException::class, 'DocumentId invalide');

    it('deux IDs générés sont toujours différents', function (): void {
        $id1 = DocumentId::generate();
        $id2 = DocumentId::generate();

        expect($id1->equals($id2))->toBeFalse();
    });

    it('deux IDs depuis la même string sont égaux', function (): void {
        $uuid = '018e4c6a-1b2c-7d3e-8f4a-5b6c7d8e9f0a';

        expect(DocumentId::fromString($uuid)->equals(DocumentId::fromString($uuid)))->toBeTrue();
    });

    it('convertit un legacy int de façon déterministe', function (): void {
        $id1 = DocumentId::fromLegacyInt(42);
        $id2 = DocumentId::fromLegacyInt(42);
        $id3 = DocumentId::fromLegacyInt(43);

        expect($id1->equals($id2))->toBeTrue();
        expect($id1->equals($id3))->toBeFalse();
    });

    it('se cast en string', function (): void {
        $uuid = '018e4c6a-1b2c-7d3e-8f4a-5b6c7d8e9f0a';
        $id   = DocumentId::fromString($uuid);

        expect((string) $id)->toBe($uuid);
    });
});
