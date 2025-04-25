<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Fields\Fieldtype;

class StructuredDataObjectBuilder extends Fieldtype
{
    protected $icon = 'code';

    protected $categories = ['structured_data'];

    protected static $handle = 'structured_data_object_builder';

    public function preProcess($data)
    {
        $data = $data ?? [
            'fields' => [],
        ];

        return $data;
    }

    public function preload()
    {
        return [
            'base_url' => config('app.url'),
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
}
