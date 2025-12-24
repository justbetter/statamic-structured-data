<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Support\Collection;
use Statamic\Fields\Value;

class ReplicatorObjectArrayTransformer implements FieldTransformerInterface
{
    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @return array<int, array<string, mixed>>
     */
    public function transform(array $field, $item = null): array
    {
        /** @var array<string, mixed>|null $config */
        $config = $field['config'] ?? null;

        if (! is_array($config)) {
            return [];
        }

        $replicatorHandle = $config['replicator_field'] ?? null;

        if (! is_string($replicatorHandle) || $replicatorHandle === '') {
            return [];
        }

        $setFilter = $config['set'] ?? null;
        $setFilter = is_string($setFilter) || $setFilter === null ? $setFilter : null;
        $mappingsRaw = $config['mappings'] ?? [];
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = is_array($mappingsRaw) ? $mappingsRaw : [];

        $replicatorData = null;

        if (is_object($item) && method_exists($item, 'get')) {
            $replicatorData = $item->get($replicatorHandle);
        }

        $replicatorData = $this->unwrapValue($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        return $this->processReplicatorRows($replicatorData, $setFilter, $mappings, $item);
    }

    /**
     * @param  array<int|string, mixed>  $replicatorData
     * @param  array<int, array<string, mixed>>  $mappings
     * @param  mixed  $item
     * @return array<int, array<string, mixed>>
     */
    protected function processReplicatorRows(array $replicatorData, ?string $setFilter, array $mappings, $item): array
    {
        $results = [];

        foreach ($replicatorData as $row) {
            $rowArray = $this->normalizeReplicatorRow($row);

            if (! $rowArray) {
                continue;
            }

            $rowSet = $rowArray['set'] ?? null;

            if (is_string($setFilter) && $setFilter !== '' && $rowSet !== $setFilter) {
                continue;
            }

            $rowValues = $this->ensureArrayValues($rowArray['values'] ?? null);

            $mapped = $this->applyMappings($mappings, $rowValues, $item);

            if (! empty($mapped)) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @param  array<string, mixed>  $rowValues
     * @param  mixed  $item
     * @return array<string, mixed>
     */
    protected function applyMappings(array $mappings, array $rowValues, $item): array
    {
        $mapped = [];

        foreach ($mappings as $mapping) {
            $mappedKey = $mapping['key'] ?? null;
            if (! is_string($mappedKey) || $mappedKey === '') {
                continue;
            }

            $mode = $mapping['mode'] ?? 'field';

            if ($mode === 'static') {
                $mapped[$mappedKey] = $mapping['static'] ?? null;

                continue;
            }

            if ($mode === 'nested_replicator') {
                $nestedField = [
                    'type' => 'replicator_object_array',
                    'config' => $mapping['nested'] ?? [],
                ];
                $mapped[$mappedKey] = $this->transformNested($nestedField, $item, $rowValues);

                continue;
            }

            $fieldHandle = $mapping['field'] ?? null;
            if (! is_string($fieldHandle) || $fieldHandle === '') {
                continue;
            }

            $mapped[$mappedKey] = $this->unwrapValue($rowValues[$fieldHandle] ?? null);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @param  array<string, mixed>|null  $sourceData
     * @return array<int, array<string, mixed>>
     */
    protected function transformNested(array $field, $item = null, ?array $sourceData = null): array
    {
        /** @var array<string, mixed>|null $config */
        $config = $field['config'] ?? null;

        if (! is_array($config)) {
            return [];
        }

        $replicatorHandle = $config['replicator_field'] ?? null;

        if (! is_string($replicatorHandle) || $replicatorHandle === '') {
            return [];
        }

        $setFilter = $config['set'] ?? null;
        $setFilter = is_string($setFilter) || $setFilter === null ? $setFilter : null;
        $mappingsRaw = $config['mappings'] ?? [];
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = is_array($mappingsRaw) ? $mappingsRaw : [];

        $replicatorData = null;

        if (is_array($sourceData) && array_key_exists($replicatorHandle, $sourceData)) {
            $replicatorData = $sourceData[$replicatorHandle];
        } elseif (is_object($item) && method_exists($item, 'get')) {
            $replicatorData = $item->get($replicatorHandle);
        }

        $replicatorData = $this->unwrapValue($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        return $this->processReplicatorRows($replicatorData, $setFilter, $mappings, $item);
    }

    /**
     * @param  mixed  $row
     * @return array<string, mixed>|null
     */
    protected function normalizeReplicatorRow($row): ?array
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

        foreach ($values as $k => $v) {
            $values[$k] = $this->unwrapValue($v);
        }

        return [
            'set' => is_string($set) ? $set : null,
            'values' => $values,
        ];
    }

    /**
     * @param  mixed  $value
     */
    protected function unwrapValue($value): mixed
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        return $value;
    }

    /**
     * Ensures the given value is an array, converting non-arrays to empty array.
     *
     * @param  mixed  $values
     * @return array<string, mixed>
     */
    protected function ensureArrayValues($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        /** @var array<string, mixed> $values */
        return $values;
    }
}
