<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Illuminate\Http\Request;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

class InjectStructuredDataAction
{
    protected $structuredDataService;

    public function __construct(StructuredDataService $structuredDataService)
    {
        $this->structuredDataService = $structuredDataService;
    }

    public function execute(): ?string
    {
        $request = request();

        if (! $this->shouldInject($request)) {
            return null;
        }

        $entry = $this->getCurrentEntry();

        if ($entry) {
            return $this->handleEntry($entry);
        }

        $term = $this->getCurrentTerm();

        if ($term) {
            return $this->handleTaxonomy($term);
        }

        return null;
    }

    protected function handleEntry($entry): ?string
    {
        if ($entry instanceof Page) {
            $entry = $entry->entry();
        }

        $enabledCollections = config('justbetter.structured-data.collections', []);

        if (! in_array($entry?->collection()?->handle(), $enabledCollections)) {
            return null;
        }

        return $this->handleScripts($entry);
    }

    protected function handleTaxonomy($term): ?string
    {
        $enabledTaxonomies = config('justbetter.structured-data.taxonomies', []);

        if (! in_array($term?->taxonomy()?->handle(), $enabledTaxonomies)) {
            return null;
        }

        return $this->handleScripts($term);
    }

    protected function handleScripts($item): ?string
    {
        $scripts = $this->structuredDataService->getJsonLdScripts($item);

        if (! $scripts ?? false) {
            return null;
        }

        return implode("\n", $scripts);
    }

    protected function shouldInject(Request $request): bool
    {
        return true;
    }

    protected function getCurrentEntry(): Page|Entry|null
    {
        $url = URL::getCurrent();

        return EntryFacade::findByUri($url, Site::current()->handle());
    }

    protected function getCurrentTerm(): ?LocalizedTerm
    {
        $url = URL::getCurrent();

        return Term::findByUri($url, Site::current()->handle());
    }
}
