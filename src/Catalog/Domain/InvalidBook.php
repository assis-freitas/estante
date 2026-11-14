<?php

namespace Estante\Catalog\Domain;

use Estante\Shared\Domain\DomainRuleViolation;

final class InvalidBook extends DomainRuleViolation
{
    public static function missingTitle(): self
    {
        return new self('A book needs a title.');
    }

    public static function nonPositivePageCount(): self
    {
        return new self('Page count must be a positive number.');
    }
}
