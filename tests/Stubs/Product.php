<?php

namespace Justbetter\StatamicStructuredData\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use StatamicRadPack\Runway\Resource;

class Product extends Model
{
    protected $table = 'products';

    protected $guarded = [];

    public ?Resource $runwayResource = null;

    public function runwayResource(): ?Resource
    {
        return $this->runwayResource;
    }
}
