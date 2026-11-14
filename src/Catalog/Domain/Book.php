<?php

namespace Estante\Catalog\Domain;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * An edition of a book, shared by every reader. It is not "my copy": owning,
 * reading and lending a book belong to the Shelf context (see Shelf\Domain\Copy).
 *
 * @property int $id
 * @property ?Isbn $isbn
 * @property string $title
 * @property ?string $subtitle
 * @property list<string> $authors
 * @property ?string $publisher
 * @property ?int $published_year
 * @property ?int $page_count
 * @property ?string $cover_url
 * @property ?string $description
 * @property string $source
 */
class Book extends Model
{
    protected $table = 'catalog_books';

    protected $guarded = [];

    public static function fromMetadata(BookMetadata $metadata): self
    {
        $book = new self;
        $book->isbn = $metadata->isbn;
        $book->title = $metadata->title;
        $book->subtitle = $metadata->subtitle;
        $book->authors = $metadata->authors;
        $book->publisher = $metadata->publisher;
        $book->published_year = $metadata->publishedYear;
        $book->page_count = $metadata->pageCount;
        $book->cover_url = $metadata->coverUrl;
        $book->description = $metadata->description;
        $book->source = $metadata->source;

        return $book;
    }

    /**
     * For editions no source knows about (old, self-published, rare...).
     *
     * @param  list<string>  $authors
     */
    public static function registerManually(
        string $title,
        array $authors,
        ?Isbn $isbn = null,
        ?int $pageCount = null,
        ?int $publishedYear = null,
    ): self {
        $title = trim($title);
        if ($title === '') {
            throw InvalidBook::missingTitle();
        }
        if ($pageCount !== null && $pageCount < 1) {
            throw InvalidBook::nonPositivePageCount();
        }

        $book = new self;
        $book->isbn = $isbn;
        $book->title = $title;
        $book->authors = array_values(array_filter(array_map('trim', $authors)));
        $book->page_count = $pageCount;
        $book->published_year = $publishedYear;
        $book->source = 'manual';

        return $book;
    }

    public function authorsLine(): string
    {
        return implode(', ', $this->authors) ?: 'Unknown author';
    }

    protected function isbn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Isbn::fromString($value),
            set: fn (Isbn|string|null $value) => match (true) {
                $value === null => null,
                $value instanceof Isbn => $value->value,
                default => Isbn::fromString($value)->value,
            },
        );
    }

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }
}
