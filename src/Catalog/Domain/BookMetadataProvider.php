<?php

namespace Estante\Catalog\Domain;

/**
 * Port: somewhere that can tell us about an edition by its ISBN.
 * Adapters live in Catalog\Infrastructure (Open Library, Google Books...).
 */
interface BookMetadataProvider
{
    /** Returns null when the source does not know the ISBN or is unavailable. */
    public function find(Isbn $isbn): ?BookMetadata;
}
