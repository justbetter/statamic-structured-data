<?php

namespace StatamicRadPack\Runway;

use Illuminate\Database\Eloquent\Model;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;

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
