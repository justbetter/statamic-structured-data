<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class ReplicatorMappedTransformer
{
    public function __construct(
        protected ReplicatorRowNormalizer $normalizer
    ) {}

    /**
     * @param  array<int|string, mixed>  $replicatorData
     * @param  array<int, array<string, mixed>>  $mappings
     * @param  mixed  $item
     * @return array<int, array<string, mixed>>
     */
    public function transform(array $replicatorData, ?string $setFilter, array $mappings, $item): array
    {
        $results = [];

        foreach ($replicatorData as $row) {
            $rowArray = $this->normalizer->normalize($row);

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

            $mapped[$mappedKey] = $this->normalizer->unwrap($rowValues[$fieldHandle] ?? null);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @param  array<string, mixed>|null  $sourceData
     * @return array<int, array<string, mixed>>
     */
    public function transformNested(array $field, $item = null, ?array $sourceData = null): array
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
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = is_array($config['mappings'] ?? null) ? $config['mappings'] : [];

        $replicatorData = null;

        if (is_array($sourceData) && array_key_exists($replicatorHandle, $sourceData)) {
            $replicatorData = $sourceData[$replicatorHandle];
        } elseif (is_object($item) && method_exists($item, 'get')) {
            $replicatorData = $item->get($replicatorHandle);
        }

        $replicatorData = $this->normalizer->unwrap($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        return $this->transform($replicatorData, $setFilter, $mappings, $item);
    }
}
