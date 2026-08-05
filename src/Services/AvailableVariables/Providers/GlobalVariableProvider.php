<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\VariableProvider;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\GlobalSet as StatamicGlobalSet;

class GlobalVariableProvider implements VariableProvider
{
    public function __construct(protected BlueprintVariableMapper $mapper) {}

    public function variables(mixed $parent = null): array
    {
        $variables = collect();

        GlobalSet::all()->each(function ($globalSet) use (&$variables): void {
            /** @var StatamicGlobalSet $globalSet */
            $fields = $globalSet->blueprint()?->fields();
            $globalVariables = [];

            if ($fields) {
                $globalVariables = $fields->items()->map(function (array $field) use ($globalSet): ?array {
                    $name = ($globalSet->handle().':'.($field['handle'] ?? ''));
                    $description = ($field['field']['display'] ?? ($field['handle'] ?? ''));

                    return $this->mapper->setFieldData($field, $name, $description);
                })->values()->all();
            }

            $variables = $variables->merge($globalVariables);
        });

        return $variables->filter()->values()->all();
    }
}
