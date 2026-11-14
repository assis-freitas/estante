# 2. Resolving books without an ISBN

- Status: accepted
- Date: 2026-11-14

## Context

The catalog (`catalog_books`) is shared by every reader, and the ISBN is
what keeps it free of duplicates. But many real descriptions have no ISBN, or
one that no source knows: old and self-published books, ebooks, lines from
another app's export. Importing a whole library must also work without
stopping to ask the reader about each line.

So we need to turn "a title, some authors and maybe an ISBN" into an edition,
automatically, without filling the shared catalog with copies of the same
work.

## Decision

`Catalog\Application\ResolveBook` resolves a `BookDescription` in five steps
and reports which one answered (`BookResolution`):

1. **ISBN**, through `FindOrImportBook` (catalog first, then the sources).
2. **Unknown ISBN**: create the edition from the description, with the ISBN.
   The unique index on `isbn` keeps it from being created twice.
3. **No ISBN, same work in the catalog**, compared by `MatchKey`.
4. **No ISBN, same work in a source**, through
   `BookMetadataProvider::findByTitle()`. Only hits that carry an ISBN count,
   so the edition joins the ISBN-keyed part of the catalog.
5. **Nothing matched**: create the edition from the description.

`MatchKey` is the main title (no subtitle after `:`, no trailing series note
in parentheses, no leading article) plus the first author's surname,
lowercased and without accents or punctuation. It is stored in an indexed
`match_key` column, kept up to date when a `Book` is saved.

Matching is **strict on purpose**: equal keys or nothing. A wrong match puts
the wrong book on someone's shelf; a missed match only costs a duplicate
edition, which can be merged later.

`match_key` is **not unique**: different works can share a title and a
surname. Steps 3 to 5 run under a cache lock per key instead, so two imports
of the same work at the same time do not both create it.

Every created edition records who described it in `source` (`manual`,
`import:goodreads`...), so they can be found and enriched later.

## Consequences

- Imports and manual entry can share one entry point that never blocks.
- A work with no author, or whose title normalizes to nothing, has no key: it
  is always created. That is rare and safe.
- Homonyms (same main title and surname, different works) would be merged by
  steps 3 and 4. We accept that risk for now; adding the publication year to
  the key is the next lever if it shows up.
- Duplicates can still appear (different surnames for the same author,
  translated titles). A later job can merge editions and repoint copies; the
  `source` column tells it where to look.
- Title searches cost external requests; callers that resolve many books
  (imports) should rate-limit.
