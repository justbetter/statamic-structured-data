<?php

namespace StatamicRadPack\Runway;

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
