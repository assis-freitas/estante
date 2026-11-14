<?php

namespace Estante\Catalog\Domain;

/**
 * Port: somewhere that can tell us about an edition.
 * Adapters live in Catalog\Infrastructure (Open Library, Google Books...).
 */
interface BookMetadataProvider
{
    /** Returns null when the source does not know the ISBN or is unavailable. */
    public function find(Isbn $isbn): ?BookMetadata;

    /**
     * An edition, with an ISBN, of exactly this work: same MatchKey as the
     * title and author given. Returns null rather than a "close enough" guess.
     */
    public function findByTitle(string $title, string $author): ?BookMetadata;
}
