<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Support\Collection;
use Statamic\Fields\Value;

class ReplicatorObjectArrayTransformer implements FieldTransformerInterface
{
    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @return array<int, array<string, mixed>>|array<string, mixed>
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

        $setFilter = is_string($config['set'] ?? null) ? $config['set'] : null;
        $mappings = is_array($config['mappings'] ?? null) ? $config['mappings'] : [];
        $flat = isset($config['flat']) && $config['flat'] === true;
        $flatKeyField = is_string($config['flat_key_field'] ?? null) ? $config['flat_key_field'] : null;
        $flatValueField = is_string($config['flat_value_field'] ?? null) ? $config['flat_value_field'] : null;

        $replicatorData = null;

        if (is_object($item) && method_exists($item, 'get')) {
            $replicatorData = $item->get($replicatorHandle);
        }

        $replicatorData = $this->unwrapValue($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        if ($flat && $flatKeyField && $flatValueField) {
            return $this->processReplicatorRowsFlat($replicatorData, $setFilter, $flatKeyField, $flatValueField);
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

            $rowValues = $rowArray['values'];
            if (! is_array($rowValues)) {
                $rowValues = [];
            }

            $mapped = $this->applyMappings($mappings, $rowValues, $item);

            if (! empty($mapped)) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * @param  array<int|string, mixed>  $replicatorData
     * @param  string|null  $setFilter
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function processReplicatorRowsFlat(array $replicatorData, ?string $setFilter, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($replicatorData as $row) {
            $flatData = $this->extractFlatDataFromRow($row, $setFilter, $keyField, $valueField);
            $result = array_merge($result, $flatData);
        }

        return $result;
    }

    /**
     * @param  mixed  $row
     * @param  string|null  $setFilter
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFlatDataFromRow($row, ?string $setFilter, string $keyField, string $valueField): array
    {
        $originalRow = is_array($row) ? $row : [];
        $directKey = $this->unwrapValue($originalRow[$keyField] ?? null);
        $directValue = $this->unwrapValue($originalRow[$valueField] ?? null);

        if ($directKey !== null && $directKey !== '') {
            return [(string) $directKey => $directValue];
        }

        $rowArray = $this->normalizeReplicatorRow($row);
        if (! $rowArray) {
            return [];
        }

        if ($this->shouldSkipRowBySetFilter($rowArray['set'] ?? null, $setFilter)) {
            return [];
        }

        $rowValues = is_array($rowArray['values']) ? $rowArray['values'] : [];

        $key = $this->unwrapValue($rowValues[$keyField] ?? null);
        $value = $this->unwrapValue($rowValues[$valueField] ?? null);

        if (($key === null || $value === null) && is_array($row)) {
            $key ??= $this->unwrapValue($row[$keyField] ?? null);
            $value ??= $this->unwrapValue($row[$valueField] ?? null);
        }

        if ($key !== null && $key !== '') {
            return [(string) $key => $value];
        }

        return $this->extractFromNestedStructures($rowValues, $keyField, $valueField);
    }

    /**
     * @param  string|null  $rowSet
     * @param  string|null  $setFilter
     */
    protected function shouldSkipRowBySetFilter(?string $rowSet, ?string $setFilter): bool
    {
        return is_string($setFilter) && $setFilter !== '' && $rowSet !== $setFilter;
    }

    /**
     * @param  array<string, mixed>  $rowValues
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromNestedStructures(array $rowValues, string $keyField, string $valueField): array
    {
        $nestedData = $this->extractFieldsFromNestedReplicators($rowValues, $keyField, $valueField);
        if (! empty($nestedData)) {
            return $nestedData;
        }

        $recursiveData = $this->searchRecursivelyForFields($rowValues, $keyField, $valueField);
        if (! empty($recursiveData)) {
            return $recursiveData;
        }

        return $this->extractFromRowValues($rowValues, $keyField, $valueField);
    }

    /**
     * @param  array<string, mixed>  $rowValues
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromRowValues(array $rowValues, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($rowValues as $fieldValue) {
            $unwrapped = $this->unwrapValue($fieldValue);

            if (! is_array($unwrapped)) {
                continue;
            }

            $extracted = $this->extractFromUnwrappedValue($unwrapped, $keyField, $valueField);
            $result = array_merge($result, $extracted);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $unwrapped
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromUnwrappedValue(array $unwrapped, string $keyField, string $valueField): array
    {
        if (isset($unwrapped[0]) && is_array($unwrapped[0])) {
            return $this->extractFromReplicatorRowsArray($unwrapped, $keyField, $valueField);
        }

        if (isset($unwrapped['type']) || isset($unwrapped['set']) || isset($unwrapped['values'])) {
            return $this->extractFromSingleReplicatorRow($unwrapped, $keyField, $valueField);
        }

        return $this->searchRecursivelyForFields($unwrapped, $keyField, $valueField);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromReplicatorRowsArray(array $rows, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($rows as $nestedRow) {
            $extracted = $this->extractFromSingleReplicatorRow($nestedRow, $keyField, $valueField);
            $result = array_merge($result, $extracted);
        }

        return $result;
    }

    /**
     * @param  mixed  $row
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromSingleReplicatorRow($row, string $keyField, string $valueField): array
    {
        $nestedRowArray = $this->normalizeReplicatorRow($row);
        if (! $nestedRowArray) {
            return [];
        }

        $nestedRowValues = $nestedRowArray['values'] ?? [];
        if (! is_array($nestedRowValues)) {
            return [];
        }

        $key = $this->unwrapValue($nestedRowValues[$keyField] ?? null);
        $value = $this->unwrapValue($nestedRowValues[$valueField] ?? null);

        if ($key !== null && $key !== '') {
            return [(string) $key => $value];
        }

        return $this->searchRecursivelyForFields($nestedRowValues, $keyField, $valueField);
    }

    /**
     * @param  array<string, mixed>  $rowValues
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFieldsFromNestedReplicators(array $rowValues, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($rowValues as $fieldValue) {
            $unwrappedValue = $this->unwrapValue($fieldValue);

            if (! is_array($unwrappedValue)) {
                continue;
            }

            $rowsToProcess = $this->getReplicatorRowsToProcess($unwrappedValue);
            if ($rowsToProcess === null) {
                $nestedResult = $this->searchRecursivelyForFields($unwrappedValue, $keyField, $valueField);
                if (! empty($nestedResult)) {
                    $result = array_merge($result, $nestedResult);
                }
                continue;
            }

            foreach ($rowsToProcess as $nestedRow) {
                $extracted = $this->extractFromSingleReplicatorRow($nestedRow, $keyField, $valueField);
                $result = array_merge($result, $extracted);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $unwrappedValue
     * @return array<int, array<string, mixed>>|null
     */
    protected function getReplicatorRowsToProcess(array $unwrappedValue): ?array
    {
        if (isset($unwrappedValue[0]) && is_array($unwrappedValue[0])) {
            return $unwrappedValue;
        }

        if (isset($unwrappedValue['type']) || isset($unwrappedValue['set']) || isset($unwrappedValue['values'])) {
            return [$unwrappedValue];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|mixed  $data
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function searchRecursivelyForFields($data, string $keyField, string $valueField): array
    {
        if (! is_array($data)) {
            return [];
        }

        $key = $this->unwrapValue($data[$keyField] ?? null);
        $value = $this->unwrapValue($data[$valueField] ?? null);

        if ($key !== null && $key !== '') {
            return [(string) $key => $value];
        }

        if ($this->isIndexedArray($data)) {
            return $this->searchInIndexedArray($data, $keyField, $valueField);
        }

        return $this->searchInAssociativeArray($data, $keyField, $valueField);
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function isIndexedArray(array $data): bool
    {
        return ! empty($data) && array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * @param  array<int, mixed>  $data
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function searchInIndexedArray(array $data, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($data as $row) {
            $unwrapped = $this->unwrapValue($row);
            if (! is_array($unwrapped)) {
                continue;
            }

            $extracted = $this->extractFromNormalizedRow($unwrapped, $keyField, $valueField);
            $result = array_merge($result, $extracted);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function searchInAssociativeArray(array $data, string $keyField, string $valueField): array
    {
        $result = [];

        foreach ($data as $value) {
            $unwrapped = $this->unwrapValue($value);
            if (! is_array($unwrapped)) {
                continue;
            }

            if (isset($unwrapped['type']) || isset($unwrapped['set']) || isset($unwrapped['values'])) {
                $extracted = $this->extractFromSingleReplicatorRow($unwrapped, $keyField, $valueField);
            } else {
                $extracted = $this->searchRecursivelyForFields($unwrapped, $keyField, $valueField);
            }

            $result = array_merge($result, $extracted);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    protected function extractFromNormalizedRow(array $row, string $keyField, string $valueField): array
    {
        $normalized = $this->normalizeReplicatorRow($row);
        if (! $normalized) {
            return $this->searchRecursivelyForFields($row, $keyField, $valueField);
        }

        $nestedValues = $normalized['values'] ?? [];
        if (! is_array($nestedValues)) {
            return [];
        }

        $nestedKey = $this->unwrapValue($nestedValues[$keyField] ?? null);
        $nestedValue = $this->unwrapValue($nestedValues[$valueField] ?? null);

        if ($nestedKey !== null && $nestedKey !== '') {
            return [(string) $nestedKey => $nestedValue];
        }

        return $this->searchRecursivelyForFields($nestedValues, $keyField, $valueField);
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

        $setFilter = is_string($config['set'] ?? null) ? $config['set'] : null;
        $mappings = is_array($config['mappings'] ?? null) ? $config['mappings'] : [];

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
}
