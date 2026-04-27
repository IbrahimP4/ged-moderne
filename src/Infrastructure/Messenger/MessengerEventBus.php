<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Domain\Port\EventBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adaptateur Symfony Messenger pour le port EventBusInterface.
 *
 * Chaque Domain Event est enveloppé dans un Symfony Message et dispatché
 * sur le bus. Le routing Messenger décide si le message est traité
 * de façon synchrone (inline) ou asynchrone (queue).
 *
 * L'Application layer ne connaît pas Messenger — il n'interagit
 * qu'avec EventBusInterface.
 */
final class MessengerEventBus implements EventBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function dispatch(object ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
