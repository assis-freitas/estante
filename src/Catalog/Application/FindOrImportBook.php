<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Use case: give me the edition with this ISBN. The local catalog acts as a
 * cache of external sources, so each ISBN is fetched from the outside once.
 */
final readonly class FindOrImportBook
{
    public function __construct(private BookMetadataProvider $provider) {}

    /** @throws BookNotFound */
    public function __invoke(Isbn $isbn): Book
    {
        $existing = $this->findLocally($isbn);
        if ($existing !== null) {
            return $existing;
        }

        $metadata = $this->provider->find($isbn) ?? throw BookNotFound::forIsbn($isbn);

        try {
            $book = Book::fromMetadata($metadata);
            $book->save();

            return $book;
        } catch (UniqueConstraintViolationException) {
            // Someone imported the same ISBN at the same time: theirs wins.
            return $this->findLocally($isbn) ?? throw BookNotFound::forIsbn($isbn);
        }
    }

    private function findLocally(Isbn $isbn): ?Book
    {
        return Book::query()->where('isbn', $isbn->value)->first();
    }
}
