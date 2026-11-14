<?php

namespace Estante\Catalog\Infrastructure;

use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Adapter for the Open Library Books API.
 *
 * @see https://openlibrary.org/dev/docs/api/books
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

    private static function year(?string $date): ?int
    {
        return $date !== null && preg_match('/\b(\d{4})\b/', $date, $m) === 1 ? (int) $m[1] : null;
    }
}
