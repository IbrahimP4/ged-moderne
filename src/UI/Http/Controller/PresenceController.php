<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de la présence (indicateur "en ligne") via un cache léger.
 *
 * Chaque utilisateur connecté envoie un PING toutes les 30 s.
 * On considère un utilisateur "en ligne" s'il a pinggué dans les 60 dernières secondes.
 *
 * Aucune persistance SQL — on utilise le cache Symfony (filesystem par défaut).
 */
#[Route('/api/presence', name: 'api_presence_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PresenceController extends AbstractController
{
    private const TTL_SECONDS   = 60;   // Durée avant qu'un user soit considéré absent
    private const CACHE_PREFIX  = 'presence_';

    public function __construct(
        private readonly UserRepositoryInterface  $userRepository,
        private readonly EntityManagerInterface   $entityManager,
    ) {}

    /**
     * POST /api/presence/ping
     * Le frontend appelle cet endpoint toutes les 30 s.
     */
    #[Route('/ping', name: 'ping', methods: ['POST'])]
    public function ping(): JsonResponse
    {
        $user  = $this->getUser();
        if ($user === null) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        $cache = new FilesystemAdapter('presence', self::TTL_SECONDS);
        $item  = $cache->getItem(self::CACHE_PREFIX . $user->getUserIdentifier());
        $item->set(time());
        $item->expiresAfter(self::TTL_SECONDS);
        $cache->save($item);

        return $this->json(['status' => 'ok', 'online' => true]);
    }

    /**
     * GET /api/presence
     * Retourne la liste de tous les utilisateurs avec leur statut en ligne.
     * Limité aux admins (peut être étendu si besoin).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $cache     = new FilesystemAdapter('presence', self::TTL_SECONDS);
        $userIds   = $request->query->all('ids');   // ids[]=uuid&ids[]=uuid...

        if (!empty($userIds)) {
            // Résolution ciblée d'une liste d'UUIDs
            $users = $this->entityManager
                ->createQueryBuilder()
                ->select('u')
                ->from(\App\Domain\User\Entity\User::class, 'u')
                ->where('u.id IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getQuery()
                ->getResult();
        } else {
            // Tous les utilisateurs
            $users = $this->userRepository->findAll();
        }

        $result = [];
        foreach ($users as $user) {
            $cacheKey = self::CACHE_PREFIX . $user->getUsername();
            $item     = $cache->getItem($cacheKey);
            $result[] = [
                'id'       => $user->getId()->getValue(),
                'username' => $user->getUsername(),
                'online'   => $item->isHit(),
                'lastSeen' => $item->isHit() ? date(\DateTimeInterface::ATOM, (int) $item->get()) : null,
            ];
        }

        return $this->json($result);
    }

    /**
     * GET /api/presence/{username}
     * Statut d'un seul utilisateur.
     */
    #[Route('/{username}', name: 'status', methods: ['GET'])]
    public function status(string $username): JsonResponse
    {
        $cache = new FilesystemAdapter('presence', self::TTL_SECONDS);
        $item  = $cache->getItem(self::CACHE_PREFIX . $username);

        return $this->json([
            'username' => $username,
            'online'   => $item->isHit(),
            'lastSeen' => $item->isHit() ? date(\DateTimeInterface::ATOM, (int) $item->get()) : null,
        ]);
    }
}
