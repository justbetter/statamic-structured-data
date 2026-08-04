<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Parser;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\View\Antlers\Parser as AntlersParserContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Fieldtypes\Bard;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Sites\Site;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use Statamic\View\Antlers\AntlersString;

class StructuredDataParserTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function makeEntry(array $data = []): Entry
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        foreach ($data as $key => $value) {
            $entry->set($key, $value);
        }

        return $entry;
    }

    private function makeParser(): StructuredDataParser
    {
        return new StructuredDataParser;
    }

    private function invokeParserMethod(StructuredDataParser $parser, string $methodName, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($parser, ...$arguments);
    }

    /**
     * @param  array<string, mixed>  $augmentedSiteData
     */
    private function mockDefaultSite(array $augmentedSiteData = []): void
    {
        $site = $this->mock(Site::class, function (MockInterface $mock) use ($augmentedSiteData): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn($augmentedSiteData)->byDefault();
            $mock->shouldReceive('handle')->andReturn('default')->byDefault();
        });

        SiteFacade::shouldReceive('current')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('default')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('all')->andReturn(collect([$site]))->byDefault();
    }

    #[Test]
    public function parse_returns_string_as_is_when_no_antlers(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $parser = new StructuredDataParser;
        $result = $parser->parse('simple string', $entry);

        $this->assertEquals('simple string', $result);
    }

    #[Test]
    public function parse_parses_antlers_string(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('title', 'Test Title');

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn([])->byDefault();
            $mock->shouldReceive('handle')->andReturn('default')->byDefault();
        });
        SiteFacade::shouldReceive('current')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('default')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('all')->andReturn(collect([$site]))->byDefault();

        $parser = new StructuredDataParser;
        $result = $parser->parse('{{ title }}', $entry);

        $this->assertIsString($result);
    }

    #[Test]
    public function parse_handles_data_object_reference(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->save();
        $dataObject = $this->mock(Term::class, function (MockInterface $mock): void {
            /** @phpstan-ignore-next-line */
            $mock->object_type = 'Person';
            /** @phpstan-ignore-next-line */
            $mock->object_data = [
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'value' => 'John Doe'],
                ],
            ];
        });

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
            $mock->shouldReceive('toAugmentedArray')->andReturn([]);
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($dataObject): void {
            $mock->shouldReceive('where')->with('taxonomy', 'structured_data_objects')->andReturnSelf();
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('where')->with('slug', 'test-object')->andReturnSelf();
            $mock->shouldReceive('first')->andReturn($dataObject);
        });

        TermFacade::shouldReceive('query')->andReturn($query);

        $parser = new StructuredDataParser;
        $result = $parser->parse('@dataObject::test-object', $entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('@type', $result);
        $this->assertEquals('Person', $result['@type']);
    }

    #[Test]
    public function parse_handles_array_recursively(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn([]);
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);

        $parser = new StructuredDataParser;
        $result = $parser->parse([
            'name' => 'Test',
            'nested' => [
                'value' => 'Nested Test',
            ],
        ], $entry);

        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['name']);
        $this->assertIsArray($result['nested']);
        $this->assertEquals('Nested Test', $result['nested']['value']);
    }

    #[Test]
    public function get_object_data_returns_empty_array_when_object_not_found(): void
    {
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock): void {
            $mock->shouldReceive('where')->with('taxonomy', 'structured_data_objects')->andReturnSelf();
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('where')->with('slug', 'nonexistent')->andReturnSelf();
            $mock->shouldReceive('first')->andReturn(null);
        });

        TermFacade::shouldReceive('query')->andReturn($query);

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getObjectData');
        $method->setAccessible(true);
        $result = $method->invoke($parser, 'nonexistent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_object_data_returns_empty_array_when_object_data_not_array(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->save();
        $dataObject = (new Term)
            ->taxonomy($taxonomy)
            ->slug('test-object')
            ->set('object_type', 'Person');

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($dataObject): void {
            $mock->shouldReceive('where')->with('taxonomy', 'structured_data_objects')->andReturnSelf();
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('where')->with('slug', 'test-object')->andReturnSelf();
            $mock->shouldReceive('first')->andReturn($dataObject);
        });

        TermFacade::shouldReceive('query')->andReturn($query);

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getObjectData');
        $method->setAccessible(true);
        $result = $method->invoke($parser, 'test-object');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function get_object_data_adds_object_type_to_fields(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->save();
        $dataObject = $this->mock(Term::class, function (MockInterface $mock): void {
            /** @phpstan-ignore-next-line */
            $mock->object_type = 'Person';
            /** @phpstan-ignore-next-line */
            $mock->object_data = [
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'value' => 'John Doe'],
                ],
            ];
        });

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false);
        SiteFacade::shouldReceive('default')->andReturn($site);

        $query = $this->mock(EloquentQueryBuilder::class, function ($mock) use ($dataObject): void {
            $mock->shouldReceive('where')->with('taxonomy', 'structured_data_objects')->andReturnSelf();
            $mock->shouldReceive('where')->with('site', 'default')->andReturnSelf();
            $mock->shouldReceive('where')->with('slug', 'test-object')->andReturnSelf();
            $mock->shouldReceive('first')->andReturn($dataObject);
        });

        TermFacade::shouldReceive('query')->andReturn($query);

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getObjectData');
        $method->setAccessible(true);
        $result = $method->invoke($parser, 'test-object');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertIsArray($result['fields']);
        $this->assertCount(2, $result['fields']);
        /** @var array<int, array<string, mixed>> $fields */
        $fields = $result['fields'];
        /** @var array<string, mixed> $firstField */
        $firstField = $fields[0];
        $this->assertEquals('@type', $firstField['key']);
        $this->assertEquals('Person', $firstField['value']);
    }

    #[Test]
    public function get_parse_context_includes_site_and_item_data(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn(['handle' => 'default'])->byDefault();
            $mock->shouldReceive('handle')->andReturn('default')->byDefault();
        });
        SiteFacade::shouldReceive('current')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('default')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('all')->andReturn(collect([$site]))->byDefault();

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getParseContext');
        $method->setAccessible(true);
        $result = $method->invoke($parser, $entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('site', $result);
        $this->assertArrayHasKey('config', $result);
    }

    #[Test]
    public function get_parse_context_includes_absolute_url_when_available(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = $this->mock(Entry::class, function (MockInterface $mock): void {
            $mock->shouldReceive('absoluteUrl')->andReturn('https://example.com/test');
            $mock->shouldReceive('toAugmentedArray')->andReturn([]);
        });

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn(['handle' => 'default']);
        });
        SiteFacade::shouldReceive('current')->andReturn($site);

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getParseContext');
        $method->setAccessible(true);
        $result = $method->invoke($parser, $entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('absolute_url', $result);
        $this->assertEquals('https://example.com/test', $result['absolute_url']);
    }

    #[Test]
    public function get_parse_context_handles_non_augmentable_entry(): void
    {
        $collection = CollectionFacade::make('blog')->save();
        $entry = $this->mock(Entry::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn([]);
            $mock->shouldReceive('absoluteUrl')->andReturn('https://example.com/test');
        });

        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn(['handle' => 'default'])->byDefault();
        });
        SiteFacade::shouldReceive('current')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('default')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('all')->andReturn(collect([$site]))->byDefault();

        $parser = new StructuredDataParser;
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('getParseContext');
        $method->setAccessible(true);
        $result = $method->invoke($parser, $entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('site', $result);
        $this->assertArrayHasKey('absolute_url', $result);
    }

    #[Test]
    public function normalize_parsed_data_unwraps_value_instances_for_bard_like_data(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $value = $this->mock(Value::class, function (MockInterface $mock): void {
            $mock->shouldReceive('value')->andReturn([
                ['type' => 'nieuwe_set', 'values' => ['text' => 'Set content']],
            ]);
        });

        $result = $this->invokeParserMethod($parser, 'normalizeParsedData', $value, $entry);

        $this->assertIsArray($result);
        $firstResult = $result[0] ?? null;
        $this->assertIsArray($firstResult);
        $values = $firstResult['values'] ?? null;
        $this->assertIsArray($values);
        $this->assertSame('Set content', $values['text'] ?? null);
    }

    #[Test]
    public function normalize_parsed_data_unwraps_collection_instances(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $parsedCollection = new SupportCollection([
            ['type' => 'nieuwe_set', 'values' => ['text' => 'Collection content']],
        ]);

        $result = $this->invokeParserMethod($parser, 'normalizeParsedData', $parsedCollection, $entry);

        $this->assertIsArray($result);
        $firstResult = $result[0] ?? null;
        $this->assertIsArray($firstResult);
        $values = $firstResult['values'] ?? null;
        $this->assertIsArray($values);
        $this->assertSame('Collection content', $values['text'] ?? null);
    }

    #[Test]
    public function normalize_parsed_data_renders_html_from_bard_segments_with_sets(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $parsed = [
            ['type' => 'text', 'text' => '<p>Before set</p>'],
            ['type' => 'nieuwe_set', 'text' => ''],
            ['type' => 'text', 'text' => '<p>After set</p>'],
        ];

        $result = $this->invokeParserMethod($parser, 'normalizeParsedData', $parsed, $entry);

        $this->assertIsString($result);
        $this->assertSame('<p>Before set</p><p>After set</p>', $result);
    }

    #[Test]
    public function normalize_parsed_data_returns_non_empty_antlers_string(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        /** @var AntlersParserContract&MockInterface $antlersParser */
        $antlersParser = $this->mock(AntlersParserContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('injectNoparse')->andReturnUsing(static fn (string $value): string => $value);
        });
        $antlersString = new AntlersString('rendered html', $antlersParser);

        $result = $this->invokeParserMethod($parser, 'normalizeParsedData', $antlersString, $entry, '{{ title }}');

        $this->assertSame('rendered html', $result);
    }

    #[Test]
    public function normalize_parsed_data_returns_empty_string_when_antlers_string_and_template_cannot_resolve(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        /** @var AntlersParserContract&MockInterface $antlersParser */
        $antlersParser = $this->mock(AntlersParserContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('injectNoparse')->andReturn('');
        });
        $antlersString = new AntlersString('', $antlersParser);

        $result = $this->invokeParserMethod($parser, 'normalizeParsedData', $antlersString, $entry, '{{ invalid template }}');

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolve_source_template_value_returns_null_for_non_string_or_invalid_template(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $this->assertNull($this->invokeParserMethod($parser, 'resolveSourceTemplateValue', null, $entry));
        $this->assertNull($this->invokeParserMethod($parser, 'resolveSourceTemplateValue', '{{ title | upper }}', $entry));
    }

    #[Test]
    public function normalize_resolved_value_handles_values_object_and_stringable_object(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $values = new Values(collect([
            ['type' => 'text', 'text' => '<p>From values</p>'],
        ]));
        $valuesResult = $this->invokeParserMethod($parser, 'normalizeResolvedValue', $values, $entry);

        $this->assertSame('<p>From values</p>', $valuesResult);

        $stringable = new class
        {
            public function __toString(): string
            {
                return 'stringable';
            }
        };
        $stringableResult = $this->invokeParserMethod($parser, 'normalizeResolvedValue', $stringable, $entry);

        $this->assertSame('stringable', $stringableResult);
        $this->assertSame(123, $this->invokeParserMethod($parser, 'normalizeResolvedValue', 123, $entry));
    }

    #[Test]
    public function normalize_resolved_value_renders_bard_value_and_handles_non_array_raw(): void
    {
        $entry = $this->makeEntry();
        $parser = $this->makeParser();

        $bardFieldtype = $this->mock(Bard::class);
        $bardValue = $this->mock(Value::class, function (MockInterface $mock) use ($bardFieldtype): void {
            $mock->shouldReceive('fieldtype')->andReturn($bardFieldtype);
            $mock->shouldReceive('raw')->andReturn('<p>Raw bard</p>');
        });

        $this->assertSame('<p>Raw bard</p>', $this->invokeParserMethod($parser, 'normalizeResolvedValue', $bardValue, $entry));
        $this->assertSame('<p>Raw bard</p>', $this->invokeParserMethod($parser, 'renderBardValueToHtml', $bardValue));

        $nonArrayBardValue = $this->mock(Value::class, function (MockInterface $mock) use ($bardFieldtype): void {
            $mock->shouldReceive('fieldtype')->andReturn($bardFieldtype);
            $mock->shouldReceive('raw')->andReturn(123);
        });

        $this->assertSame('', $this->invokeParserMethod($parser, 'renderBardValueToHtml', $nonArrayBardValue));
    }

    #[Test]
    public function render_html_from_bard_segments_covers_invalid_segment_shapes(): void
    {
        $parser = $this->makeParser();

        $this->assertNull($this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', ['assoc' => ['type' => 'text', 'text' => 'x']]));
        $this->assertNull($this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', ['invalid-segment']));
        $this->assertNull($this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', [['text' => 'missing type']]));
        $this->assertNull($this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', [['type' => 'text', 'text' => new \stdClass]]));
    }

    #[Test]
    public function render_html_from_bard_segments_handles_values_value_and_collection_segments(): void
    {
        $parser = $this->makeParser();

        $valueSegment = $this->mock(Value::class, function (MockInterface $mock): void {
            $mock->shouldReceive('value')->andReturn(['type' => 'text', 'text' => '<p>From value</p>']);
        });

        $valuesSegment = new Values(collect(['type' => 'text', 'text' => '<p>From values</p>']));
        $collectionSegment = collect(['type' => 'text', 'text' => collect(['<p>A</p>', '<p>B</p>'])]);

        $result = $this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', [$valuesSegment, $valueSegment, $collectionSegment]);

        $this->assertSame('<p>From values</p><p>From value</p><p>A</p><p>B</p>', $result);
    }

    #[Test]
    public function resolve_source_template_value_resolves_valid_variable_path(): void
    {
        $entry = $this->makeEntry(['title' => 'Resolved title']);
        $parser = $this->makeParser();
        $this->mockDefaultSite();

        $result = $this->invokeParserMethod($parser, 'resolveSourceTemplateValue', '{{ title }}', $entry);

        $this->assertSame('Resolved title', $result);
        $this->assertSame('plain-string', $this->invokeParserMethod($parser, 'normalizeResolvedValue', 'plain-string', $entry));
    }

    #[Test]
    public function render_bard_value_to_html_strips_set_placeholders(): void
    {
        $parser = $this->makeParser();

        $bardFieldtype = new Bard;
        $bardValue = $this->mock(Value::class, function (MockInterface $mock) use ($bardFieldtype): void {
            $mock->shouldReceive('fieldtype')->andReturn($bardFieldtype);
            $mock->shouldReceive('raw')->andReturn([
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before']]],
                ['type' => 'set', 'index' => 'index-0', 'attrs' => ['values' => ['type' => 'cta']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'After']]],
            ]);
        });

        $result = $this->invokeParserMethod($parser, 'renderBardValueToHtml', $bardValue);

        $this->assertIsString($result);
        $this->assertStringContainsString('Before', $result);
        $this->assertStringContainsString('After', $result);
        $this->assertStringNotContainsString('<set>', $result);
    }

    #[Test]
    public function render_html_from_bard_segments_handles_text_value_instance(): void
    {
        $parser = $this->makeParser();

        $textValue = $this->mock(Value::class, function (MockInterface $mock): void {
            $mock->shouldReceive('value')->andReturn('<p>Value text</p>');
        });

        $result = $this->invokeParserMethod($parser, 'renderHtmlFromBardSegments', [
            ['type' => 'text', 'text' => $textValue],
        ]);

        $this->assertSame('<p>Value text</p>', $result);
    }

    #[Test]
    public function resolve_absolute_url_uses_model_url_attribute(): void
    {
        $model = new class extends Model
        {
            protected $table = 'products';

            protected $attributes = [
                'url' => '/tenways-cgo800s',
            ];
        };

        $parser = new StructuredDataParser;
        $method = (new \ReflectionClass($parser))->getMethod('resolveAbsoluteUrl');
        $method->setAccessible(true);

        $result = $method->invoke($parser, $model);

        $this->assertIsString($result);
        $this->assertStringContainsString('/tenways-cgo800s', $result);
    }

    #[Test]
    public function resolve_absolute_url_keeps_absolute_model_urls(): void
    {
        $model = new class extends Model
        {
            protected $table = 'products';

            protected $attributes = [
                'url' => 'https://example.com/bike',
            ];
        };

        $parser = new StructuredDataParser;
        $method = (new \ReflectionClass($parser))->getMethod('resolveAbsoluteUrl');
        $method->setAccessible(true);

        $this->assertSame('https://example.com/bike', $method->invoke($parser, $model));
    }

    #[Test]
    public function resolve_absolute_url_returns_empty_string_for_model_without_url(): void
    {
        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $parser = new StructuredDataParser;
        $method = (new \ReflectionClass($parser))->getMethod('resolveAbsoluteUrl');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($parser, $model));
    }

    #[Test]
    public function parse_context_merges_model_attributes(): void
    {
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('toAugmentedArray')->andReturn(['handle' => 'default'])->byDefault();
            $mock->shouldReceive('handle')->andReturn('default')->byDefault();
        });
        SiteFacade::shouldReceive('current')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('hasMultiple')->andReturn(false)->byDefault();
        SiteFacade::shouldReceive('default')->andReturn($site)->byDefault();
        SiteFacade::shouldReceive('all')->andReturn(collect([$site]))->byDefault();

        $model = new class extends Model
        {
            protected $table = 'products';

            protected $attributes = [
                'name' => 'Test Bike',
                'sku' => 'BIKE-1',
            ];
        };

        $parser = new StructuredDataParser;
        $method = (new \ReflectionClass($parser))->getMethod('getParseContext');
        $method->setAccessible(true);
        $result = $method->invoke($parser, $model);

        $this->assertIsArray($result);
        $this->assertSame('Test Bike', $result['name']);
        $this->assertSame('BIKE-1', $result['sku']);
    }
}
