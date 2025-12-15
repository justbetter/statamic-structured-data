<?php

namespace Justbetter\StatamicStructuredData\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Fields\Blueprint;

class AddStructuredDataTabListener
{
    public function handle(EntryBlueprintFound|TermBlueprintFound $event): void
    {
        $blueprint = $event->blueprint;

        if ($event instanceof EntryBlueprintFound && str_contains($blueprint->namespace(), 'collections')) {
            $this->handleCollectionBlueprintFound($blueprint);

            return;
        } elseif ($event instanceof TermBlueprintFound && str_contains($blueprint->namespace(), 'taxonomies')) {
            $this->handleTaxonomyBlueprintFound($blueprint);

            return;
        }
    }

    public function handleCollectionBlueprintFound(Blueprint $blueprint): void
    {
        $namespace = (string) $blueprint->namespace();
        $handle = str_replace('collections.', '', $namespace);
        $enabledCollections = config('justbetter.structured-data.collections', []);

        if (! is_array($enabledCollections) || ! in_array($handle, $enabledCollections, true)) {
            return;
        }

        $this->addStructuredDataTab($blueprint);
    }

    public function handleTaxonomyBlueprintFound(Blueprint $blueprint): void
    {
        $namespace = (string) $blueprint->namespace();
        $handle = str_replace('taxonomies.', '', $namespace);
        $enabledTaxonomies = config('justbetter.structured-data.taxonomies', []);

        if (! is_array($enabledTaxonomies) || ! in_array($handle, $enabledTaxonomies, true)) {
            return;
        }

        $this->addStructuredDataTab($blueprint);
    }

    protected function addStructuredDataTab(Blueprint $blueprint): void
    {
        $contents = $blueprint->contents();

        if (! isset($contents['tabs']['structured_data'])) {
            $contents['tabs']['structured_data'] = [
                'display' => 'Structured Data',
                'sections' => [
                    [
                        'fields' => [
                            [
                                'handle' => 'structured_data_templates',
                                'field' => [
                                    'type' => 'entries',
                                    'display' => 'Templates',
                                    'mode' => 'select',
                                    'collections' => ['structured_data_templates'],
                                    'create' => false,
                                    'max_items' => false,
                                    'validate' => ['array'],
                                    'listable' => 'hidden',
                                    'reorderable' => true,
                                ],
                            ],
                            [
                                'handle' => 'structured_data_preview',
                                'field' => [
                                    'type' => 'structured_data_preview',
                                    'display' => 'Structured Data Preview',
                                    'instructions' => 'Preview of the selected structured data templates',
                                    'selected_templates' => '@structured_data_templates',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $blueprint->setContents($contents);
        }
    }
}
