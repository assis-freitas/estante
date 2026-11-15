<?php

namespace Estante\Catalog\Infrastructure;

use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Estante\Catalog\Domain\MatchKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Adapter for the Open Library Books and Search APIs.
 *
 * @see https://openlibrary.org/dev/docs/api/books
 * @see https://openlibrary.org/dev/docs/api/search
 */
final readonly class OpenLibraryProvider implements BookMetadataProvider
{
    public const SOURCE = 'open_library';

    public function __construct(private Http $http) {}

    public function find(Isbn $isbn): ?BookMetadata
    {
        $key = "ISBN:{$isbn->value}";

        try {
            $response = $this->http
                ->timeout(5)
                ->acceptJson()
                ->get('https://openlibrary.org/api/books', [
                    'bibkeys' => $key,
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $data = $response->successful() ? $response->json($key) : null;
        if (! is_array($data) || blank($data['title'] ?? null)) {
            return null;
        }

        return new BookMetadata(
            isbn: $isbn,
            title: $data['title'],
            authors: array_values(array_filter(array_column($data['authors'] ?? [], 'name'))),
            source: self::SOURCE,
            subtitle: $data['subtitle'] ?? null,
            publisher: $data['publishers'][0]['name'] ?? null,
            publishedYear: self::year($data['publish_date'] ?? null),
            pageCount: isset($data['number_of_pages']) ? (int) $data['number_of_pages'] : null,
            coverUrl: $data['cover']['large'] ?? $data['cover']['medium'] ?? null,
        );
    }

    public function findByTitle(string $title, string $author): ?BookMetadata
    {
        $wanted = MatchKey::of($title, [$author]);
        if ($wanted === null) {
            return null;
        }

        try {
            $response = $this->http
                ->timeout(5)
                ->acceptJson()
                ->get('https://openlibrary.org/search.json', [
                    'title' => $title,
                    'author' => $author,
                    'fields' => 'title,subtitle,author_name,isbn,publisher,first_publish_year,number_of_pages_median,cover_i',
                    'limit' => 5,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        foreach ($response->successful() ? (array) $response->json('docs') : [] as $doc) {
            $authors = array_values($doc['author_name'] ?? []);
            $isbn = self::firstValidIsbn($doc['isbn'] ?? []);

            if ($isbn === null || ! $wanted->equals(MatchKey::of($doc['title'] ?? '', $authors))) {
                continue;
            }

            return new BookMetadata(
                isbn: $isbn,
                title: $doc['title'],
                authors: $authors,
                source: self::SOURCE,
                subtitle: $doc['subtitle'] ?? null,
                publisher: $doc['publisher'][0] ?? null,
                publishedYear: isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null,
                pageCount: isset($doc['number_of_pages_median']) ? (int) $doc['number_of_pages_median'] : null,
                coverUrl: isset($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg" : null,
            );
        }

        return null;
    }

    /**
     * A search hit lists the ISBNs of every edition of the work; prefer an ISBN-13.
     *
     * @param  list<string>  $candidates
     */
    private static function firstValidIsbn(array $candidates): ?Isbn
    {
        usort($candidates, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($candidates as $candidate) {
            if (Isbn::isValid($candidate)) {
                return Isbn::fromString($candidate);
            }
        }

        return null;
    }

    private static function year(?string $date): ?int
    {
        return $date !== null && preg_match('/\b(\d{4})\b/', $date, $m) === 1 ? (int) $m[1] : null;
    }
}
