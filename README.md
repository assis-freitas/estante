# Estante

A personal library. Type the ISBN from the back cover and the book lands on your shelf with title,
authors and cover. Then track what you want to read, are reading and have read, rate it and keep notes.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 + TypeScript · Tailwind · Pest

[![CI](https://github.com/assis-freitas/estante/actions/workflows/ci.yml/badge.svg)](https://github.com/assis-freitas/estante/actions/workflows/ci.yml)

<!-- TODO: screenshot / GIF of adding a book by ISBN -->

## Features

- **Add by ISBN.** ISBN-10 or 13, with or without hyphens. The check digit is validated before any request.
  Metadata comes from [Open Library](https://openlibrary.org/developers/api), with
  [Google Books](https://developers.google.com/books) filling gaps (cover, page count).
- **Add by hand** for books no catalog knows.
- **Reading status:** want to read → reading → read (or abandoned), with dates, re-reads and 1–5 star ratings.
- **Notes** per book.
- **Search and filter** your shelf by title, author, ISBN or status.

### Roadmap

- [ ] Scan the barcode with the phone camera
- [ ] Loans: who has my book, and since when
- [ ] Year in books (stats from the `BookFinished` events)
- [ ] Import from Goodreads / Skoob CSV
- [ ] PWA
- [ ] Ask your shelf (semantic search over notes, pgvector)

## Architecture

Domain-Driven Design, the pragmatic way: see [ADR 0001](docs/adr/0001-pragmatic-ddd-in-laravel.md).

```
src/
  Shared/Domain/          DomainRuleViolation, RecordsEvents
  Catalog/                editions, shared by every reader (upstream)
    Domain/               Book, Isbn, MatchKey, BookMetadata, BookMetadataProvider (port)
    Application/          FindOrImportBook, RegisterBook, ResolveBook
    Infrastructure/       OpenLibraryProvider, GoogleBooksProvider, FirstMatchProvider
  Shelf/                  one reader's copies (downstream of Catalog)
    Domain/               Copy (aggregate), ReadingStatus, events
    Application/          AddBookToShelf, ChangeReadingStatus, ReviewCopy
app/Http/                 controllers, resources, policies: the delivery layer
```

- **Book ≠ Copy.** A `Book` is an edition (one per ISBN, shared by everyone). A `Copy` is *your* copy
  and holds everything personal: status, dates, rating, notes.
- **Aggregates guard their rules.** `Copy` only changes through methods like `startReading()` or
  `finishReading()`, which refuse impossible moves (finishing before starting, rating an unread book,
  future dates) and record domain events.
- **No duplicates in a shared catalog.** `ResolveBook` turns any description (ISBN or not) into an
  edition without asking anyone: ISBN first, then the same work by title and author (`MatchKey`),
  locally and in the sources, and only then a new edition. See [ADR 0002](docs/adr/0002-resolving-books-without-an-isbn.md).
- **Ports where there is an outside world.** Catalog lookups go through the `BookMetadataProvider`
  port. Tests use an in-memory catalog and never touch the network.
- **Rules enforced by tests.** [`tests/Arch`](tests/Arch/ArchitectureTest.php) fails the build if the
  domain imports HTTP, Inertia, facades or adapters, or if `Catalog` starts depending on `Shelf`.

## Running locally

Requires PHP 8.3+, Composer and Node 22+.

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # creates a demo reader with 10 books
composer dev                     # http://localhost:8000
```

Demo login: `demo@estante.test` / `password`.

`GOOGLE_BOOKS_KEY` is optional; it only raises the Google Books daily quota.

## Tests

```bash
vendor/bin/pest                  # domain, architecture and feature tests
vendor/bin/pint --test           # code style
```

## License

[MIT](LICENSE)
