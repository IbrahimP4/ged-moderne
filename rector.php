<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkipPath(__DIR__ . '/src/Infrastructure/Persistence/Migration')

    // ── Target PHP 8.4 ───────────────────────────────────────────────────────
    ->withPhpSets(php84: true)

    // ── Sets de règles activés ────────────────────────────────────────────────
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::INSTANCEOF,

        LevelSetList::UP_TO_PHP_84,

        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,

        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_ORM_214,

        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ])

    // ── Règles individuelles critiques ────────────────────────────────────────
    ->withRules([
        DeclareStrictTypesRector::class,
        TypedPropertyFromAssignsRector::class,
        AddReturnTypeDeclarationFromYieldTypeRector::class,
        ExplicitNullableParamTypeRector::class,
    ])

    // ── Import des namespaces automatique ─────────────────────────────────────
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false,
        removeUnusedImports: true,
    )

    // ── Cache ─────────────────────────────────────────────────────────────────
    ->withCache(
        cacheDirectory: __DIR__ . '/var/cache/rector',
    )

    // ── Parallel (accélère le traitement) ─────────────────────────────────────
    ->withParallel(
        timeoutSeconds: 120,
        maxNumberOfProcess: 4,
        jobSize: 16,
    );
