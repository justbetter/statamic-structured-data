<?php

namespace StatamicRadPack\Runway;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint;

class Runway
{
    /**
     * @return Collection<string, resource>
     */
    public static function allResources(): Collection
    {
        return new Collection;
    }

    public static function findResource(string $resourceHandle): ?Resource
    {
        return null;
    }
}

class Resource
{
    public function handle(): string
    {
        return '';
    }

    public function name(): string
    {
        return '';
    }

    public function blueprint(): Blueprint
    {
        return \Statamic\Facades\Blueprint::make();
    }

    public function model(): Model
    {
        return new class extends Model {};
    }
}

class ModelRepository
{
    public function findByUri(string $uri): mixed
    {
        return null;
    }
}
