<?php

namespace App\Http\Controllers\Shelf;

use App\Http\Controllers\Controller;
use App\Http\Resources\CopyResource;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\ReadingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShelfController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReadingStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $userId = $request->user()->id;

        $copies = Copy::query()
            ->ownedBy($userId)
            ->with('book')
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->whereHas('book', function (Builder $book) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $book->whereRaw('lower(title) like ?', [$like])
                    ->orWhereRaw('lower(authors) like ?', [$like])
                    ->orWhere('isbn', preg_replace('/[\s-]/', '', $term));
            }))
            ->latest('updated_at')
            ->get();

        $counts = Copy::query()->ownedBy($userId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('shelf/Index', [
            'copies' => CopyResource::collection($copies)->resolve(),
            'counts' => collect(ReadingStatus::cases())->mapWithKeys(fn (ReadingStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'statuses' => self::statusOptions(),
            'filters' => ['status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? ''],
        ]);
    }

    public function show(Copy $copy): Response
    {
        Gate::authorize('view', $copy);

        return Inertia::render('shelf/Show', [
            'copy' => CopyResource::make($copy->load('book'))->resolve(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function destroy(Copy $copy): RedirectResponse
    {
        Gate::authorize('delete', $copy);
        $copy->delete();

        return to_route('shelf.index');
    }

    /** @return list<array{value: string, label: string}> */
    private static function statusOptions(): array
    {
        return array_map(
            fn (ReadingStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            ReadingStatus::cases(),
        );
    }
}
