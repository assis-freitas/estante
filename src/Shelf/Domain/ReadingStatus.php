<?php

namespace Estante\Shelf\Domain;

enum ReadingStatus: string
{
    case WantToRead = 'want_to_read';
    case Reading = 'reading';
    case Read = 'read';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::WantToRead => 'Want to read',
            self::Reading => 'Reading',
            self::Read => 'Read',
            self::Abandoned => 'Abandoned',
        };
    }
}
