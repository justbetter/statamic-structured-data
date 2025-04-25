<?php

namespace Justbetter\StatamicStructuredData\Tests;

use Justbetter\StatamicStructuredData\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
