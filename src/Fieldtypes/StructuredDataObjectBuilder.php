<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Fields\Fieldtype;

class StructuredDataObjectBuilder extends Fieldtype
{
    /** @var string */
    protected $icon = 'code';

    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_object_builder';

    /**
     * @param  mixed  $data
     * @return array<string, mixed>
     */
    public function preProcess($data): array
    {
        if (! is_array($data)) {
            return [
                'fields' => [],
            ];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @return array<string, mixed> */
    public function preload(): array
    {
        return [
            'base_url' => config('app.url'),
        ];
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
}
