<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\AvailableVariables;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Collection;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Fieldset as FieldsetFacade;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldset;

class BlueprintVariableMapperTest extends TestCase
{
    #[Test]
    public function field_type_is_eligible_returns_true_for_eligible_types(): void
    {
        $mapper = new BlueprintVariableMapper;

        $this->assertTrue($mapper->fieldTypeIsEligible('text'));
        $this->assertTrue($mapper->fieldTypeIsEligible('assets'));
        $this->assertTrue($mapper->fieldTypeIsEligible('bard'));
        $this->assertTrue($mapper->fieldTypeIsEligible('toggle'));
        $this->assertTrue($mapper->fieldTypeIsEligible('integer'));
        $this->assertTrue($mapper->fieldTypeIsEligible('slug'));
        $this->assertTrue($mapper->fieldTypeIsEligible('date'));
        $this->assertTrue($mapper->fieldTypeIsEligible('entries'));
        $this->assertTrue($mapper->fieldTypeIsEligible('aardvark_seo_meta_title'));
        $this->assertTrue($mapper->fieldTypeIsEligible('aardvark_seo_meta_description'));
    }

    #[Test]
    public function field_type_is_eligible_returns_false_for_ineligible_types(): void
    {
        $mapper = new BlueprintVariableMapper;

        $this->assertFalse($mapper->fieldTypeIsEligible('unknown_type'));
        $this->assertFalse($mapper->fieldTypeIsEligible('grid'));
        $this->assertFalse($mapper->fieldTypeIsEligible('replicator'));
    }

    #[Test]
    public function set_field_data_returns_null_for_ineligible_field(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'test',
            'field' => [
                'type' => 'grid',
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_returns_null_when_handle_is_parent(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'parent',
            'field' => [
                'type' => 'text',
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_returns_field_data_for_eligible_field(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'test_field',
            'field' => [
                'type' => 'text',
                'display' => 'Test Field',
            ],
        ];

        $result = $mapper->setFieldData($field);

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
        $mapper = new BlueprintVariableMapper;

        $result = $mapper->getCollectionVariables('', []);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_handle_is_structured_data_templates(): void
    {
        $mapper = new BlueprintVariableMapper;

        $result = $mapper->getCollectionVariables('structured_data_templates', []);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_collection_not_found(): void
    {
        $mapper = new BlueprintVariableMapper;

        CollectionFacade::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $result = $mapper->getCollectionVariables('nonexistent', []);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_variables_when_collection_exists(): void
    {
        $mapper = new BlueprintVariableMapper;

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

        CollectionFacade::shouldReceive('find')->with('blog')->andReturn($collection);

        $result = $mapper->getCollectionVariables('blog', $field);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_collection_variables_returns_empty_when_no_blueprint(): void
    {
        $mapper = new BlueprintVariableMapper;

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

        CollectionFacade::shouldReceive('find')->with('blog')->andReturn($collectionMock);

        $result = $mapper->getCollectionVariables('blog', $field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function set_field_data_returns_field_with_children_for_entries_field_type(): void
    {
        $mapper = new BlueprintVariableMapper;

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

        CollectionFacade::shouldReceive('find')->with('blog')->andReturn($collection);

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
                'collections' => ['blog'],
            ],
        ];

        $result = $mapper->setFieldData($field);

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
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'related_entry',
            'field' => [
                'type' => 'entries',
                'display' => 'Related Entry',
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_returns_group_field_with_supported_children(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'media_options',
            'field' => [
                'type' => 'group',
                'display' => 'Media opties',
                'fields' => [
                    [
                        'handle' => 'object_fit',
                        'field' => [
                            'type' => 'select',
                            'display' => 'Object fit',
                        ],
                    ],
                    [
                        'handle' => 'video',
                        'field' => [
                            'type' => 'toggle',
                            'display' => 'Video',
                        ],
                    ],
                    [
                        'handle' => 'layout',
                        'field' => [
                            'type' => 'grid',
                            'display' => 'Layout',
                        ],
                    ],
                ],
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertSame('media_options', $result['name']);
        $this->assertSame('Media opties', $result['description']);
        $this->assertArrayHasKey('children', $result);
        $this->assertIsArray($result['children']);
        $this->assertCount(2, $result['children']);

        /** @var array<int, array<string, mixed>> $children */
        $children = $result['children'];
        $childNames = array_column($children, 'name');
        $childDescriptions = array_column($children, 'description');

        $this->assertContains('media_options:object_fit', $childNames);
        $this->assertContains('Media opties: Object fit', $childDescriptions);
        $this->assertContains('media_options:video', $childNames);
        $this->assertContains('Media opties: Video', $childDescriptions);
        $this->assertNotContains('media_options:layout', $childNames);
    }

    #[Test]
    public function set_field_data_returns_null_for_group_without_supported_children(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'media_options',
            'field' => [
                'type' => 'group',
                'display' => 'Media opties',
                'fields' => [
                    [
                        'handle' => 'layout',
                        'field' => [
                            'type' => 'grid',
                            'display' => 'Layout',
                        ],
                    ],
                ],
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertNull($result);
    }

    #[Test]
    public function set_field_data_group_skips_children_without_handle(): void
    {
        $mapper = new BlueprintVariableMapper;

        $field = [
            'handle' => 'media_options',
            'field' => [
                'type' => 'group',
                'display' => 'Media opties',
                'fields' => [
                    [
                        'field' => [
                            'type' => 'toggle',
                            'display' => 'Missing Handle',
                        ],
                    ],
                    [
                        'handle' => 'video',
                        'field' => [
                            'type' => 'toggle',
                            'display' => 'Video',
                        ],
                    ],
                ],
            ],
        ];

        $result = $mapper->setFieldData($field);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('children', $result);
        /** @var array<int, array<string, mixed>> $children */
        $children = $result['children'];
        $this->assertCount(1, $children);
        $this->assertSame('media_options:video', $children[0]['name'] ?? null);
    }

    #[Test]
    public function normalize_blueprint_items_converts_collection_to_array(): void
    {
        $mapper = new BlueprintVariableMapper;

        $collection = collect([
            ['handle' => 'test', 'field' => ['type' => 'text']],
        ]);

        $result = $mapper->normalizeBlueprintItems($collection);

        $this->assertCount(1, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('test', $firstResult['handle']);
    }

    #[Test]
    public function normalize_blueprint_items_returns_array_as_is(): void
    {
        $mapper = new BlueprintVariableMapper;

        $input = [
            ['handle' => 'test', 'field' => ['type' => 'text']],
        ];

        $result = $mapper->normalizeBlueprintItems($input);

        $this->assertEquals($input, $result);
    }

    #[Test]
    public function normalize_blueprint_items_returns_empty_array_for_invalid_input(): void
    {
        $mapper = new BlueprintVariableMapper;

        $result = $mapper->normalizeBlueprintItems('not-an-array');

        $this->assertEmpty($result);
    }

    #[Test]
    public function normalize_blueprint_items_includes_imported_fieldset_items(): void
    {
        $mapper = new BlueprintVariableMapper;

        $fieldsetFields = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('items')->andReturn([
                [
                    'handle' => 'imported_title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Imported Title',
                    ],
                ],
            ]);
        });

        $fieldset = $this->mock(Fieldset::class, function (MockInterface $mock) use ($fieldsetFields): void {
            $mock->shouldReceive('fields')->andReturn($fieldsetFields);
        });

        FieldsetFacade::shouldReceive('find')->with('seo_fields')->andReturn($fieldset);

        $result = $mapper->normalizeBlueprintItems([
            ['import' => 'seo_fields'],
        ]);

        $this->assertCount(1, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertSame('imported_title', $firstResult['handle']);
    }

    #[Test]
    public function normalize_blueprint_items_handles_missing_imported_fieldset(): void
    {
        $mapper = new BlueprintVariableMapper;

        FieldsetFacade::shouldReceive('find')->with('missing_fields')->andReturn(null);

        $result = $mapper->normalizeBlueprintItems([
            ['import' => 'missing_fields'],
        ]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function normalize_blueprint_items_applies_prefix_to_imported_fieldset_items(): void
    {
        $mapper = new BlueprintVariableMapper;

        $fieldsetFields = $this->mock(Fields::class, function (MockInterface $mock): void {
            $mock->shouldReceive('items')->andReturn([
                [
                    'handle' => 'image',
                    'field' => [
                        'type' => 'assets',
                        'display' => 'Image',
                    ],
                ],
            ]);
        });

        $fieldset = $this->mock(Fieldset::class, function (MockInterface $mock) use ($fieldsetFields): void {
            $mock->shouldReceive('fields')->andReturn($fieldsetFields);
        });

        FieldsetFacade::shouldReceive('find')->with('media')->andReturn($fieldset);

        $result = $mapper->normalizeBlueprintItems([
            ['import' => 'media', 'prefix' => 'featured_'],
        ]);

        $this->assertCount(1, $result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertSame('featured_image', $firstResult['handle']);
    }

    #[Test]
    public function normalize_blueprint_items_handles_nested_support_collection_and_invalid_items(): void
    {
        $mapper = new BlueprintVariableMapper;

        $nestedFields = collect([
            [
                'handle' => 'nested_title',
                'field' => [
                    'type' => 'text',
                    'display' => 'Nested Title',
                ],
            ],
        ]);

        $items = [
            collect([
                'handle' => 'wrapped_field',
                'field' => [
                    'type' => 'text',
                    'display' => 'Wrapped Field',
                ],
            ]),
            'invalid-item',
            [
                'fields' => $nestedFields,
            ],
        ];

        $result = $mapper->normalizeBlueprintItems($items);

        $fieldHandles = array_column($result, 'handle');
        $this->assertContains('wrapped_field', $fieldHandles);
        $this->assertContains('nested_title', $fieldHandles);
    }

    #[Test]
    public function normalize_blueprint_items_handles_invalid_import_handle(): void
    {
        $mapper = new BlueprintVariableMapper;

        $result = $mapper->normalizeBlueprintItems([
            ['import' => null],
        ]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_imported_fieldset_items_returns_empty_for_invalid_import_config(): void
    {
        $mapper = new BlueprintVariableMapper;

        $result = $mapper->getImportedFieldsetItems(['import' => null]);

        $this->assertEmpty($result);
    }
}
