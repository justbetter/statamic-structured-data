<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class ReplicatorFlatTransformer
{
    protected ReplicatorRowNormalizer $normalizer;

    public function __construct(ReplicatorRowNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * @param  array<int|string, mixed>  $replicatorData
     * @param  string|null  $setFilter
     * @param  string  $keyField
     * @param  string  $valueField
     * @return array<string, mixed>
     */
    public function transform(array $replicatorData, ?string $setFilter, string $keyField, string $valueField): array
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
        $directKey = $this->normalizer->unwrap($originalRow[$keyField] ?? null);
        $directValue = $this->normalizer->unwrap($originalRow[$valueField] ?? null);

        if ($directKey !== null && $directKey !== '') {
            return [(string) $directKey => $directValue];
        }

        $rowArray = $this->normalizer->normalize($row);
        if (! $rowArray) {
            return [];
        }

        if ($this->shouldSkipRowBySetFilter($rowArray['set'] ?? null, $setFilter)) {
            return [];
        }

        $rowValues = is_array($rowArray['values']) ? $rowArray['values'] : [];

        $key = $this->normalizer->unwrap($rowValues[$keyField] ?? null);
        $value = $this->normalizer->unwrap($rowValues[$valueField] ?? null);

        if (($key === null || $value === null) && is_array($row)) {
            $key ??= $this->normalizer->unwrap($row[$keyField] ?? null);
            $value ??= $this->normalizer->unwrap($row[$valueField] ?? null);
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
            $unwrapped = $this->normalizer->unwrap($fieldValue);

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
        $nestedRowArray = $this->normalizer->normalize($row);
        if (! $nestedRowArray) {
            return [];
        }

        $nestedRowValues = $nestedRowArray['values'] ?? [];
        if (! is_array($nestedRowValues)) {
            return [];
        }

        $key = $this->normalizer->unwrap($nestedRowValues[$keyField] ?? null);
        $value = $this->normalizer->unwrap($nestedRowValues[$valueField] ?? null);

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
            $unwrappedValue = $this->normalizer->unwrap($fieldValue);

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

        $key = $this->normalizer->unwrap($data[$keyField] ?? null);
        $value = $this->normalizer->unwrap($data[$valueField] ?? null);

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
            $unwrapped = $this->normalizer->unwrap($row);
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
            $unwrapped = $this->normalizer->unwrap($value);
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
        $normalized = $this->normalizer->normalize($row);
        if (! $normalized) {
            return $this->searchRecursivelyForFields($row, $keyField, $valueField);
        }

        $nestedValues = $normalized['values'] ?? [];
        if (! is_array($nestedValues)) {
            return [];
        }

        $nestedKey = $this->normalizer->unwrap($nestedValues[$keyField] ?? null);
        $nestedValue = $this->normalizer->unwrap($nestedValues[$valueField] ?? null);

        if ($nestedKey !== null && $nestedKey !== '') {
            return [(string) $nestedKey => $nestedValue];
        }

        return $this->searchRecursivelyForFields($nestedValues, $keyField, $valueField);
    }
}
