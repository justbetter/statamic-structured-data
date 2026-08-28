<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Services\ReplicatorFieldService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Fieldset as FieldsetFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\LabeledValue;
use Statamic\Taxonomies\Taxonomy;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Runway;

class ReplicatorFieldServiceTest extends TestCase
{
    #[Test]
    public function get_replicator_fields_returns_empty_array_when_no_collection_or_taxonomy(): void
    {
        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-123');

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_returns_empty_array_when_called_with_collection_and_no_blueprints(): void
    {
        /** @var Collection&MockInterface $collectionMock */
        $collectionMock = $this->mock(Collection::class, function ($mock): void {
            $mock->shouldReceive('entryBlueprints')->andReturn(collect([]));
        });

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($collectionMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_handles_taxonomy(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([]));

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_taxonomy')->andReturn($taxonomyMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_returns_empty_array_when_called_with_taxonomy_and_no_blueprints(): void
    {
        /** @var Taxonomy&MockInterface $taxonomyMock */
        $taxonomyMock = $this->mock(Taxonomy::class, function ($mock): void {
            $mock->shouldReceive('termBlueprints')->andReturn(collect([]));
        });

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($taxonomyMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_uses_taxonomy_when_collection_is_null(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([]));

        $template->use_for_taxonomy = $taxonomyMock;

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_extracts_replicator_fields_from_blueprints(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $blueprint = BlueprintFacade::make('blog')
            ->setNamespace('collections.blog')
            ->setContents([
                'fields' => [
                    [
                        'handle' => 'content',
                        'field' => [
                            'type' => 'replicator',
                            'display' => 'Content',
                            'sets' => [
                                'text' => [
                                    'display' => 'Text',
                                    'fields' => [
                                        'text_field' => ['type' => 'text', 'display' => 'Text Field'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        $blueprint->save();
        $collection->entryBlueprints();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $template->use_for_collection = $collection;

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('handle', $result[0]);
        $this->assertEquals('content', $result[0]['handle']);
    }

    #[Test]
    public function get_replicator_fields_skips_non_field_values(): void
    {
        $blueprint = $this->mock(Blueprint::class);
        $fieldsMock = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->andReturn(collect([
                'invalid' => 'not-a-field',
                'valid' => $this->mockField('valid', ['type' => 'text']),
            ]));
        });
        $blueprint->shouldReceive('fields')->andReturn($fieldsMock);

        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldsFromBlueprints');
        $method->setAccessible(true);
        $result = $method->invoke($service, collect([$blueprint]));

        $this->assertIsArray($result);
        /** @var array<int, array<string, mixed>> $result */
        $this->assertCount(1, $result);
        $this->assertSame('valid', $result[0]['handle']);
    }

    #[Test]
    public function extract_fields_from_blueprints_handles_collection_items(): void
    {
        $blueprint = $this->mock(Blueprint::class);
        $fields = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->andReturn(collect([
                'field1' => $this->mockField('field1'),
                'field2' => $this->mockField('field2'),
            ]));
        });

        $blueprint->shouldReceive('fields')->andReturn($fields);

        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldsFromBlueprints');
        $method->setAccessible(true);
        $result = $method->invoke($service, collect([$blueprint]));

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        /** @var array<int, array<string, mixed>> $result */
        $this->assertSame('field1', $result[0]['handle']);
        $this->assertSame('field2', $result[1]['handle']);
    }

    #[Test]
    public function extract_fields_from_blueprints_handles_empty_collection_items(): void
    {
        $blueprint = $this->mock(Blueprint::class);
        $fields = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->andReturn(collect([]));
        });

        $blueprint->shouldReceive('fields')->andReturn($fields);

        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldsFromBlueprints');
        $method->setAccessible(true);
        $result = $method->invoke($service, collect([$blueprint]));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function parse_replicator_fields_filters_non_replicator_fields(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseReplicatorFields');
        $method->setAccessible(true);

        $fieldsArray = [
            ['handle' => 'text_field', 'field' => ['type' => 'text']],
            ['handle' => 'replicator_field', 'field' => ['type' => 'replicator', 'sets' => []]],
        ];

        $result = $method->invoke($service, $fieldsArray);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('replicator_field', $firstResult['handle']);
    }

    #[Test]
    public function parse_replicator_fields_skips_non_array_fields(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseReplicatorFields');
        $method->setAccessible(true);

        $fieldsArray = [
            'not-an-array',
            ['handle' => 'replicator_field', 'field' => ['type' => 'replicator', 'sets' => []]],
        ];

        $result = $method->invoke($service, $fieldsArray);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function normalize_field_converts_object_to_array(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeField');
        $method->setAccessible(true);

        $object = new class
        {
            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return ['handle' => 'test'];
            }
        };

        $result = $method->invoke($service, $object);

        $this->assertIsArray($result);
        $this->assertEquals('test', $result['handle']);
    }

    #[Test]
    public function normalize_field_returns_array_as_is(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeField');
        $method->setAccessible(true);

        $array = ['handle' => 'test'];
        $result = $method->invoke($service, $array);

        $this->assertIsArray($result);
        $this->assertEquals('test', $result['handle']);
    }

    #[Test]
    public function normalize_field_returns_null_for_invalid_input(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeField');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not-an-array');

        $this->assertNull($result);
    }

    #[Test]
    public function normalize_field_returns_null_when_to_array_returns_non_array(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeField');
        $method->setAccessible(true);

        $object = new class
        {
            public function toArray(): null
            {
                return null;
            }
        };

        $result = $method->invoke($service, $object);

        $this->assertNull($result);
    }

    #[Test]
    public function normalize_field_returns_null_when_array_has_non_string_keys(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeField');
        $method->setAccessible(true);

        $array = [
            'valid' => 'value',
            0 => 'invalid',
        ];

        $result = $method->invoke($service, $array);

        $this->assertNull($result);
    }

    #[Test]
    public function build_replicator_field_returns_null_when_no_handle(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildReplicatorField');
        $method->setAccessible(true);

        $result = $method->invoke($service, [], []);

        $this->assertNull($result);
    }

    #[Test]
    public function build_replicator_field_builds_field_structure(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildReplicatorField');
        $method->setAccessible(true);

        $field = ['handle' => 'content'];
        $fieldConfig = [
            'display' => 'Content',
            'sets' => [
                'text' => ['display' => 'Text', 'fields' => []],
            ],
        ];

        $result = $method->invoke($service, $field, $fieldConfig);

        $this->assertIsArray($result);
        $this->assertEquals('content', $result['handle']);
        $this->assertEquals('Content', $result['display']);
        $this->assertArrayHasKey('sets', $result);
    }

    #[Test]
    public function parse_sets_parses_set_configurations(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSets');
        $method->setAccessible(true);

        $sets = [
            'text' => [
                'display' => 'Text Block',
                'fields' => [],
            ],
            'image' => [
                'display' => 'Image Block',
                'fields' => [],
            ],
        ];

        $result = $method->invoke($service, $sets);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('text', $firstResult['value']);
        $this->assertEquals('Text Block', $firstResult['label']);
    }

    #[Test]
    public function parse_sets_uses_nested_set_handles_from_set_groups(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSets');
        $method->setAccessible(true);

        $sets = [
            'new_opening_time' => [
                'display' => 'New opening time',
                'sets' => [
                    'opening_time' => [
                        'display' => 'Opening time',
                        'fields' => [
                            [
                                'handle' => 'day',
                                'field' => ['type' => 'select', 'display' => 'Day'],
                            ],
                            [
                                'handle' => 'opening_time',
                                'field' => ['type' => 'time', 'display' => 'Opens'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $method->invoke($service, $sets);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertSame('opening_time', $firstResult['value']);
        $this->assertSame('Opening time', $firstResult['label']);
        /** @var array<int, array<string, mixed>> $fields */
        $fields = $firstResult['fields'];
        $this->assertCount(2, $fields);
        $this->assertSame('day', $fields[0]['value']);
        $this->assertSame('select', $fields[0]['type']);
        $this->assertSame('opening_time', $fields[1]['value']);
    }

    #[Test]
    public function parse_sets_skips_invalid_sets(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSets');
        $method->setAccessible(true);

        $sets = [
            'text' => ['display' => 'Text Block', 'fields' => []],
            123 => 'invalid',
            'image' => 'not-an-array',
        ];

        $result = $method->invoke($service, $sets);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function parse_set_fields_parses_field_configurations(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            'text_field' => ['type' => 'text', 'display' => 'Text Field'],
            'textarea_field' => ['type' => 'textarea', 'display' => 'Textarea Field'],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('text_field', $firstResult['value']);
        $this->assertEquals('Text Field', $firstResult['label']);
        $this->assertEquals('text', $firstResult['type']);
    }

    #[Test]
    public function parse_set_fields_handles_list_array_format(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            ['handle' => 'text_field', 'field' => ['type' => 'text', 'display' => 'Text Field']],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('text_field', $firstResult['value']);
    }

    #[Test]
    public function parse_set_fields_skips_fields_without_valid_handle(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            ['field' => ['type' => 'text']],
            ['handle' => 'text_field', 'field' => ['type' => 'text', 'display' => 'Text Field']],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function parse_set_fields_filters_ineligible_field_types(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            'text_field' => ['type' => 'text', 'display' => 'Text Field'],
            'invalid_field' => ['type' => 'invalid_type', 'display' => 'Invalid Field'],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('text_field', $firstResult['value']);
    }

    #[Test]
    public function parse_set_fields_skips_fields_with_non_string_handle_from_associative_array(): void
    {
        $service = Mockery::mock(ReplicatorFieldService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('extractFieldData')
            ->with('valid_field', ['type' => 'text', 'display' => 'Valid Field'])
            ->andReturn(['valid_field', ['type' => 'text', 'display' => 'Valid Field']]);

        $service->shouldReceive('extractFieldData')
            ->with(123, ['type' => 'text', 'display' => 'Text Field'])
            ->andReturn([123, ['type' => 'text', 'display' => 'Text Field']]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            123 => ['type' => 'text', 'display' => 'Text Field'],
            'valid_field' => ['type' => 'text', 'display' => 'Valid Field'],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('valid_field', $firstResult['value']);
    }

    #[Test]
    public function extract_field_data_handles_list_array_format(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldData');
        $method->setAccessible(true);

        $setFieldData = [
            'handle' => 'text_field',
            'field' => ['type' => 'text'],
        ];

        $result = $method->invoke($service, 0, $setFieldData);

        $this->assertIsArray($result);
        $this->assertEquals('text_field', $result[0]);
        $this->assertIsArray($result[1]);
    }

    #[Test]
    public function extract_field_data_handles_associative_array_format(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldData');
        $method->setAccessible(true);

        $setFieldData = ['type' => 'text', 'display' => 'Text Field'];

        $result = $method->invoke($service, 'text_field', $setFieldData);

        $this->assertIsArray($result);
        $this->assertEquals('text_field', $result[0]);
        $this->assertIsArray($result[1]);
    }

    #[Test]
    public function extract_field_data_returns_null_for_invalid_input(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldData');
        $method->setAccessible(true);

        $result = $method->invoke($service, 123, 'not-an-array');

        $this->assertNull($result);
    }

    #[Test]
    public function parse_set_fields_skips_fields_with_non_string_handle(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseSetFields');
        $method->setAccessible(true);

        $setFields = [
            ['handle' => 123, 'field' => ['type' => 'text']],
            ['handle' => 'valid_field', 'field' => ['type' => 'text', 'display' => 'Valid Field']],
        ];

        $result = $method->invoke($service, $setFields);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        /** @var array<int, array<string, mixed>> $result */
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('valid_field', $firstResult['value']);
    }

    #[Test]
    public function is_field_type_eligible_returns_true_for_eligible_types(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isFieldTypeEligible');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'text'));
        $this->assertTrue($method->invoke($service, 'textarea'));
        $this->assertTrue($method->invoke($service, 'date'));
        $this->assertTrue($method->invoke($service, 'select'));
    }

    #[Test]
    public function is_field_type_eligible_returns_false_for_ineligible_types(): void
    {
        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isFieldTypeEligible');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, 'invalid_type'));
        $this->assertFalse($method->invoke($service, null));
    }

    #[Test]
    public function it_returns_empty_array_when_blueprints_exist_but_first_is_null(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $collectionMock = $this->mock(Collection::class, function ($mock): void {
            $blueprintsCollection = collect([null]);
            $mock->shouldReceive('entryBlueprints')->andReturn($blueprintsCollection);
            $mock->shouldReceive('toArray')->andReturn([]);
        });

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn($collectionMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_taxonomy_blueprints_exist_but_first_is_null(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([null]));

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn(null);
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_taxonomy')->andReturn($taxonomyMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_collection_entry_blueprints_returns_null(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $collectionMock = $this->mock(Collection::class);
        $collectionMock->shouldReceive('entryBlueprints')->andReturn(null);
        $collectionMock->shouldReceive('toArray')->andReturn([]);

        $template->set('use_for_collection', $collectionMock);
        $template->set('use_for_taxonomy', null);

        // Verify it's stored
        $this->assertNotNull($template->get('use_for_collection'), 'Collection should be stored');

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertEmpty($result);
    }

    #[Test]
    public function extract_fields_from_blueprints_handles_multiple_blueprints(): void
    {
        $blueprint1 = $this->mock(Blueprint::class);
        $blueprint2 = $this->mock(Blueprint::class);
        $fields1 = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->andReturn(collect([
                'field1' => $this->mockField('field1'),
            ]));
        });
        $fields2 = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->andReturn(collect([
                'field2' => $this->mockField('field2'),
            ]));
        });

        $blueprint1->shouldReceive('fields')->andReturn($fields1);
        $blueprint2->shouldReceive('fields')->andReturn($fields2);

        $service = new ReplicatorFieldService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldsFromBlueprints');
        $method->setAccessible(true);
        $result = $method->invoke($service, collect([$blueprint1, $blueprint2]));

        /** @var array<int, array<string, mixed>> $result */
        $this->assertCount(2, $result);
        $this->assertSame('field1', $result[0]['handle']);
        $this->assertSame('field2', $result[1]['handle']);
    }

    #[Test]
    public function it_returns_empty_array_when_taxonomy_term_blueprints_returns_null(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(null);

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn(null);
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_taxonomy')->andReturn($taxonomyMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_collection_entry_blueprints_returns_false(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $collectionMock = $this->mock(Collection::class, function ($mock): void {
            $mock->shouldReceive('entryBlueprints')->andReturn(false);
            $mock->shouldReceive('toArray')->andReturn([]);
        });

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn($collectionMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_collection_entry_blueprints_returns_empty_array(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $collectionMock = $this->mock(Collection::class, function ($mock): void {
            $mock->shouldReceive('entryBlueprints')->andReturn([]);
            $mock->shouldReceive('toArray')->andReturn([]);
        });

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn($collectionMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_collection_entry_blueprints_returns_empty_collection(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $emptyCollection = collect([]);

        $collectionMock = $this->mock(Collection::class);
        $collectionMock->shouldReceive('entryBlueprints')->andReturn($emptyCollection);
        $collectionMock->shouldReceive('toArray')->andReturn([]);

        $template->set('use_for_collection', $collectionMock);
        $template->set('use_for_taxonomy', null);

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_taxonomy_term_blueprints_returns_empty_array(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $taxonomyMock = Mockery::mock($taxonomy)->makePartial();
        $taxonomyMock->shouldReceive('termBlueprints')->andReturn(collect([]));

        /** @var Entry $templateMock */
        $templateMock = Mockery::mock($template)->makePartial();
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_collection')->andReturn(null);
        /** @phpstan-ignore-next-line */
        $templateMock->shouldReceive('get')->with('use_for_taxonomy')->andReturn($taxonomyMock);

        $service = new ReplicatorFieldService;
        /** @var \Statamic\Contracts\Entries\Entry $templateMock */
        $result = $service->getReplicatorFields($templateMock);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_uses_runway_blueprint_when_configured(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $blueprint = BlueprintFacade::make('runway-product')->setContents([
            'fields' => [
                [
                    'handle' => 'blocks',
                    'field' => [
                        'type' => 'replicator',
                        'display' => 'Blocks',
                        'sets' => [
                            'text' => [
                                'display' => 'Text',
                                'fields' => [
                                    [
                                        'handle' => 'content',
                                        'field' => [
                                            'type' => 'text',
                                            'display' => 'Content',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $resource = new Resource;
        $resource->resourceHandle = 'product';
        $resource->resourceBlueprint = $blueprint;
        Runway::$findResults['product'] = $resource;

        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = (new Entry)
            ->collection($collection)
            ->id('template-runway');
        $template->use_for_runway = 'product';

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertNotEmpty($result);
        $this->assertSame('blocks', $result[0]['handle']);
    }

    #[Test]
    public function get_replicator_fields_unwraps_labeled_value_runway_handle(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $blueprint = BlueprintFacade::make('runway-product-labeled')->setContents([
            'fields' => [
                [
                    'handle' => 'blocks',
                    'field' => [
                        'type' => 'replicator',
                        'display' => 'Blocks',
                        'sets' => [
                            'text' => [
                                'display' => 'Text',
                                'fields' => [
                                    [
                                        'handle' => 'content',
                                        'field' => [
                                            'type' => 'text',
                                            'display' => 'Content',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $resource = new Resource;
        $resource->resourceHandle = 'product';
        $resource->resourceBlueprint = $blueprint;
        Runway::$findResults['product'] = $resource;

        $collection = CollectionFacade::make('structured_data_templates');
        $collection->save();
        $template = new class extends Entry
        {
            public mixed $use_for_runway;
        };
        $template->collection($collection)->id('template-runway-labeled');
        $template->use_for_runway = new LabeledValue('product', 'Products');

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_replicator_fields_resolves_imported_fieldset_replicators(): void
    {
        FieldsetFacade::make('opening_times')
            ->setContents([
                'fields' => [
                    [
                        'handle' => 'opening_times',
                        'field' => [
                            'type' => 'replicator',
                            'display' => 'Opening hours',
                            'sets' => [
                                'new_opening_time' => [
                                    'display' => 'New opening time',
                                    'sets' => [
                                        'opening_time' => [
                                            'display' => 'Opening time',
                                            'fields' => [
                                                [
                                                    'handle' => 'day',
                                                    'field' => [
                                                        'type' => 'select',
                                                        'display' => 'Day',
                                                    ],
                                                ],
                                                [
                                                    'handle' => 'opening_time',
                                                    'field' => [
                                                        'type' => 'time',
                                                        'display' => 'Opens',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->save();

        $collection = CollectionFacade::make('locations');
        $collection->save();

        BlueprintFacade::make('location')
            ->setNamespace('collections.locations')
            ->setContents([
                'fields' => [
                    [
                        'handle' => 'sidebar_links',
                        'field' => [
                            'type' => 'replicator',
                            'display' => 'Sidebar links',
                            'sets' => [
                                'link' => [
                                    'display' => 'Link',
                                    'fields' => [
                                        [
                                            'handle' => 'url',
                                            'field' => [
                                                'type' => 'text',
                                                'display' => 'URL',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'import' => 'opening_times',
                    ],
                ],
            ])
            ->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-imported');

        $template->use_for_collection = $collection;

        $service = new ReplicatorFieldService;
        $result = $service->getReplicatorFields($template);

        $handles = array_column($result, 'handle');
        $this->assertContains('sidebar_links', $handles);
        $this->assertContains('opening_times', $handles);

        /** @var array<string, mixed>|null $openingTimes */
        $openingTimes = collect($result)->firstWhere('handle', 'opening_times');
        $this->assertIsArray($openingTimes);
        $this->assertSame('Opening hours', $openingTimes['display']);

        /** @var array<int, array<string, mixed>> $sets */
        $sets = $openingTimes['sets'];
        $this->assertCount(1, $sets);
        $this->assertSame('opening_time', $sets[0]['value']);

        /** @var array<int, array<string, mixed>> $fields */
        $fields = $sets[0]['fields'];
        $fieldHandles = array_column($fields, 'value');
        $this->assertContains('day', $fieldHandles);
        $this->assertContains('opening_time', $fieldHandles);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mockField(string $handle, array $config = []): Field
    {
        /** @var Field&MockInterface $field */
        $field = $this->mock(Field::class, function (MockInterface $mock) use ($handle, $config): void {
            $mock->shouldReceive('handle')->andReturn($handle);
            $mock->shouldReceive('config')->andReturn($config);
        });

        return $field;
    }
}
