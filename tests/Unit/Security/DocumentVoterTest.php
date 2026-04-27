<?php

declare(strict_types=1);

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Storage\ValueObject\StoragePath;
use App\Domain\User\Entity\User;
use App\Infrastructure\Security\SecurityUser;
use App\UI\Http\Security\DocumentVoter;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeSecurityToken(User $user): UsernamePasswordToken
{
    return new UsernamePasswordToken(new SecurityUser($user), 'main', (new SecurityUser($user))->getRoles());
}

function makeDoc(User $owner): Document
{
    $folder = Folder::createRoot('GED', $owner);

    return Document::upload(
        title: 'Test Document',
        folder: $folder,
        owner: $owner,
        mimeType: MimeType::fromString('application/pdf'),
        fileSize: FileSize::fromBytes(1024),
        originalFilename: 'test.pdf',
        storagePath: StoragePath::fromString('documents/2026/04/test.pdf'),
    );
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('DocumentVoter', function (): void {

    beforeEach(function (): void {
        $this->voter = new DocumentVoter();
        $this->owner = User::create('alice', 'alice@test.com', '$2y$fakehash');
        $this->other = User::create('bob', 'bob@test.com', '$2y$fakehash');
        $this->admin = User::create('admin', 'admin@test.com', '$2y$fakehash', isAdmin: true);
    });

    // ── VIEW ──────────────────────────────────────────────────────────────────

    describe(DocumentVoter::VIEW, function (): void {

        it('le propriétaire peut voir son document DRAFT', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->owner);

            expect($this->voter->vote($token, $doc, [DocumentVoter::VIEW]))->toBe(1);
        });

        it('un autre utilisateur NE PEUT PAS voir un document DRAFT', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->other);

            expect($this->voter->vote($token, $doc, [DocumentVoter::VIEW]))->toBe(-1);
        });

        it('un autre utilisateur PEUT voir un document APPROVED', function (): void {
            $doc   = makeDoc($this->owner);
            $doc->submitForReview($this->owner);
            $doc->approve($this->admin);

            $token = makeSecurityToken($this->other);

            expect($this->voter->vote($token, $doc, [DocumentVoter::VIEW]))->toBe(1);
        });

        it('l\'admin peut voir n\'importe quel document', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->admin);

            expect($this->voter->vote($token, $doc, [DocumentVoter::VIEW]))->toBe(1);
        });
    });

    // ── EDIT ──────────────────────────────────────────────────────────────────

    describe(DocumentVoter::EDIT, function (): void {

        it('le propriétaire peut éditer un document DRAFT', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->owner);

            expect($this->voter->vote($token, $doc, [DocumentVoter::EDIT]))->toBe(1);
        });

        it('un autre utilisateur NE PEUT PAS éditer', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->other);

            expect($this->voter->vote($token, $doc, [DocumentVoter::EDIT]))->toBe(-1);
        });

        it('NE PEUT PAS éditer un document PENDING_REVIEW même propriétaire', function (): void {
            $doc = makeDoc($this->owner);
            $doc->submitForReview($this->owner);

            $token = makeSecurityToken($this->owner);

            expect($this->voter->vote($token, $doc, [DocumentVoter::EDIT]))->toBe(-1);
        });

        it('l\'admin peut éditer un document DRAFT d\'un autre utilisateur', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->admin);

            expect($this->voter->vote($token, $doc, [DocumentVoter::EDIT]))->toBe(1);
        });
    });

    // ── DELETE ────────────────────────────────────────────────────────────────

    describe(DocumentVoter::DELETE, function (): void {

        it('le propriétaire peut supprimer son document DRAFT', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->owner);

            expect($this->voter->vote($token, $doc, [DocumentVoter::DELETE]))->toBe(1);
        });

        it('un autre utilisateur NE PEUT PAS supprimer', function (): void {
            $doc   = makeDoc($this->owner);
            $token = makeSecurityToken($this->other);

            expect($this->voter->vote($token, $doc, [DocumentVoter::DELETE]))->toBe(-1);
        });

        it('le propriétaire NE PEUT PAS supprimer un document APPROVED', function (): void {
            $doc = makeDoc($this->owner);
            $doc->submitForReview($this->owner);
            $doc->approve($this->admin);

            $token = makeSecurityToken($this->owner);

            expect($this->voter->vote($token, $doc, [DocumentVoter::DELETE]))->toBe(-1);
        });

        it('l\'admin PEUT supprimer un document APPROVED', function (): void {
            $doc = makeDoc($this->owner);
            $doc->submitForReview($this->owner);
            $doc->approve($this->admin);

            $token = makeSecurityToken($this->admin);

            expect($this->voter->vote($token, $doc, [DocumentVoter::DELETE]))->toBe(1);
        });
    });

    // ── APPROVE ───────────────────────────────────────────────────────────────

    describe(DocumentVoter::APPROVE, function (): void {

        it('seul l\'admin peut approuver', function (): void {
            $doc        = makeDoc($this->owner);
            $adminToken = makeSecurityToken($this->admin);
            $userToken  = makeSecurityToken($this->owner);

            expect($this->voter->vote($adminToken, $doc, [DocumentVoter::APPROVE]))->toBe(1);
            expect($this->voter->vote($userToken, $doc, [DocumentVoter::APPROVE]))->toBe(-1);
        });
    });
});
