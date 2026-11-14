<?php

namespace App\Http\Resources;

use Estante\Shelf\Domain\Copy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Copy */
class CopyResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'started_on' => $this->started_on?->toDateString(),
            'finished_on' => $this->finished_on?->toDateString(),
            'rating' => $this->rating,
            'notes' => $this->notes,
            'added_at' => $this->created_at?->toDateString(),
            'book' => BookResource::make($this->whenLoaded('book')),
        ];
    }
}
