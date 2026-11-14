<?php

namespace Estante\Shelf\Application;

use Estante\Catalog\Domain\Book;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\ReadingRuleViolated;

/**
 * Use case: put an edition on a reader's shelf. In v1 a reader holds at most
 * one copy of each edition (also enforced by a unique index).
 */
final readonly class AddBookToShelf
{
    public function __invoke(int $ownerId, Book $book): Copy
    {
        $alreadyThere = Copy::query()->ownedBy($ownerId)->where('book_id', $book->id)->exists();
        if ($alreadyThere) {
            throw ReadingRuleViolated::alreadyOnShelf();
        }

        $copy = Copy::acquire($ownerId, $book);
        $copy->save();

        return $copy;
    }
}
