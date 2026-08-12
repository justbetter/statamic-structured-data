<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Resolvers;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Resolvers\EntryResolver;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Structures\Page;

class EntryResolverTest extends TestCase
{
    #[Test]
    public function resolve_current_returns_entry(): void
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
        $resolver = new EntryResolver($service);

        $result = $resolver->resolveCurrent();

        $this->assertInstanceOf(Entry::class, $result);
    }

    #[Test]
    public function resolve_current_returns_page(): void
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
        $resolver = new EntryResolver($service);

        $result = $resolver->resolveCurrent();

        $this->assertInstanceOf(Page::class, $result);
    }

    #[Test]
    public function resolve_current_returns_null_when_not_entry_or_page(): void
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
        $resolver = new EntryResolver($service);

        $result = $resolver->resolveCurrent();

        $this->assertNull($result);
    }

    #[Test]
    public function supports_returns_true_for_entry_and_page(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new EntryResolver($service);

        $this->assertTrue($resolver->supports(new Entry));
        $this->assertTrue($resolver->supports($this->mock(Page::class)));
        $this->assertFalse($resolver->supports('not-an-entry'));
    }

    #[Test]
    public function handle_returns_null_for_unsupported_item(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new EntryResolver($service);

        $this->assertNull($resolver->handle('not-an-entry'));
    }

    #[Test]
    public function handle_returns_null_when_collection_not_enabled(): void
    {
        Config::set('justbetter.structured-data.collections', ['other']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new EntryResolver($service);

        $this->assertNull($resolver->handle($entry));
    }

    #[Test]
    public function handle_returns_scripts_for_enabled_entry(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('getJsonLdScripts')->once()->with($entry)->andReturn(['<script>a</script>', '<script>b</script>']);
        });

        /** @var StructuredDataService $service */
        $resolver = new EntryResolver($service);

        $this->assertSame("<script>a</script>\n<script>b</script>", $resolver->handle($entry));
    }

    #[Test]
    public function handle_unwraps_page_to_entry(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');
        $page = $this->mock(Page::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('entry')->andReturn($entry);
        });

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('getJsonLdScripts')->once()->with($entry)->andReturn(['<script>page</script>']);
        });

        /** @var StructuredDataService $service */
        $resolver = new EntryResolver($service);

        $this->assertSame('<script>page</script>', $resolver->handle($page));
    }
}
