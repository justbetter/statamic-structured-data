<?php

namespace Justbetter\StatamicStructuredData\Tags;

use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Statamic\Tags\Tags;

class StructuredData extends Tags
{
    /** @var string */
    protected static $handle = 'structured-data';

    protected InjectStructuredDataAction $action;

    public function __construct(InjectStructuredDataAction $action)
    {
        $this->action = $action;
    }

    public function head(): ?string
    {
        return $this->action->execute();
    }
}
