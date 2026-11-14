<?php

use Estante\Catalog\Domain\InvalidIsbn;
use Estante\Catalog\Domain\Isbn;

it('normalizes ISBN-13 with hyphens and spaces', function () {
    expect(Isbn::fromString('978-85-359-0277-8')->value)->toBe('9788535902778')
        ->and(Isbn::fromString(' 978 0 13 235088 4 ')->value)->toBe('9780132350884');
});

it('converts ISBN-10 to ISBN-13', function (string $isbn10, string $isbn13) {
    expect(Isbn::fromString($isbn10)->value)->toBe($isbn13);
})->with([
    'plain' => ['0132350882', '9780132350884'],
    'hyphens' => ['0-13-235088-2', '9780132350884'],
    'X check digit' => ['043942089X', '9780439420891'],
    'lowercase x' => ['043942089x', '9780439420891'],
]);

it('gives back the ISBN-10 form for 978 ISBNs only', function () {
    expect(Isbn::fromString('9780439420891')->toIsbn10())->toBe('043942089X')
        ->and(Isbn::fromString('9791032305690')->toIsbn10())->toBeNull();
});

it('rejects a wrong check digit', function (string $raw) {
    Isbn::fromString($raw);
})->with(['9780132350885', '0132350883'])->throws(InvalidIsbn::class, 'wrong check digit');

it('rejects things that are not ISBNs', function (string $raw) {
    Isbn::fromString($raw);
})->with(['', 'abc', '12345', '1234567890123', '01323508X2'])->throws(InvalidIsbn::class);

it('compares by value', function () {
    expect(Isbn::fromString('0132350882')->equals(Isbn::fromString('978-0-13-235088-4')))->toBeTrue();
});
