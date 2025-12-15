<?php

namespace Justbetter\StatamicStructuredData\Listeners;

use Statamic\Events\EntryCreated;
use Statamic\Facades\Entry;
use Statamic\Query\EloquentQueryBuilder;

class EntryCreatedListener
{
    public function handle(EntryCreated $event): void
    {
        $entry = $event->entry;
        $collectionHandle = $entry->collection()->handle();

        $enabledCollections = config()->array('justbetter.structured-data.collections', []);

        if (! in_array($collectionHandle, $enabledCollections)) {
            return;
        }

        /** @var EloquentQueryBuilder $query */
        $query = Entry::query();

        $templatesIds = $query
            ->where('collection', 'structured_data_templates')
            ->whereStatus('published')
            ->where('blueprint_type', 'collection')
            ->where('use_for_collection', $collectionHandle)
            ->get()
            ->pluck('id')
            ->toArray();

        if (empty($templatesIds)) {
            return;
        }

        $entry->structured_data_templates = $templatesIds;
        $entry->saveQuietly();
    }
}
