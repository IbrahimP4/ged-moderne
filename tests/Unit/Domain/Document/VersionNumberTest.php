<?php

declare(strict_types=1);

use App\Domain\Document\ValueObject\VersionNumber;

describe('VersionNumber', function (): void {

    it('crée la version 1 avec first()', function (): void {
        $version = VersionNumber::first();

        expect($version->getValue())->toBe(1);
        expect($version->isFirst())->toBeTrue();
    });

    it('incrémente correctement', function (): void {
        $v1 = VersionNumber::first();
        $v2 = $v1->next();
        $v3 = $v2->next();

        expect($v2->getValue())->toBe(2);
        expect($v3->getValue())->toBe(3);
        expect($v2->isFirst())->toBeFalse();
    });

    it('est immuable : next() retourne une nouvelle instance', function (): void {
        $v1 = VersionNumber::first();
        $v2 = $v1->next();

        expect($v1->getValue())->toBe(1); // v1 inchangée
        expect($v2->getValue())->toBe(2);
    });

    it('lève une exception si valeur < 1', function (): void {
        VersionNumber::fromInt(0);
    })->throws(InvalidArgumentException::class, '>= 1');

    it('compare les versions', function (): void {
        $v1 = VersionNumber::fromInt(1);
        $v5 = VersionNumber::fromInt(5);

        expect($v5->isGreaterThan($v1))->toBeTrue();
        expect($v1->isGreaterThan($v5))->toBeFalse();
    });

    it('vérifie l\'égalité', function (): void {
        expect(VersionNumber::fromInt(3)->equals(VersionNumber::fromInt(3)))->toBeTrue();
        expect(VersionNumber::fromInt(3)->equals(VersionNumber::fromInt(4)))->toBeFalse();
    });
});
