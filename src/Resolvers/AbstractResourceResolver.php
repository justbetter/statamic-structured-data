<?php

namespace Justbetter\StatamicStructuredData\Resolvers;

use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Taxonomies\LocalizedTerm;

abstract class AbstractResourceResolver implements ResourceResolver
{
    public function __construct(protected StructuredDataService $structuredDataService) {}

    protected function handleScripts(EntryContract|LocalizedTerm $item): ?string
    {
        $scripts = $this->structuredDataService->getJsonLdScripts($item);

        if (! $scripts) {
            return null;
        }

        return implode("\n", $scripts);
    }
}
