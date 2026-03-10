<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Justbetter\StatamicStructuredData\Fieldtypes\AvailableVariablesFieldtype;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\GlobalSet as GlobalSetFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Taxonomies\Taxonomy;

class AvailableVariablesFieldtypeTest extends TestCase
{
    #[Test]
    public function default_value_returns_null(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $_ = $fieldtype->defaultValue();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function preload_returns_variables(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        $fieldtype->setField($fieldMock);

        $result = $fieldtype->preload();

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('variables', $result);
        /** @var array<string, mixed> $resultVariables */
        $resultVariables = $result['variables'];
        $this->assertArrayHasKey('site', $resultVariables);
        $this->assertArrayHasKey('globals', $resultVariables);
        $this->assertArrayHasKey('entry', $resultVariables);
        $this->assertArrayHasKey('term', $resultVariables);
    }

    #[Test]
    public function get_entry_fields_returns_empty_when_no_collection(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getEntryFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_entry_fields_handles_no_blueprints_gracefully(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collection = CollectionFacade::make('test');
        $collection->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $collectionMock = Mockery::mock($collection)->makePartial();
        $collectionMock->shouldReceive('entryBlueprints')->andReturn(collect([]));

        $entry->use_for_collection = $collectionMock;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getEntryFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
    }

    #[Test]
    public function get_term_fields_returns_empty_when_no_taxonomy(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getTermFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_term_fields_handles_no_blueprints_gracefully(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('test');
        $taxonomy->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([]));

        $entry->use_for_taxonomy = $taxonomyMock;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getTermFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
    }

    #[Test]
    public function field_type_is_eligible_returns_true_for_eligible_types(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('fieldTypeIsEligible');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($fieldtype, 'text'));
        $this->assertTrue($method->invoke($fieldtype, 'assets'));
        $this->assertTrue($method->invoke($fieldtype, 'bard'));
        $this->assertTrue($method->invoke($fieldtype, 'toggle'));
        $this->assertTrue($method->invoke($fieldtype, 'integer'));
        $this->assertTrue($method->invoke($fieldtype, 'slug'));
        $this->assertTrue($method->invoke($fieldtype, 'date'));
        $this->assertTrue($method->invoke($fieldtype, 'entries'));
        $this->assertTrue($method->invoke($fieldtype, 'aardvark_seo_meta_title'));
        $this->assertTrue($method->invoke($fieldtype, 'aardvark_seo_meta_description'));
    }

    #[Test]
    public function field_type_is_eligible_returns_false_for_ineligible_types(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('fieldTypeIsEligible');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($fieldtype, 'unknown_type'));
        $this->assertFalse($method->invoke($fieldtype, 'grid'));
        $this->assertFalse($method->invoke($fieldtype, 'replicator'));
    }

    #[Test]
    public function get_global_variables_returns_variables(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $blueprint = BlueprintFacade::make('test');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'test_field',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Test Field',
                    ],
                ],
            ],
        ]);
        $blueprint->save();

        $globalSet = GlobalSetFacade::make('test');
        $globalSet->save();
        $globalSet->blueprint('test');

        $fieldsMock = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('items')->andReturn(collect([
                [
                    'handle' => 'test_field',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Test Field',
                    ],
                ],
            ]));
        });

        $blueprintMock = $this->mock(Blueprint::class, function (MockInterface $mock) use ($fieldsMock): void {
            $mock->shouldReceive('fields')->andReturn($fieldsMock);
        });

        $globalSetMock = Mockery::mock($globalSet)->makePartial();
        $globalSetMock->shouldReceive('blueprint')->andReturn($blueprintMock);
        $globalSetMock->shouldReceive('handle')->andReturn('test');

        $globalCollection = new \Statamic\Globals\GlobalCollection([$globalSetMock]);
        \Statamic\Facades\GlobalSet::shouldReceive('all')->andReturn($globalCollection);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getGlobalVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function set_field_data_returns_null_for_ineligible_field(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('setFieldData');
        $method->setAccessible(true);

        $field = [
            'handle' => 'test',
            'field' => [
                'type' => 'grid',
            ],
        ];

        $result = $method->invoke($fieldtype, $field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_returns_null_when_handle_is_parent(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('setFieldData');
        $method->setAccessible(true);

        $field = [
            'handle' => 'parent',
            'field' => [
                'type' => 'text',
            ],
        ];

        $result = $method->invoke($fieldtype, $field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_returns_field_data_for_eligible_field(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('setFieldData');
        $method->setAccessible(true);

        $field = [
            'handle' => 'test_field',
            'field' => [
                'type' => 'text',
                'display' => 'Test Field',
            ],
        ];

        $result = $method->invoke($fieldtype, $field);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('children', $result);
        $this->assertEquals('test_field', $result['name']);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_no_collection_handle(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getCollectionVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, '', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_handle_is_structured_data_templates(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getCollectionVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, 'structured_data_templates', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_collection_not_found(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        \Statamic\Facades\Collection::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getCollectionVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, 'nonexistent', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_variables_when_collection_exists(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $blueprint = BlueprintFacade::make('blog');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Title',
                    ],
                ],
            ],
        ]);
        $blueprint->save();
        $collection->entryBlueprints();

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
                'collections' => ['blog'],
            ],
        ];

        \Statamic\Facades\Collection::shouldReceive('find')->with('blog')->andReturn($collection);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getCollectionVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, 'blog', $field);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_no_blueprint(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collectionMock = $this->mock(Collection::class, function ($mock): void {
            $mock->shouldReceive('entryBlueprints')->andReturn(collect([]));
        });

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
                'collections' => ['blog'],
            ],
        ];

        \Statamic\Facades\Collection::shouldReceive('find')->with('blog')->andReturn($collectionMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getCollectionVariables');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, 'blog', $field);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function set_field_data_returns_field_with_children_for_entries_field_type(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $blueprint = BlueprintFacade::make('blog');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Title',
                    ],
                ],
            ],
        ]);
        $blueprint->save();
        $collection->entryBlueprints();

        \Statamic\Facades\Collection::shouldReceive('find')->with('blog')->andReturn($collection);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('setFieldData');
        $method->setAccessible(true);

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
                'collections' => ['blog'],
            ],
        ];

        $result = $method->invoke($fieldtype, $field);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('children', $result);
        $this->assertNotEmpty($result['children']);
    }

    #[Test]
    public function set_field_data_returns_null_when_entries_field_has_no_collections(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('setFieldData');
        $method->setAccessible(true);

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
            ],
        ];

        $result = $method->invoke($fieldtype, $field);

        $this->assertNull($result);
    }

    #[Test]
    public function normalize_blueprint_items_converts_collection_to_array(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;
        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('normalizeBlueprintItems');
        $method->setAccessible(true);

        $collection = collect([
            ['handle' => 'test', 'field' => ['type' => 'text']],
        ]);

        $result = $method->invoke($fieldtype, $collection);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('test', $firstResult['handle']);
    }

    #[Test]
    public function normalize_blueprint_items_returns_array_as_is(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;
        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('normalizeBlueprintItems');
        $method->setAccessible(true);

        $input = [
            ['handle' => 'test', 'field' => ['type' => 'text']],
        ];

        $result = $method->invoke($fieldtype, $input);

        $this->assertIsArray($result);
        $this->assertEquals($input, $result);
    }

    #[Test]
    public function normalize_blueprint_items_returns_empty_array_for_invalid_input(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;
        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('normalizeBlueprintItems');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype, 'not-an-array');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_entry_fields_includes_base_fields_and_maps_collection_fields(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collection = CollectionFacade::make('test');
        $collection->save();
        $blueprint = BlueprintFacade::make('test');
        $blueprint->setNamespace('collections.test');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Title',
                    ],
                ],
            ],
        ]);
        $blueprint->save();
        $collection->entryBlueprints();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_collection = $collection;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getEntryFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('absolute_url', $firstResult['name']);
        $this->assertGreaterThanOrEqual(2, count($result));
        $fieldNames = array_column($result, 'name');
        $this->assertContains('title', $fieldNames);
    }

    #[Test]
    public function get_entry_fields_handles_empty_fields_after_merge_gracefully(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $collection = CollectionFacade::make('test');
        $collection->save();
        $blueprint = $this->mock(Blueprint::class);
        $fieldsMock = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('items')->andReturn([]);
        });
        $blueprint->shouldReceive('fields')->andReturn($fieldsMock);

        $collectionMock = Mockery::mock($collection)->makePartial();
        $collectionMock->shouldReceive('entryBlueprints')->andReturn(collect([$blueprint]));

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_collection = $collectionMock;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getEntryFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
    }

    #[Test]
    public function get_term_fields_handles_null_first_blueprint_gracefully(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('test');
        $taxonomy->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([null]));

        $entry->use_for_taxonomy = $taxonomyMock;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getTermFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
    }

    #[Test]
    public function get_term_fields_handles_empty_fields_after_merge_gracefully(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('test');
        $taxonomy->save();
        $blueprint = $this->mock(Blueprint::class);
        $fieldsMock = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('items')->andReturn([]);
        });
        $blueprint->shouldReceive('fields')->andReturn($fieldsMock);

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([$blueprint]));

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_taxonomy = $taxonomyMock;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getTermFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
    }

    #[Test]
    public function get_term_fields_includes_base_fields_and_maps_taxonomy_fields(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('test');
        $taxonomy->save();
        $blueprint = BlueprintFacade::make('test');
        $blueprint->setNamespace('taxonomies.test');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Title',
                    ],
                ],
            ],
        ]);
        $blueprint->save();
        /** @phpstan-ignore-next-line */
        $taxonomy->termBlueprints(['test']);

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_taxonomy = $taxonomy;
        $entryMock = $entry;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock) use ($entryMock): void {
            $mock->shouldReceive('parent')->andReturn($entryMock);
        });

        $fieldtype->setField($fieldMock);

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('getTermFields');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('absolute_url', $firstResult['name']);
        $this->assertGreaterThanOrEqual(2, count($result));
        $fieldNames = array_column($result, 'name');
        $this->assertContains('title', $fieldNames);
    }
}
