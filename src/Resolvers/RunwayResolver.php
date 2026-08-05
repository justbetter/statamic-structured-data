<?php

namespace Justbetter\StatamicStructuredData\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Facades\URL;

class RunwayResolver extends AbstractResourceResolver
{
    public function resolveCurrent(): ?Model
    {
        if (! RunwaySupport::isInstalled()) {
            return null;
        }

        $url = URL::getCurrent();
        $model = RunwaySupport::findByUri($url);

        if (! $model instanceof Model) {
            return null;
        }

        $handle = RunwaySupport::resolveResourceHandle($model);

        return $handle ? $model : null;
    }

    public function supports(mixed $item): bool
    {
        return $item instanceof Model;
    }

    public function handle(mixed $item, ?string $resourceHandle = null): ?string
    {
        if (! $item instanceof Model) {
            return null;
        }

        $handle = RunwaySupport::resolveResourceHandle($item, $resourceHandle);

        if (! $handle) {
            return null;
        }

        $scripts = $this->structuredDataService->getJsonLdScripts($item, false, $handle);

        if (! $scripts) {
            return null;
        }

        return implode("\n", $scripts);
    }
}
