<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Isbn;

/**
 * What someone outside the catalog says about a book: a reader typing it in,
 * or a line from another app's export. It may be incomplete or wrong.
 */
final readonly class BookDescription
{
    /**
     * @param  list<string>  $authors
     * @param  string  $source  who described it: 'manual', 'import:goodreads'...
     */
    public function __construct(
        public string $title,
        public array $authors,
        public ?Isbn $isbn = null,
        public ?int $pageCount = null,
        public ?int $publishedYear = null,
        public ?string $publisher = null,
        public string $source = 'manual',
    ) {}
}
