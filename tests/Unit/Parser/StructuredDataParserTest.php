<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Parser;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Sites\Site;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;

class StructuredDataParserTest extends TestCase
{
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
}
