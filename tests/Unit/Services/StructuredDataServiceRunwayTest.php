<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;

class StructuredDataServiceRunwayTest extends TestCase
{
    #[Test]
    public function get_templates_returns_runway_templates_for_model(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('runway-template-1')
            ->set('blueprint_type', 'runway')
            ->set('use_for_runway', 'product')
            ->set('schema_data', [[
                'specialProps' => ['context' => 'https://schema.org', 'type' => 'Product'],
                'fields' => [],
            ]]);
        $template->save();

        $model = new class extends Model
        {
            protected $table = 'products';

            protected $attributes = [
                'name' => 'Test Bike',
                'sku' => 'BIKE-1',
            ];
        };

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $templates = $service->getTemplates($model, 'product');

        $this->assertContains('runway-template-1', $templates);
    }

    #[Test]
    public function get_templates_returns_empty_when_runway_handle_not_enabled(): void
    {
        Config::set('justbetter.structured-data.runway', []);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $this->assertSame([], $service->getTemplates($model, 'product'));
    }

    #[Test]
    public function get_json_ld_scripts_parses_schemas_for_runway_model(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $schema = [[
            'specialProps' => ['context' => 'https://schema.org', 'type' => 'Product'],
            'fields' => [
                ['key' => 'name', 'type' => 'string', 'value' => '{{ name }}'],
            ],
        ]];

        $template = (new Entry)
            ->collection($templatesCollection)
            ->id('runway-template-2')
            ->set('blueprint_type', 'runway')
            ->set('use_for_runway', 'product')
            ->set('schema_data', $schema);
        $template->save();

        $model = new class extends Model
        {
            protected $table = 'products';

            protected $attributes = [
                'name' => 'Test Bike',
            ];
        };

        $parser = $this->mock(StructuredDataParser::class, function (MockInterface $mock) use ($schema, $model): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($schema, $model)
                ->andReturn([[
                    'specialProps' => ['context' => 'https://schema.org', 'type' => 'Product'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'string', 'value' => 'Test Bike'],
                    ],
                ]]);
        });

        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $scripts = $service->getJsonLdScripts($model, false, 'product');

        $this->assertCount(1, $scripts);
        $this->assertStringContainsString('application/ld+json', $scripts[0]);
        $this->assertStringContainsString('Product', $scripts[0]);
    }

    #[Test]
    public function get_runway_template_ids_returns_empty_when_handle_not_enabled(): void
    {
        Config::set('justbetter.structured-data.runway', []);

        $parser = $this->mock(StructuredDataParser::class);
        /** @var StructuredDataParser $parser */
        $service = new StructuredDataService($parser);

        $this->assertSame([], $service->getRunwayTemplateIds('product'));
    }
}
