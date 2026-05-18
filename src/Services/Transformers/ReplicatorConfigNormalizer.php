<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class ReplicatorConfigNormalizer
{
    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>|null
     */
    public function normalizeFieldConfig(array $field): ?array
    {
        /** @var array<string, mixed> $config */
        $config = is_array($field['config'] ?? null) ? $field['config'] : [];

        /** @var array<string, mixed> $valueConfig */
        $valueConfig = is_array($field['value'] ?? null) ? $field['value'] : [];

        $config = array_merge($valueConfig, $config);

        if ($config === []) {
            return null;
        }

        $replicatorField = $this->normalizeString($config['replicator_field'] ?? null) ?? '';
        $rawMappings = $config['mappings'] ?? $field['mappings'] ?? [];
        $mappings = $this->normalizeMappings(is_array($rawMappings) ? $rawMappings : []);

        if ($replicatorField === '' && $mappings === []) {
            return null;
        }

        $config['replicator_field'] = $replicatorField;
        $config['set'] = $this->normalizeString($config['set'] ?? null, allowEmpty: true) ?? '';
        $config['flat_key_field'] = $this->normalizeString($config['flat_key_field'] ?? null) ?? '';
        $config['flat_value_field'] = $this->normalizeString($config['flat_value_field'] ?? null) ?? '';
        $config['mappings'] = $mappings;
        $config['flat'] = ($config['flat'] ?? false) === true;

        return $config;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array<int, array<string, mixed>>
     */
    public function normalizeMappings(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            $key = $this->normalizeString($mapping['key'] ?? null);

            if ($key === null) {
                continue;
            }

            $mode = $this->normalizeString($mapping['mode'] ?? 'field') ?? 'field';

            $entry = [
                'key' => $key,
                'mode' => $mode,
            ];

            if ($mode === 'static') {
                $entry['static'] = $mapping['static'] ?? null;

                $normalized[] = $entry;

                continue;
            }

            if ($mode === 'nested_replicator') {
                $nested = $mapping['nested'] ?? [];

                if (is_array($nested)) {
                    $entry['nested'] = $nested;
                }

                $normalized[] = $entry;

                continue;
            }

            $fieldHandle = $this->normalizeString($mapping['field'] ?? null);

            if ($fieldHandle === null) {
                continue;
            }

            $entry['field'] = $fieldHandle;
            $normalized[] = $entry;
        }

        return $normalized;
    }

    public function normalizeString(mixed $value, bool $allowEmpty = false): ?string
    {
        if (is_string($value)) {
            return $allowEmpty ? $value : ($value !== '' ? $value : null);
        }

        if (is_array($value) && is_string($value['value'] ?? null)) {
            $stringValue = $value['value'];

            return $allowEmpty ? $stringValue : ($stringValue !== '' ? $stringValue : null);
        }

        if (is_numeric($value)) {
            $stringValue = (string) $value;

            return $allowEmpty ? $stringValue : $stringValue;
        }

        return null;
    }
}
