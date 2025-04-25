<?php

namespace Justbetter\StatamicStructuredData\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection;

class AddStructuredDataTab
{
    public function handle(EntryBlueprintFound $event): void
    {
        $blueprint = $event->blueprint;

        if (! str_contains($blueprint->namespace(), 'collections')) {
            return;
        }

        $handle = str_replace('collections.', '', $blueprint->namespace());

        $enabledCollections = config('justbetter.structured-data.collections', []);
        if (! in_array($handle, $enabledCollections)) {
            return;
        }

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
