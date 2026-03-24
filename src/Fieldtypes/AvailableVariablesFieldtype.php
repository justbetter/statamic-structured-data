<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Fieldtype;
use Statamic\Globals\GlobalSet as StatamicGlobalSet;
use Statamic\Taxonomies\Taxonomy;

class AvailableVariablesFieldtype extends Fieldtype
{
    /** @var string */
    protected $icon = 'code';

    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_available_variables';

    /** @return null */
    public function defaultValue()
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function preload(): array
    {
        return [
            'variables' => $this->getAvailableVariables(),
        ];
    }

    /** @return array<string, array<int, mixed>> */
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

    /** @return array<int, array<string, mixed>> */
    protected function getEntryFields(): array
    {
        /** @var ?Entry $dataTemplate */
        $dataTemplate = $this->field->parent();
        /** @var ?Collection $collection */
        $collection = $dataTemplate?->get('use_for_collection') ?? $dataTemplate?->use_for_collection; /** @phpstan-ignore-line */
        if (! $collection instanceof Collection) {
            return [];
        }

        $blueprints = $collection->entryBlueprints();

        if (! $blueprints || ! $blueprints->first()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $fieldsArray */
        $fieldsArray = $blueprints->reduce(function (array $carry, $blueprint): array {
            $items = $this->normalizeBlueprintItems($blueprint->fields()->items());

            return array_merge($carry, $items);
        }, []);

        $fields = collect($fieldsArray);

        if ($fields->isEmpty()) {
            return [];
        }

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        $collectionFields = $fields->map(function (array $field) {
            return $this->setFieldData($field);
        })
            ->filter()
            ->values()
            ->all();

        return array_merge($baseFields, $collectionFields);
    }

    /** @return array<int, array<string, mixed>> */
    protected function getTermFields(): array
    {
        /** @var ?Entry $dataTemplate */
        $dataTemplate = $this->field->parent();
        /** @var ?Taxonomy $taxonomy */
        $taxonomy = $dataTemplate?->get('use_for_taxonomy') ?? $dataTemplate?->use_for_taxonomy; /** @phpstan-ignore-line */
        if (! $taxonomy instanceof Taxonomy) {
            return [];
        }

        $blueprints = $taxonomy->termBlueprints();

        if (! $blueprints || ! $blueprints->first()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $fieldsArray */
        $fieldsArray = $blueprints->reduce(function (array $carry, $blueprint): array {
            $items = $this->normalizeBlueprintItems($blueprint->fields()->items());

            return array_merge($carry, $items);
        }, []);

        $fields = collect($fieldsArray);

        if ($fields->isEmpty()) {
            return [];
        }

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        $collectionFields = $fields->map(function (array $field) {
            return $this->setFieldData($field);
        })
            ->filter()
            ->values()
            ->all();

        return array_merge($baseFields, $collectionFields);
    }

    /** @return array<int, mixed> */
    protected function getGlobalVariables(): array
    {
        /** @var SupportCollection<int, array<string, mixed>> $variables */
        $variables = collect();

        GlobalSet::all()->each(function ($globalSet) use (&$variables): void {
            /** @var StatamicGlobalSet $globalSet */
            $fields = $globalSet->blueprint()?->fields();
            $globalVariables = [];

            if ($fields) {
                $globalVariables = $fields->items()->map(function (array $field) use ($globalSet): ?array {
                    $name = ($globalSet->handle().':'.($field['handle'] ?? ''));
                    $description = ($field['field']['display'] ?? ($field['handle'] ?? ''));

                    return $this->setFieldData($field, $name, $description);
                })->values()->all();
            }

            $variables = $variables->merge($globalVariables);
        });

        return $variables->filter()->values()->all();
    }

    /** @param array<string, mixed> $field
     * @return array<int, array<string, mixed>>
     */
    protected function getCollectionVariables(string $collectionHandle, array $field): array
    {
        if (! $collectionHandle || $collectionHandle === 'structured_data_templates') {
            return [];
        }

        $collection = CollectionFacade::find($collectionHandle);

        if (! $collection) {
            return [];
        }

        $blueprint = $collection->entryBlueprints()->first();

        if (! $blueprint) {
            return [];
        }

        return $blueprint->fields()->items()->map(function (array $entryField) use ($field) {
            $entryFieldConfig = is_array($entryField['field'] ?? null) ? $entryField['field'] : [];
            $parentFieldConfig = is_array($field['field'] ?? null) ? $field['field'] : [];
            $fieldHandle = is_string($field['handle'] ?? null) ? $field['handle'] : '';
            $entryFieldHandle = is_string($entryField['handle'] ?? null) ? $entryField['handle'] : '';
            $name = $fieldHandle.':'.$entryFieldHandle;
            $parentDisplay = is_string($parentFieldConfig['display'] ?? null) ? $parentFieldConfig['display'] : '';
            $entryDisplay = is_string($entryFieldConfig['display'] ?? null) ? $entryFieldConfig['display'] : '';
            $description = $parentDisplay.': '.($entryDisplay ?: $entryFieldHandle);

            return $this->setFieldData($entryField, $name, $description, false);
        })->filter()->values()->all();
    }

    /** @param array<string, mixed> $field
     * @return array{name: string, description: string, children: array<int, array<string, mixed>>}|null
     */
    protected function setFieldData(array $field, ?string $name = null, ?string $description = null, bool $recursive = true): ?array
    {
        $fieldHandle = $field['handle'] ?? null;
        /** @var array<string, mixed>|null $fieldConfig */
        $fieldConfig = $field['field'] ?? null;
        $fieldType = is_array($fieldConfig) ? ($fieldConfig['type'] ?? null) : null;

        if (! is_string($fieldHandle) || $fieldHandle === 'parent' || ! is_string($fieldType) || ! $this->fieldTypeIsEligible($fieldType)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $children */
        $children = [];

        if ($fieldType === 'entries' && $recursive) {
            $collections = is_array($fieldConfig['collections'] ?? null) ? $fieldConfig['collections'] : null;

            if (! isset($collections[0]) || ! is_string($collections[0])) {
                return null;
            }

            $children = $this->getCollectionVariables($collections[0], $field);
        }

        $descriptionValue = is_string($description) ? $description : null;
        $display = is_array($fieldConfig) && is_string($fieldConfig['display'] ?? null) ? $fieldConfig['display'] : null;

        return [
            'name' => $name ?? $fieldHandle,
            'description' => $descriptionValue ?? $display ?? $fieldHandle,
            'children' => $children,
        ];
    }

    /**
     * Normalizes blueprint items to ensure they are an array.
     *
     * @param  mixed  $items
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeBlueprintItems($items): array
    {
        if (is_array($items)) {
            /** @var array<int, array<string, mixed>> $items */
            return $items;
        }

        if ($items instanceof \Illuminate\Support\Collection) {
            /** @var array<int, array<string, mixed>> $result */
            $result = $items->all();

            return $result;
        }

        return [];
    }
}
