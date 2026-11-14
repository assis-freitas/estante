<?php

namespace Estante\Shared\Domain;

/**
 * Lets an aggregate record domain events while it changes. The application
 * layer releases and dispatches them only after the aggregate is persisted.
 */
trait RecordsEvents
{
    /** @var list<object> */
    private array $recordedEvents = [];

    protected function recordThat(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
