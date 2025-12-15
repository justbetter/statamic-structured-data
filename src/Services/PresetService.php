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
        $this->defaultPresetsPath = __DIR__.'/../../resources/presets';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getAvailablePresets(): Collection
    {
        $presets = collect();

        if (! config('justbetter.structured-data.presets.enabled', true)) {
            return $presets;
        }

        $defaultPresets = $this->loadDefaultPresets();
        $presets = $presets->merge($defaultPresets);

        $customPresets = $this->loadCustomPresets();
        $presets = $presets->merge($customPresets);

        return $presets;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function loadDefaultPresets(): Collection
    {
        $presets = collect();
        /** @var array<int, string> $enabledPresets */
        $enabledPresets = config('justbetter.structured-data.presets.default_presets', []);

        if (! File::exists($this->defaultPresetsPath)) {
            return $presets;
        }

        foreach ($enabledPresets as $presetName) {
            if (! is_string($presetName)) {
                continue;
            }

            $presetFile = $this->defaultPresetsPath."/{$presetName}.json";

            if (File::exists($presetFile)) {
                try {
                    /** @var array<string, mixed>|null $presetData */
                    $presetData = json_decode(File::get($presetFile), true);
                    if (is_array($presetData) && $this->validatePresetStructure($presetData)) {
                        $presets->push($presetData);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load preset: {$presetName}", ['error' => $e->getMessage()]);
                }
            }
        }

        return $presets;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function loadCustomPresets(): Collection
    {
        $presets = collect();
        /** @var array<int, string> $customPaths */
        $customPaths = config('justbetter.structured-data.presets.custom_preset_paths', []);

        foreach ($customPaths as $path) {
            if (! is_string($path) || ! File::exists($path)) {
                continue;
            }

            $files = File::files($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'json') {
                    try {
                        /** @var array<string, mixed>|null $presetData */
                        $presetData = json_decode(File::get($file->getPathname()), true);
                        if (is_array($presetData) && $this->validatePresetStructure($presetData)) {
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

    /**
     * @param  array<string, mixed>  $preset
     */
    protected function validatePresetStructure(array $preset): bool
    {
        if (! isset($preset['name']) || ! isset($preset['description']) || ! isset($preset['schema'])) {
            return false;
        }

        $schema = $preset['schema'];
        if (! is_array($schema)) {
            return false;
        }

        return isset($schema['specialProps'])
            && isset($schema['fields'])
            && is_array($schema['fields']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPresetByName(string $name): ?array
    {
        /** @var array<string, mixed>|null $preset */
        $preset = $this->getAvailablePresets()
            ->first(function (array $preset) use ($name): bool {
                return isset($preset['name']) && $preset['name'] === $name;
            });

        return $preset;
    }
}
