<?php

namespace Estante\Catalog\Application;

/** Which step of ResolveBook found (or created) the edition. */
enum BookResolution: string
{
    /** The ISBN was in the catalog or a source knew it. */
    case FoundByIsbn = 'found_by_isbn';

    /** Nobody knew the ISBN, so the edition was created from the description. */
    case CreatedWithIsbn = 'created_with_isbn';

    /** No ISBN, but the catalog already had this work (same MatchKey). */
    case FoundByTitle = 'found_by_title';

    /** No ISBN; a source found the work by title and author. */
    case ImportedByTitle = 'imported_by_title';

    /** Nothing matched, so the edition was created from the description. */
    case Created = 'created';
}
