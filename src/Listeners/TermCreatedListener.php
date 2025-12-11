<?php

namespace Justbetter\StatamicStructuredData\Listeners;

use Statamic\Events\TermCreated;
use Statamic\Facades\Entry;
use Statamic\Taxonomies\Term;

class TermCreatedListener
{
    public function handle(TermCreated $event): void
    {
        /** @var Term $term */
        $term = $event->term;
        $taxonomyHandle = $term->taxonomy()->handle();

        if (! in_array($taxonomyHandle, config('justbetter.structured-data.taxonomies', []))) {
            return;
        }

        $templatesIds = Entry::query()
            ->where('collection', 'structured_data_templates')
            ->whereStatus('published')
            ->where('blueprint_type', 'taxonomy')
            ->where('use_for_taxonomy', $taxonomyHandle)
            ->get()
            ->pluck('id')
            ->toArray();

        if (empty($templatesIds)) {
            return;
        }

        $term->set('structured_data_templates', $templatesIds);
        $term->saveQuietly();
    }
}
