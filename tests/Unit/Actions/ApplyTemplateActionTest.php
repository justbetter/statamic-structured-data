<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Actions;

use Illuminate\Support\Facades\Queue;
use Justbetter\StatamicStructuredData\Actions\ApplyTemplateAction;
use Justbetter\StatamicStructuredData\Jobs\ApplyTemplateToItemJob;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class ApplyTemplateActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function title_returns_translated_string(): void
    {
        $action = new ApplyTemplateAction;
        $result = $action->title();

        /** @phpstan-ignore-next-line */
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function confirmation_text_returns_translated_string(): void
    {
        $action = new ApplyTemplateAction;
        $result = $action->confirmationText();

        /** @phpstan-ignore-next-line */
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function visible_to_returns_false_when_not_entry(): void
    {
        $action = new ApplyTemplateAction;
        $result = $action->visibleTo('not-an-entry');

        $this->assertFalse($result);
    }

    #[Test]
    public function visible_to_returns_false_when_entry_not_template_collection(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $action = new ApplyTemplateAction;
        $result = $action->visibleTo($entry);

        $this->assertFalse($result);
    }

    #[Test]
    public function visible_to_returns_true_when_entry_is_template(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('template-123');

        $action = new ApplyTemplateAction;
        $result = $action->visibleTo($entry);

        $this->assertTrue($result);
    }

    #[Test]
    public function run_dispatches_jobs_for_collection_template(): void
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'collection')
            ->set('use_for_collection', 'blog');

        $entry1 = (new Entry)->collection($blogCollection)->id('entry-1');
        $entry2 = (new Entry)->collection($blogCollection)->id('entry-2');

        $site = $this->mock(Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        $templateMock = Mockery::mock($template)->makePartial();
        $templateMock->shouldReceive('site')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($entry1, $entry2): void {
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$entry1, $entry2]));
        });

        $blogCollectionMock = Mockery::mock($blogCollection)->makePartial();
        $blogCollectionMock->shouldReceive('queryEntries')->andReturn($query);

        CollectionFacade::shouldReceive('find')->with('blog')->andReturn($blogCollectionMock);
        CollectionFacade::shouldReceive('findByHandle')->andReturn($blogCollection);

        /** @var \Statamic\Entries\Entry $templateMock */
        $action = new ApplyTemplateAction;
        $result = $action->run(collect([$templateMock]), []);

        $this->assertArrayHasKey('message', $result);
        Queue::assertPushed(ApplyTemplateToItemJob::class, 2);
    }

    #[Test]
    public function run_dispatches_jobs_for_taxonomy_template(): void
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'taxonomy')
            ->set('use_for_taxonomy', 'categories');

        $term1 = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('id')->andReturn('term-1');
        });
        $term2 = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('id')->andReturn('term-2');
        });

        $site = $this->mock(Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        $templateMock = Mockery::mock($template)->makePartial();
        $templateMock->shouldReceive('site')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($term1, $term2): void {
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$term1, $term2]));
        });

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('queryTerms')->andReturn($query);

        TaxonomyFacade::shouldReceive('find')->with('categories')->andReturn($taxonomyMock);
        /** @var \Statamic\Taxonomies\Taxonomy $taxonomy */
        TaxonomyFacade::shouldReceive('all')->andReturn(collect([$taxonomy]));

        /** @var \Statamic\Entries\Entry $templateMock */
        $action = new ApplyTemplateAction;
        $result = $action->run(collect([$templateMock]), []);

        $this->assertArrayHasKey('message', $result);
        Queue::assertPushed(ApplyTemplateToItemJob::class, 2);
    }

    #[Test]
    public function apply_templates_returns_zero_for_invalid_blueprint_type(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123')
            ->set('blueprint_type', 'invalid');

        $action = new ApplyTemplateAction;
        $result = $action->applyTemplates($template);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function apply_template_to_collection_returns_zero_when_no_collection_value(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123')
            ->set('blueprint_type', 'collection');

        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToCollection($template, 'template-123');

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function apply_template_to_collection_returns_zero_when_collection_not_found(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123')
            ->set('blueprint_type', 'collection')
            ->set('use_for_collection', 'nonexistent');

        CollectionFacade::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToCollection($template, 'template-123');

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function apply_template_to_taxonomy_returns_zero_when_no_taxonomy_value(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123')
            ->set('blueprint_type', 'taxonomy');

        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToTaxonomy($template, 'template-123');

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function apply_template_to_taxonomy_returns_zero_when_taxonomy_not_found(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123')
            ->set('blueprint_type', 'taxonomy')
            ->set('use_for_taxonomy', 'nonexistent');

        TaxonomyFacade::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToTaxonomy($template, 'template-123');

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function apply_template_to_collection_handles_collection_object(): void
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();

        $entry1 = (new Entry)->collection($blogCollection)->id('entry-1');

        $site = $this->mock(Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($entry1): void {
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$entry1]));
        });

        $blogCollectionMock = Mockery::mock($blogCollection)->makePartial();
        $blogCollectionMock->shouldReceive('queryEntries')->andReturn($query);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'collection')
            ->set('use_for_collection', $blogCollectionMock);

        $templateMock = Mockery::mock($template)->makePartial();
        $templateMock->shouldReceive('site')->andReturn($site);

        /** @var \Statamic\Entries\Entry $templateMock */
        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToCollection($templateMock, 'template-123');

        $this->assertEquals(1, $result);
        Queue::assertPushed(ApplyTemplateToItemJob::class, 1);
    }

    #[Test]
    public function apply_template_to_taxonomy_handles_taxonomy_object(): void
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();

        $term1 = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('id')->andReturn('term-1');
        });

        $site = $this->mock(Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($term1): void {
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$term1]));
        });

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('queryTerms')->andReturn($query);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('blueprint_type', 'taxonomy')
            ->set('use_for_taxonomy', $taxonomyMock);

        $templateMock = Mockery::mock($template)->makePartial();
        $templateMock->shouldReceive('site')->andReturn($site);

        /** @var \Statamic\Entries\Entry $templateMock */
        $action = new ApplyTemplateAction;
        $result = $action->applyTemplateToTaxonomy($templateMock, 'template-123');

        $this->assertEquals(1, $result);
        Queue::assertPushed(ApplyTemplateToItemJob::class, 1);
    }
}
