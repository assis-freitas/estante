<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\Isbn;
use Estante\Shelf\Domain\Copy;
use Illuminate\Database\Seeder;

/**
 * A demo reader with a shelf in every state. Works offline: metadata is
 * written here, and only the covers load from Open Library at runtime.
 * Goes through the domain methods, so the demo data obeys the same rules.
 */
class DemoShelfSeeder extends Seeder
{
    public function run(): void
    {
        $reader = User::query()->updateOrCreate(
            ['email' => 'demo@estante.test'],
            ['name' => 'Demo Reader', 'password' => 'password', 'email_verified_at' => now()],
        );
        Copy::query()->ownedBy($reader->id)->delete();

        $today = CarbonImmutable::today();

        foreach ($this->books() as [$isbn, $title, $authors, $year, $pages, $shelf]) {
            $book = Book::query()->where('isbn', Isbn::fromString($isbn)->value)->first()
                ?? tap(Book::fromMetadata(new BookMetadata(
                    isbn: Isbn::fromString($isbn),
                    title: $title,
                    authors: $authors,
                    source: 'seed',
                    publishedYear: $year,
                    pageCount: $pages,
                    coverUrl: 'https://covers.openlibrary.org/b/isbn/'.Isbn::fromString($isbn)->value.'-L.jpg?default=false',
                )))->save();

            $copy = Copy::acquire($reader->id, $book);
            $copy->save();

            match ($shelf['status']) {
                'reading' => $copy->startReading($today->subDays($shelf['started'])),
                'read' => [
                    $copy->startReading($today->subDays($shelf['started'])),
                    $copy->finishReading($today->subDays($shelf['finished'])),
                    $copy->rate($shelf['rating']),
                ],
                'abandoned' => [$copy->startReading($today->subDays($shelf['started'])), $copy->abandon()],
                default => null,
            };
            $copy->annotate($shelf['notes'] ?? null);
            $copy->save();
            $copy->releaseEvents();
        }
    }

    /** @return list<array{string, string, list<string>, int, int, array<string, mixed>}> */
    private function books(): array
    {
        return [
            ['0321125215', 'Domain-Driven Design', ['Eric Evans'], 2003, 529, ['status' => 'reading', 'started' => 12]],
            ['1449373321', 'Designing Data-Intensive Applications', ['Martin Kleppmann'], 2017, 590, ['status' => 'read', 'started' => 90, 'finished' => 40, 'rating' => 5, 'notes' => 'Chapter 7 on transactions is worth rereading every year.']],
            ['0132350882', 'Clean Code', ['Robert C. Martin'], 2008, 431, ['status' => 'read', 'started' => 400, 'finished' => 360, 'rating' => 4]],
            ['020161622X', 'The Pragmatic Programmer', ['Andrew Hunt', 'David Thomas'], 1999, 321, ['status' => 'read', 'started' => 200, 'finished' => 180, 'rating' => 5]],
            ['0134757599', 'Refactoring', ['Martin Fowler'], 2018, 448, ['status' => 'want_to_read']],
            ['0441172717', 'Dune', ['Frank Herbert'], 1990, 535, ['status' => 'reading', 'started' => 3]],
            ['054792822X', 'The Hobbit', ['J.R.R. Tolkien'], 2012, 300, ['status' => 'read', 'started' => 30, 'finished' => 21, 'rating' => 5, 'notes' => 'Read it aloud with the kids.']],
            ['0451524934', '1984', ['George Orwell'], 1961, 328, ['status' => 'want_to_read']],
            ['0262033844', 'Introduction to Algorithms', ['Thomas H. Cormen', 'Charles E. Leiserson', 'Ronald L. Rivest', 'Clifford Stein'], 2009, 1312, ['status' => 'abandoned', 'started' => 150]],
            ['0596007124', 'Head First Design Patterns', ['Eric Freeman', 'Elisabeth Robson'], 2004, 694, ['status' => 'want_to_read']],
        ];
    }
}
