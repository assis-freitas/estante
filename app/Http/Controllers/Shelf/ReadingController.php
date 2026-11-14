<?php

namespace App\Http\Controllers\Shelf;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Estante\Shelf\Application\ChangeReadingStatus;
use Estante\Shelf\Application\ReviewCopy;
use Estante\Shelf\Domain\Copy;
use Estante\Shelf\Domain\ReadingStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReadingController extends Controller
{
    public function status(Request $request, Copy $copy, ChangeReadingStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('update', $copy);
        $data = $request->validate([
            'status' => ['required', Rule::enum(ReadingStatus::class)],
            'on' => ['nullable', 'date'],
        ]);

        $changeStatus(
            $copy,
            ReadingStatus::from($data['status']),
            isset($data['on']) ? CarbonImmutable::parse($data['on']) : CarbonImmutable::today(),
        );

        return back();
    }

    public function review(Request $request, Copy $copy, ReviewCopy $review): RedirectResponse
    {
        Gate::authorize('update', $copy);
        $data = $request->validate([
            'rating' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $review($copy, $data['rating'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'Saved.');
    }
}
