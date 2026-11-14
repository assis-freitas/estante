<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Estante\Catalog\Domain\MatchKey;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Use case: turn a description into an edition of the catalog, always.
 * It never asks anyone: it tries, in order,
 *
 *   1. the ISBN (catalog, then external sources);
 *   2. if nobody knows the ISBN, a new edition with that ISBN;
 *   3. without an ISBN, the same work already in the catalog (MatchKey);
 *   4. the same work in an external source, by title and author;
 *   5. a new edition from the description.
 *
 * The catalog is shared by every reader, so steps 3 and 4 exist to avoid
 * duplicates, and both only accept an exact MatchKey.
 *
 * @see docs/adr/0002-resolving-books-without-an-isbn.md
 */
final readonly class ResolveBook
{
    public function __construct(
        private FindOrImportBook $findOrImport,
        private BookMetadataProvider $provider,
        private Cache $cache,
    ) {}

    public function __invoke(BookDescription $description): ResolvedBook
    {
        if ($description->isbn !== null) {
            return $this->byIsbn($description->isbn, $description);
        }

        $key = MatchKey::of($description->title, $description->authors);
        if ($key === null) {
            return new ResolvedBook($this->create($description), BookResolution::Created);
        }

        // Two imports of the same work at once would both miss step 3.
        return $this->exclusively("catalog:resolve:{$key}", fn () => $this->byTitle($key, $description));
    }

    private function byIsbn(Isbn $isbn, BookDescription $description): ResolvedBook
    {
        try {
            return new ResolvedBook(($this->findOrImport)($isbn), BookResolution::FoundByIsbn);
        } catch (BookNotFound) {
            try {
                return new ResolvedBook($this->create($description), BookResolution::CreatedWithIsbn);
            } catch (UniqueConstraintViolationException) {
                // Someone created it in the meantime: theirs wins.
                return new ResolvedBook($this->findByIsbn($isbn), BookResolution::FoundByIsbn);
            }
        }
    }

    private function byTitle(MatchKey $key, BookDescription $description): ResolvedBook
    {
        $local = Book::query()->where('match_key', $key->value)->oldest('id')->first();
        if ($local !== null) {
            return new ResolvedBook($local, BookResolution::FoundByTitle);
        }

        $remote = $this->provider->findByTitle($description->title, $description->authors[0]);
        if ($remote !== null) {
            return new ResolvedBook($this->importFound($remote), BookResolution::ImportedByTitle);
        }

        return new ResolvedBook($this->create($description), BookResolution::Created);
    }

    /** A title search may land on an ISBN the catalog already has under another spelling. */
    private function importFound(BookMetadata $metadata): Book
    {
        $existing = Book::query()->where('isbn', $metadata->isbn->value)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            $book = Book::fromMetadata($metadata);
            $book->save();

            return $book;
        } catch (UniqueConstraintViolationException) {
            return $this->findByIsbn($metadata->isbn);
        }
    }

    private function create(BookDescription $description): Book
    {
        $book = Book::registerManually(
            title: $description->title,
            authors: $description->authors,
            isbn: $description->isbn,
            pageCount: $description->pageCount,
            publishedYear: $description->publishedYear,
            publisher: $description->publisher,
            source: $description->source,
        );
        $book->save();

        return $book;
    }

    private function findByIsbn(Isbn $isbn): Book
    {
        return Book::query()->where('isbn', $isbn->value)->firstOrFail();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function exclusively(string $name, callable $callback): mixed
    {
        $store = $this->cache->getStore();
        if (! $store instanceof LockProvider) {
            return $callback();
        }

        return $store->lock($name, 30)->block(10, $callback);
    }
}
