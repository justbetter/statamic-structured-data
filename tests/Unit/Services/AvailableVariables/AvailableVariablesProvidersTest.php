<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\AvailableVariables;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\AvailableVariablesService;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\BlueprintVariableMapper;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\EntryVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\TermVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\SeoProVariables;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\Stubs\Product;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\GlobalSet as GlobalSetFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fields;
use Statamic\Fields\LabeledValue;
use Statamic\Globals\GlobalCollection;
use Statamic\Taxonomies\Taxonomy;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Runway;

class AvailableVariablesProvidersTest extends TestCase
{
    #[Test]
    public function get_entry_fields_returns_empty_when_no_collection(): void
    {
        $service = new AvailableVariablesService;

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $this->assertSame([], $service->entry()->variables(null));
        $this->assertSame([], $service->entry()->variables($entry));
    }

    #[Test]
    public function get_entry_fields_handles_no_blueprints_gracefully(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->entry()->variables($entry);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_entry_fields_includes_base_fields_and_maps_collection_fields(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->entry()->variables($entry);

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
        $service = new AvailableVariablesService;

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

        $result = $service->entry()->variables($entry);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_entry_fields_appends_seo_pro_variables_when_seo_pro_is_available(): void
    {
        $seoProVariables = new class extends SeoProVariables
        {
            public function isInstalled(): bool
            {
                return true;
            }
        };

        $provider = new EntryVariableProvider(new BlueprintVariableMapper, $seoProVariables);

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

        $result = $provider->variables($entry);

        $fieldNames = array_column($result, 'name');

        $this->assertContains('seo:compiled_title', $fieldNames);

        $titleIndex = array_search('title', $fieldNames, true);
        $seoTitleIndex = array_search('seo:title', $fieldNames, true);

        $this->assertNotFalse($titleIndex);
        $this->assertNotFalse($seoTitleIndex);
        $this->assertGreaterThan($titleIndex, $seoTitleIndex);
    }

    #[Test]
    public function get_entry_fields_omits_seo_pro_variables_when_seo_is_disabled_for_collection(): void
    {
        $seoProVariables = new class extends SeoProVariables
        {
            public function isInstalled(): bool
            {
                return true;
            }
        };

        $provider = new EntryVariableProvider(new BlueprintVariableMapper, $seoProVariables);

        $collection = CollectionFacade::make('test');
        $collection->cascade(['seo' => false]);
        $collection->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_collection = $collection;

        $result = $provider->variables($entry);

        $fieldNames = array_column($result, 'name');

        $this->assertNotContains('seo:title', $fieldNames);
    }

    #[Test]
    public function get_term_fields_returns_empty_when_no_taxonomy(): void
    {
        $service = new AvailableVariablesService;

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $this->assertSame([], $service->term()->variables(null));
        $this->assertSame([], $service->term()->variables($entry));
    }

    #[Test]
    public function get_term_fields_handles_no_blueprints_gracefully(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->term()->variables($entry);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_term_fields_handles_null_first_blueprint_gracefully(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->term()->variables($entry);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_term_fields_handles_empty_fields_after_merge_gracefully(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->term()->variables($entry);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_term_fields_includes_base_fields_and_maps_taxonomy_fields(): void
    {
        $service = new AvailableVariablesService;

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

        $result = $service->term()->variables($entry);

        $this->assertNotEmpty($result);
        /** @var array<string, mixed> $firstResult */
        $firstResult = $result[0];
        $this->assertEquals('absolute_url', $firstResult['name']);
        $this->assertGreaterThanOrEqual(2, count($result));
        $fieldNames = array_column($result, 'name');
        $this->assertContains('title', $fieldNames);
    }

    #[Test]
    public function get_term_fields_appends_seo_pro_variables_when_seo_pro_is_available(): void
    {
        $seoProVariables = new class extends SeoProVariables
        {
            public function isInstalled(): bool
            {
                return true;
            }
        };

        $provider = new TermVariableProvider(new BlueprintVariableMapper, $seoProVariables);

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

        $result = $provider->variables($entry);

        $fieldNames = array_column($result, 'name');

        $this->assertContains('seo:description', $fieldNames);
    }

    #[Test]
    public function get_term_fields_omits_seo_pro_variables_when_seo_is_disabled_for_taxonomy(): void
    {
        $seoProVariables = new class extends SeoProVariables
        {
            public function isInstalled(): bool
            {
                return true;
            }
        };

        $provider = new TermVariableProvider(new BlueprintVariableMapper, $seoProVariables);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('test');
        $taxonomy->cascade(['seo' => false]);
        $taxonomy->save();

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)
            ->collection($templatesCollection)
            ->id('template-123');

        $entry->use_for_taxonomy = $taxonomy;

        $result = $provider->variables($entry);

        $fieldNames = array_column($result, 'name');

        $this->assertNotContains('seo:title', $fieldNames);
    }

    #[Test]
    public function get_global_variables_returns_variables(): void
    {
        $service = new AvailableVariablesService;

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

        $globalCollection = new GlobalCollection([$globalSetMock]);
        GlobalSet::shouldReceive('all')->andReturn($globalCollection);

        $result = $service->globals()->variables();

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function get_seo_pro_variables_returns_core_cascaded_fields(): void
    {
        $service = new AvailableVariablesService;

        $result = $service->seoPro()->all();

        $this->assertCount(7, $result);
        $names = array_column($result, 'name');
        $this->assertSame([
            'seo:title',
            'seo:compiled_title',
            'seo:description',
            'seo:canonical_url',
            'seo:og_title',
            'seo:og_type',
            'seo:image',
        ], $names);
    }

    #[Test]
    public function get_runway_fields_returns_empty_when_runway_not_installed(): void
    {
        RunwaySupport::fakeInstalled(false);

        $service = new AvailableVariablesService;

        $this->assertSame([], $service->runway()->variables(null));
    }

    #[Test]
    public function get_runway_fields_returns_empty_when_parent_is_not_entry(): void
    {
        RunwaySupport::fakeInstalled(true);

        $service = new AvailableVariablesService;

        $this->assertSame([], $service->runway()->variables(null));
    }

    #[Test]
    public function get_runway_fields_returns_empty_when_no_resource_handle(): void
    {
        RunwaySupport::fakeInstalled(true);

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)->collection($templatesCollection)->id('template-123');

        $service = new AvailableVariablesService;

        $this->assertSame([], $service->runway()->variables($entry));
    }

    #[Test]
    public function get_runway_fields_returns_empty_when_resource_not_found(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)->collection($templatesCollection)->id('template-123');
        $entry->use_for_runway = 'product';

        $service = new AvailableVariablesService;

        $this->assertSame([], $service->runway()->variables($entry));
    }

    #[Test]
    public function get_runway_fields_returns_blueprint_and_model_variables(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $blueprint = BlueprintFacade::make('runway-product')->setContents([
            'fields' => [
                [
                    'handle' => 'name',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Name',
                    ],
                ],
            ],
        ]);

        $model = new Product([
            'sku' => 'ABC',
            'name' => 'Bike',
        ]);
        $model->fillable(['sku', 'color']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';
        $resource->resourceName = 'Products';
        $resource->resourceBlueprint = $blueprint;
        $resource->resourceModel = $model;
        Runway::$findResults['product'] = $resource;

        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();
        $entry = (new Entry)->collection($templatesCollection)->id('template-123');
        $entry->use_for_runway = 'product';

        $service = new AvailableVariablesService;

        $result = $service->runway()->variables($entry);

        $names = array_column($result, 'name');
        $this->assertContains('absolute_url', $names);
        $this->assertContains('name', $names);
        $this->assertContains('sku', $names);
        $this->assertContains('color', $names);
    }

    #[Test]
    public function resolve_runway_resource_handle_unwraps_labeled_value(): void
    {
        $templatesCollection = CollectionFacade::make('structured_data_templates');
        $templatesCollection->save();

        $entry = new class extends Entry
        {
            public mixed $use_for_runway;
        };
        $entry->collection($templatesCollection)->id('template-123');
        $entry->use_for_runway = new LabeledValue('product', 'Products');

        $service = new AvailableVariablesService;

        $this->assertSame('product', $service->runway()->resolveResourceHandle($entry));
    }
}
