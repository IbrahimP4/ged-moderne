<?php

declare(strict_types=1);

use Pest\Arch\Options\ArchOptions;

// ── Tests unitaires : pure PHP, pas de Symfony Kernel ─────────────────────────
pest()
    ->in('tests/Unit');

// ── Tests d'intégration : SQLite + vrai EntityManager ────────────────────────
pest()
    ->extend(Tests\IntegrationTestCase::class)
    ->in('tests/Integration');

// ── Tests fonctionnels : WebTestCase + loginUser ──────────────────────────────
pest()
    ->extend(Tests\FunctionalTestCase::class)
    ->in('tests/Functional');

// ── Tests de caractérisation : standalone HTTP ────────────────────────────────
pest()
    ->in('tests/Characterization');

// ── Architecture Testing (règles du Domain) ───────────────────────────────────
arch()
    ->options(ArchOptions::create()->ignoreVendorNamespaces())
    ->preset()->php();

arch('Le Domain ne dépend d\'aucun framework')
    ->expect('App\Domain')
    ->not->toUse('Symfony')
    ->not->toUse('Doctrine')
    ->not->toUse('App\Infrastructure')
    ->not->toUse('App\UI');

arch('Les Value Objects sont readonly')
    ->expect('App\Domain\Document\ValueObject')
    ->toBeReadonly();

arch('Les Events du Domain sont readonly')
    ->expect('App\Domain\Document\Event')
    ->toBeReadonly()
    ->whenExists();

arch('Pas de exit() ni die() dans src/')
    ->expect('App')
    ->not->toUse('exit')
    ->not->toUse('die');

arch('Les Exceptions du Domain étendent DomainException')
    ->expect('App\Domain')
    ->classes()
    ->that->haveNameEndingWith('Exception')
    ->toExtend(\DomainException::class)
    ->whenExists();
