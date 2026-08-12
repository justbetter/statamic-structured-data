<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\VariableProvider;

class SiteVariableProvider implements VariableProvider
{
    public function variables(mixed $parent = null): array
    {
        return [
            ['name' => 'site:name', 'description' => 'Site Name'],
            ['name' => 'site:url', 'description' => 'Site URL'],
        ];
    }
}
