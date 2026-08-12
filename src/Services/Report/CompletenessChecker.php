<?php

namespace Justbetter\StatamicStructuredData\Services\Report;

use Illuminate\Support\Arr;

class CompletenessChecker
{
    /**
     * Compare template schema definitions with transformed output and return empty leaf paths.
     *
     * @param  array<int, mixed>  $schemas  Raw or parsed schema_data from the template
     * @param  array<int, mixed>  $transformedSchemas  Output of parseAndTransformSchemas
     * @return array<int, array{schema_index: int, schema_type: string|null, field_path: string}>
     */
    public function findEmptyFields(array $schemas, array $transformedSchemas): array
    {
        $issues = [];

        foreach ($schemas as $index => $schema) {
            if (! is_array($schema)) {
                continue;
            }

            /** @var array<string, mixed> $schema */
            $schemaType = null;
            if (isset($schema['specialProps']) && is_array($schema['specialProps'])) {
                $type = $schema['specialProps']['type'] ?? null;
                $schemaType = is_string($type) ? $type : null;
            }

            $transformedRaw = $transformedSchemas[$index] ?? [];
            /** @var array<string, mixed> $transformed */
            $transformed = is_array($transformedRaw) ? $transformedRaw : [];

            foreach ($this->collectExpectedPaths($schema) as $path) {
                if ($this->isEmpty(Arr::get($transformed, $path))) {
                    $issues[] = [
                        'schema_index' => $index,
                        'schema_type' => $schemaType,
                        'field_path' => $path,
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    public function collectExpectedPaths(array $schema, string $prefix = ''): array
    {
        $paths = [];

        if (isset($schema['specialProps']) && is_array($schema['specialProps'])) {
            foreach (['id' => '@id'] as $prop => $jsonKey) {
                if (! array_key_exists($prop, $schema['specialProps'])) {
                    continue;
                }

                $value = $schema['specialProps'][$prop];
                if ($this->looksLikeDynamicValue($value)) {
                    $paths[] = $prefix === '' ? $jsonKey : $prefix.'.'.$jsonKey;
                }
            }
        }

        if (! isset($schema['fields']) || ! is_array($schema['fields'])) {
            return $paths;
        }

        foreach ($schema['fields'] as $field) {
            if (! is_array($field) || ! isset($field['key']) || ! is_string($field['key']) || $field['key'] === '') {
                continue;
            }

            $key = $field['key'];
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $type = $field['type'] ?? null;

            if ($type === 'object' && isset($field['value']) && is_array($field['value'])) {
                /** @var array<string, mixed> $nested */
                $nested = $field['value'];
                $paths = array_merge($paths, $this->collectExpectedPaths($nested, $path));

                continue;
            }

            if ($type === 'object_array' && isset($field['values']) && is_array($field['values'])) {
                foreach ($field['values'] as $i => $value) {
                    if (! is_array($value)) {
                        continue;
                    }

                    /** @var array<string, mixed> $value */
                    $paths = array_merge($paths, $this->collectExpectedPaths($value, $path.'.'.$i));
                }

                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    public function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    protected function looksLikeDynamicValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return str_contains($value, '{{') || str_contains($value, '@');
    }
}
