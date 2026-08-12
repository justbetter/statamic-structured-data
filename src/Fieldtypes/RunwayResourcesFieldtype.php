<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Fieldtypes\Select;

class RunwayResourcesFieldtype extends Select
{
    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_runway_resources';

    /** @var string */
    protected $component = 'select';

    /** @var string */
    protected $indexComponent = 'tags';

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Appearance'),
                'fields' => [
                    'placeholder' => [
                        'display' => __('Placeholder'),
                        'type' => 'text',
                        'default' => '',
                    ],
                    'clearable' => [
                        'display' => __('Clearable'),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function getOptions(): array
    {
        return collect(RunwaySupport::resourceOptions())
            ->map(fn (string $label, string $key): array => [
                'value' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
