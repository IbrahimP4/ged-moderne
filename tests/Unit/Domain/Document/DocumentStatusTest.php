<?php

declare(strict_types=1);

use App\Domain\Document\ValueObject\DocumentStatus;

describe('DocumentStatus', function (): void {

    it('a les bons labels en français', function (): void {
        expect(DocumentStatus::DRAFT->label())->toBe('Brouillon');
        expect(DocumentStatus::APPROVED->label())->toBe('Approuvé');
        expect(DocumentStatus::PENDING_REVIEW->label())->toBe('En attente de validation');
    });

    it('DRAFT et REJECTED sont éditables', function (): void {
        expect(DocumentStatus::DRAFT->isEditable())->toBeTrue();
        expect(DocumentStatus::REJECTED->isEditable())->toBeTrue();
        expect(DocumentStatus::APPROVED->isEditable())->toBeFalse();
        expect(DocumentStatus::ARCHIVED->isEditable())->toBeFalse();
    });

    describe('transitions autorisées', function (): void {

        it('DRAFT peut passer à PENDING_REVIEW', function (): void {
            expect(DocumentStatus::DRAFT->canTransitionTo(DocumentStatus::PENDING_REVIEW))->toBeTrue();
        });

        it('DRAFT peut passer directement à APPROVED (approbation admin)', function (): void {
            expect(DocumentStatus::DRAFT->canTransitionTo(DocumentStatus::APPROVED))->toBeTrue();
        });

        it('DRAFT ne peut pas passer directement à ARCHIVED', function (): void {
            expect(DocumentStatus::DRAFT->canTransitionTo(DocumentStatus::ARCHIVED))->toBeFalse();
        });

        it('PENDING_REVIEW peut être APPROVED ou REJECTED', function (): void {
            expect(DocumentStatus::PENDING_REVIEW->canTransitionTo(DocumentStatus::APPROVED))->toBeTrue();
            expect(DocumentStatus::PENDING_REVIEW->canTransitionTo(DocumentStatus::REJECTED))->toBeTrue();
        });

        it('APPROVED peut être ARCHIVED ou OBSOLETE', function (): void {
            expect(DocumentStatus::APPROVED->canTransitionTo(DocumentStatus::ARCHIVED))->toBeTrue();
            expect(DocumentStatus::APPROVED->canTransitionTo(DocumentStatus::OBSOLETE))->toBeTrue();
        });

        it('ARCHIVED est un état terminal', function (): void {
            foreach (DocumentStatus::cases() as $target) {
                expect(DocumentStatus::ARCHIVED->canTransitionTo($target))->toBeFalse();
            }
        });

        it('REJECTED peut revenir en DRAFT pour correction', function (): void {
            expect(DocumentStatus::REJECTED->canTransitionTo(DocumentStatus::DRAFT))->toBeTrue();
        });
    });

    describe('migration depuis le legacy', function (): void {

        dataset('legacy_statuts', [
            'brouillon (-2)'       => [-2, DocumentStatus::DRAFT],
            'en attente (-1)'      => [-1, DocumentStatus::PENDING_REVIEW],
            'rejeté (0)'           => [0,  DocumentStatus::REJECTED],
            'approuvé (1)'         => [1,  DocumentStatus::APPROVED],
            'archivé (2)'          => [2,  DocumentStatus::ARCHIVED],
            'inconnu → brouillon'  => [99, DocumentStatus::DRAFT],
        ]);

        it('convertit les constantes entières SeedDMS', function (int $legacy, DocumentStatus $expected): void {
            expect(DocumentStatus::fromLegacyInt($legacy))->toBe($expected);
        })->with('legacy_statuts');
    });
});
