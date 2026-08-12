<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\AvailableVariablesService;
use Statamic\Fields\Fieldtype;

class AvailableVariablesFieldtype extends Fieldtype
{
    /** @var string */
    protected $icon = 'code';

    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_available_variables';

    public function __construct(
        protected AvailableVariablesService $availableVariables = new AvailableVariablesService,
    ) {}

    /** @return null */
    public function defaultValue()
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function preload(): array
    {
        return [
            'variables' => $this->availableVariables->all($this->field->parent()),
        ];
    }
}
