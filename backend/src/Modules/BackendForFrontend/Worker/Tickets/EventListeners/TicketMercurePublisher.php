<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Tickets\EventListeners;

use App\Modules\Tickets\Application\Event\TicketAddedEvent;
use App\Modules\Tickets\Application\Event\TicketChangedEvent;
use App\Modules\Tickets\Application\Event\TicketEventInterface;
use App\Modules\Tickets\Application\Event\TicketRemovedEvent;
use App\Modules\Tickets\Application\Event\TicketStatusChangedEvent;
use App\Modules\Tickets\Application\Event\TicketUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class TicketMercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[AsEventListener]
    public function onTicketAdded(TicketAddedEvent $event): void
    {
        $this->publish($event);
    }

    #[AsEventListener]
    public function onTicketUpdated(TicketUpdatedEvent $event): void
    {
        $this->publish($event);
    }

    #[AsEventListener]
    public function onTicketRemoved(TicketRemovedEvent $event): void
    {
        $this->publish($event);
    }

    #[AsEventListener]
    public function onTicketChanged(TicketChangedEvent $event): void
    {
        $this->publish($event);
    }

    #[AsEventListener]
    public function onTicketStatusChanged(TicketStatusChangedEvent $event): void
    {
        $this->publish($event);
    }

    private function publish(TicketEventInterface $event): void
    {
        $topics = $this->resolveTopics($event);

        if ([] === $topics) {
            return;
        }

        $payload = [
            'type' => $event->getType(),
            'ticketId' => $event->getTicketId(),
            'data' => $event->getData(),
            'timestamp' => $event->getTimestamp()->format(DATE_ATOM),
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger?->error('Failed to encode ticket event payload for Mercure.', [
                'exception' => $exception,
                'eventType' => $event->getType(),
                'ticketId' => $event->getTicketId(),
            ]);

            return;
        }

        $this->hub->publish(new Update($topics, $encoded, true, $event->getType()));
    }

    /**
     * @return list<string>
     */
    private function resolveTopics(TicketEventInterface $event): array
    {
        $topics = $event->getTopics();

        if (null !== $event->getWorkerId()) {
            $topics[] = sprintf('worker/schedule/%s', $event->getWorkerId());
        }

        if ([] === $topics) {
            $topics[] = sprintf('tickets/%s', $event->getTicketId());
        }
        $topics[] = 'manager/monitoring';

        return array_values(array_unique($topics));
    }
}
