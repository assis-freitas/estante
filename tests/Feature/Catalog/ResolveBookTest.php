<?php

use Estante\Catalog\Application\BookDescription;
use Estante\Catalog\Application\BookResolution;
use Estante\Catalog\Application\ResolveBook;
use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Estante\Catalog\Domain\MatchKey;

/** An in-memory stand-in for the external sources. */
function sources(array $byIsbn = [], array $byTitle = []): object
{
    $fake = new class($byIsbn, $byTitle) implements BookMetadataProvider
    {
        public array $calls = [];

        /** @param array<string, BookMetadata> $byIsbn  @param list<BookMetadata> $byTitle */
        public function __construct(private array $byIsbn, private array $byTitle) {}

        public function find(Isbn $isbn): ?BookMetadata
        {
            $this->calls[] = "isbn:{$isbn}";

            return $this->byIsbn[$isbn->value] ?? null;
        }

        public function findByTitle(string $title, string $author): ?BookMetadata
        {
            $this->calls[] = "title:{$title}";
            $wanted = MatchKey::of($title, [$author]);

            foreach ($this->byTitle as $metadata) {
                if ($wanted?->equals(MatchKey::of($metadata->title, $metadata->authors))) {
                    return $metadata;
                }
            }

            return null;
        }
    };
    app()->instance(BookMetadataProvider::class, $fake);

    return $fake;
}

function cleanCode(string $isbn = '9780132350884'): BookMetadata
{
    return new BookMetadata(Isbn::fromString($isbn), 'Clean Code', ['Robert C. Martin'], 'fake', pageCount: 431);
}

function resolveBook(BookDescription $description)
{
    return app(ResolveBook::class)($description);
}

it('uses the ISBN when a source knows it', function () {
    sources(byIsbn: ['9780132350884' => cleanCode()]);

    $resolved = resolveBook(new BookDescription('Clean code (whatever)', ['R. Martin'], Isbn::fromString('0132350882')));

    expect($resolved->resolution)->toBe(BookResolution::FoundByIsbn)
        ->and($resolved->book->title)->toBe('Clean Code')
        ->and($resolved->book->source)->toBe('fake')
        ->and($resolved->book->match_key)->toBe('clean code|martin');
});

it('creates the edition with its ISBN when nobody knows it, and reuses it afterwards', function () {
    sources();
    $description = new BookDescription(
        title: 'Um Livro Raro',
        authors: ['Fulana de Tal'],
        isbn: Isbn::fromString('9788535914849'),
        pageCount: 120,
        publishedYear: 1999,
        publisher: 'Editora Pequena',
        source: 'import:goodreads',
    );

    $first = resolveBook($description);
    $second = resolveBook($description);

    expect($first->resolution)->toBe(BookResolution::CreatedWithIsbn)
        ->and($first->book->isbn->value)->toBe('9788535914849')
        ->and($first->book->publisher)->toBe('Editora Pequena')
        ->and($first->book->source)->toBe('import:goodreads')
        ->and($second->resolution)->toBe(BookResolution::FoundByIsbn)
        ->and($second->book->id)->toBe($first->book->id)
        ->and(Book::count())->toBe(1);
});

it('without an ISBN, finds the same work already in the catalog without asking the sources', function () {
    $sources = sources();
    $existing = Book::registerManually('O Senhor dos Anéis: A Sociedade do Anel', ['J.R.R. Tolkien']);
    $existing->save();

    $resolved = resolveBook(new BookDescription('Senhor dos Aneis (Trilogia, #1)', ['Tolkien, J.R.R.']));

    expect($resolved->resolution)->toBe(BookResolution::FoundByTitle)
        ->and($resolved->book->id)->toBe($existing->id)
        ->and($sources->calls)->toBe([]);
});

it('without an ISBN, imports the work a source finds by title', function () {
    sources(byTitle: [cleanCode()]);

    $resolved = resolveBook(new BookDescription('Clean Code: A Handbook', ['Robert C. Martin'], source: 'import:goodreads'));

    expect($resolved->resolution)->toBe(BookResolution::ImportedByTitle)
        ->and($resolved->book->isbn->value)->toBe('9780132350884')
        ->and($resolved->book->source)->toBe('fake');
});

it('reuses the edition when the title search lands on an ISBN the catalog has under another spelling', function () {
    $existing = Book::registerManually('Clean Code!', ['Martin'], Isbn::fromString('9780132350884'));
    $existing->match_key = null; // as if it had been saved before match keys existed
    $existing->saveQuietly();
    sources(byTitle: [cleanCode()]);

    $resolved = resolveBook(new BookDescription('Clean Code', ['Robert C. Martin']));

    expect($resolved->book->id)->toBe($existing->id)
        ->and(Book::count())->toBe(1);
});

it('creates the edition from the description when nothing matches, and finds it next time', function () {
    sources();
    $description = new BookDescription('Memórias de um Livro Sem ISBN', ['Fulano Filho'], pageCount: 80, source: 'import:skoob');

    $first = resolveBook($description);
    $second = resolveBook(new BookDescription('Memorias de um livro sem ISBN', ['Fulano Filho']));

    expect($first->resolution)->toBe(BookResolution::Created)
        ->and($first->book->isbn)->toBeNull()
        ->and($first->book->page_count)->toBe(80)
        ->and($first->book->source)->toBe('import:skoob')
        ->and($second->resolution)->toBe(BookResolution::FoundByTitle)
        ->and($second->book->id)->toBe($first->book->id);
});

it('creates when there is no author to compare with', function () {
    $sources = sources(byTitle: [cleanCode()]);

    $resolved = resolveBook(new BookDescription('Clean Code', []));

    expect($resolved->resolution)->toBe(BookResolution::Created)
        ->and($sources->calls)->toBe([]);
});
