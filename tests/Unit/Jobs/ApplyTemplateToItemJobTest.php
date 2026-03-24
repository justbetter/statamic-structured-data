<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Event;
use Justbetter\StatamicStructuredData\Jobs\ApplyTemplateToItemJob;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Events\TermSaving;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class ApplyTemplateToItemJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EntrySaving::class, TermSaving::class]);
    }

    #[Test]
    public function handle_applies_template_to_entry(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        EntryFacade::shouldReceive('find')->with('entry-123')->andReturn($entryMock);

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');
        $job->handle();

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertIsArray($templates);
        $this->assertContains('template-456', $templates);
    }

    #[Test]
    public function handle_does_nothing_when_entry_not_found(): void
    {
        EntryFacade::shouldReceive('find')->with('entry-123')->andReturn(null);

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');
        $job->handle();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handle_applies_template_to_term(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('get')->with('structured_data_templates')->andReturn([]);
            $mock->shouldReceive('set')->with('structured_data_templates', Mockery::any())->once();
            $mock->shouldReceive('saveQuietly')->once();
        });

        TermFacade::shouldReceive('find')->with('term-123')->andReturn($term);

        $job = new ApplyTemplateToItemJob('term-123', 'term', 'template-456');
        $job->handle();

    }

    #[Test]
    public function handle_does_nothing_when_term_not_found(): void
    {
        TermFacade::shouldReceive('find')->with('term-123')->andReturn(null);

        $job = new ApplyTemplateToItemJob('term-123', 'term', 'template-456');
        $job->handle();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_template_to_item_adds_template_when_not_present(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertIsArray($templates);
        $this->assertContains('template-456', $templates);
    }

    #[Test]
    public function apply_template_to_item_does_not_add_duplicate_template(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-456']);

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entry);

        $templates = $entry->get('structured_data_templates');
        $this->assertCount(1, $templates);
        $this->assertContains('template-456', $templates);
    }

    #[Test]
    public function apply_template_to_item_handles_string_template_ids(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123', 'template-456']);
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-789');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertCount(3, $templates);
        $this->assertContains('template-789', $templates);
    }

    #[Test]
    public function apply_template_to_item_handles_int_template_ids(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', [123, 456]);
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', '789');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertCount(3, $templates);
        $this->assertContains('789', $templates);
    }

    #[Test]
    public function apply_template_to_item_handles_object_template_ids(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templateEntry = (new Entry)->collection($collection)->id('template-123');
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', [$templateEntry]);
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertIsArray($templates);
        $this->assertContains('template-456', $templates);
    }

    #[Test]
    public function apply_template_to_item_handles_empty_templates_array(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', []);
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertCount(1, $templates);
        $this->assertContains('template-456', $templates);
    }

    #[Test]
    public function apply_template_to_item_handles_null_templates(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');
        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('saveQuietly')->once();

        $job = new ApplyTemplateToItemJob('entry-123', 'entry', 'template-456');

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('applyTemplateToItem');
        $method->setAccessible(true);
        $method->invoke($job, $entryMock);

        /** @var Entry $entryMock */
        $templates = $entryMock->get('structured_data_templates');
        $this->assertIsArray($templates);
        $this->assertContains('template-456', $templates);
    }
}
