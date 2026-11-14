<?php

namespace Estante\Catalog\Infrastructure;

use Estante\Catalog\Domain\BookMetadataProvider;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

/** Wires the Catalog ports to their adapters. */
final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookMetadataProvider::class, fn ($app) => new FirstMatchProvider(
            new OpenLibraryProvider($app->make(Http::class)),
            new GoogleBooksProvider($app->make(Http::class), config('services.google_books.key')),
        ));
    }
}
