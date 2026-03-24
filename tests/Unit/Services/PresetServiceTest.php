<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Services\PresetService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PresetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('justbetter.structured-data.presets.enabled', true);
        Config::set('justbetter.structured-data.presets.default_presets', ['website', 'organization']);
        Config::set('justbetter.structured-data.presets.custom_preset_paths', []);
    }

    #[Test]
    public function it_returns_empty_collection_when_presets_are_disabled(): void
    {
        Config::set('justbetter.structured-data.presets.enabled', false);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_loads_default_presets_when_files_exist(): void
    {
        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_preset_by_name_returns_preset_when_found(): void
    {
        $service = new PresetService;

        $presets = $service->getAvailablePresets();
        $this->assertNotEmpty($presets, 'Presets should be loaded for this test');

        $firstPreset = $presets->first();
        $this->assertIsArray($firstPreset, 'First preset should be an array');
        $presetName = $firstPreset['name'] ?? null;
        $this->assertNotNull($presetName, 'Preset should have a name');
        $this->assertIsString($presetName);

        $result = $service->getPresetByName($presetName);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals($presetName, $result['name']);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('schema', $result);
    }

    #[Test]
    public function get_preset_by_name_returns_null_when_preset_not_found(): void
    {
        $service = new PresetService;

        $result = $service->getPresetByName('nonexistent_preset_name_12345');

        $this->assertNull($result);
    }

    #[Test]
    public function it_loads_custom_presets_from_custom_paths(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $customPreset = [
            'name' => 'custom_test',
            'description' => 'Custom Test',
            'schema' => [
                'specialProps' => [],
                'fields' => [],
            ],
        ];

        file_put_contents($tempDir.'/custom_test.json', json_encode($customPreset));

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);

        $customPresetResult = $service->getPresetByName('custom_test');
        $this->assertIsArray($customPresetResult);
        $this->assertArrayHasKey('name', $customPresetResult);
        $this->assertEquals('custom_test', $customPresetResult['name']);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_skips_non_json_files_in_custom_preset_paths(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/not_json.txt', 'some content');

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_skips_invalid_preset_files(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/invalid.json', 'invalid json content {');

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_skips_presets_with_invalid_structure(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $invalidPreset = ['name' => 'test'];
        file_put_contents($tempDir.'/invalid.json', json_encode($invalidPreset));

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_rejects_preset_without_name(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $invalidPreset = [
            'description' => 'Test',
            'schema' => ['specialProps' => [], 'fields' => []],
        ];
        file_put_contents($tempDir.'/invalid.json', json_encode($invalidPreset));

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_rejects_preset_without_schema_fields_array(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $invalidPreset = [
            'name' => 'test',
            'description' => 'Test',
            'schema' => [
                'specialProps' => [],
                'fields' => 'not an array',
            ],
        ];
        file_put_contents($tempDir.'/invalid.json', json_encode($invalidPreset));

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_skips_invalid_custom_preset_paths(): void
    {
        $invalidPath = '/nonexistent/path/'.uniqid();

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$invalidPath]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_skips_non_string_preset_names_in_default_presets_config(): void
    {
        Config::set('justbetter.structured-data.presets.default_presets', ['website', 123, null, 'organization']);

        $service = new PresetService;

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_returns_empty_when_default_presets_path_does_not_exist(): void
    {
        $service = new PresetService;
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('defaultPresetsPath');
        $property->setAccessible(true);
        $originalPath = $property->getValue($service);
        $property->setValue($service, '/nonexistent/path/'.uniqid());

        Config::set('justbetter.structured-data.presets.default_presets', ['website']);

        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        $property->setValue($service, $originalPath);
    }

    #[Test]
    public function it_validates_schema_is_array(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $invalidPreset = [
            'name' => 'test',
            'description' => 'Test',
            'schema' => 'not an array',
        ];
        file_put_contents($tempDir.'/invalid.json', json_encode($invalidPreset));

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;
        $result = $service->getAvailablePresets();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);

        File::deleteDirectory($tempDir);
    }

    #[Test]
    public function it_handles_exceptions_when_loading_default_presets(): void
    {
        $service = new PresetService;
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('defaultPresetsPath');
        $property->setAccessible(true);
        $originalPath = $property->getValue($service);

        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $presetFile = $tempDir.'/test.json';
        touch($presetFile);
        chmod($presetFile, 0000);

        $property->setValue($service, $tempDir);
        Config::set('justbetter.structured-data.presets.default_presets', ['test']);

        try {
            $result = $service->getAvailablePresets();
            $this->assertInstanceOf(Collection::class, $result);
        } finally {
            chmod($presetFile, 0644);
            File::deleteDirectory($tempDir);
            $property->setValue($service, $originalPath);
        }
    }

    #[Test]
    public function it_handles_exceptions_when_loading_custom_presets(): void
    {
        $tempDir = sys_get_temp_dir().'/test_presets_'.uniqid();
        mkdir($tempDir, 0755, true);

        $presetFile = $tempDir.'/test.json';
        touch($presetFile);
        chmod($presetFile, 0000);

        Config::set('justbetter.structured-data.presets.custom_preset_paths', [$tempDir]);
        Config::set('justbetter.structured-data.presets.default_presets', []);

        $service = new PresetService;

        try {
            $result = $service->getAvailablePresets();
            $this->assertInstanceOf(Collection::class, $result);
        } finally {
            chmod($presetFile, 0644);
            File::deleteDirectory($tempDir);
        }
    }
}
