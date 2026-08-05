<?php

namespace Justbetter\StatamicStructuredData\Listeners;

use Statamic\Events\TermCreated;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Taxonomies\Term;

class TermCreatedListener
{
    public function handle(TermCreated $event): void
    {
        /** @var Term $term */
        $term = $event->term;
        $taxonomyHandle = $term->taxonomy()->handle();

        $enabledTaxonomies = config()->array('justbetter.structured-data.taxonomies', []);

        if (! in_array($taxonomyHandle, $enabledTaxonomies)) {
            return;
        }

        /** @var EloquentQueryBuilder $query */
        $query = EntryFacade::query();

        $templatesIds = $query
            ->where('collection', 'structured_data_templates')
            ->whereStatus('published')
            ->where('blueprint_type', 'taxonomy')
            ->where('use_for_taxonomy', $taxonomyHandle)
            ->where('apply_automatically', true)
            ->get()
            ->pluck('id')
            ->values()
            ->all();

        if ($templatesIds === []) {
            return;
        }

        $term->set('structured_data_templates', $templatesIds);
        $term->saveQuietly();
    }
}
