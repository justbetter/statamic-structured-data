<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers;

use Illuminate\Support\Collection as SupportCollection;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\SeoProVariables;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\VariableProvider;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Taxonomies\Taxonomy;

class TermVariableProvider implements VariableProvider
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

        /** @var ?Taxonomy $taxonomy */
        $taxonomy = $parent->use_for_taxonomy;
        if (! $taxonomy instanceof Taxonomy) {
            return [];
        }

        /** @var SupportCollection<int, Blueprint>|array<int, Blueprint>|null $termBlueprints */
        $termBlueprints = $taxonomy->termBlueprints();

        $baseFields = [['name' => 'absolute_url', 'description' => 'Full URL']];

        return array_merge(
            $baseFields,
            $this->mapper->mapBlueprintsToVariables(collect($termBlueprints)->filter()),
            $this->seoProVariables->forSection($taxonomy)
        );
    }
}
