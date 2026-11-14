<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\Isbn;

/**
 * Use case: add an edition by hand. If the ISBN is already in the catalog we
 * reuse that edition instead of creating a duplicate.
 */
final readonly class RegisterBook
{
    /** @param  list<string>  $authors */
    public function __invoke(
        string $title,
        array $authors,
        ?Isbn $isbn = null,
        ?int $pageCount = null,
        ?int $publishedYear = null,
    ): Book {
        if ($isbn !== null) {
            $existing = Book::query()->where('isbn', $isbn->value)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $book = Book::registerManually($title, $authors, $isbn, $pageCount, $publishedYear);
        $book->save();

        return $book;
    }
}
