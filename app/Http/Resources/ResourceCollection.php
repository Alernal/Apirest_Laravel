<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection as BaseCollection;

class ResourceCollection extends BaseCollection
{
    public function toArray(Request $request): array
    {
        return [
            static::$wrap => $this->collection,
            'total' => $this->total(),
            'count' => $this->count(),
            'per_page' => $this->perPage(),
            'current_page' => $this->currentPage(),
            'last_page' => $this->lastPage(),
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
            'total_pages' => (int) ceil($this->total() / $this->perPage()),
        ];
    }
}
