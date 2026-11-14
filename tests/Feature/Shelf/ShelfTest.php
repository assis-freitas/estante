<?php

use App\Models\User;
use Estante\Catalog\Domain\Book;
use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\Events\BookFinished;
use Estante\Shelf\Domain\ReadingStatus;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/** Replaces the real catalogs with an in-memory one, so tests never hit the network. */
function fakeCatalog(array $known = []): void
{
    app()->instance(BookMetadataProvider::class, new class($known) implements BookMetadataProvider
    {
        public int $calls = 0;

        public function __construct(private array $known) {}

        public function find(Isbn $isbn): ?BookMetadata
        {
            $this->calls++;
            $title = $this->known[$isbn->value] ?? null;

            return $title === null ? null : new BookMetadata($isbn, $title, ['Some Author'], 'fake', pageCount: 200);
        }

        public function findByTitle(string $title, string $author): ?BookMetadata
        {
            return null;
        }
    });
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('previews a book by ISBN and caches it in the catalog', function () {
    fakeCatalog(['9780132350884' => 'Clean Code']);

    $this->getJson(route('shelf.lookup', ['isbn' => '0-13-235088-2']))
        ->assertOk()
        ->assertJsonPath('book.title', 'Clean Code')
        ->assertJsonPath('book.isbn', '9780132350884');
    $this->getJson(route('shelf.lookup', ['isbn' => '9780132350884']))->assertOk();

    expect(Book::count())->toBe(1)
        ->and(app(BookMetadataProvider::class)->calls)->toBe(1);
});

it('explains unknown and invalid ISBNs', function () {
    fakeCatalog();

    $this->getJson(route('shelf.lookup', ['isbn' => '9780132350884']))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'add it by hand'));
    $this->getJson(route('shelf.lookup', ['isbn' => '123']))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'not an ISBN'));
});

it('adds a book by ISBN', function () {
    fakeCatalog(['9780132350884' => 'Clean Code']);

    $response = $this->post(route('shelf.store'), ['mode' => 'isbn', 'isbn' => '9780132350884']);

    $copy = Copy::sole();
    $response->assertRedirect(route('shelf.show', $copy));
    expect($copy->owner_id)->toBe($this->user->id)
        ->and($copy->status)->toBe(ReadingStatus::WantToRead)
        ->and($copy->book->title)->toBe('Clean Code');
});

it('adds a book by hand when no catalog knows it', function () {
    fakeCatalog();

    $this->post(route('shelf.store'), [
        'mode' => 'manual',
        'title' => 'Zine da faculdade',
        'authors' => 'Ana, Bia',
        'page_count' => 40,
    ])->assertRedirect();

    expect(Copy::sole()->book)
        ->title->toBe('Zine da faculdade')
        ->authors->toBe(['Ana', 'Bia'])
        ->isbn->toBeNull();
});

it('does not add the same book twice', function () {
    fakeCatalog(['9780132350884' => 'Clean Code']);
    $this->post(route('shelf.store'), ['mode' => 'isbn', 'isbn' => '9780132350884']);

    $this->from(route('shelf.create'))
        ->post(route('shelf.store'), ['mode' => 'isbn', 'isbn' => '9780132350884'])
        ->assertRedirect(route('shelf.create'))
        ->assertSessionHasErrors(['domain' => 'This book is already on your shelf.']);

    expect(Copy::count())->toBe(1);
});

it('lets two readers own the same edition', function () {
    fakeCatalog(['9780132350884' => 'Clean Code']);
    $this->post(route('shelf.store'), ['mode' => 'isbn', 'isbn' => '9780132350884']);
    $this->actingAs(User::factory()->create())
        ->post(route('shelf.store'), ['mode' => 'isbn', 'isbn' => '9780132350884']);

    expect(Book::count())->toBe(1)->and(Copy::count())->toBe(2);
});

it('lists, filters and searches the shelf', function () {
    $mine = Copy::acquire($this->user->id, tap(Book::registerManually('Dom Casmurro', ['Machado de Assis']))->save());
    $mine->save();
    $other = Copy::acquire($this->user->id, tap(Book::registerManually('Grande Sertão: Veredas', ['Guimarães Rosa']))->save());
    $other->save();
    $other->finishReading(now()->toImmutable());
    $other->save();
    Copy::acquire(User::factory()->create()->id, tap(Book::registerManually('Not mine', ['X']))->save())->save();

    $this->get(route('shelf.index'))->assertInertia(fn (Assert $page) => $page
        ->component('shelf/Index')
        ->has('copies', 2)
        ->where('counts.read', 1)
        ->where('counts.want_to_read', 1));

    $this->get(route('shelf.index', ['status' => 'read']))
        ->assertInertia(fn (Assert $page) => $page->has('copies', 1)->where('copies.0.book.title', 'Grande Sertão: Veredas'));

    $this->get(route('shelf.index', ['q' => 'machado']))
        ->assertInertia(fn (Assert $page) => $page->has('copies', 1)->where('copies.0.book.title', 'Dom Casmurro'));
});

it('tracks reading and publishes BookFinished', function () {
    Event::fake([BookFinished::class]);
    $copy = Copy::acquire($this->user->id, tap(Book::registerManually('Dom Casmurro', ['Machado de Assis']))->save());
    $copy->save();

    $this->patch(route('shelf.status', $copy), ['status' => 'reading', 'on' => now()->subDays(5)->toDateString()]);
    $this->patch(route('shelf.status', $copy), ['status' => 'read']);
    $this->patch(route('shelf.review', $copy), ['rating' => 5, 'notes' => 'Capitu!'])->assertSessionHasNoErrors();

    expect($copy->fresh())
        ->status->toBe(ReadingStatus::Read)
        ->rating->toBe(5)
        ->notes->toBe('Capitu!');
    Event::assertDispatched(BookFinished::class, fn (BookFinished $e) => $e->copyId === $copy->id);
});

it('turns broken reading rules into form errors', function () {
    $copy = Copy::acquire($this->user->id, tap(Book::registerManually('Dom Casmurro', ['Machado de Assis']))->save());
    $copy->save();

    $this->from(route('shelf.show', $copy))
        ->patch(route('shelf.review', $copy), ['rating' => 5])
        ->assertSessionHasErrors(['domain' => 'You can only rate a book after reading it.']);
});

it('keeps other readers out of my shelf', function () {
    $copy = Copy::acquire(User::factory()->create()->id, tap(Book::registerManually('Secret diary', ['Someone']))->save());
    $copy->save();

    $this->get(route('shelf.show', $copy))->assertForbidden();
    $this->patch(route('shelf.status', $copy), ['status' => 'reading'])->assertForbidden();
    $this->delete(route('shelf.destroy', $copy))->assertForbidden();
});

it('removes a copy but keeps the edition in the catalog', function () {
    $copy = Copy::acquire($this->user->id, tap(Book::registerManually('Dom Casmurro', ['Machado de Assis']))->save());
    $copy->save();

    $this->delete(route('shelf.destroy', $copy))->assertRedirect(route('shelf.index'));

    expect(Copy::count())->toBe(0)->and(Book::count())->toBe(1);
});
