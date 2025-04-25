<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Fields\Fieldtype;

class StructuredDataPreview extends Fieldtype
{
    protected $categories = ['structured_data'];

    protected static $handle = 'structured_data_preview';

    public function defaultValue()
    {
        return null;
    }
}
