<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Justbetter\StatamicStructuredData\Listeners\TermCreatedListener;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Events\TermCreated;
use Statamic\Events\TermSaving;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;

class TermCreatedListenerTest extends TestCase
{
    #[Test]
    public function it_does_nothing_when_taxonomy_not_enabled(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['other_taxonomy']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = (new Term)->taxonomy($taxonomy)->slug('term-123');

        $event = new TermCreated($term);

        $listener = new TermCreatedListener;
        $listener->handle($event);

        $this->assertNull($term->get('structured_data_templates'));
    }

    #[Test]
    public function it_does_nothing_when_no_templates_found(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = (new Term)->taxonomy($taxonomy)->slug('term-123');

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock): void {
            $mock->shouldReceive('where')->with('collection', 'structured_data_templates')->andReturnSelf();
            $mock->shouldReceive('whereStatus')->with('published')->andReturnSelf();
            $mock->shouldReceive('where')->with('blueprint_type', 'taxonomy')->andReturnSelf();
            $mock->shouldReceive('where')->with('use_for_taxonomy', 'categories')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([]));
        });

        EntryFacade::shouldReceive('query')->andReturn($query);

        $event = new TermCreated($term);

        $listener = new TermCreatedListener;
        $listener->handle($event);

        $this->assertNull($term->get('structured_data_templates'));
    }

    #[Test]
    public function it_sets_structured_data_templates_when_templates_found(): void
    {
        Event::fake([TermSaving::class]);

        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = (new Term)->taxonomy($taxonomy)->slug('term-123');

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $templateEntry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'taxonomy')
            ->set('use_for_taxonomy', 'categories')
            ->published(true);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($templateEntry): void {
            $mock->shouldReceive('where')->with('collection', 'structured_data_templates')->andReturnSelf();
            $mock->shouldReceive('whereStatus')->with('published')->andReturnSelf();
            $mock->shouldReceive('where')->with('blueprint_type', 'taxonomy')->andReturnSelf();
            $mock->shouldReceive('where')->with('use_for_taxonomy', 'categories')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$templateEntry]));
        });

        EntryFacade::shouldReceive('query')->andReturn($query);

        $event = new TermCreated($term);

        $listener = new TermCreatedListener;
        $listener->handle($event);

        $this->assertEquals(['template-123'], $term->get('structured_data_templates'));
    }
}
