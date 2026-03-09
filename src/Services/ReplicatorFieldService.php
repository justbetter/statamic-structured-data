<?php

namespace Justbetter\StatamicStructuredData\Services;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Taxonomies\Taxonomy;

class ReplicatorFieldService
{
    /** @return array<int, array<string, mixed>> */
    public function getReplicatorFields(EntryContract $dataTemplate): array
    {
        /** @var Entry $dataTemplate */
        /** @var ?Collection $collection */
        $collection = $dataTemplate->use_for_collection; /** @phpstan-ignore-line */
        /** @var ?Taxonomy $taxonomy */
        $taxonomy = $dataTemplate->use_for_taxonomy; /** @phpstan-ignore-line */
        if (! $collection && ! $taxonomy) {
            return [];
        }

        $blueprints = $collection
            ? ($collection->entryBlueprints() ?? false)
            : ($taxonomy->termBlueprints() ?? false);

        if (! $blueprints || ! $blueprints->first()) {
            return [];
        }

        $fieldsArray = $this->extractFieldsFromBlueprints($blueprints);

        return $this->parseReplicatorFields($fieldsArray);
    }

    /**
     * @param  SupportCollection<int, Blueprint>  $blueprints
     * @return array<int, array<string, mixed>>
     */
    protected function extractFieldsFromBlueprints(SupportCollection $blueprints): array
    {
        return $blueprints->reduce(function (array $carry, Blueprint $blueprint): array {
            $items = $blueprint->fields()->items();

            if ($items instanceof \Illuminate\Support\Collection) {
                $items = $items->all();
            }

            return array_merge($carry, is_array($items) ? $items : []);
        }, []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldsArray
     * @return array<int, array<string, mixed>>
     */
    protected function parseReplicatorFields(array $fieldsArray): array
    {
        $replicatorFields = [];

        foreach ($fieldsArray as $field) {
            $field = $this->normalizeField($field);

            if (! is_array($field)) {
                continue;
            }

            /** @var array<string, mixed> $field */
            $fieldConfig = is_array($field['field'] ?? null) ? $field['field'] : [];
            /** @var array<string, mixed> $fieldConfig */
            $fieldType = is_string($fieldConfig['type'] ?? null) ? $fieldConfig['type'] : null;

            if ($fieldType !== 'replicator') {
                continue;
            }

            $replicatorField = $this->buildReplicatorField($field, $fieldConfig);

            if ($replicatorField) {
                $replicatorFields[] = $replicatorField;
            }
        }

        return $replicatorFields;
    }

    /**
     * @param  mixed  $field
     * @return array<string, mixed>|null
     */
    protected function normalizeField($field): ?array
    {
        if (is_object($field) && method_exists($field, 'toArray')) {
            $field = $field->toArray();
        }

        if (! is_array($field)) {
            return null;
        }

        $normalized = [];

        foreach ($field as $key => $value) {
            if (! is_string($key)) {
                return null;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $fieldConfig
     * @return array<string, mixed>|null
     */
    protected function buildReplicatorField(array $field, array $fieldConfig): ?array
    {
        $handle = $field['handle'] ?? null;

        if (! is_string($handle)) {
            return null;
        }

        $display = is_string($fieldConfig['display'] ?? null) ? $fieldConfig['display'] : $handle;
        $sets = is_array($fieldConfig['sets'] ?? null) ? $fieldConfig['sets'] : [];
        $setOptions = $this->parseSets($sets);

        return [
            'handle' => $handle,
            'display' => $display,
            'sets' => $setOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $sets
     * @return array<int, array<string, mixed>>
     */
    protected function parseSets(array $sets): array
    {
        $setOptions = [];

        foreach ($sets as $setHandle => $setConfig) {
            /** @phpstan-ignore-next-line */
            if (! is_string($setHandle) || ! is_array($setConfig)) {
                continue;
            }

            /** @var array<string, mixed> $setConfig */
            $setDisplay = is_string($setConfig['display'] ?? null) ? $setConfig['display'] : $setHandle;
            $setFields = is_array($setConfig['fields'] ?? null) ? $setConfig['fields'] : [];
            $nestedSets = is_array($setConfig['sets'] ?? null) ? $setConfig['sets'] : [];

            $fieldOptions = $this->parseSetFields($setFields);

            if (! empty($nestedSets)) {
                $nestedFields = $this->extractFieldsFromNestedSets($nestedSets);
                $fieldOptions = array_merge($fieldOptions, $nestedFields);
            }

            $setOptions[] = [
                'value' => $setHandle,
                'label' => $setDisplay,
                'fields' => $fieldOptions,
            ];
        }

        return $setOptions;
    }

    /**
     * @param  array<string, mixed>  $nestedSets
     * @return array<int, array<string, mixed>>
     */
    protected function extractFieldsFromNestedSets(array $nestedSets): array
    {
        $allFields = [];

        foreach ($nestedSets as $nestedSetConfig) {
            if (! is_array($nestedSetConfig)) {
                continue;
            }

            /** @var array<string, mixed> $nestedSetConfig */
            $nestedSetFields = is_array($nestedSetConfig['fields'] ?? null) ? $nestedSetConfig['fields'] : [];
            $fields = $this->parseSetFields($nestedSetFields);
            $allFields = array_merge($allFields, $fields);
            $deeperNestedSets = is_array($nestedSetConfig['sets'] ?? null) ? $nestedSetConfig['sets'] : [];

            if (! empty($deeperNestedSets)) {
                $deeperFields = $this->extractFieldsFromNestedSets($deeperNestedSets);
                $allFields = array_merge($allFields, $deeperFields);
            }
        }

        return $allFields;
    }

    /**
     * @param  array<int|string, mixed>  $setFields
     * @return array<int, array<string, mixed>>
     */
    protected function parseSetFields(array $setFields): array
    {
        $fieldOptions = [];

        foreach ($setFields as $setFieldKey => $setFieldData) {
            $fieldData = $this->extractFieldData($setFieldKey, $setFieldData);

            if (! $fieldData) {
                continue;
            }

            /** @var string $setFieldHandle */
            /** @var array<string, mixed> $setFieldConfig */
            [$setFieldHandle, $setFieldConfig] = $fieldData;

            /** @phpstan-ignore-next-line */
            if (! is_string($setFieldHandle)) {
                continue;
            }

            /** @var array<string, mixed> $setFieldConfig */
            $setFieldDisplay = is_string($setFieldConfig['display'] ?? null) ? $setFieldConfig['display'] : $setFieldHandle;
            $setFieldType = is_string($setFieldConfig['type'] ?? null) ? $setFieldConfig['type'] : null;

            if ($this->isFieldTypeEligible($setFieldType)) {
                $fieldOptions[] = [
                    'value' => $setFieldHandle,
                    'label' => $setFieldDisplay,
                    'type' => $setFieldType,
                ];
            }
        }

        return $fieldOptions;
    }

    /**
     * @param  int|string  $setFieldKey
     * @param  mixed  $setFieldData
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    protected function extractFieldData($setFieldKey, $setFieldData): ?array
    {
        if (is_array($setFieldData) && isset($setFieldData['handle'])) {
            $handle = $setFieldData['handle'];

            if (! is_string($handle)) {
                return null;
            }

            $fieldConfig = is_array($setFieldData['field'] ?? null) ? $setFieldData['field'] : [];
            /** @var array<string, mixed> $fieldConfig */
            $fieldConfig = $fieldConfig;

            return [
                $handle,
                $fieldConfig,
            ];
        }

        if (is_string($setFieldKey) && is_array($setFieldData)) {
            /** @var array<string, mixed> $config */
            $config = $setFieldData;

            return [
                $setFieldKey,
                $config,
            ];
        }

        return null;
    }

    protected function isFieldTypeEligible(?string $fieldType): bool
    {
        if ($fieldType === null) {
            return false;
        }

        $eligibleFieldTypes = [
            'text',
            'textarea',
            'markdown',
            'assets',
            'bard',
            'toggle',
            'integer',
            'float',
            'slug',
            'date',
            'time',
            'datetime',
            'entries',
            'terms',
            'users',
            'link',
            'url',
        ];

        return in_array($fieldType, $eligibleFieldTypes, true);
    }
}
