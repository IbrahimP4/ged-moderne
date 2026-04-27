<?php

declare(strict_types=1);

use App\Application\Folder\Command\CreateFolderCommand;
use App\Application\Folder\Command\CreateFolderHandler;
use App\Application\Folder\Command\RenameFolderCommand;
use App\Application\Folder\Command\RenameFolderHandler;
use App\Application\Folder\Command\DeleteFolderCommand;
use App\Application\Folder\Command\DeleteFolderHandler;
use App\Application\Folder\Command\SetFolderPermissionCommand;
use App\Application\Folder\Command\SetFolderPermissionHandler;
use App\Application\Folder\Command\SetFolderRestrictedCommand;
use App\Application\Folder\Command\SetFolderRestrictedHandler;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Entity\FolderPermission;
use App\Domain\Folder\Exception\FolderNotFoundException;
use App\Domain\Folder\Repository\FolderPermissionRepositoryInterface;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\Folder\ValueObject\PermissionLevel;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeFolderUser(string $name = 'alice', bool $admin = false): User
{
    return User::create(
        username: $name,
        email: $name . '@ged.test',
        hashedPassword: '$2y$10$fakehash',
        isAdmin: $admin,
    );
}

function makeRootFolder(User $owner, string $name = 'DRH'): Folder
{
    return Folder::createRoot($name, $owner);
}

// ── Tests CreateFolderHandler ─────────────────────────────────────────────────

describe('CreateFolderHandler', function (): void {

    beforeEach(function (): void {
        $this->folderRepo = Mockery::mock(FolderRepositoryInterface::class);
        $this->userRepo   = Mockery::mock(UserRepositoryInterface::class);
        $this->handler    = new CreateFolderHandler($this->folderRepo, $this->userRepo);
        $this->owner      = makeFolderUser('alice');
    });

    afterEach(fn () => Mockery::close());

    it('crée un dossier racine et retourne son FolderId', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->folderRepo->shouldReceive('save')->once();

        $result = ($this->handler)(new CreateFolderCommand(
            name:           'Finances',
            createdBy:      $this->owner->getId(),
            parentFolderId: null,
            comment:        null,
        ));

        expect($result)->toBeInstanceOf(FolderId::class);
    });

    it('crée un sous-dossier avec un parent valide', function (): void {
        $parent = makeRootFolder($this->owner, 'Parent');

        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($parent);
        $this->folderRepo->shouldReceive('save')->once();

        $result = ($this->handler)(new CreateFolderCommand(
            name:           'Sous-dossier',
            createdBy:      $this->owner->getId(),
            parentFolderId: $parent->getId(),
        ));

        expect($result)->toBeInstanceOf(FolderId::class);
    });

    it('lève DomainException si l\'utilisateur est introuvable', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new CreateFolderCommand(
            name:      'Test',
            createdBy: UserId::generate(),
        ));
    })->throws(\DomainException::class, 'introuvable');

    it('lève FolderNotFoundException si le dossier parent est introuvable', function (): void {
        $this->userRepo->shouldReceive('findById')->once()->andReturn($this->owner);
        $this->folderRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new CreateFolderCommand(
            name:           'Orphelin',
            createdBy:      $this->owner->getId(),
            parentFolderId: FolderId::generate(),
        ));
    })->throws(FolderNotFoundException::class);
});

// ── Tests RenameFolderHandler ─────────────────────────────────────────────────

describe('RenameFolderHandler', function (): void {

    beforeEach(function (): void {
        $this->folderRepo = Mockery::mock(FolderRepositoryInterface::class);
        $this->handler    = new RenameFolderHandler($this->folderRepo);
        $this->owner      = makeFolderUser('bob');
        $this->folder     = makeRootFolder($this->owner, 'Ancien nom');
    });

    afterEach(fn () => Mockery::close());

    it('renomme un dossier existant', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->folderRepo->shouldReceive('save')->once();

        ($this->handler)(new RenameFolderCommand(
            folderId:  $this->folder->getId(),
            renamedBy: $this->owner->getId(),
            newName:   'Nouveau nom',
        ));

        expect($this->folder->getName())->toBe('Nouveau nom');
    });

    it('lève FolderNotFoundException si le dossier est introuvable', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new RenameFolderCommand(
            folderId:  FolderId::generate(),
            renamedBy: $this->owner->getId(),
            newName:   'Test',
        ));
    })->throws(FolderNotFoundException::class);

    it('lève DomainException si le nouveau nom est vide', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);

        ($this->handler)(new RenameFolderCommand(
            folderId:  $this->folder->getId(),
            renamedBy: $this->owner->getId(),
            newName:   '   ',
        ));
    })->throws(\DomainException::class, 'vide');

    it('lève DomainException si le nom dépasse 255 caractères', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);

        ($this->handler)(new RenameFolderCommand(
            folderId:  $this->folder->getId(),
            renamedBy: $this->owner->getId(),
            newName:   str_repeat('a', 256),
        ));
    })->throws(\DomainException::class, '255');
});

// ── Tests DeleteFolderHandler ─────────────────────────────────────────────────

describe('DeleteFolderHandler', function (): void {

    beforeEach(function (): void {
        $this->folderRepo  = Mockery::mock(FolderRepositoryInterface::class);
        $this->documentRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $this->handler     = new DeleteFolderHandler($this->folderRepo, $this->documentRepo);
        $this->owner       = makeFolderUser('carol');
        // Un dossier enfant (non-root) pour permettre la suppression
        $this->parent      = makeRootFolder($this->owner, 'Parent');
        $this->folder      = Folder::create('Enfant', $this->owner, $this->parent);
    });

    afterEach(fn () => Mockery::close());

    it('supprime un dossier vide non-racine', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->folderRepo->shouldReceive('findChildren')->once()->andReturn([]);
        $this->documentRepo->shouldReceive('findByFolder')->once()->andReturn([]);
        $this->folderRepo->shouldReceive('delete')->once();

        ($this->handler)(new DeleteFolderCommand(
            folderId:  $this->folder->getId(),
            deletedBy: $this->owner->getId(),
        ));
    });

    it('lève FolderNotFoundException si le dossier est introuvable', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new DeleteFolderCommand(
            folderId:  FolderId::generate(),
            deletedBy: $this->owner->getId(),
        ));
    })->throws(FolderNotFoundException::class);

    it('lève DomainException si on tente de supprimer un dossier racine', function (): void {
        $root = makeRootFolder($this->owner, 'Racine');
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($root);

        ($this->handler)(new DeleteFolderCommand(
            folderId:  $root->getId(),
            deletedBy: $this->owner->getId(),
        ));
    })->throws(\DomainException::class, 'racine');

    it('lève DomainException si le dossier contient des sous-dossiers', function (): void {
        $child = Folder::create('Enfant2', $this->owner, $this->folder);
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->folderRepo->shouldReceive('findChildren')->once()->andReturn([$child]);
        // Handler vérifie documents aussi (count($children) > 0 || count($documents) > 0)
        $this->documentRepo->shouldReceive('findByFolder')->once()->andReturn([]);

        ($this->handler)(new DeleteFolderCommand(
            folderId:  $this->folder->getId(),
            deletedBy: $this->owner->getId(),
        ));
    })->throws(\DomainException::class, 'vide');
});

// ── Tests SetFolderPermissionHandler ─────────────────────────────────────────

describe('SetFolderPermissionHandler', function (): void {

    beforeEach(function (): void {
        $this->folderRepo = Mockery::mock(FolderRepositoryInterface::class);
        $this->permRepo   = Mockery::mock(FolderPermissionRepositoryInterface::class);
        $this->userRepo   = Mockery::mock(UserRepositoryInterface::class);

        $this->handler = new SetFolderPermissionHandler(
            $this->folderRepo,
            $this->permRepo,
            $this->userRepo,
        );

        $this->admin  = makeFolderUser('admin', admin: true);
        $this->target = makeFolderUser('david');
        $this->folder = makeRootFolder($this->admin, 'Restreint');
    });

    afterEach(fn () => Mockery::close());

    it('crée une nouvelle permission si elle n\'existe pas encore', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->target, $this->admin);
        $this->permRepo->shouldReceive('findByFolderAndUser')->once()->andReturn(null);
        $this->permRepo->shouldReceive('save')->once();

        ($this->handler)(new SetFolderPermissionCommand(
            folderId:     $this->folder->getId(),
            targetUserId: $this->target->getId(),
            level:        PermissionLevel::READ,
            grantedBy:    $this->admin->getId(),
        ));
    });

    it('met à jour une permission existante', function (): void {
        $existing = FolderPermission::grant(
            $this->folder,
            $this->target,
            PermissionLevel::READ,
            $this->admin,
        );

        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->userRepo->shouldReceive('findById')
            ->twice()
            ->andReturn($this->target, $this->admin);
        $this->permRepo->shouldReceive('findByFolderAndUser')->once()->andReturn($existing);
        $this->permRepo->shouldReceive('save')->once();

        ($this->handler)(new SetFolderPermissionCommand(
            folderId:     $this->folder->getId(),
            targetUserId: $this->target->getId(),
            level:        PermissionLevel::WRITE,
            grantedBy:    $this->admin->getId(),
        ));

        expect($existing->getLevel())->toBe(PermissionLevel::WRITE);
    });

    it('lève FolderNotFoundException si le dossier est introuvable', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new SetFolderPermissionCommand(
            folderId:     FolderId::generate(),
            targetUserId: $this->target->getId(),
            level:        PermissionLevel::READ,
            grantedBy:    $this->admin->getId(),
        ));
    })->throws(FolderNotFoundException::class);

    it('lève DomainException si l\'utilisateur cible est introuvable', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new SetFolderPermissionCommand(
            folderId:     $this->folder->getId(),
            targetUserId: UserId::generate(),
            level:        PermissionLevel::READ,
            grantedBy:    $this->admin->getId(),
        ));
    })->throws(\DomainException::class, 'cible');
});

// ── Tests SetFolderRestrictedHandler ─────────────────────────────────────────

describe('SetFolderRestrictedHandler', function (): void {

    beforeEach(function (): void {
        $this->folderRepo = Mockery::mock(FolderRepositoryInterface::class);
        $this->handler    = new SetFolderRestrictedHandler($this->folderRepo);
        $this->owner      = makeFolderUser('eve');
        $this->folder     = makeRootFolder($this->owner, 'Sensible');
    });

    afterEach(fn () => Mockery::close());

    it('passe un dossier en mode restreint', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->folderRepo->shouldReceive('save')->once();

        ($this->handler)(new SetFolderRestrictedCommand(
            folderId:   $this->folder->getId(),
            restricted: true,
        ));

        expect($this->folder->isRestricted())->toBeTrue();
    });

    it('retire le mode restreint', function (): void {
        $this->folder->setRestricted(true);

        $this->folderRepo->shouldReceive('findById')->once()->andReturn($this->folder);
        $this->folderRepo->shouldReceive('save')->once();

        ($this->handler)(new SetFolderRestrictedCommand(
            folderId:   $this->folder->getId(),
            restricted: false,
        ));

        expect($this->folder->isRestricted())->toBeFalse();
    });

    it('lève FolderNotFoundException si le dossier est introuvable', function (): void {
        $this->folderRepo->shouldReceive('findById')->once()->andReturn(null);

        ($this->handler)(new SetFolderRestrictedCommand(
            folderId:   FolderId::generate(),
            restricted: true,
        ));
    })->throws(FolderNotFoundException::class);
});
