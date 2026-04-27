<?php

declare(strict_types=1);

namespace App\Application\Signature\Command;

use App\Application\Notification\EventListener\NotifyOnSignatureRequested;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Signature\Entity\SignatureRequest;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class CreateSignatureRequestHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface        $documentRepository,
        private readonly UserRepositoryInterface            $userRepository,
        private readonly SignatureRequestRepositoryInterface $signatureRequestRepository,
        private readonly NotifyOnSignatureRequested         $notifyOnSignatureRequested,
    ) {}

    public function __invoke(CreateSignatureRequestCommand $command): SignatureRequest
    {
        $document = $this->documentRepository->findById($command->documentId);

        if ($document === null) {
            throw new DocumentNotFoundException($command->documentId);
        }

        $requester = $this->userRepository->findById($command->requesterId);

        if ($requester === null) {
            throw new \DomainException('Utilisateur demandeur introuvable.');
        }

        $signer = $this->userRepository->findById($command->signerId);

        if ($signer === null) {
            throw new \DomainException('Signataire introuvable.');
        }

        $signatureRequest = SignatureRequest::create(
            document:  $document,
            requester: $requester,
            signer:    $signer,
            message:   $command->message,
        );

        $this->signatureRequestRepository->save($signatureRequest);

        // Notifie le signataire en temps réel
        $this->notifyOnSignatureRequested->notifySigner(
            signerId:            $signer->getId(),
            requesterUsername:   $requester->getUsername(),
            documentTitle:       $document->getTitle(),
            documentId:          $document->getId()->getValue(),
            signatureRequestId:  $signatureRequest->getId()->getValue(),
        );

        return $signatureRequest;
    }
}
