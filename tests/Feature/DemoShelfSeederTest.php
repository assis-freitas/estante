<?php

use Database\Seeders\DemoShelfSeeder;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\ReadingStatus;

it('builds a demo shelf with every reading status, and can run twice', function () {
    $this->seed(DemoShelfSeeder::class);
    $this->seed(DemoShelfSeeder::class);

    expect(Copy::count())->toBe(10)
        ->and(Copy::query()->distinct()->pluck('status')->all())
        ->toEqualCanonicalizing(ReadingStatus::cases());
});
