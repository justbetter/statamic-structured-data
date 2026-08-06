<?php

namespace Justbetter\StatamicStructuredData\Tags;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Statamic\Entries\Entry;
use Statamic\Structures\Page;
use Statamic\Tags\Tags;
use Statamic\Taxonomies\LocalizedTerm;

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

    public function for(): ?string
    {
        $item = $this->params->get('item');

        if (! $item instanceof Entry
            && ! $item instanceof Page
            && ! $item instanceof LocalizedTerm
            && ! $item instanceof Model
        ) {
            return null;
        }

        $resource = $this->params->get('resource');
        $resourceHandle = is_string($resource) && $resource !== '' ? $resource : null;

        return $this->action->executeForItem($item, $resourceHandle);
    }
}
