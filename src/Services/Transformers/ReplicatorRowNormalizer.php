<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

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

        if ($row instanceof Values) {
            $row = $row->all();
        }

        if ($row instanceof Arrayable && ! is_array($row)) {
            $row = $row->toArray();
        }

        if (! is_array($row)) {
            return null;
        }

        $set = $row['type'] ?? $row['set'] ?? null;
        $values = $this->extractRowValues($row);

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

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function extractRowValues(array $row): array
    {
        $values = [];

        if (isset($row['values']) && is_array($row['values'])) {
            $values = $row['values'];
        }

        if (isset($row['fields']) && is_array($row['fields'])) {
            $values = array_merge($values, $row['fields']);
        }

        $metaKeys = ['type', 'set', 'values', 'fields', 'enabled', 'id', '_id'];

        foreach ($row as $key => $value) {
            if (! is_string($key) || in_array($key, $metaKeys, true)) {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Statamic may store replicator rows as JSON strings in publish values.
     */
    public function decodeReplicatorData(mixed $data): mixed
    {
        $data = $this->unwrap($data);

        if (is_string($data)) {
            $trimmed = trim($data);

            if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
                $decoded = json_decode($data, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return $data;
    }
}
