<?php

namespace Justbetter\StatamicStructuredData\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use StatamicRadPack\Runway\ModelRepository;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Runway;

class RunwaySupport
{
    public static function isInstalled(): bool
    {
        return class_exists(Runway::class);
    }

    /**
     * @return array<int, string>
     */
    public static function enabledHandles(): array
    {
        $handles = config('justbetter.structured-data.runway', []);

        return is_array($handles) ? array_values(array_filter($handles, 'is_string')) : [];
    }

    public static function isHandleEnabled(string $handle): bool
    {
        return in_array($handle, self::enabledHandles(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function resourceOptions(): array
    {
        if (! self::isInstalled()) {
            return [];
        }

        $enabled = self::enabledHandles();

        if ($enabled === []) {
            return [];
        }

        return Runway::allResources()
            ->filter(fn (Resource $resource): bool => in_array($resource->handle(), $enabled, true))
            ->mapWithKeys(fn (Resource $resource): array => [$resource->handle() => $resource->name()])
            ->all();
    }

    public static function resolveResourceHandle(mixed $item, ?string $explicitResource = null): ?string
    {
        if (is_string($explicitResource) && $explicitResource !== '') {
            return self::isHandleEnabled($explicitResource) ? $explicitResource : null;
        }

        if (self::isInstalled() && is_object($item) && method_exists($item, 'runwayResource')) {
            try {
                $resource = $item->runwayResource();

                if ($resource instanceof Resource) {
                    $handle = $resource->handle();

                    if (self::isHandleEnabled($handle)) {
                        return $handle;
                    }
                }
            } catch (\Throwable) {
                // Model may not be registered as a Runway resource.
            }
        }

        if (is_object($item)) {
            $basenameHandle = Str::snake(class_basename($item));

            if (self::isHandleEnabled($basenameHandle)) {
                return $basenameHandle;
            }
        }

        return null;
    }

    public static function findByUri(string $uri): ?Model
    {
        if (! self::isInstalled()) {
            return null;
        }

        $model = app(ModelRepository::class)->findByUri($uri);

        return $model instanceof Model ? $model : null;
    }

    public static function findResource(string $handle): ?Resource
    {
        if (! self::isInstalled() || ! self::isHandleEnabled($handle)) {
            return null;
        }

        return Runway::findResource($handle);
    }
}
