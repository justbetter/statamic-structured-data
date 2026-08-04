<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $this->assertSame('product', RunwaySupport::resolveResourceHandle($model, 'product'));
        $this->assertNull(RunwaySupport::resolveResourceHandle($model, 'category'));
    }

    #[Test]
    public function resolve_resource_handle_falls_back_to_class_basename(): void
    {
        Config::set('justbetter.structured-data.runway', ['runway_support_test_product']);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        // Anonymous class basename won't match; use a named stub instead.
        $named = new RunwaySupportTestProduct;

        $this->assertSame('runway_support_test_product', RunwaySupport::resolveResourceHandle($named));
    }

    #[Test]
    public function resource_options_returns_empty_when_runway_not_installed(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        if (RunwaySupport::isInstalled()) {
            $this->markTestSkipped('Runway is installed in this environment.');
        }

        $this->assertSame([], RunwaySupport::resourceOptions());
    }

    #[Test]
    public function find_by_uri_returns_null_when_runway_not_installed(): void
    {
        if (RunwaySupport::isInstalled()) {
            $this->markTestSkipped('Runway is installed in this environment.');
        }

        $this->assertNull(RunwaySupport::findByUri('/some-product'));
    }
}

class RunwaySupportTestProduct extends Model
{
    protected $table = 'products';
}
