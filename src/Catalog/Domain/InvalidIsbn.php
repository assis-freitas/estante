<?php

namespace Estante\Catalog\Domain;

use Estante\Shared\Domain\DomainRuleViolation;

final class InvalidIsbn extends DomainRuleViolation
{
    public static function malformed(string $raw): self
    {
        return new self("\"{$raw}\" is not an ISBN. Use the 10 or 13 digits printed on the back cover.");
    }

    public static function wrongCheckDigit(string $raw): self
    {
        return new self("\"{$raw}\" has a wrong check digit. Double-check the number.");
    }
}
