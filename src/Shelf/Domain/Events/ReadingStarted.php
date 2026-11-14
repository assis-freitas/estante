<?php

namespace Estante\Shelf\Domain\Events;

use Carbon\CarbonImmutable;

final readonly class ReadingStarted
{
    public function __construct(
        public int $copyId,
        public int $ownerId,
        public int $bookId,
        public CarbonImmutable $startedOn,
    ) {}
}
