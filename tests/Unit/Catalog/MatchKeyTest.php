<?php

use Estante\Catalog\Domain\MatchKey;

it('keeps the main title and the first author surname', function () {
    expect(MatchKey::of('O Senhor dos Anéis: A Sociedade do Anel', ['J.R.R. Tolkien', 'Someone Else'])?->value)
        ->toBe('senhor dos aneis|tolkien');
});

it('treats spellings of the same work as equal', function (string $title, array $authors) {
    expect(MatchKey::of($title, $authors)?->value)->toBe('senhor dos aneis|tolkien');
})->with([
    'series note' => ['Senhor dos Aneis (Trilogia, #1)', ['J. R. R. Tolkien']],
    'surname first' => ['O senhor dos anéis', ['Tolkien, J.R.R.']],
    'punctuation and case' => ['O SENHOR DOS ANÉIS!', ['tolkien']],
]);

it('ignores name suffixes when picking the surname', function () {
    expect(MatchKey::of('Dom Casmurro', ['Machado de Assis'])?->value)->toBe('dom casmurro|assis')
        ->and(MatchKey::of('Some Book', ['Martin Luther King Jr.'])?->value)->toBe('some book|king');
});

it('does not drop a title that is only an article', function () {
    expect(MatchKey::of('A', ['Someone'])?->value)->toBe('a|someone');
});

it('tells different works apart', function () {
    expect(MatchKey::of('Clean Code', ['Robert C. Martin'])->equals(MatchKey::of('Clean Architecture', ['Robert C. Martin'])))
        ->toBeFalse()
        ->and(MatchKey::of('Clean Code', ['Robert C. Martin'])->equals(MatchKey::of('Clean Code', ['Uncle Bob Smith'])))
        ->toBeFalse();
});

it('refuses to compare without a title or an author', function () {
    expect(MatchKey::of('Clean Code', []))->toBeNull()
        ->and(MatchKey::of('  :  ', ['Robert C. Martin']))->toBeNull()
        ->and(MatchKey::of('Clean Code', ['Jr.']))->toBeNull();
});
