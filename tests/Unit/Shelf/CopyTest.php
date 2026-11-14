<?php

use Carbon\CarbonImmutable;
use Estante\Catalog\Domain\Book;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\Events\BookFinished;
use Estante\Shelf\Domain\Events\ReadingStarted;
use Estante\Shelf\Domain\ReadingRuleViolated;
use Estante\Shelf\Domain\ReadingStatus;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-14');

    $book = Book::registerManually('Dom Casmurro', ['Machado de Assis']);
    $book->id = 7;
    $this->copy = Copy::acquire(ownerId: 1, book: $book);
    $this->copy->id = 42;
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('starts on the wishlist', function () {
    expect($this->copy->status)->toBe(ReadingStatus::WantToRead)
        ->and($this->copy->releaseEvents())->toBe([]);
});

it('records when reading starts and finishes', function () {
    $this->copy->startReading(CarbonImmutable::parse('2026-11-01'));
    $this->copy->finishReading(CarbonImmutable::parse('2026-11-10'));

    expect($this->copy->status)->toBe(ReadingStatus::Read)
        ->and($this->copy->started_on->toDateString())->toBe('2026-11-01')
        ->and($this->copy->finished_on->toDateString())->toBe('2026-11-10');

    [$started, $finished] = $this->copy->releaseEvents();
    expect($started)->toBeInstanceOf(ReadingStarted::class)
        ->and($started->copyId)->toBe(42)
        ->and($finished)->toBeInstanceOf(BookFinished::class)
        ->and($finished->bookId)->toBe(7)
        ->and($this->copy->releaseEvents())->toBe([]);
});

it('can be marked as read straight from the wishlist, without a start date', function () {
    $this->copy->finishReading(CarbonImmutable::parse('2020-05-01'));

    expect($this->copy->status)->toBe(ReadingStatus::Read)
        ->and($this->copy->started_on)->toBeNull();
});

it('does not finish before it started', function () {
    $this->copy->startReading(CarbonImmutable::parse('2026-11-10'));
    $this->copy->finishReading(CarbonImmutable::parse('2026-11-01'));
})->throws(ReadingRuleViolated::class, 'before the start date');

it('does not accept dates in the future', function () {
    $this->copy->startReading(CarbonImmutable::parse('2026-11-15'));
})->throws(ReadingRuleViolated::class, 'future');

it('does not start a book that is already being read', function () {
    $this->copy->startReading(CarbonImmutable::today());
    $this->copy->startReading(CarbonImmutable::today());
})->throws(ReadingRuleViolated::class, 'already reading');

it('treats starting a finished book as a re-read', function () {
    $this->copy->finishReading(CarbonImmutable::parse('2025-01-10'));
    $this->copy->startReading(CarbonImmutable::parse('2026-11-01'));

    expect($this->copy->status)->toBe(ReadingStatus::Reading)
        ->and($this->copy->finished_on)->toBeNull();
});

it('only abandons books in progress', function () {
    $this->copy->abandon();
})->throws(ReadingRuleViolated::class, 'abandon');

it('only rates books that were read, from 1 to 5 stars', function () {
    expect(fn () => $this->copy->rate(5))->toThrow(ReadingRuleViolated::class, 'after reading');

    $this->copy->finishReading(CarbonImmutable::today());
    $this->copy->rate(4);
    expect($this->copy->rating)->toBe(4)
        ->and(fn () => $this->copy->rate(6))->toThrow(ReadingRuleViolated::class, '1 to 5');
});

it('forgets dates and rating when going back to the wishlist', function () {
    $this->copy->finishReading(CarbonImmutable::today());
    $this->copy->rate(3);
    $this->copy->backToWishlist();

    expect($this->copy->status)->toBe(ReadingStatus::WantToRead)
        ->and($this->copy->finished_on)->toBeNull()
        ->and($this->copy->rating)->toBeNull();
});

it('stores blank notes as null', function () {
    $this->copy->annotate("   \n ");

    expect($this->copy->notes)->toBeNull();
});
