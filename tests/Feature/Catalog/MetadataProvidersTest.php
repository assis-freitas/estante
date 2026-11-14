<?php

use Estante\Catalog\Domain\Isbn;
use Estante\Catalog\Infrastructure\GoogleBooksProvider;
use Estante\Catalog\Infrastructure\OpenLibraryProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->isbn = Isbn::fromString('9780132350884'));

it('translates an Open Library answer', function () {
    Http::fake(['openlibrary.org/*' => Http::response([
        'ISBN:9780132350884' => [
            'title' => 'Clean Code',
            'subtitle' => 'A Handbook of Agile Software Craftsmanship',
            'authors' => [['name' => 'Robert C. Martin']],
            'publishers' => [['name' => 'Prentice Hall']],
            'publish_date' => 'August 2008',
            'number_of_pages' => 431,
            'cover' => ['medium' => 'https://covers.openlibrary.org/b/id/1-M.jpg', 'large' => 'https://covers.openlibrary.org/b/id/1-L.jpg'],
        ],
    ])]);

    $found = app(OpenLibraryProvider::class)->find($this->isbn);

    expect($found->title)->toBe('Clean Code')
        ->and($found->authors)->toBe(['Robert C. Martin'])
        ->and($found->publisher)->toBe('Prentice Hall')
        ->and($found->publishedYear)->toBe(2008)
        ->and($found->pageCount)->toBe(431)
        ->and($found->coverUrl)->toEndWith('-L.jpg')
        ->and($found->source)->toBe('open_library');

    Http::assertSent(fn ($request) => $request['bibkeys'] === 'ISBN:9780132350884');
});

it('translates a Google Books answer', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
        'title' => 'Clean Code',
        'authors' => ['Robert C. Martin'],
        'publishedDate' => '2008-08-01',
        'pageCount' => 431,
        'description' => '<p>Even bad code can function.</p>',
        'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=x&zoom=1&edge=curl'],
    ]]]])]);

    $found = (new GoogleBooksProvider(app(Factory::class)))->find($this->isbn);

    expect($found->publishedYear)->toBe(2008)
        ->and($found->description)->toBe('Even bad code can function.')
        ->and($found->coverUrl)->toBe('https://books.google.com/books/content?id=x&zoom=1');
});

it('answers null when the source does not know the ISBN', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([]),
        'www.googleapis.com/*' => Http::response(['totalItems' => 0]),
    ]);

    expect(app(OpenLibraryProvider::class)->find($this->isbn))->toBeNull()
        ->and(app(GoogleBooksProvider::class)->find($this->isbn))->toBeNull();
});

it('answers null when the source is down', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response('oops', 503),
        'www.googleapis.com/*' => fn () => throw new ConnectionException('timeout'),
    ]);

    expect(app(OpenLibraryProvider::class)->find($this->isbn))->toBeNull()
        ->and(app(GoogleBooksProvider::class)->find($this->isbn))->toBeNull();
});
