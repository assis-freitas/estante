<?php

namespace App\Http\Resources;

use Estante\Catalog\Domain\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Book */
class BookResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn?->value,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors,
            'publisher' => $this->publisher,
            'published_year' => $this->published_year,
            'page_count' => $this->page_count,
            'cover_url' => $this->cover_url,
            'description' => $this->description,
        ];
    }
}
