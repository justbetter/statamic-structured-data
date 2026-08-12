<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Resolvers;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Resolvers\RunwayResolver;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\Stubs\Product;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\URL;
use StatamicRadPack\Runway\ModelRepository;
use StatamicRadPack\Runway\Resource;

class RunwayResolverTest extends TestCase
{
    #[Test]
    public function resolve_current_returns_null_when_runway_not_installed(): void
    {
        RunwaySupport::fakeInstalled(false);

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->resolveCurrent());
    }

    #[Test]
    public function resolve_current_returns_null_when_uri_result_is_not_model(): void
    {
        RunwaySupport::fakeInstalled(true);
        ModelRepository::$findByUriResult = 'not-a-model';

        URL::shouldReceive('getCurrent')->andReturn('/product-url');

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->resolveCurrent());
    }

    #[Test]
    public function resolve_current_returns_null_when_resource_not_enabled(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', []);

        $model = new Product;

        ModelRepository::$findByUriResult = $model;
        URL::shouldReceive('getCurrent')->andReturn('/product-url');

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->resolveCurrent());
    }

    #[Test]
    public function resolve_current_returns_model_when_enabled(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';

        $model = new Product;
        $model->runwayResource = $resource;

        ModelRepository::$findByUriResult = $model;
        URL::shouldReceive('getCurrent')->andReturn('/product-url');

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertSame($model, $resolver->resolveCurrent());
    }

    #[Test]
    public function supports_returns_true_for_model(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertTrue($resolver->supports(new Product));
        $this->assertFalse($resolver->supports('not-a-model'));
    }

    #[Test]
    public function handle_returns_null_for_unsupported_item(): void
    {
        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->handle('not-a-model'));
    }

    #[Test]
    public function handle_returns_null_when_resource_not_enabled(): void
    {
        Config::set('justbetter.structured-data.runway', []);

        $model = new Product;

        /** @var StructuredDataService $service */
        $service = $this->mock(StructuredDataService::class);
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->handle($model, 'product'));
    }

    #[Test]
    public function handle_returns_null_when_scripts_empty(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new Product;

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn([]);
        });

        /** @var StructuredDataService $service */
        $resolver = new RunwayResolver($service);

        $this->assertNull($resolver->handle($model, 'product'));
    }

    #[Test]
    public function handle_returns_imploded_scripts(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new Product;

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn(['<script>a</script>', '<script>b</script>']);
        });

        /** @var StructuredDataService $service */
        $resolver = new RunwayResolver($service);

        $this->assertSame("<script>a</script>\n<script>b</script>", $resolver->handle($model, 'product'));
    }
}
