<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerFactory;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerInterface;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class StructuredDataServiceTest extends TestCase
{
    /** @param array<int, string> $templates */
    protected function createBlogEntry(array $templates = []): Entry
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $blogEntry = (new Entry)
            ->collection($blogCollection)
            ->id('entry-123');

        if ($templates) {
            $blogEntry->set('structured_data_templates', $templates);
        }

        return $blogEntry;
    }

    /** @param array<string, mixed> $schemaData */
    protected function createTemplateEntry(array $schemaData = []): Entry
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $templateEntry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        if ($schemaData) {
            $templateEntry->set('schema_data', $schemaData);
        }

        return $templateEntry;
    }

    #[Test]
    public function it_returns_empty_array_when_no_templates(): void
    {
        $blogEntry = $this->createBlogEntry();

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($blogEntry);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_returns_empty_array_when_template_not_found(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $blogEntry = (new Entry)
            ->collection($blogCollection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn(null);

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($blogEntry);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_returns_empty_array_when_template_has_no_schema_data(): void
    {
        $blogCollection = CollectionFacade::make('blog');
        $blogCollection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $blogEntry = (new Entry)
            ->collection($blogCollection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $templateEntry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($templateEntry);

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($blogEntry);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_returns_scripts_when_template_has_schema_data(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('schema_data', [
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($template);

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn([
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($entry);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<script type="application/ld+json">', $result[0]);
    }

    #[Test]
    public function get_json_ld_scripts_handles_page_instance(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('schema_data', [
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($template);

        $page = $this->mock(Page::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('entry')->andReturn($entry);
        });

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn([
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        /** @var Page $page */
        $result = $service->getJsonLdScripts($page);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<script type="application/ld+json">', $result[0]);
    }

    #[Test]
    public function get_json_ld_scripts_handles_localized_term(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock): void {
            $mock->shouldReceive('get')->with('structured_data_templates')->andReturn([]);
        });

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        /** @var \Statamic\Taxonomies\LocalizedTerm $term */
        $result = $service->getJsonLdScripts($term);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_skips_invalid_schemas(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('schema_data', [
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($template);

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn(null);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($entry);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_handles_exceptions(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('schema_data', [
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($template);

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andThrow(new \Exception('Parse error'));
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($entry);

        $this->assertEmpty($result);
    }

    #[Test]
    public function format_json_ld_returns_script_tag_when_json_false(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'specialProps' => ['type' => 'Article'],
            'fields' => [],
        ];

        $result = $service->formatJsonLd($schema, false);

        $this->assertStringStartsWith('<script type="application/ld+json">', $result);
        $this->assertStringEndsWith('</script>', $result);
    }

    #[Test]
    public function format_json_ld_returns_json_when_json_true(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'specialProps' => ['type' => 'Article'],
            'fields' => [],
        ];

        $result = $service->formatJsonLd($schema, true);

        $this->assertStringStartsWith('{', $result);
        $this->assertStringEndsWith('}', $result);
        $this->assertStringNotContainsString('<script', $result);
    }

    #[Test]
    public function transform_schema_handles_special_props(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'specialProps' => [
                'context' => 'https://schema.org',
                'type' => 'Article',
                'id' => 'https://example.com/article',
            ],
            'fields' => [],
        ];

        $result = $service->transformSchema($schema);

        $this->assertEquals('https://schema.org', $result['@context']);
        $this->assertEquals('Article', $result['@type']);
        $this->assertEquals('https://example.com/article', $result['@id']);
    }

    #[Test]
    public function transform_schema_handles_fields(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'value' => 'Test Name'],
                ['key' => 'description', 'type' => 'textarea', 'value' => 'Test Description'],
            ],
        ];

        $result = $service->transformSchema($schema);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
    }

    #[Test]
    public function transform_schema_skips_fields_without_key(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'fields' => [
                ['type' => 'text', 'value' => 'Test Name'],
                ['key' => 'description', 'type' => 'textarea', 'value' => 'Test Description'],
            ],
        ];

        $result = $service->transformSchema($schema);

        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
    }

    #[Test]
    public function transform_field_handles_object_type(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $field = [
            'type' => 'object',
            'value' => [
                'specialProps' => ['type' => 'Person'],
                'fields' => [],
            ],
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('transformField');
        $method->setAccessible(true);
        $result = $method->invoke($service, $field);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('@type', $result);
    }

    #[Test]
    public function transform_field_handles_object_array_type(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $field = [
            'type' => 'object_array',
            'values' => [
                [
                    'specialProps' => ['type' => 'Person'],
                    'fields' => [],
                ],
                [
                    'specialProps' => ['type' => 'Organization'],
                    'fields' => [],
                ],
            ],
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('transformField');
        $method->setAccessible(true);
        $result = $method->invoke($service, $field);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function parse_and_transform_schemas_returns_empty_array_for_invalid_item(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schemas = [
            ['fields' => []],
        ];

        $result = $service->parseAndTransformSchemas($schemas, null);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_and_transform_schemas_transforms_valid_schemas(): void
    {
        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn([
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => 'Test Name'],
                    ],
                ],
            ]);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $entry = $this->createBlogEntry();
        $schemas = [['fields' => []]];

        $result = $service->parseAndTransformSchemas($schemas, $entry);

        $this->assertCount(1, $result);
        $this->assertSame('Test Name', $result[0]['name']);
    }

    #[Test]
    public function transform_schema_keeps_flat_field_key_and_uses_transformed_value(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $stubTransformer = new class implements FieldTransformerInterface
        {
            public function transform(array $field, $item = null): mixed
            {
                return ['merged_key' => 'merged_value'];
            }
        };

        $stubFactory = new class($stubTransformer) extends FieldTransformerFactory
        {
            public function __construct(private FieldTransformerInterface $transformer) {}

            public function getTransformer(?string $type): FieldTransformerInterface
            {
                return $this->transformer;
            }
        };

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('transformerFactory');
        $property->setAccessible(true);
        $property->setValue($service, $stubFactory);

        $schema = [
            'fields' => [
                [
                    'key' => 'flat_field',
                    'type' => 'replicator_object_array',
                    'config' => [
                        'flat' => true,
                    ],
                ],
            ],
        ];

        $result = $service->transformSchema($schema);

        $this->assertArrayHasKey('flat_field', $result);
        $this->assertIsArray($result['flat_field']);
        $this->assertArrayHasKey('merged_key', $result['flat_field']);
        $this->assertSame('merged_value', $result['flat_field']['merged_key']);
    }

    #[Test]
    public function transform_schema_skips_fields_when_transformer_returns_null(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $stubTransformer = new class implements FieldTransformerInterface
        {
            public function transform(array $field, $item = null): mixed
            {
                return null;
            }
        };

        $stubFactory = new class($stubTransformer) extends FieldTransformerFactory
        {
            public function __construct(private FieldTransformerInterface $transformer) {}

            public function getTransformer(?string $type): FieldTransformerInterface
            {
                return $this->transformer;
            }
        };

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('transformerFactory');
        $property->setAccessible(true);
        $property->setValue($service, $stubFactory);

        $schema = [
            'fields' => [
                [
                    'key' => 'skipped_field',
                    'type' => 'text',
                    'value' => 'Should be skipped',
                ],
            ],
        ];

        $result = $service->transformSchema($schema);
        $this->assertArrayNotHasKey('skipped_field', $result);
    }

    #[Test]
    public function is_associative_array_helper_behaves_as_expected(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isAssociativeArray');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, []));
        $this->assertFalse($method->invoke($service, [1, 2, 3]));
        $this->assertTrue($method->invoke($service, ['a' => 1, 'b' => 2]));
    }

    #[Test]
    public function get_templates_handles_entry(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123', 'template-456']);

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTemplates');
        $method->setAccessible(true);
        $result = $method->invoke($service, $entry);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('template-123', $result);
        $this->assertContains('template-456', $result);
    }

    #[Test]
    public function get_templates_handles_page(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $page = $this->mock(Page::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('entry')->andReturn($entry);
        });

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTemplates');
        $method->setAccessible(true);
        $result = $method->invoke($service, $page);

        $this->assertIsArray($result);
        $this->assertContains('template-123', $result);
    }

    #[Test]
    public function get_templates_handles_localized_term(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock): void {
            $mock->shouldReceive('get')->with('structured_data_templates')->andReturn(['template-123']);
        });

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTemplates');
        $method->setAccessible(true);
        $result = $method->invoke($service, $term);

        $this->assertIsArray($result);
        $this->assertContains('template-123', $result);
    }

    #[Test]
    public function get_templates_returns_empty_array_for_invalid_item(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTemplates');
        $method->setAccessible(true);
        $result = $method->invoke($service, 'invalid-item');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_templates_handles_null_templates(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTemplates');
        $method->setAccessible(true);
        $result = $method->invoke($service, $entry);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_json_ld_scripts_skips_non_array_parsed_schemas(): void
    {
        $collection = CollectionFacade::make('blog');
        $collection->save();
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('structured_data_templates', ['template-123']);

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123')
            ->set('schema_data', [
                [
                    'specialProps' => ['type' => 'Article'],
                    'fields' => [],
                ],
            ]);

        EntryFacade::shouldReceive('find')->with('template-123')->andReturn($template);

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn([
                'not-an-array',
                ['specialProps' => ['type' => 'Article'], 'fields' => []],
            ]);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $result = $service->getJsonLdScripts($entry);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function transform_schema_skips_fields_with_non_string_key(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $schema = [
            'fields' => [
                ['key' => 123, 'type' => 'string', 'value' => 'test'],
                ['key' => 'valid_key', 'type' => 'string', 'value' => 'valid_value'],
            ],
        ];

        $result = $service->transformSchema($schema);

        $this->assertArrayHasKey('valid_key', $result);
        $this->assertEquals('valid_value', $result['valid_key']);
        $this->assertArrayNotHasKey(123, $result);
    }
}
