<?php

namespace StatamicRadPack\Runway;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;

class Runway
{
    /** @var Collection<string, resource>|null */
    public static $resources = null;

    /** @var array<string, resource|null> */
    public static array $findResults = [];

    public static function reset(): void
    {
        self::$resources = null;
        self::$findResults = [];
        Resource::reset();
        ModelRepository::reset();
    }

    /**
     * @param  array<string, resource>  $resources
     */
    public static function fakeResources(array $resources): void
    {
        self::$resources = new Collection($resources);
    }

    /**
     * @return Collection<string, resource>
     */
    public static function allResources(): Collection
    {
        return self::$resources ?? new Collection;
    }

    public static function findResource(string $resourceHandle): ?Resource
    {
        if (array_key_exists($resourceHandle, self::$findResults)) {
            return self::$findResults[$resourceHandle];
        }

        return self::allResources()->get($resourceHandle);
    }
}

class Resource
{
    public string $resourceHandle = '';

    public string $resourceName = '';

    public ?Blueprint $resourceBlueprint = null;

    public ?Model $resourceModel = null;

    public static function reset(): void
    {
        // no static state beyond instances
    }

    public function handle(): string
    {
        return $this->resourceHandle;
    }

    public function name(): string
    {
        return $this->resourceName;
    }

    public function blueprint(): Blueprint
    {
        return $this->resourceBlueprint ?? BlueprintFacade::make();
    }

    public function model(): Model
    {
        return $this->resourceModel ?? new class extends Model {};
    }
}

class ModelRepository
{
    public static mixed $findByUriResult = null;

    public static function reset(): void
    {
        self::$findByUriResult = null;
    }

    public function findByUri(string $uri): mixed
    {
        return self::$findByUriResult;
    }
}
