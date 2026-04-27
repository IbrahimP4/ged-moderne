<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Signature;

use App\Domain\Signature\Entity\CompanyStamp;
use App\Domain\Signature\Entity\ProfileSignature;
use App\Infrastructure\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SignatureProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Ma signature personnelle ──────────────────────────────────────────────

    #[Route('/api/profile/signature', name: 'api_profile_signature_get', methods: ['GET'])]
    public function getSignature(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $sig = $this->em->find(ProfileSignature::class, $user->getDomainUser());

        if ($sig === null) {
            return $this->json(null);
        }

        return $this->json([
            'dataUrl'   => $sig->getDataUrl(),
            'type'      => $sig->getType(),
            'updatedAt' => $sig->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/api/profile/signature', name: 'api_profile_signature_save', methods: ['POST'])]
    public function saveSignature(
        #[CurrentUser] SecurityUser $user,
        Request $request,
    ): JsonResponse {
        $body   = json_decode($request->getContent(), true);
        $dataUrl = \is_array($body) && \is_string($body['dataUrl'] ?? null) ? $body['dataUrl'] : '';
        $type    = \is_array($body) && \is_string($body['type'] ?? null) ? $body['type'] : 'drawn';

        if ($dataUrl === '' || ! str_starts_with($dataUrl, 'data:image/')) {
            return $this->json(['error' => 'dataUrl invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($dataUrl) > 2_000_000) {
            return $this->json(['error' => 'Image trop grande (max 1.5 MB).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $domainUser = $user->getDomainUser();
        $sig        = $this->em->find(ProfileSignature::class, $domainUser);

        if ($sig === null) {
            $sig = ProfileSignature::create($domainUser, $dataUrl, $type);
            $this->em->persist($sig);
        } else {
            $sig->update($dataUrl, $type);
        }

        $this->em->flush();

        return $this->json([
            'dataUrl'   => $sig->getDataUrl(),
            'type'      => $sig->getType(),
            'updatedAt' => $sig->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/api/profile/signature', name: 'api_profile_signature_delete', methods: ['DELETE'])]
    public function deleteSignature(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        $sig = $this->em->find(ProfileSignature::class, $user->getDomainUser());

        if ($sig !== null) {
            $this->em->remove($sig);
            $this->em->flush();
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Tampon entreprise (admin uniquement) ──────────────────────────────────

    #[Route('/api/company-stamp', name: 'api_company_stamp_get', methods: ['GET'])]
    public function getCompanyStamp(): JsonResponse
    {
        $stamp = $this->em->find(CompanyStamp::class, 1);

        if ($stamp === null) {
            return $this->json(null);
        }

        return $this->json([
            'dataUrl'   => $stamp->getDataUrl(),
            'updatedAt' => $stamp->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/api/company-stamp', name: 'api_company_stamp_save', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function saveCompanyStamp(
        #[CurrentUser] SecurityUser $user,
        Request $request,
    ): JsonResponse {
        $body    = json_decode($request->getContent(), true);
        $dataUrl = \is_array($body) && \is_string($body['dataUrl'] ?? null) ? $body['dataUrl'] : '';

        if ($dataUrl === '' || ! str_starts_with($dataUrl, 'data:image/')) {
            return $this->json(['error' => 'dataUrl invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($dataUrl) > 2_000_000) {
            return $this->json(['error' => 'Image trop grande (max 1.5 MB).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stamp = $this->em->find(CompanyStamp::class, 1);

        if ($stamp === null) {
            $stamp = CompanyStamp::create($dataUrl, $user->getDomainUser());
            $this->em->persist($stamp);
        } else {
            $stamp->update($dataUrl, $user->getDomainUser());
        }

        $this->em->flush();

        return $this->json([
            'dataUrl'   => $stamp->getDataUrl(),
            'updatedAt' => $stamp->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/api/company-stamp', name: 'api_company_stamp_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCompanyStamp(): JsonResponse
    {
        $stamp = $this->em->find(CompanyStamp::class, 1);

        if ($stamp !== null) {
            $this->em->remove($stamp);
            $this->em->flush();
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
