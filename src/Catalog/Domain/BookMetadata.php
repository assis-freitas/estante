<?php

namespace Estante\Catalog\Domain;

/**
 * What an external source knows about an edition. Immutable and free of any
 * provider format: each adapter translates its own payload into this.
 */
final readonly class BookMetadata
{
    /**
     * @param  list<string>  $authors
     */
    public function __construct(
        public Isbn $isbn,
        public string $title,
        public array $authors,
        public string $source,
        public ?string $subtitle = null,
        public ?string $publisher = null,
        public ?int $publishedYear = null,
        public ?int $pageCount = null,
        public ?string $coverUrl = null,
        public ?string $description = null,
    ) {}
}
