<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Support\Collection;
use Statamic\Fields\Value;

class ReplicatorRowNormalizer
{
    /**
     * @param  mixed  $row
     * @return array<string, mixed>|null
     */
    public function normalize($row): ?array
    {
        if ($row instanceof Value) {
            $row = $row->value();
        }

        if ($row instanceof Collection) {
            $row = $row->all();
        }

        if (! is_array($row)) {
            return null;
        }

        $set = $row['type'] ?? $row['set'] ?? null;
        $values = $row['values'] ?? $row;

        if (! is_array($values)) {
            $values = [];
        }

        foreach ($values as $key => $value) {
            $values[$key] = $this->unwrap($value);
        }

        return [
            'set' => is_string($set) ? $set : null,
            'values' => $values,
        ];
    }

    /**
     * @param  mixed  $value
     */
    public function unwrap($value): mixed
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        return $value;
    }
}
