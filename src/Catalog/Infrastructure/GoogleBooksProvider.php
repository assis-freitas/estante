<?php

namespace Estante\Catalog\Infrastructure;

use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Adapter for the Google Books API. Works without a key, but a key
 * (GOOGLE_BOOKS_KEY) gives a much higher daily quota.
 *
 * @see https://developers.google.com/books/docs/v1/using
 */
final readonly class GoogleBooksProvider implements BookMetadataProvider
{
    public const SOURCE = 'google_books';

    public function __construct(private Http $http, private ?string $apiKey = null) {}

    public function find(Isbn $isbn): ?BookMetadata
    {
        try {
            $response = $this->http
                ->timeout(5)
                ->acceptJson()
                ->get('https://www.googleapis.com/books/v1/volumes', array_filter([
                    'q' => "isbn:{$isbn->value}",
                    'key' => $this->apiKey,
                ]));
        } catch (ConnectionException) {
            return null;
        }

        $info = $response->successful() ? $response->json('items.0.volumeInfo') : null;
        if (! is_array($info) || blank($info['title'] ?? null)) {
            return null;
        }

        return new BookMetadata(
            isbn: $isbn,
            title: $info['title'],
            authors: array_values($info['authors'] ?? []),
            source: self::SOURCE,
            subtitle: $info['subtitle'] ?? null,
            publisher: $info['publisher'] ?? null,
            publishedYear: isset($info['publishedDate']) ? (int) substr($info['publishedDate'], 0, 4) : null,
            pageCount: ($info['pageCount'] ?? 0) > 0 ? (int) $info['pageCount'] : null,
            coverUrl: self::cover($info['imageLinks'] ?? []),
            description: isset($info['description']) ? strip_tags($info['description']) : null,
        );
    }

    /** @param  array<string, string>  $links */
    private static function cover(array $links): ?string
    {
        $url = $links['thumbnail'] ?? $links['smallThumbnail'] ?? null;

        return $url === null ? null : str_replace(['http://', '&edge=curl'], ['https://', ''], $url);
    }
}
