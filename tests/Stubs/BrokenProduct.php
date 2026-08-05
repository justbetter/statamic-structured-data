<?php

namespace Justbetter\StatamicStructuredData\Tests\Stubs;

use StatamicRadPack\Runway\Resource;

class BrokenProduct extends Product
{
    public function runwayResource(): Resource
    {
        throw new \RuntimeException('not registered');
    }
}
