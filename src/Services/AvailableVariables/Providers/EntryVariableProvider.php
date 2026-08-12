<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers;

use Illuminate\Support\Collection as SupportCollection;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\SeoProVariables;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\VariableProvider;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;

class EntryVariableProvider implements VariableProvider
{
    public function __construct(
        protected BlueprintVariableMapper $mapper,
        protected SeoProVariables $seoProVariables,
    ) {}

    public function variables(mixed $parent = null): array
    {
        if (! $parent instanceof Entry) {
            return [];
        }

        /** @var ?Collection $collection */
        $collection = $parent->use_for_collection; /** @phpstan-ignore-line */
        if (! $collection instanceof Collection) {
            return [];
        }

        /** @var SupportCollection<int, Blueprint>|array<int, Blueprint>|null $entryBlueprints */
        $entryBlueprints = $collection->entryBlueprints();

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge(
            $baseFields,
            $this->mapper->mapBlueprintsToVariables(collect($entryBlueprints)->filter()),
            $this->seoProVariables->forSection($collection)
        );
    }
}
