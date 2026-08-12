<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\VariableProvider;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Entries\Entry;
use Statamic\Fields\LabeledValue;

class RunwayVariableProvider implements VariableProvider
{
    public function __construct(protected BlueprintVariableMapper $mapper) {}

    public function variables(mixed $parent = null): array
    {
        if (! RunwaySupport::isInstalled()) {
            return [];
        }

        $resourceHandle = $this->resolveResourceHandle($parent);

        if (! $resourceHandle) {
            return [];
        }

        $resource = RunwaySupport::findResource($resourceHandle);

        if (! $resource) {
            return [];
        }

        $blueprint = $resource->blueprint();
        $items = $this->mapper->normalizeBlueprintItems($blueprint->fields()->items());

        $blueprintFields = collect($items)
            ->map(fn (array $field) => $this->mapper->setFieldData($field))
            ->filter()
            ->values()
            ->all();

        $blueprintHandles = collect($blueprintFields)->pluck('name')->filter()->all();

        $modelAttributes = $this->modelAttributeVariables($resource->model(), $blueprintHandles);

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge($baseFields, $blueprintFields, $modelAttributes);
    }

    public function resolveResourceHandle(mixed $dataTemplate): ?string
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
    public function modelAttributeVariables(Model $model, array $existingHandles): array
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
}
