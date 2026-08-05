<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Fieldset as FieldsetFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\LabeledValue;
use Statamic\Globals\GlobalSet as StatamicGlobalSet;
use Statamic\SeoPro\Cascade;
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
            'runway' => $this->getRunwayFields(),
        ];
    }

    protected function fieldTypeIsEligible(string $fieldType): bool
    {
        $eligibleFieldTypes = [
            'text',
            'assets',
            'bard',
            'toggle',
            'select',
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
        $collection = $dataTemplate?->use_for_collection;
        if (! $collection instanceof Collection) {
            return [];
        }

        /** @var SupportCollection<int, Blueprint>|array<int, Blueprint>|null $entryBlueprints */
        $entryBlueprints = $collection->entryBlueprints();
        $blueprints = collect($entryBlueprints)->filter();

        /** @var array<int, array<string, mixed>> $fieldsArray */
        $fieldsArray = $blueprints->reduce(function (array $carry, $blueprint): array {
            $items = $this->normalizeBlueprintItems($blueprint->fields()->items());

            return array_merge($carry, $items);
        }, []);

        $fields = collect($fieldsArray);

        $collectionFields = $fields->map(function (array $field) {
            return $this->setFieldData($field);
        })
            ->filter()
            ->values()
            ->all();

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge(
            $baseFields,
            $collectionFields,
            $this->getSeoProVariablesForSection($collection)
        );
    }

    /** @return array<int, array<string, mixed>> */
    protected function getTermFields(): array
    {
        /** @var ?Entry $dataTemplate */
        $dataTemplate = $this->field->parent();
        /** @var ?Taxonomy $taxonomy */
        $taxonomy = $dataTemplate?->use_for_taxonomy;
        if (! $taxonomy instanceof Taxonomy) {
            return [];
        }

        /** @var SupportCollection<int, Blueprint>|array<int, Blueprint>|null $termBlueprints */
        $termBlueprints = $taxonomy->termBlueprints();
        $blueprints = collect($termBlueprints)->filter();

        /** @var array<int, array<string, mixed>> $fieldsArray */
        $fieldsArray = $blueprints->reduce(function (array $carry, $blueprint): array {
            $items = $this->normalizeBlueprintItems($blueprint->fields()->items());

            return array_merge($carry, $items);
        }, []);

        $fields = collect($fieldsArray);

        $collectionFields = $fields->map(function (array $field) {
            return $this->setFieldData($field);
        })
            ->filter()
            ->values()
            ->all();

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge(
            $baseFields,
            $collectionFields,
            $this->getSeoProVariablesForSection($taxonomy)
        );
    }

    /** @return array<int, array<string, mixed>> */
    protected function getRunwayFields(): array
    {
        if (! RunwaySupport::isInstalled()) {
            return [];
        }

        $resourceHandle = $this->resolveRunwayResourceHandle($this->field->parent());

        if (! $resourceHandle) {
            return [];
        }

        $resource = RunwaySupport::findResource($resourceHandle);

        if (! $resource) {
            return [];
        }

        $blueprint = $resource->blueprint();
        $items = $this->normalizeBlueprintItems($blueprint->fields()->items());

        $blueprintFields = collect($items)
            ->map(fn (array $field) => $this->setFieldData($field))
            ->filter()
            ->values()
            ->all();

        $blueprintHandles = collect($blueprintFields)->pluck('name')->filter()->all();

        $modelAttributes = $this->getRunwayModelAttributeVariables($resource->model(), $blueprintHandles);

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge($baseFields, $blueprintFields, $modelAttributes);
    }

    protected function resolveRunwayResourceHandle(mixed $dataTemplate): ?string
    {
        if (! $dataTemplate instanceof Entry) {
            return null;
        }

        $runwayHandle = $dataTemplate->use_for_runway ?? null;
        if ($runwayHandle instanceof LabeledValue) {
            $runwayHandle = $runwayHandle->value();
        }

        return is_string($runwayHandle) && $runwayHandle !== '' ? $runwayHandle : null;
    }

    /**
     * @param  array<int, mixed>  $existingHandles
     * @return array<int, array<string, mixed>>
     */
    protected function getRunwayModelAttributeVariables(Model $model, array $existingHandles): array
    {
        $existing = collect($existingHandles)
            ->filter(fn ($handle): bool => is_string($handle))
            ->values()
            ->all();

        $attributeNames = collect()
            ->merge($model->getFillable())
            ->merge($model->getAppends())
            ->merge(array_keys($model->getAttributes()))
            ->filter(fn ($name): bool => is_string($name) && $name !== '')
            ->unique()
            ->reject(fn (string $name): bool => in_array($name, $existing, true) || $name === 'parent')
            ->values();

        return $attributeNames
            ->map(fn (string $name): array => [
                'name' => $name,
                'description' => $name,
                'children' => [],
            ])
            ->all();
    }

    protected function seoProIsInstalled(): bool
    {
        return class_exists(Cascade::class);
    }

    protected function seoIsEnabledForSection(Collection|Taxonomy $section): bool
    {
        if (! $this->seoProIsInstalled()) {
            return false;
        }

        return $section->cascade('seo') !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getSeoProVariablesForSection(Collection|Taxonomy $section): array
    {
        if (! $this->seoIsEnabledForSection($section)) {
            return [];
        }

        return $this->getSeoProVariables();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getSeoProVariables(): array
    {
        $variables = [
            'title' => 'Meta Title',
            'compiled_title' => 'Compiled Title',
            'description' => 'Meta Description',
            'canonical_url' => 'Canonical URL',
            'og_title' => 'Open Graph Title',
            'og_type' => 'Open Graph Type',
            'image' => 'Open Graph Image',
        ];

        return collect($variables)
            ->map(fn (string $description, string $handle): array => [
                'name' => 'seo:'.$handle,
                'description' => $description,
                'children' => [],
            ])
            ->values()
            ->all();
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
        /** @var array<string, mixed> $fieldConfig */
        $fieldConfig = is_array($field['field'] ?? null) ? $field['field'] : [];
        $fieldType = $fieldConfig['type'] ?? null;

        if (! is_string($fieldHandle) || $fieldHandle === 'parent' || ! is_string($fieldType)) {
            return null;
        }

        $isGroupField = $fieldType === 'group';
        if (! $isGroupField && ! $this->fieldTypeIsEligible($fieldType)) {
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

        if ($isGroupField && $recursive) {
            $children = $this->getGroupVariables($field, $fieldConfig);
        }

        $descriptionValue = is_string($description) ? $description : null;
        $display = is_string($fieldConfig['display'] ?? null) ? $fieldConfig['display'] : null;

        if ($isGroupField && empty($children)) {
            return null;
        }

        return [
            'name' => $name ?? $fieldHandle,
            'description' => $descriptionValue ?? $display ?? $fieldHandle,
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $fieldConfig
     * @return array<int, array<string, mixed>>
     */
    protected function getGroupVariables(array $field, array $fieldConfig): array
    {
        $groupFields = $this->normalizeBlueprintItems($fieldConfig['fields'] ?? []);
        $groupHandle = is_string($field['handle'] ?? null) ? $field['handle'] : '';
        $groupDisplay = is_string($fieldConfig['display'] ?? null) ? $fieldConfig['display'] : $groupHandle;

        return collect($groupFields)
            ->map(function (array $groupField) use ($groupHandle, $groupDisplay): ?array {
                /** @var string $childHandle */
                $childHandle = $groupField['handle'];

                $childConfig = is_array($groupField['field'] ?? null) ? $groupField['field'] : [];
                $childDisplay = is_string($childConfig['display'] ?? null) ? $childConfig['display'] : $childHandle;

                return $this->setFieldData(
                    $groupField,
                    $groupHandle.':'.$childHandle,
                    $groupDisplay.': '.$childDisplay,
                    false
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Normalizes blueprint items to ensure they are an array.
     *
     * @param  mixed  $items
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeBlueprintItems($items): array
    {
        if ($items instanceof SupportCollection) {
            $items = $items->all();
        }

        if (! is_array($items)) {
            return [];
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if ($item instanceof SupportCollection) {
                $item = $item->all();
            }

            if (! is_array($item)) {
                continue;
            }

            $import = $item['import'] ?? null;
            if (is_string($import) && $import !== '') {
                $normalizedItems = array_merge($normalizedItems, $this->getImportedFieldsetItems($item));

                continue;
            }

            if (isset($item['handle']) && is_array($item['field'] ?? null)) {
                /** @var array<string, mixed> $item */
                $normalizedItems[] = $item;
            }

            $nestedItems = $item['fields'] ?? null;
            if (is_array($nestedItems) || $nestedItems instanceof SupportCollection) {
                $normalizedItems = array_merge(
                    $normalizedItems,
                    $this->normalizeBlueprintItems($nestedItems)
                );
            }
        }

        return $normalizedItems;
    }

    /**
     * @param  array<string, mixed>  $importConfig
     * @return array<int, array<string, mixed>>
     */
    protected function getImportedFieldsetItems(array $importConfig): array
    {
        $fieldsetHandle = $importConfig['import'] ?? null;
        if (! is_string($fieldsetHandle) || $fieldsetHandle === '') {
            return [];
        }

        $prefix = $importConfig['prefix'] ?? null;
        if (! is_string($prefix)) {
            $prefix = '';
        }

        $fieldset = FieldsetFacade::find($fieldsetHandle);

        if (! $fieldset) {
            return [];
        }

        $items = $this->normalizeBlueprintItems($fieldset->fields()->items());

        if ($prefix === '') {
            return $items;
        }

        return array_map(function (array $item) use ($prefix): array {
            $handle = $item['handle'] ?? null;

            if (is_string($handle) && $handle !== '') {
                $item['handle'] = $prefix.$handle;
            }

            return $item;
        }, $items);
    }
}
