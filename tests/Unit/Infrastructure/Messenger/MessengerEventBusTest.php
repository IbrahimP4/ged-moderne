<?php

declare(strict_types=1);

use App\Domain\Document\Event\DocumentUploaded;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Folder\ValueObject\FolderId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Messenger\MessengerEventBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

describe('MessengerEventBus', function (): void {

    afterEach(fn () => Mockery::close());

    it('dispatche chaque event sur le MessageBus Symfony', function (): void {
        $bus = Mockery::mock(MessageBusInterface::class);
        $eventBus = new MessengerEventBus($bus);

        $event1 = new DocumentUploaded(
            documentId: DocumentId::generate(),
            folderId: FolderId::generate(),
            uploadedBy: UserId::generate(),
            title: 'Doc A',
            mimeType: 'application/pdf',
            occurredAt: new \DateTimeImmutable(),
        );

        $event2 = new DocumentUploaded(
            documentId: DocumentId::generate(),
            folderId: FolderId::generate(),
            uploadedBy: UserId::generate(),
            title: 'Doc B',
            mimeType: 'image/png',
            occurredAt: new \DateTimeImmutable(),
        );

        $bus->shouldReceive('dispatch')
            ->twice()
            ->andReturn(new Envelope(new \stdClass()));

        $eventBus->dispatch($event1, $event2);
    });

    it('ne dispatche rien si aucun event', function (): void {
        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')->never();

        $eventBus = new MessengerEventBus($bus);
        $eventBus->dispatch();
    });
});
