<?php

declare(strict_types=1);

namespace App\Domain\Port;

/**
 * Port de publication des Domain Events.
 *
 * Le Domain ne connaît ni Symfony Messenger, ni RabbitMQ, ni Redis Streams.
 * Il publie ses événements vers ce contrat abstrait ; l'Infrastructure
 * choisit comment les transporter.
 */
interface EventBusInterface
{
    /**
     * Publie un ou plusieurs Domain Events.
     * L'ordre d'émission est garanti ; le transport sous-jacent peut être
     * synchrone (inline) ou asynchrone (queue).
     *
     * @param object ...$events
     */
    public function dispatch(object ...$events): void;
}
