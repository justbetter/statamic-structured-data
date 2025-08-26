<?php

namespace Justbetter\StatamicStructuredData\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PresetService
{
    protected string $defaultPresetsPath;

    public function __construct()
    {
        $this->defaultPresetsPath = __DIR__ . '/../../resources/presets';
    }

    public function getAvailablePresets(): Collection
    {
        $presets = collect();

        if (!config('justbetter.structured-data.presets.enabled', true)) {
            return $presets;
        }

        $defaultPresets = $this->loadDefaultPresets();
        $presets = $presets->merge($defaultPresets);

        $customPresets = $this->loadCustomPresets();
        $presets = $presets->merge($customPresets);

        return $presets;
    }

    protected function loadDefaultPresets(): Collection
    {
        $presets = collect();
        $enabledPresets = config('justbetter.structured-data.presets.default_presets', []);

        if (!File::exists($this->defaultPresetsPath)) {
            return $presets;
        }

        foreach ($enabledPresets as $presetName) {
            $presetFile = $this->defaultPresetsPath . "/{$presetName}.json";
            
            if (File::exists($presetFile)) {
                try {
                    $presetData = json_decode(File::get($presetFile), true);
                    if ($presetData && $this->validatePresetStructure($presetData)) {
                        $presets->push($presetData);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load preset: {$presetName}", ['error' => $e->getMessage()]);
                }
            }
        }

        return $presets;
    }

    protected function loadCustomPresets(): Collection
    {
        $presets = collect();
        $customPaths = config('justbetter.structured-data.presets.custom_preset_paths', []);

        foreach ($customPaths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::files($path);
            
            foreach ($files as $file) {
                if ($file->getExtension() === 'json') {
                    try {
                        $presetData = json_decode(File::get($file->getPathname()), true);
                        if ($presetData && $this->validatePresetStructure($presetData)) {
                            $presets->push($presetData);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to load custom preset: {$file->getFilename()}", ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        return $presets;
    }

    protected function validatePresetStructure(array $preset): bool
    {
        return isset($preset['name']) 
            && isset($preset['description'])
            && isset($preset['schema'])
            && isset($preset['schema']['specialProps'])
            && isset($preset['schema']['fields'])
            && is_array($preset['schema']['fields']);
    }

    public function getPresetByName(string $name): ?array
    {
        return $this->getAvailablePresets()
            ->first(function (array $preset) use ($name): bool {
                return $preset['name'] === $name;
            });
    }
}