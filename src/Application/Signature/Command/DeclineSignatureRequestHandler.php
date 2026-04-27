<?php

declare(strict_types=1);

namespace App\Application\Signature\Command;

use App\Domain\Signature\Exception\SignatureRequestNotFoundException;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final class DeclineSignatureRequestHandler
{
    public function __construct(
        private readonly SignatureRequestRepositoryInterface $signatureRequestRepository,
        private readonly UserRepositoryInterface            $userRepository,
    ) {}

    public function __invoke(DeclineSignatureRequestCommand $command): void
    {
        $request = $this->signatureRequestRepository->findById($command->signatureRequestId);

        if ($request === null) {
            throw new SignatureRequestNotFoundException($command->signatureRequestId);
        }

        $user = $this->userRepository->findById($command->declinedBy);

        if ($user === null) {
            throw new \DomainException('Utilisateur introuvable.');
        }

        $request->decline($user, $command->comment);

        $this->signatureRequestRepository->save($request);
    }
}
