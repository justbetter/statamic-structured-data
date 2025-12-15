<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Fields\Fieldtype;

class StructuredDataPreview extends Fieldtype
{
    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_preview';

    /** @return null */
    public function defaultValue()
    {
        return null;
    }
}
