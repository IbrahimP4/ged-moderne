<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Signature;

use App\Application\Signature\Command\CreateSignatureRequestCommand;
use App\Application\Signature\Command\CreateSignatureRequestHandler;
use App\Application\Signature\Command\DeclineSignatureRequestCommand;
use App\Application\Signature\Command\DeclineSignatureRequestHandler;
use App\Application\Signature\Command\SignDocumentCommand;
use App\Application\Signature\Command\SignDocumentHandler;
use App\Application\Signature\Query\SignatureRequestDTO;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Signature\Exception\SignatureRequestNotFoundException;
use App\Domain\Signature\Repository\SignatureRequestRepositoryInterface;
use App\Domain\Signature\ValueObject\SignatureRequestId;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/signature-requests', name: 'api_signature_requests_')]
#[IsGranted('ROLE_USER')]
final class SignatureController extends AbstractController
{
    public function __construct(
        private readonly SignatureRequestRepositoryInterface $signatureRequestRepository,
        private readonly UserRepositoryInterface            $userRepository,
        private readonly CreateSignatureRequestHandler      $createHandler,
        private readonly SignDocumentHandler                $signHandler,
        private readonly DeclineSignatureRequestHandler     $declineHandler,
    ) {}

    /**
     * Crée une demande de signature sur un document.
     * Body: { document_id, signer_id, message? }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[CurrentUser] SecurityUser $user,
        Request $request,
    ): JsonResponse {
        $body = json_decode($request->getContent(), true);

        $documentIdRaw = \is_array($body) && \is_string($body['document_id'] ?? null) ? $body['document_id'] : '';
        $signerIdRaw   = \is_array($body) && \is_string($body['signer_id'] ?? null) ? $body['signer_id'] : '';
        $message       = \is_array($body) && \is_string($body['message'] ?? null) ? trim($body['message']) : null;

        try {
            $documentId = DocumentId::fromString($documentIdRaw);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de document invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $signerId = UserId::fromString($signerIdRaw);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant de signataire invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $signatureRequest = ($this->createHandler)(new CreateSignatureRequestCommand(
                documentId:  $documentId,
                requesterId: $user->getDomainUser()->getId(),
                signerId:    $signerId,
                message:     $message !== '' ? $message : null,
            ));
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(
            SignatureRequestDTO::fromEntity($signatureRequest),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Liste les demandes de signature.
     * - Admin : toutes les demandes
     * - Utilisateur : ses demandes en tant que signataire + ses demandes envoyées
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $domainUser = $user->getDomainUser();

        if ($domainUser->isAdmin()) {
            $requests = $this->signatureRequestRepository->findAll();
        } else {
            $asSigner    = $this->signatureRequestRepository->findBySigner($domainUser);
            $asRequester = $this->signatureRequestRepository->findByRequester($domainUser);
            // Merge + dédup par id
            $seen     = [];
            $requests = [];
            foreach ([...$asSigner, ...$asRequester] as $req) {
                $key = $req->getId()->getValue();
                if (! isset($seen[$key])) {
                    $seen[$key]  = true;
                    $requests[]  = $req;
                }
            }
        }

        return $this->json(array_map(
            static fn ($r) => SignatureRequestDTO::fromEntity($r),
            $requests,
        ));
    }

    /**
     * Nombre de demandes en attente pour l'utilisateur connecté (badge).
     */
    #[Route('/pending-count', name: 'pending_count', methods: ['GET'])]
    public function pendingCount(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $count = $this->signatureRequestRepository->countPendingBySigner(
            $user->getDomainUser(),
        );

        return $this->json(['count' => $count]);
    }

    /**
     * Liste les admins disponibles comme signataires.
     */
    #[Route('/available-signers', name: 'available_signers', methods: ['GET'])]
    public function availableSigners(#[CurrentUser] SecurityUser $currentUser): JsonResponse
    {
        $allUsers = $this->userRepository->findAll();
        $signers  = array_values(array_filter(
            $allUsers,
            static fn ($u) => $u->isAdmin()
                && ! $u->getId()->equals($currentUser->getDomainUser()->getId()),
        ));

        return $this->json(array_map(
            static fn ($u) => [
                'id'       => $u->getId()->getValue(),
                'username' => $u->getUsername(),
            ],
            $signers,
        ));
    }

    /**
     * Signe une demande.
     * Body: { comment? }
     */
    #[Route('/{id}/sign', name: 'sign', methods: ['PATCH'])]
    public function sign(
        string $id,
        #[CurrentUser] SecurityUser $user,
        Request $request,
    ): JsonResponse {
        try {
            $requestId = SignatureRequestId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body    = json_decode($request->getContent(), true);
        $comment = \is_array($body) && \is_string($body['comment'] ?? null) ? trim($body['comment']) : null;

        try {
            ($this->signHandler)(new SignDocumentCommand(
                signatureRequestId: $requestId,
                signedBy:           $user->getDomainUser()->getId(),
                comment:            $comment !== '' ? $comment : null,
            ));
        } catch (SignatureRequestNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Refuse une demande.
     * Body: { comment? }
     */
    #[Route('/{id}/decline', name: 'decline', methods: ['PATCH'])]
    public function decline(
        string $id,
        #[CurrentUser] SecurityUser $user,
        Request $request,
    ): JsonResponse {
        try {
            $requestId = SignatureRequestId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiant invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $body    = json_decode($request->getContent(), true);
        $comment = \is_array($body) && \is_string($body['comment'] ?? null) ? trim($body['comment']) : null;

        try {
            ($this->declineHandler)(new DeclineSignatureRequestCommand(
                signatureRequestId: $requestId,
                declinedBy:         $user->getDomainUser()->getId(),
                comment:            $comment !== '' ? $comment : null,
            ));
        } catch (SignatureRequestNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
