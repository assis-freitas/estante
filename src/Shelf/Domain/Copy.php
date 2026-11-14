<?php

namespace Estante\Shelf\Domain;

use Carbon\CarbonImmutable;
use Estante\Catalog\Domain\Book;
use Estante\Shared\Domain\RecordsEvents;
use Estante\Shelf\Domain\Events\BookFinished;
use Estante\Shelf\Domain\Events\ReadingStarted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aggregate root of the Shelf context: one reader's copy of an edition, and
 * where that reader is with it. Every state change goes through a method that
 * guards the reading rules; attributes are never set from the outside.
 *
 * @property int $id
 * @property int $owner_id
 * @property int $book_id
 * @property ReadingStatus $status
 * @property ?CarbonImmutable $started_on
 * @property ?CarbonImmutable $finished_on
 * @property ?int $rating
 * @property ?string $notes
 * @property-read Book $book
 */
class Copy extends Model
{
    use RecordsEvents;

    protected $table = 'shelf_copies';

    public static function acquire(int $ownerId, Book $book): self
    {
        $copy = new self;
        $copy->owner_id = $ownerId;
        $copy->book_id = $book->id;
        $copy->status = ReadingStatus::WantToRead;
        $copy->setRelation('book', $book);

        return $copy;
    }

    public function startReading(CarbonImmutable $on): void
    {
        if ($this->status === ReadingStatus::Reading) {
            throw ReadingRuleViolated::alreadyReading();
        }
        self::guardNotInFuture($on);

        // Starting again after finishing or abandoning is a re-read.
        $this->status = ReadingStatus::Reading;
        $this->started_on = $on;
        $this->finished_on = null;

        $this->recordThat(new ReadingStarted($this->id, $this->owner_id, $this->book_id, $on));
    }

    /** Also works straight from "want to read", for books read before joining. */
    public function finishReading(CarbonImmutable $on): void
    {
        if ($this->status === ReadingStatus::Read) {
            throw ReadingRuleViolated::alreadyRead();
        }
        self::guardNotInFuture($on);
        if ($this->status === ReadingStatus::Reading && $this->started_on?->isAfter($on)) {
            throw ReadingRuleViolated::finishedBeforeStarting();
        }
        if ($this->status !== ReadingStatus::Reading) {
            $this->started_on = null;
        }

        $this->status = ReadingStatus::Read;
        $this->finished_on = $on;

        $this->recordThat(new BookFinished($this->id, $this->owner_id, $this->book_id, $on));
    }

    public function abandon(): void
    {
        if ($this->status !== ReadingStatus::Reading) {
            throw ReadingRuleViolated::canOnlyAbandonWhileReading();
        }

        $this->status = ReadingStatus::Abandoned;
    }

    public function backToWishlist(): void
    {
        $this->status = ReadingStatus::WantToRead;
        $this->started_on = null;
        $this->finished_on = null;
        $this->rating = null;
    }

    public function rate(?int $stars): void
    {
        if ($stars === null) {
            $this->rating = null;

            return;
        }
        if ($this->status !== ReadingStatus::Read) {
            throw ReadingRuleViolated::canOnlyRateReadBooks();
        }
        if ($stars < 1 || $stars > 5) {
            throw ReadingRuleViolated::ratingOutOfRange();
        }

        $this->rating = $stars;
    }

    public function annotate(?string $notes): void
    {
        $notes = $notes === null ? null : trim($notes);
        $this->notes = $notes === '' ? null : $notes;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->owner_id === $userId;
    }

    /** @param  Builder<self>  $query */
    public function scopeOwnedBy(Builder $query, int $userId): void
    {
        $query->where('owner_id', $userId);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'book_id' => 'integer',
            'status' => ReadingStatus::class,
            'started_on' => 'immutable_date',
            'finished_on' => 'immutable_date',
            'rating' => 'integer',
        ];
    }

    private static function guardNotInFuture(CarbonImmutable $date): void
    {
        if ($date->startOfDay()->isAfter(CarbonImmutable::today())) {
            throw ReadingRuleViolated::dateInTheFuture();
        }
    }
}
