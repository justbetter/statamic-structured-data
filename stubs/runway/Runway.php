<?php

namespace StatamicRadPack\Runway;

use Illuminate\Support\Collection;

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
