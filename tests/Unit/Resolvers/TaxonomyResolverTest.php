<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Resolvers;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Resolvers\TaxonomyResolver;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class TaxonomyResolverTest extends TestCase
{
    #[Test]
    public function resolve_current_returns_localized_term(): void
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
        $resolver = new TaxonomyResolver($service);

        $result = $resolver->resolveCurrent();

        $this->assertInstanceOf(LocalizedTerm::class, $result);
    }

    #[Test]
    public function resolve_current_returns_null_when_not_localized_term(): void
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
        $resolver = new TaxonomyResolver($service);

        $result = $resolver->resolveCurrent();

        $this->assertNull($result);
    }

    #[Test]
    public function supports_returns_true_for_localized_term(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new TaxonomyResolver($service);

        $this->assertTrue($resolver->supports($this->mock(LocalizedTerm::class)));
        $this->assertFalse($resolver->supports('not-a-term'));
    }

    #[Test]
    public function handle_returns_null_for_unsupported_item(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new TaxonomyResolver($service);

        $this->assertNull($resolver->handle('not-a-term'));
    }

    #[Test]
    public function handle_returns_null_when_taxonomy_not_enabled(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['other']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new TaxonomyResolver($service);

        $this->assertNull($resolver->handle($term));
    }

    #[Test]
    public function handle_returns_scripts_for_enabled_term(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($term): void {
            $mock->shouldReceive('getJsonLdScripts')->once()->with($term)->andReturn(['<script>term</script>']);
        });

        /** @var StructuredDataService $service */
        $resolver = new TaxonomyResolver($service);

        $this->assertSame('<script>term</script>', $resolver->handle($term));
    }
}
