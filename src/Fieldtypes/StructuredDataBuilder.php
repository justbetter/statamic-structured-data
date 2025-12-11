<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Services\PresetService;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Fieldtype;

class StructuredDataBuilder extends Fieldtype
{
    protected $icon = 'code';

    protected $categories = ['structured_data'];

    protected static $handle = 'structured_data_builder';

    public function __construct(protected PresetService $presetService) {}

    public function preProcess($data)
    {
        $data = $data ?? [
            [
                'specialProps' => [
                    'context' => 'http://schema.org',
                    'type' => '',
                    'id' => '',
                ],
                'fields' => [],
            ],
        ];

        return $data;
    }

    public function preload()
    {
        return [
            'base_url' => config('app.url'),
            'taxonomy_terms' => $this->getStructuredDataObjects(),
            'presets' => $this->presetService->getAvailablePresets()->toArray(),
            'presets_enabled' => config('justbetter.structured-data.presets.enabled', true),
        ];
    }

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

    protected function getStructuredDataObjects(): Collection
    {
        $terms = Taxonomy::findByHandle('structured_data_objects')
            ->queryTerms()
            ->where('site', Site::selected()->handle())
            ->get();

        return $terms->map(function ($term) {
            return [
                'title' => $term->title,
                'slug' => $term->slug,
                'object_data' => $term->object_data,
            ];
        });
    }
}
