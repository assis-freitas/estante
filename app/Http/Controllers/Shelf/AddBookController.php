<?php

namespace App\Http\Controllers\Shelf;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use Estante\Catalog\Application\FindOrImportBook;
use Estante\Catalog\Application\RegisterBook;
use Estante\Catalog\Domain\Isbn;
use Estante\Shelf\Application\AddBookToShelf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddBookController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('shelf/Create');
    }

    /** Preview an edition before adding it. Imports it into the catalog on first lookup. */
    public function lookup(Request $request, FindOrImportBook $findBook): JsonResponse
    {
        $data = $request->validate(['isbn' => ['required', 'string', 'max:20']]);

        $book = $findBook(Isbn::fromString($data['isbn']));

        return response()->json(['book' => BookResource::make($book)->resolve()]);
    }

    public function store(
        Request $request,
        FindOrImportBook $findBook,
        RegisterBook $registerBook,
        AddBookToShelf $addToShelf,
    ): RedirectResponse {
        $data = $request->validate([
            'mode' => ['required', 'in:isbn,manual'],
            'isbn' => ['required_if:mode,isbn', 'nullable', 'string', 'max:20'],
            'title' => ['required_if:mode,manual', 'nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
        ]);

        $isbn = filled($data['isbn'] ?? null) ? Isbn::fromString($data['isbn']) : null;

        $book = $data['mode'] === 'isbn'
            ? $findBook($isbn)
            : $registerBook(
                title: $data['title'],
                authors: explode(',', $data['authors'] ?? ''),
                isbn: $isbn,
                pageCount: $data['page_count'] ?? null,
                publishedYear: $data['published_year'] ?? null,
            );

        $copy = $addToShelf($request->user()->id, $book);

        return to_route('shelf.show', $copy)->with('success', "“{$book->title}” is on your shelf.");
    }
}
