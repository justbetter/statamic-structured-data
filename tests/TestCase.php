<?php

namespace Justbetter\StatamicStructuredData\Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Justbetter\StatamicStructuredData\ServiceProvider;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use StatamicRadPack\Runway\ModelRepository;
use StatamicRadPack\Runway\Runway;

abstract class TestCase extends AddonTestCase
{
    use LazilyRefreshDatabase;
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        Runway::reset();
        RunwaySupport::fakeInstalled(false);

        assert($this->app !== null);
        $this->app->instance(ModelRepository::class, new ModelRepository);
    }

    protected function tearDown(): void
    {
        RunwaySupport::fakeInstalled(null);
        Runway::reset();

        parent::tearDown();
    }
}
