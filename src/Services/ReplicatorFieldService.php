<?php

namespace Justbetter\StatamicStructuredData\Services;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Fields\Blueprint;

class ReplicatorFieldService
{
    /** @return array<int, array<string, mixed>> */
    public function getReplicatorFields(EntryContract $dataTemplate): array
    {
        // @phpstan-ignore-next-line
        $collection = $dataTemplate->use_for_collection;
        // @phpstan-ignore-next-line
        $taxonomy = $dataTemplate->use_for_taxonomy;

        if (! $collection && ! $taxonomy) {
            return [];
        }

        $blueprints = $collection
            ? $collection->entryBlueprints()
            : $taxonomy->termBlueprints();

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
            return $field->toArray();
        }

        return is_array($field) ? $field : null;
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
            if (! is_string($setHandle) || ! is_array($setConfig)) {
                continue;
            }

            /** @var array<string, mixed> $setConfig */
            $setDisplay = is_string($setConfig['display'] ?? null) ? $setConfig['display'] : $setHandle;
            $setFields = is_array($setConfig['fields'] ?? null) ? $setConfig['fields'] : [];
            $fieldOptions = $this->parseSetFields($setFields);

            $setOptions[] = [
                'value' => $setHandle,
                'label' => $setDisplay,
                'fields' => $fieldOptions,
            ];
        }

        return $setOptions;
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

            [$setFieldHandle, $setFieldConfig] = $fieldData;

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
        // Handle list array format: [['handle' => 'text', 'field' => [...]]]
        if (is_array($setFieldData) && isset($setFieldData['handle'])) {
            return [
                $setFieldData['handle'],
                $setFieldData['field'] ?? [],
            ];
        }

        // Handle associative array format: ['text' => ['type' => ...]]
        if (is_string($setFieldKey) && is_array($setFieldData)) {
            return [
                $setFieldKey,
                $setFieldData,
            ];
        }

        return null;
    }

    protected function isFieldTypeEligible(?string $fieldType): bool
    {
        if (! is_string($fieldType)) {
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
