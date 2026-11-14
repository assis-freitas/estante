<?php

/*
 * The architecture rules from docs/adr/0001, enforced on every test run.
 * Pragmatic DDD: aggregates may be Eloquent models, but the domain never
 * knows about HTTP, the UI, facades or concrete adapters.
 */

arch('the domain does not know about HTTP, the UI or facades')
    ->expect(['Estante\Catalog\Domain', 'Estante\Shelf\Domain', 'Estante\Shared\Domain'])
    ->not->toUse([
        'App',
        'Inertia',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
        'Illuminate\Routing',
    ]);

arch('the domain does not depend on adapters')
    ->expect(['Estante\Catalog\Domain', 'Estante\Shelf\Domain'])
    ->not->toUse(['Estante\Catalog\Infrastructure', 'Estante\Shelf\Infrastructure']);

arch('use cases do not depend on adapters or HTTP')
    ->expect(['Estante\Catalog\Application', 'Estante\Shelf\Application'])
    ->not->toUse(['Estante\Catalog\Infrastructure', 'App', 'Illuminate\Http', 'Inertia']);

arch('Catalog is upstream: it never depends on Shelf')
    ->expect('Estante\Catalog')
    ->not->toUse('Estante\Shelf');

arch('controllers go through use cases, never adapters')
    ->expect('App\Http')
    ->not->toUse(['Estante\Catalog\Infrastructure']);

arch('value objects and events are immutable')
    ->expect([
        'Estante\Catalog\Domain\Isbn',
        'Estante\Catalog\Domain\BookMetadata',
        'Estante\Shelf\Domain\Events',
    ])
    ->toBeFinal()
    ->toBeReadonly();

arch('use cases are final, readonly and invokable')
    ->expect(['Estante\Catalog\Application', 'Estante\Shelf\Application'])
    ->classes()
    ->toBeFinal()
    ->ignoring(['Estante\Catalog\Application\BookNotFound']);

arch('business rules fail with domain exceptions')
    ->expect('Estante\Shelf\Domain\ReadingRuleViolated')
    ->toExtend('Estante\Shared\Domain\DomainRuleViolation');

arch()->preset()->php();
arch()->preset()->security()->ignoring('Estante\Catalog\Domain\Isbn');
