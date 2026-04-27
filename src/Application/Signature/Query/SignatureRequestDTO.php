<?php

declare(strict_types=1);

namespace App\Application\Signature\Query;

use App\Domain\Signature\Entity\SignatureRequest;

final readonly class SignatureRequestDTO
{
    public function __construct(
        public string  $id,
        public string  $status,
        public string  $statusLabel,
        public ?string $message,
        public ?string $comment,
        public string  $requestedAt,
        public ?string $resolvedAt,
        public string  $documentId,
        public string  $documentTitle,
        public string  $requesterId,
        public string  $requesterUsername,
        public string  $signerId,
        public string  $signerUsername,
    ) {}

    public static function fromEntity(SignatureRequest $req): self
    {
        return new self(
            id:                $req->getId()->getValue(),
            status:            $req->getStatus()->value,
            statusLabel:       $req->getStatus()->label(),
            message:           $req->getMessage(),
            comment:           $req->getComment(),
            requestedAt:       $req->getRequestedAt()->format(\DateTimeInterface::ATOM),
            resolvedAt:        $req->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            documentId:        $req->getDocument()->getId()->getValue(),
            documentTitle:     $req->getDocument()->getTitle(),
            requesterId:       $req->getRequester()->getId()->getValue(),
            requesterUsername: $req->getRequester()->getUsername(),
            signerId:          $req->getSigner()->getId()->getValue(),
            signerUsername:    $req->getSigner()->getUsername(),
        );
    }
}
