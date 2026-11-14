<?php

use Estante\Catalog\Domain\BookMetadata;
use Estante\Catalog\Domain\BookMetadataProvider;
use Estante\Catalog\Domain\Isbn;
use Estante\Catalog\Infrastructure\FirstMatchProvider;

function fixedProvider(?BookMetadata $answer, int &$calls = 0): BookMetadataProvider
{
    return new class($answer, $calls) implements BookMetadataProvider
    {
        public function __construct(private ?BookMetadata $answer, private int &$calls) {}

        public function find(Isbn $isbn): ?BookMetadata
        {
            $this->calls++;

            return $this->answer;
        }
    };
}

beforeEach(fn () => $this->isbn = Isbn::fromString('9780132350884'));

it('returns null when no source knows the ISBN', function () {
    expect((new FirstMatchProvider(fixedProvider(null), fixedProvider(null)))->find($this->isbn))->toBeNull();
});

it('stops at the first complete answer', function () {
    $complete = new BookMetadata($this->isbn, 'Clean Code', ['Robert C. Martin'], 'a', pageCount: 431, coverUrl: 'https://img/a.jpg');
    $secondCalls = 0;

    $found = (new FirstMatchProvider(fixedProvider($complete), fixedProvider(null, $secondCalls)))->find($this->isbn);

    expect($found)->toBe($complete)->and($secondCalls)->toBe(0);
});

it('fills missing cover and page count from later sources, keeping the first title', function () {
    $partial = new BookMetadata($this->isbn, 'Clean Code', ['Robert C. Martin'], 'a');
    $other = new BookMetadata($this->isbn, 'Clean code: a handbook', [], 'b', pageCount: 431, coverUrl: 'https://img/b.jpg');

    $found = (new FirstMatchProvider(fixedProvider($partial), fixedProvider($other)))->find($this->isbn);

    expect($found->title)->toBe('Clean Code')
        ->and($found->source)->toBe('a')
        ->and($found->pageCount)->toBe(431)
        ->and($found->coverUrl)->toBe('https://img/b.jpg');
});
