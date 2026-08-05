<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Support;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\Stubs\BrokenProduct;
use Justbetter\StatamicStructuredData\Tests\Stubs\Product;
use Justbetter\StatamicStructuredData\Tests\Stubs\RunwaySupportTestProduct;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Runway\ModelRepository;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Runway;

class RunwaySupportTest extends TestCase
{
    #[Test]
    public function enabled_handles_returns_configured_handles(): void
    {
        Config::set('justbetter.structured-data.runway', ['product', 'category']);

        $this->assertSame(['product', 'category'], RunwaySupport::enabledHandles());
    }

    #[Test]
    public function is_handle_enabled_checks_config(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $this->assertTrue(RunwaySupport::isHandleEnabled('product'));
        $this->assertFalse(RunwaySupport::isHandleEnabled('category'));
    }

    #[Test]
    public function resolve_resource_handle_uses_explicit_resource(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new Product;

        $this->assertSame('product', RunwaySupport::resolveResourceHandle($model, 'product'));
        $this->assertNull(RunwaySupport::resolveResourceHandle($model, 'category'));
    }

    #[Test]
    public function resolve_resource_handle_falls_back_to_class_basename(): void
    {
        Config::set('justbetter.structured-data.runway', ['runway_support_test_product']);

        $named = new RunwaySupportTestProduct;

        $this->assertSame('runway_support_test_product', RunwaySupport::resolveResourceHandle($named));
    }

    #[Test]
    public function resource_options_returns_empty_when_runway_not_installed(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);
        RunwaySupport::fakeInstalled(false);

        $this->assertSame([], RunwaySupport::resourceOptions());
    }

    #[Test]
    public function resource_options_returns_empty_when_no_handles_enabled(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', []);

        $this->assertSame([], RunwaySupport::resourceOptions());
    }

    #[Test]
    public function resource_options_returns_enabled_resources_when_installed(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $product = new Resource;
        $product->resourceHandle = 'product';
        $product->resourceName = 'Products';

        $category = new Resource;
        $category->resourceHandle = 'category';
        $category->resourceName = 'Categories';

        Runway::fakeResources([
            'product' => $product,
            'category' => $category,
        ]);

        $this->assertSame(['product' => 'Products'], RunwaySupport::resourceOptions());
    }

    #[Test]
    public function resolve_resource_handle_uses_runway_resource_method(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';

        $model = new Product;
        $model->runwayResource = $resource;

        $this->assertSame('product', RunwaySupport::resolveResourceHandle($model));
    }

    #[Test]
    public function resolve_resource_handle_ignores_disabled_runway_resource(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['category']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';

        $model = new Product;
        $model->runwayResource = $resource;

        $this->assertNull(RunwaySupport::resolveResourceHandle($model));
    }

    #[Test]
    public function resolve_resource_handle_catches_runway_resource_exceptions(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $this->assertNull(RunwaySupport::resolveResourceHandle(new BrokenProduct));
    }

    #[Test]
    public function resolve_resource_handle_returns_null_for_non_objects_without_explicit_handle(): void
    {
        $this->assertNull(RunwaySupport::resolveResourceHandle('not-an-object'));
    }

    #[Test]
    public function find_by_uri_returns_null_when_runway_not_installed(): void
    {
        RunwaySupport::fakeInstalled(false);

        $this->assertNull(RunwaySupport::findByUri('/some-product'));
    }

    #[Test]
    public function find_by_uri_returns_model_when_repository_finds_one(): void
    {
        RunwaySupport::fakeInstalled(true);

        $model = new Product;

        ModelRepository::$findByUriResult = $model;

        $this->assertSame($model, RunwaySupport::findByUri('/product'));
    }

    #[Test]
    public function find_by_uri_returns_null_when_repository_result_is_not_a_model(): void
    {
        RunwaySupport::fakeInstalled(true);
        ModelRepository::$findByUriResult = 'not-a-model';

        $this->assertNull(RunwaySupport::findByUri('/product'));
    }

    #[Test]
    public function find_resource_returns_null_when_not_installed_or_disabled(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);
        RunwaySupport::fakeInstalled(false);
        $this->assertNull(RunwaySupport::findResource('product'));

        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', []);
        $this->assertNull(RunwaySupport::findResource('product'));
    }

    #[Test]
    public function find_resource_returns_resource_when_enabled(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';
        Runway::$findResults['product'] = $resource;

        $this->assertSame($resource, RunwaySupport::findResource('product'));
    }
}
