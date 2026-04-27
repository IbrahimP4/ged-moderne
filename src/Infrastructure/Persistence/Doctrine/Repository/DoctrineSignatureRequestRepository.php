<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Signature\Entity\SignatureRequest;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\Signature\ValueObject\SignatureRequestStatus;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSignatureRequestRepository implements SignatureRequestRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findById(SignatureRequestId $id): ?SignatureRequest
    {
        return $this->entityManager->find(SignatureRequest::class, $id);
    }

    /** @return list<SignatureRequest> */
    public function findBySigner(User $signer): array
    {
        /** @var list<SignatureRequest> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sr')
            ->from(SignatureRequest::class, 'sr')
            ->where('sr.signer = :signer')
            ->orderBy('sr.requestedAt', 'DESC')
            ->setParameter('signer', $signer)
            ->getQuery()
            ->getResult();
    }

    /** @return list<SignatureRequest> */
    public function findPendingBySigner(User $signer): array
    {
        /** @var list<SignatureRequest> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sr')
            ->from(SignatureRequest::class, 'sr')
            ->where('sr.signer = :signer')
            ->andWhere('sr.status = :status')
            ->orderBy('sr.requestedAt', 'ASC')
            ->setParameter('signer', $signer)
            ->setParameter('status', SignatureRequestStatus::PENDING)
            ->getQuery()
            ->getResult();
    }

    /** @return list<SignatureRequest> */
    public function findByRequester(User $requester): array
    {
        /** @var list<SignatureRequest> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sr')
            ->from(SignatureRequest::class, 'sr')
            ->where('sr.requester = :requester')
            ->orderBy('sr.requestedAt', 'DESC')
            ->setParameter('requester', $requester)
            ->getQuery()
            ->getResult();
    }

    /** @return list<SignatureRequest> */
    public function findAll(): array
    {
        /** @var list<SignatureRequest> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sr')
            ->from(SignatureRequest::class, 'sr')
            ->orderBy('sr.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingBySigner(User $signer): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(sr.id)')
            ->from(SignatureRequest::class, 'sr')
            ->where('sr.signer = :signer')
            ->andWhere('sr.status = :status')
            ->setParameter('signer', $signer)
            ->setParameter('status', SignatureRequestStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(SignatureRequest $request): void
    {
        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }
}
