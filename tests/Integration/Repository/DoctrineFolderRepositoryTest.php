<?php

declare(strict_types=1);

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Tests\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('DoctrineFolderRepository', function (): void {

    beforeEach(function (): void {
        $this->folderRepo = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->userRepo   = self::getContainer()->get(UserRepositoryInterface::class);

        $this->owner = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->userRepo->save($this->owner);
    });

    it('persiste et retrouve un dossier racine', function (): void {
        $root = Folder::createRoot('DRH', $this->owner);
        $this->folderRepo->save($root);
        $this->em->clear();

        $found = $this->folderRepo->findById($root->getId());

        expect($found)->not->toBeNull()
            ->and($found->getName())->toBe('DRH')
            ->and($found->isRoot())->toBeTrue();
    });

    it('retourne null pour un ID inconnu', function (): void {
        $id = \App\Domain\Folder\ValueObject\FolderId::generate();

        expect($this->folderRepo->findById($id))->toBeNull();
    });

    it('retrouve le dossier racine via findRoot()', function (): void {
        $root = Folder::createRoot('Racine', $this->owner);
        $this->folderRepo->save($root);
        $this->em->clear();

        $found = $this->folderRepo->findRoot();

        expect($found)->not->toBeNull()
            ->and($found->getName())->toBe('Racine');
    });

    it('retourne null quand il n\'y a pas de racine', function (): void {
        expect($this->folderRepo->findRoot())->toBeNull();
    });

    it('retrouve les enfants d\'un dossier', function (): void {
        $root  = Folder::createRoot('GED', $this->owner);
        $child = Folder::create('RH', $this->owner, $root);
        $this->folderRepo->save($root);
        $this->folderRepo->save($child);
        $this->em->clear();

        $parent   = $this->folderRepo->findById($root->getId());
        $children = $this->folderRepo->findChildren($parent);

        expect($children)->toHaveCount(1)
            ->and($children[0]->getName())->toBe('RH');
    });

    it('supprime un dossier feuille', function (): void {
        $folder = Folder::createRoot('A supprimer', $this->owner);
        $this->folderRepo->save($folder);
        $id = $folder->getId();

        $this->folderRepo->delete($folder);
        $this->em->clear();

        expect($this->folderRepo->findById($id))->toBeNull();
    });

    it('reconstruit le chemin complet d\'un sous-dossier', function (): void {
        $root  = Folder::createRoot('GED', $this->owner);
        $drh   = Folder::create('DRH', $this->owner, $root);
        $sub   = Folder::create('Contrats', $this->owner, $drh);

        $this->folderRepo->save($root);
        $this->folderRepo->save($drh);
        $this->folderRepo->save($sub);
        $this->em->clear();

        $found = $this->folderRepo->findById($sub->getId());

        expect($found->getFullPath())->toBe('GED / DRH / Contrats');
    });
});
