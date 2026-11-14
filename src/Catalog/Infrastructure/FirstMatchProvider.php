<?php

namespace Estante\Catalog\Infrastructure;

use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;

/**
 * Asks each source in order and returns the first answer. When the winner has
 * no cover or page count, later sources may fill those gaps.
 */
final readonly class FirstMatchProvider implements BookMetadataProvider
{
    /** @var list<BookMetadataProvider> */
    private array $providers;

    public function __construct(BookMetadataProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }

    public function find(Isbn $isbn): ?BookMetadata
    {
        $found = null;

        foreach ($this->providers as $provider) {
            $candidate = $provider->find($isbn);
            if ($candidate === null) {
                continue;
            }

            $found = $found === null ? $candidate : self::fillGaps($found, $candidate);

            if ($found->coverUrl !== null && $found->pageCount !== null) {
                break;
            }
        }

        return $found;
    }

    private static function fillGaps(BookMetadata $base, BookMetadata $extra): BookMetadata
    {
        return new BookMetadata(
            isbn: $base->isbn,
            title: $base->title,
            authors: $base->authors ?: $extra->authors,
            source: $base->source,
            subtitle: $base->subtitle ?? $extra->subtitle,
            publisher: $base->publisher ?? $extra->publisher,
            publishedYear: $base->publishedYear ?? $extra->publishedYear,
            pageCount: $base->pageCount ?? $extra->pageCount,
            coverUrl: $base->coverUrl ?? $extra->coverUrl,
            description: $base->description ?? $extra->description,
        );
    }
}
