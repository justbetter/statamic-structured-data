<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Fieldtype;
use WithCandour\AardvarkSeo\Blueprints\CP\OnPageSeoBlueprint;

class AvailableVariablesFieldtype extends Fieldtype
{
    protected $icon = 'code';

    protected $categories = ['structured_data'];

    protected static $handle = 'structured_data_available_variables';

    public function defaultValue()
    {
        return null;
    }

    public function preload()
    {
        return [
            'variables' => $this->getAvailableVariables(),
        ];
    }

    protected function getAvailableVariables(): array
    {
        return [
            'site' => [
                    ['name' => 'site:name', 'description' => 'Site Name'],
                    ['name' => 'site:url', 'description' => 'Site URL'],
                ],
            'globals' => $this->getGlobalVariables(),
            'entry' => $this->getEntryFields(),
            'term' => $this->getTermFields(),
        ];
    }

    protected function fieldTypeIsEligible(string $fieldType): bool
    {
        $eligibleFieldTypes = [
            'text',
            'assets',
            'bard',
            'toggle',
            'integer',
            'slug',
            'date',
            'entries',
            'aardvark_seo_meta_title',
            'aardvark_seo_meta_description',
        ];

        return in_array($fieldType, $eligibleFieldTypes, true);
    }

    protected function getEntryFields(): array
    {
        $dataTemplate = $this->field->parent();

        if (! $dataTemplate?->use_for_collection?->entryBlueprints()?->first()) {
            return [];
        }

        $fields = collect($dataTemplate?->use_for_collection?->entryBlueprints()?->reduce(function($carry, $blueprint) {
            return array_merge($carry, $blueprint->fields()->items()->toArray());
        }, []) ?? []);

        if(class_exists(OnPageSeoBlueprint::class)) {
            $fields = $fields->merge(OnPageSeoBlueprint::requestBlueprint()->fields()?->items());
        }

        if (!$fields) {
            return [];
        }

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        $collectionFields = $fields->map(function ($field) {
            return $this->setFieldData($field);
        })
        ->filter()
        ->values()
        ->all();

        return array_merge($baseFields, $collectionFields);
    }

    protected function getTermFields(): array
    {
        $dataTemplate = $this->field->parent();

        if (! $dataTemplate?->use_for_taxonomy?->termBlueprints()?->first()) {
            return [];
        }

        $fields = collect($dataTemplate?->use_for_taxonomy?->termBlueprints()?->reduce(function($carry, $blueprint) {
            return array_merge($carry, $blueprint->fields()->items()->toArray());
        }, []) ?? []);

        if(class_exists(OnPageSeoBlueprint::class)) {
            $fields = $fields->merge(OnPageSeoBlueprint::requestBlueprint()->fields()?->items());
        }

        if (!$fields) {
            return [];
        }

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        $collectionFields = $fields->map(function ($field) {
            return $this->setFieldData($field);
        })
        ->filter()
        ->values()
        ->all();

        return array_merge($baseFields, $collectionFields);
    }

    protected function getGlobalVariables(): array
    {
        $variables = collect();

        GlobalSet::all()->each(function ($globalSet) use (&$variables) {
            $fields = $globalSet?->blueprint()?->fields();
            $globalVariables = [];

            if ($fields) {
                $globalVariables = $fields->items()->map(function ($field) use ($globalSet) {
                    $name = ($globalSet->handle() . ':' . ($field['handle'] ?? ''));
                    $description = ($field['field']['display'] ?? ($field['handle'] ?? ''));
                    return $this->setFieldData($field, $name, $description);
                })->values()->all();
            }

            $variables = $variables->merge($globalVariables);
        });

        return $variables->filter()->values()->all();
    }

    protected function getCollectionVariables(string $collectionHandle, array $field): array
    {
        if(!$collectionHandle || $collectionHandle === 'structured_data_templates') {
            return [];
        }

        return Collection::find($collectionHandle)?->entryBlueprints()?->first()?->fields()?->items()?->map(function ($entryField) use ($field) {
            $name = ($field['handle'] . ':' . ($entryField['handle'] ?? ''));
            $description = (($field['field']['display'] ?? '') . ': ' . ($entryField['field']['display'] ?? ($entryField['handle'] ?? '')));
            return $this->setFieldData($entryField, $name, $description, false);
        })->filter()->values()->all();
    }

    protected function setFieldData(array $field, ?string $name = null, ?string $description = null, bool $recursive = true): ?array
    {
        if(!($field['handle'] ?? false) || $field['handle'] === 'parent' || !($field['field']['type'] ?? false) || !$this->fieldTypeIsEligible($field['field']['type'])) {
            return null;
        }

        $children = [];

        if($field['field']['type'] === 'entries' && $recursive) {
            if(!isset($field['field']['collections'][0])) {
                return null;
            }

            $children = $this->getCollectionVariables($field['field']['collections'][0], $field);
        }

        return [
            'name' => $name ?? $field['handle'],
            'description' => $description ?? ($field['field']['display'] ?? $field['handle']),
            'children' => $children
        ];
    }
}
