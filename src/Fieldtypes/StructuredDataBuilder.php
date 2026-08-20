<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Services\PresetService;
use Justbetter\StatamicStructuredData\Services\ReplicatorFieldService;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Fieldtype;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class StructuredDataBuilder extends Fieldtype
{
    /** @var string */
    protected $icon = 'code';

    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_builder';

    public function __construct(
        protected PresetService $presetService,
        protected ReplicatorFieldService $replicatorFieldService
    ) {}

    /**
     * @param  mixed  $data
     * @return array<int, array<string, mixed>>
     */
    public function preProcess($data): array
    {
        $default = [
            [
                'specialProps' => [
                    'context' => 'https://schema.org',
                    'type' => '',
                    'id' => '',
                ],
                'fields' => [],
            ],
        ];

        if (! is_array($data)) {
            return $default;
        }

        /** @var array<int, array<string, mixed>> $data */
        return $data;
    }

    /** @return array<string, mixed> */
    public function preload(): array
    {
        return [
            'base_url' => config('app.url'),
            'taxonomy_terms' => $this->getStructuredDataObjects(),
            'presets' => $this->presetService->getAvailablePresets()->toArray(),
            'presets_enabled' => config('justbetter.structured-data.presets.enabled', true),
            'replicator_fields' => $this->getReplicatorFields(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function getReplicatorFields(): array
    {
        $dataTemplate = $this->field->parent();

        if (! $dataTemplate) {
            return [];
        }

        return $this->replicatorFieldService->getReplicatorFields($dataTemplate);
    }

    /** @return array<string, array<string, mixed>> */
    protected function configFieldItems(): array
    {
        return [
            'allow_multiple' => [
                'display' => 'Allow Multiple Objects',
                'instructions' => 'Allow multiple schema objects to be created',
                'type' => 'toggle',
                'default' => true,
            ],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function getStructuredDataObjects(): Collection
    {
        /** @var Taxonomy|null $taxonomy */
        $taxonomy = TaxonomyFacade::findByHandle('structured_data_objects');

        /** @var Site|null $site */
        $site = SiteFacade::selected();

        if (! $taxonomy || ! $site) {
            return collect();
        }

        $terms = $taxonomy
            ->queryTerms()
            ->where('site', $site->handle())
            ->get();

        return $terms->map(function ($term) {
            /** @var LocalizedTerm $term */
            return [
                'title' => $term->title,
                'slug' => $term->slug(),
                'object_data' => $term->object_data,
            ];
        });
    }
}
