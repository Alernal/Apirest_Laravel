<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSearch
{
    public function searchFields()
    {
        return [];
    }

    public function scopeSearch(Builder $builder, $search = '')
    {
        $search = $search ?: request()->get('search');
        if (!$search) return;

        $fields = $this->searchFields();

        $builder->where(function (Builder $builder) use ($fields, $search) {
            foreach (explode(' ', $search) as $word) {
                foreach ($fields as $field) {
                    if (str_contains($field, '.')) {
                        // Campo relacionado (e.g. user.email)
                        [$relation, $relationField] = explode('.', $field, 2);
                        $builder->orWhereHas($relation, function (Builder $q) use ($relationField, $word) {
                            $q->where($relationField, 'LIKE', "%{$word}%");
                        });
                    } else {
                        // Campo directo
                        $builder->orWhere($field, 'LIKE', "%{$word}%");
                    }
                }
                // $builder->whereAny($fields, 'LIKE', "%{$word}%");
            }
        });
    }
}
