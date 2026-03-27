<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataBuilder;
use Justbetter\StatamicStructuredData\Services\PresetService;
use Justbetter\StatamicStructuredData\Services\ReplicatorFieldService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Field;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class StructuredDataBuilderTest extends TestCase
{
    #[Test]
    public function pre_process_returns_default_when_data_not_array(): void
    {
        /** @var PresetService $presetService */
        $presetService = $this->mock(PresetService::class);
        /** @var ReplicatorFieldService $replicatorFieldService */
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $result = $fieldtype->preProcess('not-an-array');

        /** @var array<int, array<string, mixed>> $result */
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('specialProps', $result[0]);
        $this->assertArrayHasKey('fields', $result[0]);
        /** @var array<string, mixed> $specialProps */
        $specialProps = $result[0]['specialProps'];
        $this->assertEquals('https://schema.org', $specialProps['context']);
        $this->assertEquals('', $specialProps['type']);
        $this->assertEquals('', $specialProps['id']);
        $this->assertIsArray($result[0]['fields']);
        $this->assertEmpty($result[0]['fields']);
    }

    #[Test]
    public function pre_process_returns_data_when_array(): void
    {
        /** @var PresetService $presetService */
        $presetService = $this->mock(PresetService::class);
        /** @var ReplicatorFieldService $replicatorFieldService */
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $data = [
            [
                'specialProps' => [
                    'context' => 'https://schema.org',
                    'type' => 'Person',
                    'id' => 'person-1',
                ],
                'fields' => ['name' => 'John'],
            ],
        ];

        $result = $fieldtype->preProcess($data);

        $this->assertEquals($data, $result);
    }

    #[Test]
    public function preload_returns_expected_data(): void
    {
        Config::set('app.url', 'https://example.com');
        Config::set('justbetter.structured-data.presets.enabled', true);

        $presetService = $this->mock(PresetService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAvailablePresets')->andReturn(collect([
                ['name' => 'test', 'description' => 'Test'],
            ]));
        });

        $replicatorFieldService = $this->mock(ReplicatorFieldService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReplicatorFields')->andReturn([]);
        });

        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        /** @var Field $fieldMock */
        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);
        $fieldtype->setField($fieldMock);

        $result = $fieldtype->preload();

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('base_url', $result);
        $this->assertArrayHasKey('taxonomy_terms', $result);
        $this->assertArrayHasKey('presets', $result);
        $this->assertArrayHasKey('presets_enabled', $result);
        $this->assertArrayHasKey('replicator_fields', $result);
        $this->assertEquals('https://example.com', $result['base_url']);
        $this->assertTrue($result['presets_enabled']);
    }

    #[Test]
    public function get_replicator_fields_returns_empty_when_no_parent(): void
    {
        $presetService = $this->mock(PresetService::class);
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        /** @var Field $fieldMock */
        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);
        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getReplicatorFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_returns_fields_when_parent_exists(): void
    {
        $presetService = $this->mock(PresetService::class);
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReplicatorFields')->andReturn([
                ['handle' => 'test_field', 'type' => 'text'],
            ]);
        });

        $collection = CollectionFacade::make('test');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123');

        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('parent')->andReturn($entry);
        });

        /** @var Field $fieldMock */
        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);
        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getReplicatorFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_structured_data_objects_returns_empty_when_no_taxonomy(): void
    {
        $presetService = $this->mock(PresetService::class);
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        TaxonomyFacade::shouldReceive('findByHandle')->with('structured_data_objects')->andReturn(null);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getStructuredDataObjects');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_structured_data_objects_returns_empty_when_no_site(): void
    {
        $presetService = $this->mock(PresetService::class);
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->save();

        TaxonomyFacade::shouldReceive('findByHandle')->with('structured_data_objects')->andReturn($taxonomy);
        Site::shouldReceive('selected')->andReturn(null);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getStructuredDataObjects');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_structured_data_objects_returns_terms_when_taxonomy_and_site_exist(): void
    {
        $presetService = $this->mock(PresetService::class);
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->save();
        $site = $this->mock(\Statamic\Sites\Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        $term = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('get')->with('title')->andReturn('Test Object');
            $mock->shouldReceive('slug')->andReturn('test-object');
            $mock->shouldReceive('get')->with('object_data')->andReturn(['test' => 'data']);
        });

        $queryBuilder = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($term): void {
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('get')->andReturn(collect([$term]));
        });

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('queryTerms')->andReturn($queryBuilder);

        TaxonomyFacade::shouldReceive('findByHandle')->with('structured_data_objects')->andReturn($taxonomyMock);
        Site::shouldReceive('selected')->andReturn($site);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getStructuredDataObjects');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);
        /** @var array<string, mixed> $firstItem */
        $firstItem = $result->first();
        $this->assertArrayHasKey('title', $firstItem);
        $this->assertArrayHasKey('slug', $firstItem);
        $this->assertArrayHasKey('object_data', $firstItem);
    }

    #[Test]
    public function config_field_items_returns_expected_config(): void
    {
        /** @var PresetService $presetService */
        $presetService = $this->mock(PresetService::class);
        /** @var ReplicatorFieldService $replicatorFieldService */
        $replicatorFieldService = $this->mock(ReplicatorFieldService::class);

        /** @var PresetService $presetService */
        /** @var ReplicatorFieldService $replicatorFieldService */
        $fieldtype = new StructuredDataBuilder($presetService, $replicatorFieldService);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('configFieldItems');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('allow_multiple', $result);
        /** @var array<string, mixed> $allowMultiple */
        $allowMultiple = $result['allow_multiple'];
        $this->assertEquals('Allow Multiple Objects', $allowMultiple['display']);
        $this->assertEquals('toggle', $allowMultiple['type']);
        $this->assertTrue($allowMultiple['default']);
    }
}
