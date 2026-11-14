<?php

namespace Estante\Shelf\Domain;

use Estante\Shared\Domain\DomainRuleViolation;

final class ReadingRuleViolated extends DomainRuleViolation
{
    public static function alreadyReading(): self
    {
        return new self('You are already reading this book.');
    }

    public static function alreadyRead(): self
    {
        return new self('This book is already marked as read.');
    }

    public static function canOnlyAbandonWhileReading(): self
    {
        return new self('You can only abandon a book you are reading.');
    }

    public static function finishedBeforeStarting(): self
    {
        return new self('The finish date cannot be before the start date.');
    }

    public static function dateInTheFuture(): self
    {
        return new self('Reading dates cannot be in the future.');
    }

    public static function canOnlyRateReadBooks(): self
    {
        return new self('You can only rate a book after reading it.');
    }

    public static function ratingOutOfRange(): self
    {
        return new self('Ratings go from 1 to 5 stars.');
    }

    public static function alreadyOnShelf(): self
    {
        return new self('This book is already on your shelf.');
    }
}
