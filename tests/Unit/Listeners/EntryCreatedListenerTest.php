<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Listeners\EntryCreatedListener;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Events\EntryCreated;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\EloquentQueryBuilder;

class EntryCreatedListenerTest extends TestCase
{
    #[Test]
    public function it_does_nothing_when_collection_not_enabled(): void
    {
        Config::set('justbetter.structured-data.collections', ['other_collection']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $event = new EntryCreated($entry);

        $listener = new EntryCreatedListener;
        $listener->handle($event);

        $this->assertNull($entry->get('structured_data_templates'));
    }

    #[Test]
    public function it_does_nothing_when_no_templates_found(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock): void {
            $mock->shouldReceive('where')->with('collection', 'structured_data_templates')->andReturnSelf();
            $mock->shouldReceive('whereStatus')->with('published')->andReturnSelf();
            $mock->shouldReceive('where')->with('blueprint_type', 'collection')->andReturnSelf();
            $mock->shouldReceive('where')->with('use_for_collection', 'blog')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([]));
        });

        EntryFacade::shouldReceive('query')->andReturn($query);

        $event = new EntryCreated($entry);

        $listener = new EntryCreatedListener;
        $listener->handle($event);

        $this->assertNull($entry->get('structured_data_templates'));
    }

    #[Test]
    public function it_sets_structured_data_templates_when_templates_found(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)->collection($blogCollection)->id('entry-123');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once()->andReturnSelf();

        $templateEntry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'collection')
            ->set('use_for_collection', 'blog')
            ->published(true);

        $templateCollection = collect([$templateEntry]);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($templateCollection): void {
            $mock->shouldReceive('where')->andReturnSelf();
            $mock->shouldReceive('whereStatus')->andReturnSelf();
            $mock->shouldReceive('whereIn')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn($templateCollection);
        });

        EntryFacade::shouldReceive('query')->andReturn($query);

        $event = new EntryCreated($entryMock);

        $listener = new EntryCreatedListener;
        $listener->handle($event);

        /** @var Entry $entryMock */
        $this->assertNotNull($entryMock->get('structured_data_templates'));
        $this->assertNotEmpty($entryMock->get('structured_data_templates'));
    }
}
