<?php

namespace Justbetter\StatamicStructuredData\Resolvers;

use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;

class TaxonomyResolver extends AbstractResourceResolver
{
    public function resolveCurrent(): ?LocalizedTerm
    {
        $url = URL::getCurrent();

        /** @var Site $site */
        $site = SiteFacade::current();

        $term = Term::findByUri($url, $site->handle());

        return $term instanceof LocalizedTerm ? $term : null;
    }

    public function supports(mixed $item): bool
    {
        return $item instanceof LocalizedTerm;
    }

    public function handle(mixed $item, ?string $resourceHandle = null): ?string
    {
        if (! $item instanceof LocalizedTerm) {
            return null;
        }

        $enabledTaxonomies = config()->array('justbetter.structured-data.taxonomies', []);

        if (! in_array($item->taxonomy()->handle(), $enabledTaxonomies)) {
            return null;
        }

        return $this->handleScripts($item);
    }
}
