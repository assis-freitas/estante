<?php

namespace Estante\Catalog\Application;

use Estante\Catalog\Domain\Isbn;
use Estante\Shared\Domain\DomainRuleViolation;

final class BookNotFound extends DomainRuleViolation
{
    public static function forIsbn(Isbn $isbn): self
    {
        return new self("We couldn't find ISBN {$isbn} in any catalog. You can add it by hand.");
    }
}
