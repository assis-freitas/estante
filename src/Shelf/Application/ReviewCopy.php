<?php

namespace Estante\Shelf\Application;

use Estante\Shelf\Domain\Copy;

/** Use case: rate a copy and/or write notes about it. */
final readonly class ReviewCopy
{
    public function __invoke(Copy $copy, ?int $rating, ?string $notes): void
    {
        $copy->rate($rating);
        $copy->annotate($notes);
        $copy->save();
    }
}
