<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Messaging\Repository\MessageRepositoryInterface;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint Server-Sent Events (SSE).
 *
 * URL : GET /api/sse?token=<JWT>&since=<ISO8601>
 *
 * Le client doit se reconnecter automatiquement (comportement natif de EventSource).
 * On limite chaque connexion à ~50 secondes pour éviter les timeouts PHP.
 * Le paramètre `since` permet au client de ne recevoir que les nouveautés après reconnexion.
 */
#[Route('/api/sse', name: 'api_sse', methods: ['GET'])]
final class SseController extends AbstractController
{
    public function __construct(
        private readonly JWTEncoderInterface              $jwtEncoder,
        private readonly UserRepositoryInterface          $userRepository,
        private readonly NotificationRepositoryInterface  $notificationRepository,
        private readonly MessageRepositoryInterface       $messageRepository,
        private readonly EntityManagerInterface           $entityManager,
    ) {}

    public function __invoke(Request $request): Response
    {
        // ── 1. Authentification via query param ───────────────────────────
        $token = $request->query->get('token', '');
        if ($token === '') {
            return new Response('Token manquant.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtEncoder->decode($token);
        } catch (JWTDecodeFailureException) {
            return new Response('Token invalide.', Response::HTTP_UNAUTHORIZED);
        }

        $username = $payload['username'] ?? null;
        if (! is_string($username)) {
            return new Response('Token invalide.', Response::HTTP_UNAUTHORIZED);
        }

        $domainUser = $this->userRepository->findByUsername($username);
        if ($domainUser === null) {
            return new Response('Utilisateur introuvable.', Response::HTTP_UNAUTHORIZED);
        }

        $userId = $domainUser->getId();

        // ── 2. Curseur "since" ────────────────────────────────────────────
        $sinceRaw = $request->query->get('since', '');
        try {
            $since = $sinceRaw !== ''
                ? new \DateTimeImmutable($sinceRaw)
                : new \DateTimeImmutable('-5 seconds');
        } catch (\Exception) {
            $since = new \DateTimeImmutable('-5 seconds');
        }

        // ── 3. Réponse SSE streamée ───────────────────────────────────────
        $response = new StreamedResponse(function () use ($userId, $since): void {
            $this->streamEvents($userId, $since);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');   // Désactive nginx buffering
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    private function streamEvents(UserId $userId, \DateTimeImmutable $since): void
    {
        // Désactive le timeout PHP et le buffering
        set_time_limit(0);
        ignore_user_abort(true);

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $startedAt  = time();
        $maxSeconds = 50;  // reconnexion toutes les ~50 s
        $pollEvery  = 2;   // polling DB toutes les 2 s
        $lastPing   = time();
        $cursor     = $since;

        // Envoi immédiat du "connected" pour confirmer la connexion
        $this->emit('connected', ['userId' => $userId->getValue(), 'since' => $cursor->format(\DateTimeInterface::ATOM)]);

        while (true) {
            // ── Arrêt si déconnexion ou durée max ──────────────────────
            if (connection_aborted() || (time() - $startedAt) >= $maxSeconds) {
                break;
            }

            // ── Heartbeat toutes les 15 s ──────────────────────────────
            if ((time() - $lastPing) >= 15) {
                echo ": ping\n\n";
                $this->flush();
                $lastPing = time();
            }

            // ── Clear Doctrine cache (données fraîches) ───────────────
            $this->entityManager->clear();

            // ── Nouvelles notifications ────────────────────────────────
            $notifications = $this->notificationRepository->findCreatedAfter($cursor, $userId);
            foreach ($notifications as $notif) {
                $this->emit('notification', $notif->toArray());
                $cursor = $notif->getCreatedAt();
            }

            // ── Nouveaux messages ──────────────────────────────────────
            $messages = $this->messageRepository->findReceivedAfter($cursor, $userId);
            foreach ($messages as $msg) {
                $this->emit('message', $msg->toArray());
                $cursor = $msg->getSentAt();
            }

            sleep($pollEvery);
        }

        // Indique au client quand se reconnecter (en millisecondes)
        echo "retry: 3000\n\n";
        $this->flush();
    }

    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, \JSON_UNESCAPED_UNICODE) . "\n\n";
        $this->flush();
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
