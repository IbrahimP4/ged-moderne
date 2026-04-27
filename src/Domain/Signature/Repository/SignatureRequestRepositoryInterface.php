<?php

declare(strict_types=1);

namespace App\Domain\Signature\Repository;

use App\Domain\Signature\Entity\SignatureRequest;
use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\User\Entity\User;

interface SignatureRequestRepositoryInterface
{
    public function findById(SignatureRequestId $id): ?SignatureRequest;

    /**
     * Toutes les demandes destinées à un signataire donné.
     *
     * @return list<SignatureRequest>
     */
    public function findBySigner(User $signer): array;

    /**
     * Demandes en attente destinées à un signataire donné.
     *
     * @return list<SignatureRequest>
     */
    public function findPendingBySigner(User $signer): array;

    /**
     * Demandes créées par un utilisateur donné.
     *
     * @return list<SignatureRequest>
     */
    public function findByRequester(User $requester): array;

    /**
     * Toutes les demandes (vue admin).
     *
     * @return list<SignatureRequest>
     */
    public function findAll(): array;

    /** Nombre de demandes en attente pour un signataire donné (badge). */
    public function countPendingBySigner(User $signer): int;

    public function save(SignatureRequest $request): void;
}
