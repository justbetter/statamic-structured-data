<?php

namespace Justbetter\StatamicStructuredData\Resolvers;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Structures\Page;

class EntryResolver extends AbstractResourceResolver
{
    public function resolveCurrent(): Entry|Page|null
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $entry = EntryFacade::findByUri($url, $site->handle());

        return $entry instanceof Entry ? $entry : ($entry instanceof Page ? $entry : null);
    }

    public function supports(mixed $item): bool
    {
        return $item instanceof Entry || $item instanceof Page;
    }

    public function handle(mixed $item, ?string $resourceHandle = null): ?string
    {
        if (! ($item instanceof Entry || $item instanceof Page)) {
            return null;
        }

        if ($item instanceof Page) {
            /** @var Entry $item */
            $item = $item->entry();
        }

        $enabledCollections = config()->array('justbetter.structured-data.collections', []);

        if (! in_array($item->collection()->handle(), $enabledCollections)) {
            return null;
        }

        return $this->handleScripts($item);
    }
}
