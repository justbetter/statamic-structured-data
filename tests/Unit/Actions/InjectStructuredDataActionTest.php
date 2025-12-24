<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Actions;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class InjectStructuredDataActionTest extends TestCase
{
    #[Test]
    public function execute_returns_null_when_no_entry_or_term(): void
    {
        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn(null);
        Term::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn(null);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertNull($result);
    }

    #[Test]
    public function execute_handles_entry_when_entry_exists(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($entry);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getJsonLdScripts')->andReturn(['<script>test</script>']);
        });

        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertEquals('<script>test</script>', $result);
    }

    #[Test]
    public function execute_handles_term_when_term_exists(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->andReturn(null);
        Term::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($term);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getJsonLdScripts')->andReturn(['<script>test</script>']);
        });

        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertEquals('<script>test</script>', $result);
    }

    #[Test]
    public function execute_returns_null_when_entry_collection_not_enabled(): void
    {
        Config::set('justbetter.structured-data.collections', ['other_collection']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($entry);
        Term::shouldReceive('findByUri')->andReturn(null);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertNull($result);
    }

    #[Test]
    public function execute_handles_page_instance(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');
        $page = $this->mock(Page::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('entry')->andReturn($entry);
        });

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($page);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getJsonLdScripts')->andReturn(['<script>test</script>']);
        });

        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertEquals('<script>test</script>', $result);
    }

    #[Test]
    public function execute_returns_null_when_term_taxonomy_not_enabled(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['other_taxonomy']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->andReturn(null);
        Term::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($term);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertNull($result);
    }

    #[Test]
    public function execute_returns_null_when_no_scripts(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($entry);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getJsonLdScripts')->andReturn([]);
        });

        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertNull($result);
    }

    #[Test]
    public function execute_returns_imploded_scripts(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($entry);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getJsonLdScripts')->andReturn(['<script>script1</script>', '<script>script2</script>']);
        });

        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->execute();

        $this->assertEquals("<script>script1</script>\n<script>script2</script>", $result);
    }

    #[Test]
    public function get_current_entry_returns_entry(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($entry);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('getCurrentEntry');
        $method->setAccessible(true);
        $result = $method->invoke($action);

        $this->assertInstanceOf(Entry::class, $result);
    }

    #[Test]
    public function get_current_entry_returns_page(): void
    {
        $page = $this->mock(Page::class);

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($page);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('getCurrentEntry');
        $method->setAccessible(true);
        $result = $method->invoke($action);

        $this->assertInstanceOf(Page::class, $result);
    }

    #[Test]
    public function get_current_entry_returns_null_when_not_entry_or_page(): void
    {
        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn(null);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('getCurrentEntry');
        $method->setAccessible(true);
        $result = $method->invoke($action);

        $this->assertNull($result);
    }

    #[Test]
    public function get_current_term_returns_localized_term(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        Term::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn($term);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('getCurrentTerm');
        $method->setAccessible(true);
        $result = $method->invoke($action);

        $this->assertInstanceOf(LocalizedTerm::class, $result);
    }

    #[Test]
    public function get_current_term_returns_null_when_not_localized_term(): void
    {
        URL::shouldReceive('getCurrent')->andReturn('/test-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        Term::shouldReceive('findByUri')->with('/test-url', 'default')->andReturn(null);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        /** @var \Justbetter\StatamicStructuredData\Services\StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('getCurrentTerm');
        $method->setAccessible(true);
        $result = $method->invoke($action);

        $this->assertNull($result);
    }
}
