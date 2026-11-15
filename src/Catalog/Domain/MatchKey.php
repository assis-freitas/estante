<?php

namespace Estante\Catalog\Domain;

use Illuminate\Support\Str;

/**
 * How we tell that two descriptions without an ISBN are the same work:
 * main title (no subtitle, no series note) plus the first author's surname,
 * lowercased, without accents, punctuation or a leading article.
 *
 * "O Senhor dos Anéis: A Sociedade do Anel" by J.R.R. Tolkien
 * and "Senhor dos Aneis (Trilogia, #1)" by Tolkien, J.R.R.
 * both become "senhor dos aneis|tolkien".
 *
 * It is deliberately strict: a wrong match puts the wrong book on a shelf,
 * which is worse than creating a new edition.
 */
final readonly class MatchKey implements \Stringable
{
    private const LEADING_ARTICLES = ['the', 'a', 'an', 'o', 'os', 'as', 'um', 'uma', 'el', 'la', 'los', 'las'];

    private const NAME_SUFFIXES = ['jr', 'junior', 'sr', 'filho', 'neto', 'sobrinho'];

    private function __construct(public string $value) {}

    /**
     * Null when there is not enough to compare safely (no title or no author).
     *
     * @param  list<string>  $authors
     */
    public static function of(string $title, array $authors): ?self
    {
        $title = self::mainTitle($title);
        $surname = self::surname($authors[0] ?? '');

        if ($title === '' || $surname === '') {
            return null;
        }

        return new self("{$title}|{$surname}");
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function mainTitle(string $title): string
    {
        // "Title (Series, #1)" and "Title: Subtitle" keep only "Title".
        $title = (string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $title);
        $title = explode(':', $title, 2)[0];

        $words = self::words($title);
        if (count($words) > 1 && in_array($words[0], self::LEADING_ARTICLES, true)) {
            array_shift($words);
        }

        return implode(' ', $words);
    }

    private static function surname(string $author): string
    {
        // "Martin, Robert C." is already surname first.
        $author = str_contains($author, ',') ? explode(',', $author, 2)[0] : $author;

        $words = array_values(array_diff(self::words($author), self::NAME_SUFFIXES));

        return $words === [] ? '' : $words[count($words) - 1];
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $text = Str::lower(Str::ascii($text));
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return array_values(array_filter(explode(' ', $text), fn (string $w) => $w !== ''));
    }
}
