<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Book;

final readonly class ResolvedBook
{
    public function __construct(
        public Book $book,
        public BookResolution $resolution,
    ) {}
}
