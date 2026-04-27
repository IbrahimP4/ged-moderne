<?php

declare(strict_types=1);

use App\Application\Document\Command\UploadDocumentCommand;
use App\Application\Document\Command\UploadDocumentHandler;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Tests\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('Event dispatch — intégration end-to-end', function (): void {

    beforeEach(function (): void {
        $this->userRepo     = self::getContainer()->get(UserRepositoryInterface::class);
        $this->folderRepo   = self::getContainer()->get(FolderRepositoryInterface::class);
        $this->auditLogRepo = self::getContainer()->get(AuditLogRepositoryInterface::class);
        $this->handler      = self::getContainer()->get(UploadDocumentHandler::class);

        $this->owner  = User::create('alice', 'alice@example.com', '$2y$13$fakehash');
        $this->folder = Folder::createRoot('GED', $this->owner);

        $this->userRepo->save($this->owner);
        $this->folderRepo->save($this->folder);
        $this->em->clear();
    });

    it('crée une entrée d\'audit après un upload réussi', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ged_test_') . '.pdf';
        file_put_contents($tmpFile, '%PDF-1.4 test content');

        $command = new UploadDocumentCommand(
            folderId: $this->folder->getId(),
            uploadedBy: $this->owner->getId(),
            title: 'Rapport Q1',
            originalFilename: 'rapport.pdf',
            mimeType: MimeType::fromString('application/pdf')->getValue(),
            fileSizeBytes: filesize($tmpFile),
            tempFilePath: $tmpFile,
            comment: null,
        );

        $documentId = ($this->handler)($command);

        @unlink($tmpFile);

        $logs = $this->auditLogRepo->findByAggregate('Document', $documentId->getValue());

        // Au moins un log est créé, et le premier est toujours document.uploaded
        expect($logs)->not->toBeEmpty();

        $uploadLogs = array_values(array_filter(
            $logs,
            fn ($l) => $l->getEventName() === 'document.uploaded',
        ));

        expect($uploadLogs)->not->toBeEmpty()
            ->and($uploadLogs[0]->getAggregateId())->toBe($documentId->getValue())
            ->and($uploadLogs[0]->getActorId())->toBe($this->owner->getId()->getValue())
            ->and($uploadLogs[0]->getPayload()['title'])->toBe('Rapport Q1');
    });

    it('l\'audit log contient le mimeType correct', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ged_test_') . '.pdf';
        file_put_contents($tmpFile, '%PDF-1.4 test');

        $command = new UploadDocumentCommand(
            folderId: $this->folder->getId(),
            uploadedBy: $this->owner->getId(),
            title: 'Contrat',
            originalFilename: 'contrat.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: filesize($tmpFile),
            tempFilePath: $tmpFile,
        );

        $documentId = ($this->handler)($command);
        @unlink($tmpFile);

        $logs = $this->auditLogRepo->findByAggregate('Document', $documentId->getValue());

        expect($logs[0]->getPayload()['mimeType'])->toBe('application/pdf');
    });

    it('plusieurs uploads génèrent des logs distincts', function (): void {
        foreach (['Doc A', 'Doc B', 'Doc C'] as $title) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ged_test_') . '.pdf';
            file_put_contents($tmpFile, '%PDF-1.4');

            ($this->handler)(new UploadDocumentCommand(
                folderId: $this->folder->getId(),
                uploadedBy: $this->owner->getId(),
                title: $title,
                originalFilename: strtolower($title) . '.pdf',
                mimeType: 'application/pdf',
                fileSizeBytes: filesize($tmpFile),
                tempFilePath: $tmpFile,
            ));

            @unlink($tmpFile);
        }

        // 3 documents → 3 audit logs
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(\App\Domain\AuditLog\Entity\AuditLog::class, 'a')
            ->where("a.eventName = 'document.uploaded'");

        expect((int) $qb->getQuery()->getSingleScalarResult())->toBe(3);
    });
});
