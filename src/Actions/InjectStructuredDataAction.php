<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
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

        $runwayModel = $this->getCurrentRunwayModel();

        if ($runwayModel) {
            return $this->handleRunwayModel($runwayModel);
        }

        return null;
    }

    /**
     * @param  EntryContract|Page|LocalizedTerm|Model  $item
     */
    public function executeForItem($item, ?string $resourceHandle = null): ?string
    {
        if ($item instanceof Entry || $item instanceof Page) {
            return $this->handleEntry($item);
        }

        if ($item instanceof LocalizedTerm) {
            return $this->handleTaxonomy($item);
        }

        if ($item instanceof Model) {
            return $this->handleRunwayModel($item, $resourceHandle);
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

    protected function handleRunwayModel(Model $model, ?string $resourceHandle = null): ?string
    {
        $handle = RunwaySupport::resolveResourceHandle($model, $resourceHandle);

        if (! $handle) {
            return null;
        }

        $scripts = $this->structuredDataService->getJsonLdScripts($model, false, $handle);

        if (! $scripts) {
            return null;
        }

        return implode("\n", $scripts);
    }

    protected function handleScripts(EntryContract|Page|LocalizedTerm $item): ?string
    {
        $scripts = $this->structuredDataService->getJsonLdScripts($item);

        if (! $scripts) {
            return null;
        }

        return implode("\n", $scripts);
    }

    protected function getCurrentEntry(): Entry|Page|null
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $entry = EntryFacade::findByUri($url, $site->handle());

        return $entry instanceof Entry ? $entry : ($entry instanceof Page ? $entry : null);
    }

    protected function getCurrentTerm(): ?LocalizedTerm
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $term = Term::findByUri($url, $site->handle());

        return $term instanceof LocalizedTerm ? $term : null;
    }

    protected function getCurrentRunwayModel(): ?Model
    {
        if (! RunwaySupport::isInstalled()) {
            return null;
        }

        $url = URL::getCurrent();
        $model = RunwaySupport::findByUri($url);

        if (! $model instanceof Model) {
            return null;
        }

        $handle = RunwaySupport::resolveResourceHandle($model);

        return $handle ? $model : null;
    }
}
