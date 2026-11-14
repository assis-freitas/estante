<?php

namespace Estante\Shelf\Application;

use Carbon\CarbonImmutable;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\ReadingStatus;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Use case: move a copy to another reading status. The aggregate decides if
 * the move is allowed; this class persists it and publishes what happened.
 */
final readonly class ChangeReadingStatus
{
    public function __construct(private Dispatcher $events) {}

    public function __invoke(Copy $copy, ReadingStatus $to, CarbonImmutable $on): void
    {
        match ($to) {
            ReadingStatus::Reading => $copy->startReading($on),
            ReadingStatus::Read => $copy->finishReading($on),
            ReadingStatus::Abandoned => $copy->abandon(),
            ReadingStatus::WantToRead => $copy->backToWishlist(),
        };

        $copy->save();

        foreach ($copy->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
