<?php

namespace Justbetter\StatamicStructuredData\Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Justbetter\StatamicStructuredData\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use LazilyRefreshDatabase;
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;
}
