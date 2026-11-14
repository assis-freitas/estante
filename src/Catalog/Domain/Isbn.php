<?php

namespace Estante\Catalog\Domain;

/**
 * An ISBN, always normalized to ISBN-13. Accepts ISBN-10 or ISBN-13, with or
 * without hyphens and spaces, and rejects anything with a wrong check digit.
 */
final readonly class Isbn implements \JsonSerializable, \Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $digits = strtoupper((string) preg_replace('/[\s-]/', '', $raw));

        if (preg_match('/^\d{9}[\dX]$/', $digits) === 1) {
            if (! self::hasValidIsbn10Checksum($digits)) {
                throw InvalidIsbn::wrongCheckDigit($raw);
            }

            return new self(self::isbn10To13($digits));
        }

        if (preg_match('/^97[89]\d{10}$/', $digits) === 1) {
            if (! self::hasValidIsbn13Checksum($digits)) {
                throw InvalidIsbn::wrongCheckDigit($raw);
            }

            return new self($digits);
        }

        throw InvalidIsbn::malformed($raw);
    }

    public static function isValid(string $raw): bool
    {
        try {
            self::fromString($raw);

            return true;
        } catch (InvalidIsbn) {
            return false;
        }
    }

    /** The ISBN-10 form, which only exists for the 978 prefix. */
    public function toIsbn10(): ?string
    {
        if (! str_starts_with($this->value, '978')) {
            return null;
        }

        $body = substr($this->value, 3, 9);
        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (10 - $i) * (int) $digit;
        }
        $check = (11 - $sum % 11) % 11;

        return $body.($check === 10 ? 'X' : (string) $check);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function hasValidIsbn10Checksum(string $digits): bool
    {
        $sum = 0;
        foreach (str_split($digits) as $i => $char) {
            if ($char === 'X' && $i !== 9) {
                return false;
            }
            $sum += (10 - $i) * ($char === 'X' ? 10 : (int) $char);
        }

        return $sum % 11 === 0;
    }

    private static function hasValidIsbn13Checksum(string $digits): bool
    {
        $sum = 0;
        foreach (str_split($digits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    private static function isbn10To13(string $isbn10): string
    {
        $body = '978'.substr($isbn10, 0, 9);
        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
