<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Http\Controllers;

use Illuminate\Http\Request;
use Justbetter\StatamicStructuredData\Http\Controllers\StructuredDataController;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class StructuredDataControllerTest extends TestCase
{
    #[Test]
    public function parse_antlers_in_data_calls_parser(): void
    {
        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->with('{{ title }}', Mockery::type(Entry::class))->andReturn('Parsed Title');
        });

        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123');

        $result = $controller->parseAntlersInData('{{ title }}', $entry);

        $this->assertEquals('Parsed Title', $result);
    }

    #[Test]
    public function get_parse_context_includes_config_site_and_entry_data(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->set('title', 'Test Entry');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('toAugmentedArray')->andReturn(['title' => 'Test Entry']);

        $site = $this->mock(Site::class, function ($mock) {
            $mock->shouldReceive('toAugmentedArray')->andReturn(['handle' => 'default', 'name' => 'Default']);
        });

        \Statamic\Facades\Site::shouldReceive('current')->andReturn($site);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getParseContext');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $entryMock);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('site', $result);
    }

    #[Test]
    public function get_templates_returns_error_when_entry_not_found(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $request = Request::create('/test', 'GET', [
            'ids' => ['template-123'],
            'entry_id' => 'nonexistent',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('nonexistent')->andReturn(null);
        Term::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $response = $controller->getTemplates($request);

        $this->assertEquals(404, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Content entry not found', $data['error']);
    }

    #[Test]
    public function get_templates_returns_templates_for_entry(): void
    {
        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn(['parsed' => 'data']);
        });

        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $contentEntry = (new Entry)
            ->collection($collection)
            ->id('content-123')
            ->set('title', 'Content Entry');

        $templateCollection = CollectionFacade::make('structured_data_templates');
        $templateCollection->save();
        $templateEntry = (new Entry)
            ->collection($templateCollection)
            ->id('template-123')
            ->set('title', 'Template')
            ->set('schema_data', ['test' => 'data']);

        $request = Request::create('/test', 'GET', [
            'ids' => ['template-123'],
            'entry_id' => 'content-123',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('content-123')->andReturn($contentEntry);
        \Statamic\Facades\Entry::shouldReceive('find')->with('template-123')->andReturn($templateEntry);

        $response = $controller->getTemplates($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<int, array<string, mixed>> $data */
        $this->assertCount(1, $data);
        $this->assertEquals('template-123', $data[0]['id']);
        $this->assertEquals('Template', $data[0]['title']);
        $this->assertArrayHasKey('structuredData', $data[0]);
    }

    #[Test]
    public function get_templates_returns_templates_for_term(): void
    {
        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->andReturn(['parsed' => 'data']);
        });

        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();
        $contentTerm = $this->mock(LocalizedTerm::class, function ($mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
            $mock->shouldReceive('in')->with('nl')->andReturnSelf();
        });

        $templateCollection = CollectionFacade::make('structured_data_templates');
        $templateCollection->save();
        $templateEntry = (new Entry)
            ->collection($templateCollection)
            ->id('template-123')
            ->set('title', 'Template')
            ->set('schema_data', ['test' => 'data']);

        $request = Request::create('/test', 'GET', [
            'ids' => ['template-123'],
            'entry_id' => 'test-term',
            'site' => 'nl',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('test-term')->andReturn(null);
        Term::shouldReceive('find')->with('test-term')->andReturn($contentTerm);
        \Statamic\Facades\Entry::shouldReceive('find')->with('template-123')->andReturn($templateEntry);

        $response = $controller->getTemplates($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
    }

    #[Test]
    public function get_templates_filters_out_invalid_template_entries(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $contentEntry = (new Entry)
            ->collection($collection)
            ->id('content-123');

        $request = Request::create('/test', 'GET', [
            'ids' => ['template-123', 'template-456'],
            'entry_id' => 'content-123',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('content-123')->andReturn($contentEntry);
        \Statamic\Facades\Entry::shouldReceive('find')->with('template-123')->andReturn(null);
        \Statamic\Facades\Entry::shouldReceive('find')->with('template-456')->andReturn(null);

        $response = $controller->getTemplates($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    #[Test]
    public function get_templates_filters_out_templates_without_schema_data(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $contentEntry = (new Entry)
            ->collection($collection)
            ->id('content-123');

        $templateCollection = CollectionFacade::make('structured_data_templates');
        $templateCollection->save();
        $templateEntry = (new Entry)
            ->collection($templateCollection)
            ->id('template-123')
            ->set('title', 'Template');

        $request = Request::create('/test', 'GET', [
            'ids' => ['template-123'],
            'entry_id' => 'content-123',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('content-123')->andReturn($contentEntry);
        \Statamic\Facades\Entry::shouldReceive('find')->with('template-123')->andReturn($templateEntry);

        $response = $controller->getTemplates($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    #[Test]
    public function get_available_variables_returns_basic_variables_when_no_entry(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $request = Request::create('/test', 'GET', [
            'entry_id' => 'nonexistent',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('nonexistent')->andReturn(null);

        $response = $controller->getAvailableVariables($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('config', $data);
        $this->assertArrayHasKey('entry', $data);
        $this->assertIsArray($data['entry']);
        $this->assertEmpty($data['entry']);
    }

    #[Test]
    public function get_available_variables_returns_entry_fields_when_entry_exists(): void
    {
        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $controller = new StructuredDataController($parser);

        $collection = CollectionFacade::make('test');
        $collection->save();
        $blueprint = BlueprintFacade::make('test');
        $blueprint->setContents([
            'fields' => [
                [
                    'handle' => 'title',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Title',
                    ],
                ],
                [
                    'handle' => 'content',
                    'field' => [
                        'type' => 'textarea',
                        'display' => 'Content',
                    ],
                ],
            ],
        ]);
        $blueprint->save();
        $collection->entryBlueprints();

        $entry = (new Entry)
            ->collection($collection)
            ->id('entry-123')
            ->blueprint('test');

        $request = Request::create('/test', 'GET', [
            'entry_id' => 'entry-123',
        ]);

        \Statamic\Facades\Entry::shouldReceive('find')->with('entry-123')->andReturn($entry);

        $response = $controller->getAvailableVariables($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('entry', $data);
        $this->assertIsArray($data['entry']);
        $this->assertNotEmpty($data['entry']);
    }
}
