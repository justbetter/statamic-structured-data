<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

class InjectStructuredDataAction
{
    public function __construct(protected StructuredDataService $structuredDataService) {}

    public function execute(): ?string
    {
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

    protected function handleEntry(Entry|Page $entry): ?string
    {
        if ($entry instanceof Page) {
            /** @var Entry $entry */
            $entry = $entry->entry();
        }

        $enabledCollections = config()->array('justbetter.structured-data.collections', []);

        if (! in_array($entry->collection()->handle(), $enabledCollections)) {
            return null;
        }

        return $this->handleScripts($entry);
    }

    protected function handleTaxonomy(LocalizedTerm $term): ?string
    {
        $enabledTaxonomies = config()->array('justbetter.structured-data.taxonomies', []);

        if (! in_array($term->taxonomy()->handle(), $enabledTaxonomies)) {
            return null;
        }

        return $this->handleScripts($term);
    }

    protected function handleScripts(EntryContract|Page|LocalizedTerm $item): ?string
    {
        $scripts = $this->structuredDataService->getJsonLdScripts($item);

        if (! $scripts) {
            return null;
        }

        return implode("\n", $scripts);
    }

    protected function getCurrentEntry(): ?Entry
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $entry = EntryFacade::findByUri($url, $site->handle());

        return $entry instanceof Entry ? $entry : null;
    }

    protected function getCurrentTerm(): ?LocalizedTerm
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $term = Term::findByUri($url, $site->handle());

        return $term instanceof LocalizedTerm ? $term : null;
    }
}
