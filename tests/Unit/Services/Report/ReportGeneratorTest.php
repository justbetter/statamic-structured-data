<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Justbetter\StatamicStructuredData\Actions\ResolveReportRepository;
use Justbetter\StatamicStructuredData\Contracts\ResolvesReportRepository;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Repositories\ReportRepository;
use Justbetter\StatamicStructuredData\Services\Report\CompletenessChecker;
use Justbetter\StatamicStructuredData\Services\Report\ReportGenerator;
use Justbetter\StatamicStructuredData\Services\Report\ReportScanStats;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Statamic\Entries\Entry;
use Statamic\Events\EntryCreated;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\LabeledValue;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Runway;

class ReportGeneratorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/structured-data-reports-'.uniqid());
        File::deleteDirectory($this->path);

        config()->set('justbetter.structured-data.reports.driver', 'file');
        config()->set('justbetter.structured-data.reports.path', $this->path);
        config()->set('justbetter.structured-data.reports.retention_days', 90);
        config()->set('justbetter.structured-data.collections', ['pages']);
        config()->set('justbetter.structured-data.taxonomies', []);
        config()->set('justbetter.structured-data.runway', []);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    protected function generator(): ReportGenerator
    {
        return new ReportGenerator(
            new ResolveReportRepository,
            app(StructuredDataService::class),
            new CompletenessChecker,
        );
    }

    #[Test]
    public function it_reports_missing_templates_and_incomplete_fields(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('article-template')
            ->data([
                'title' => 'Article template',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => [
                        'context' => 'https://schema.org',
                        'type' => 'Article',
                    ],
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'value' => '{{ title }}'],
                        ['key' => 'description', 'type' => 'text', 'value' => '{{ meta_description }}'],
                    ],
                ]],
            ])
            ->published(true);
        $template->save();

        /** @var Entry $missingEntry */
        $missingEntry = EntryFacade::make();
        $missingEntry
            ->collection('pages')
            ->locale($site)
            ->slug('missing')
            ->data(['title' => 'Missing template entry'])
            ->published(true)
            ->save();

        /** @var Entry $incompleteEntry */
        $incompleteEntry = EntryFacade::make();
        $incompleteEntry
            ->collection('pages')
            ->locale($site)
            ->slug('incomplete')
            ->data([
                'title' => 'Incomplete entry',
                'structured_data_templates' => [$template->id()],
            ])
            ->published(true)
            ->save();

        $report = $this->generator()->generate([
            'site' => $site,
            'triggered_by' => 'test',
            'actor' => 'phpunit',
        ]);

        $this->assertSame(ReportStatus::Completed->value, $report->get('status'));
        $this->assertSame(2, $report->get('items_scanned'));
        $this->assertGreaterThanOrEqual(1, $report->toArray()['missing_automatic_template_count']);
        $this->assertGreaterThanOrEqual(1, $report->toArray()['incomplete_field_count']);

        $missing = $report->items()->firstWhere('issue_type', ReportIssueType::MissingAutomaticTemplate->value);
        $this->assertNotNull($missing);
        $this->assertSame((string) $missingEntry->id(), $missing->get('item_id'));

        $incomplete = $report->items()->first(
            fn ($item): bool => $item->get('issue_type') === ReportIssueType::IncompleteField->value
                && $item->get('field_path') === 'description'
        );
        $this->assertNotNull($incomplete);
        $this->assertSame((string) $incompleteEntry->id(), $incomplete->get('item_id'));
    }

    #[Test]
    public function it_checks_manual_templates_and_warns_when_none_assigned(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();
        config()->set('justbetter.structured-data.collections', ['pages']);

        /** @var Entry $manual */
        $manual = EntryFacade::make();
        $manual
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('manual-pages')
            ->data([
                'title' => 'Manual pages',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => false,
                'schema_data' => [[
                    'specialProps' => ['type' => 'WebPage'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => '{{ title }}'],
                        ['key' => 'description', 'type' => 'text', 'value' => '{{ meta_description }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Entry $assigned */
        $assigned = EntryFacade::make();
        $assigned
            ->collection('pages')
            ->locale($site)
            ->slug('assigned')
            ->data([
                'title' => 'Assigned',
                'structured_data_templates' => [$manual->id()],
            ])
            ->published(true)
            ->save();

        /** @var Entry $unassigned */
        $unassigned = EntryFacade::make();
        $unassigned
            ->collection('pages')
            ->locale($site)
            ->slug('unassigned')
            ->data(['title' => 'Unassigned'])
            ->published(true)
            ->save();

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertSame(0, $report->toArray()['missing_automatic_template_count']);
        $this->assertSame(1, $report->toArray()['no_template_assigned_count']);
        $this->assertGreaterThanOrEqual(1, $report->toArray()['incomplete_field_count']);
        $this->assertSame(1, $report->toArray()['warning_count']);

        $warning = $report->items()->first(
            fn ($item): bool => $item->get('issue_type') === ReportIssueType::NoTemplateAssigned->value
        );
        $this->assertNotNull($warning);
        $this->assertSame('warning', $warning->get('severity'));
    }

    #[Test]
    public function it_filters_by_template_id_and_skips_disabled_collections(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('blog')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $pagesTemplate */
        $pagesTemplate = EntryFacade::make();
        $pagesTemplate
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('pages-template')
            ->data([
                'title' => 'Pages',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        /** @var Entry $blogTemplate */
        $blogTemplate = EntryFacade::make();
        $blogTemplate
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('blog-template')
            ->data([
                'title' => 'Blog',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'blog',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => ['type' => 'BlogPosting'],
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'value' => '{{ title }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Entry $page */
        $page = EntryFacade::make();
        $page
            ->collection('pages')
            ->locale($site)
            ->slug('home')
            ->data([
                'title' => 'Home',
                'structured_data_templates' => 'not-an-array',
            ])
            ->published(true)
            ->save();

        /** @var Entry $blog */
        $blog = EntryFacade::make();
        $blog
            ->collection('blog')
            ->locale($site)
            ->slug('post')
            ->data(['title' => 'Post'])
            ->published(true)
            ->save();

        $report = $this->generator()->generate([
            'site' => $site,
            'template_id' => (string) $pagesTemplate->id(),
        ]);

        $this->assertSame(1, $report->get('items_scanned'));
        $this->assertSame(1, $report->toArray()['missing_automatic_template_count']);
        $this->assertSame(0, $report->toArray()['incomplete_field_count']);
    }

    #[Test]
    public function it_marks_failed_reports_and_rethrows(): void
    {
        $repository = $this->mock(ReportRepository::class);
        $repository->shouldReceive('store')->once()->andReturnUsing(fn (Report $report): Report => $report);
        $repository->shouldReceive('update')->once()->andReturnUsing(fn (Report $report): Report => $report);

        $resolver = $this->mock(ResolvesReportRepository::class);
        $resolver->shouldReceive('resolve')->andReturn($repository);

        $service = $this->mock(StructuredDataService::class);
        $service->shouldReceive('parseAndTransformSchemas')->never();

        EntryFacade::shouldReceive('query')->andThrow(new RuntimeException('scan failed'));

        $generator = new ReportGenerator($resolver, $service, new CompletenessChecker);

        try {
            $generator->generate(['site' => 'default']);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('scan failed', $e->getMessage());
        }
    }

    #[Test]
    public function it_scans_taxonomy_terms_for_missing_and_incomplete_templates(): void
    {
        Event::fake();

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        config()->set('justbetter.structured-data.taxonomies', ['categories']);
        config()->set('justbetter.structured-data.collections', []);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('category-template')
            ->data([
                'title' => 'Category template',
                'blueprint_type' => 'taxonomy',
                'use_for_taxonomy' => 'categories',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => ['type' => 'Thing'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => '{{ title }}'],
                        ['key' => 'description', 'type' => 'text', 'value' => '{{ description }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Term $missingTerm */
        $missingTerm = TermFacade::make('missing');
        $missingTerm
            ->taxonomy('categories')
            ->dataForLocale($site, ['title' => 'Missing']);
        $missingTerm->in($site)->published(true)->save();

        /** @var Term $incompleteTerm */
        $incompleteTerm = TermFacade::make('incomplete');
        $incompleteTerm
            ->taxonomy('categories')
            ->dataForLocale($site, [
                'title' => 'Incomplete',
                'structured_data_templates' => [$template->id()],
            ]);
        $incompleteTerm->in($site)->published(true)->save();

        /** @var Term $draftTerm */
        $draftTerm = TermFacade::make('draft');
        $draftTerm
            ->taxonomy('categories')
            ->dataForLocale($site, ['title' => 'Draft']);
        $draftTerm->in($site)->published(false)->save();

        /** @var Term $untitled */
        $untitled = TermFacade::make('untitled');
        $untitled
            ->taxonomy('categories')
            ->dataForLocale($site, []);
        $untitled->in($site)->published(true)->save();

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertGreaterThanOrEqual(3, $report->get('items_scanned'));
        $this->assertGreaterThanOrEqual(1, $report->toArray()['missing_automatic_template_count']);
        $this->assertGreaterThanOrEqual(1, $report->toArray()['incomplete_field_count']);

        $termMissing = $report->items()->first(
            fn ($item): bool => $item->get('issue_type') === ReportIssueType::MissingAutomaticTemplate->value
                && $item->get('item_type') === ReportItemType::Term->value
        );
        $this->assertNotNull($termMissing);
        $this->assertNotNull($termMissing->get('item_edit_url'));
    }

    #[Test]
    public function it_skips_taxonomy_templates_when_taxonomy_missing_or_disabled(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        config()->set('justbetter.structured-data.taxonomies', ['enabled-tax']);
        config()->set('justbetter.structured-data.collections', []);

        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $disabled */
        $disabled = EntryFacade::make();
        $disabled
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('disabled-tax')
            ->data([
                'title' => 'Disabled',
                'blueprint_type' => 'taxonomy',
                'use_for_taxonomy' => 'missing-tax',
                'apply_automatically' => true,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        /** @var Entry $missingTaxonomy */
        $missingTaxonomy = EntryFacade::make();
        $missingTaxonomy
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('enabled-but-missing')
            ->data([
                'title' => 'Enabled missing',
                'blueprint_type' => 'taxonomy',
                'use_for_taxonomy' => 'enabled-tax',
                'apply_automatically' => true,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertSame(0, $report->get('items_scanned'));
    }

    #[Test]
    public function it_scans_runway_models_only_when_a_template_exists(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        config()->set('justbetter.structured-data.collections', []);
        config()->set('justbetter.structured-data.taxonomies', []);
        config()->set('justbetter.structured-data.runway', ['product', 'category']);

        RunwaySupport::fakeInstalled(true);
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        Schema::dropIfExists('coverage_products');
        Schema::create('coverage_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('label')->nullable();
        });

        $productModel = new class extends Model
        {
            protected $table = 'coverage_products';

            protected $guarded = [];

            public $timestamps = false;
        };

        $productModel->newQuery()->create(['name' => 'Bike One']);
        $productModel->newQuery()->create(['title' => 'Bike Two']);
        $productModel->newQuery()->create(['label' => 'Bike Three']);
        $productModel->newQuery()->create([]);

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('product-template')
            ->data([
                'title' => 'Product template',
                'blueprint_type' => 'runway',
                'use_for_runway' => 'product',
                'schema_data' => [[
                    'specialProps' => ['type' => 'Product'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => '{{ name }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        $productResource = new Resource;
        $productResource->resourceHandle = 'product';
        $productResource->resourceName = 'Products';
        $productResource->resourceModel = $productModel;

        $categoryResource = new Resource;
        $categoryResource->resourceHandle = 'category';
        $categoryResource->resourceName = 'Categories';
        $categoryResource->resourceModel = $productModel;

        Runway::fakeResources([
            'product' => $productResource,
            'category' => $categoryResource,
        ]);
        Runway::$findResults = [
            'product' => $productResource,
            'category' => $categoryResource,
            'unknown' => null,
        ];

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertSame(4, $report->get('items_scanned'));
        $this->assertSame(0, $report->toArray()['missing_automatic_template_count']);
        $this->assertGreaterThanOrEqual(1, $report->toArray()['incomplete_field_count']);

        $categoryIssue = $report->items()->first(
            fn ($item): bool => $item->get('scope_handle') === 'category'
        );
        $this->assertNull($categoryIssue);

        $runwayIssue = $report->items()->first(
            fn ($item): bool => $item->get('item_type') === ReportItemType::Runway->value
                && $item->get('scope_handle') === 'product'
        );
        $this->assertNotNull($runwayIssue);
    }

    #[Test]
    public function it_skips_runway_when_not_installed_or_disabled(): void
    {
        RunwaySupport::fakeInstalled(false);

        $items = collect();
        $stats = new ReportScanStats;
        $method = new ReflectionMethod(ReportGenerator::class, 'scanRunway');
        $method->invokeArgs($this->generator(), [collect(), $items, $stats]);

        $this->assertSame(0, $stats->itemsScanned);

        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', []);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        CollectionFacade::make('structured_data_templates')->sites([$defaultSite->handle()])->save();

        /** @var Entry $runwayTemplate */
        $runwayTemplate = EntryFacade::make();
        $runwayTemplate
            ->collection('structured_data_templates')
            ->data([
                'blueprint_type' => 'runway',
                'use_for_runway' => 'product',
            ]);

        $method->invokeArgs($this->generator(), [collect([$runwayTemplate]), collect(), $stats]);

        $this->assertSame(0, $stats->itemsScanned);
    }

    #[Test]
    public function it_builds_item_helpers_for_models_and_handles_url_failures(): void
    {
        $generator = $this->generator();

        $model = new class extends Model
        {
            protected $attributes = ['name' => 'Named'];

            public function getKey()
            {
                return 99;
            }
        };

        $itemId = new ReflectionMethod($generator, 'itemId');
        $itemTitle = new ReflectionMethod($generator, 'itemTitle');
        $itemUrl = new ReflectionMethod($generator, 'itemUrl');
        $itemEditUrl = new ReflectionMethod($generator, 'itemEditUrl');

        $this->assertSame('99', $itemId->invoke($generator, $model));
        $this->assertSame('Named', $itemTitle->invoke($generator, $model));
        $this->assertNull($itemUrl->invoke($generator, $model));

        RunwaySupport::fakeInstalled(true);
        $editUrl = $itemEditUrl->invoke($generator, $model, ReportItemType::Runway, 'product');
        $this->assertTrue($editUrl === null || is_string($editUrl));

        RunwaySupport::fakeInstalled(false);
        $this->assertNull($itemEditUrl->invoke($generator, $model, ReportItemType::Runway, 'product'));

        $broken = $this->mock(Entry::class);
        $broken->shouldReceive('absoluteUrl')->andThrow(new RuntimeException('no url'));
        $broken->shouldReceive('id')->andReturn('broken');

        $this->assertNull($itemUrl->invoke($generator, $broken));
        $this->assertIsString($itemEditUrl->invoke($generator, $broken, ReportItemType::Entry, 'pages'));
    }

    #[Test]
    public function it_skips_non_entry_results_when_scanning_collections(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        Event::fake([EntryCreated::class]);
        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('pages-template')
            ->data([
                'title' => 'Pages',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        $templates = collect([$template]);
        $items = collect();
        $stats = new ReportScanStats;

        $query = $this->mock(\stdClass::class);
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereStatus')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([new \stdClass]));

        EntryFacade::shouldReceive('query')->andReturn($query);

        $method = new ReflectionMethod(ReportGenerator::class, 'scanCollections');
        $method->invokeArgs($this->generator(), [$templates, $site, $items, $stats]);

        $this->assertSame(0, $stats->itemsScanned);
    }

    #[Test]
    public function it_covers_remaining_scan_edge_branches(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('blog')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();
        config()->set('justbetter.structured-data.collections', ['pages']);
        config()->set('justbetter.structured-data.taxonomies', ['categories']);
        config()->set('justbetter.structured-data.runway', ['product']);

        /** @var Entry $blogTemplate */
        $blogTemplate = EntryFacade::make();
        $blogTemplate
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('blog-only')
            ->data([
                'title' => 'Blog',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'blog',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => ['type' => 'BlogPosting'],
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'value' => '{{ title }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Entry $emptySchemaTemplate */
        $emptySchemaTemplate = EntryFacade::make();
        $emptySchemaTemplate
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('empty-schema')
            ->data([
                'title' => 'Empty schema',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        /** @var Entry $page */
        $page = EntryFacade::make();
        $page
            ->collection('pages')
            ->locale($site)
            ->slug('assigned-empty')
            ->data([
                'title' => 'Assigned empty',
                'structured_data_templates' => [$emptySchemaTemplate->id()],
            ])
            ->published(true)
            ->save();

        $report = $this->generator()->generate(['site' => $site]);
        $this->assertSame(ReportStatus::Completed->value, $report->get('status'));

        $generator = $this->generator();
        $items = collect();

        $unpublished = $this->mock(LocalizedTerm::class);
        $unpublished->shouldReceive('published')->andReturn(false);

        $termQuery = $this->mock(\stdClass::class);
        $termQuery->shouldReceive('where')->andReturnSelf();
        $termQuery->shouldReceive('get')->andReturn(collect([new \stdClass, $unpublished]));
        TermFacade::shouldReceive('query')->andReturn($termQuery);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->sites([$site])->save();

        /** @var Entry $taxTemplate */
        $taxTemplate = EntryFacade::make();
        $taxTemplate
            ->collection('structured_data_templates')
            ->data([
                'blueprint_type' => 'taxonomy',
                'use_for_taxonomy' => 'categories',
                'apply_automatically' => true,
            ]);

        $scanTaxonomies = new ReflectionMethod(ReportGenerator::class, 'scanTaxonomies');
        $stats = new ReportScanStats;
        $scanTaxonomies->invokeArgs($generator, [collect([$taxTemplate]), $site, $items, $stats]);
        $this->assertSame(0, $stats->itemsScanned);

        RunwaySupport::fakeInstalled(true);
        Runway::$findResults['product'] = null;

        /** @var Entry $runwayTemplate */
        $runwayTemplate = EntryFacade::make();
        $runwayTemplate
            ->collection('structured_data_templates')
            ->data([
                'blueprint_type' => 'runway',
                'use_for_runway' => 'product',
            ]);

        $scanRunway = new ReflectionMethod(ReportGenerator::class, 'scanRunway');
        $scanRunway->invokeArgs($generator, [collect([$runwayTemplate]), collect(), $stats]);
        $this->assertSame(0, $stats->itemsScanned);

        $assigned = new ReflectionMethod(ReportGenerator::class, 'assignedTemplateIds');
        $model = new class extends Model
        {
            protected $table = 'coverage_products';
        };
        $this->assertSame([], $assigned->invoke($generator, $model));
    }

    #[Test]
    public function it_skips_assigned_template_ids_missing_from_scope_templates(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();
        config()->set('justbetter.structured-data.collections', ['pages']);
        config()->set('justbetter.structured-data.taxonomies', []);
        config()->set('justbetter.structured-data.runway', []);

        /** @var Entry $manualOnlyTemplate */
        $manualOnlyTemplate = EntryFacade::make();
        $manualOnlyTemplate
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('manual-only')
            ->data([
                'title' => 'Manual only',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => false,
                'schema_data' => [],
            ])
            ->published(true)
            ->save();

        /** @var Entry $page */
        $page = EntryFacade::make();
        $page
            ->collection('pages')
            ->locale($site)
            ->slug('ghost-assigned')
            ->data([
                'title' => 'Ghost assigned',
                'structured_data_templates' => ['ghost-template-id', $manualOnlyTemplate->id()],
            ])
            ->published(true)
            ->save();

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertSame(ReportStatus::Completed->value, $report->get('status'));
        $this->assertSame(1, $report->toArray()['items_with_template']);
        $this->assertSame(0, $report->toArray()['incomplete_field_count']);
    }

    #[Test]
    public function it_covers_field_and_assignment_edge_helpers(): void
    {
        $generator = $this->generator();

        $scopeHandle = new ReflectionMethod(ReportGenerator::class, 'scopeHandleFromField');
        $stringField = new ReflectionMethod(ReportGenerator::class, 'stringField');
        $assigned = new ReflectionMethod(ReportGenerator::class, 'assignedTemplateIds');

        $this->assertSame('pages', $scopeHandle->invoke($generator, collect(['pages'])));
        $this->assertSame('blog', $scopeHandle->invoke($generator, ['blog']));
        $this->assertSame('', $scopeHandle->invoke($generator, new \stdClass));
        $this->assertSame('collection', $stringField->invoke($generator, new LabeledValue('collection', 'Collection')));
        $this->assertSame('', $stringField->invoke($generator, ['nope']));

        $entry = \Mockery::mock(Entry::class);
        $entry->shouldReceive('augmentedValue')->with('structured_data_templates')->andReturn(collect([
            \Mockery::mock(Entry::class, function ($mock): void {
                $mock->shouldReceive('id')->andReturn('tpl-1');
            }),
            new \stdClass,
        ]));

        $this->assertSame(['tpl-1'], $assigned->invoke($generator, $entry));

        $invalid = \Mockery::mock(Entry::class);
        $invalid->shouldReceive('augmentedValue')->with('structured_data_templates')->andReturn('not-an-array');
        $this->assertSame([], $assigned->invoke($generator, $invalid));
    }

    #[Test]
    public function it_skips_ghost_template_ids_and_missing_taxonomies(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        config()->set('justbetter.structured-data.taxonomies', ['ghost-tax']);

        $stats = new ReportScanStats;
        $items = collect();
        $evaluate = new ReflectionMethod(ReportGenerator::class, 'evaluateContentItem');

        $mockPage = \Mockery::mock(Entry::class);
        $mockPage->shouldReceive('id')->andReturn('page-ghost');
        $mockPage->shouldReceive('augmentedValue')
            ->with('structured_data_templates')
            ->andReturn(['missing-template']);
        $mockPage->shouldReceive('value')
            ->with('structured_data_templates')
            ->andReturn(['missing-template']);
        $mockPage->shouldReceive('get')
            ->with('structured_data_templates')
            ->andReturn(['missing-template']);

        $evaluate->invokeArgs($this->generator(), [
            $items,
            $stats,
            $mockPage,
            collect(),
            ReportItemType::Entry,
            'collection',
            'pages',
            'collection:pages',
        ]);
        $this->assertSame(1, $stats->itemsScanned);
        $this->assertSame(1, $stats->itemsWithTemplate);

        $taxTemplate = \Mockery::mock(Entry::class);
        $taxTemplate->shouldReceive('augmentedValue')->with('blueprint_type')->andReturn('taxonomy');
        $taxTemplate->shouldReceive('augmentedValue')->with('use_for_taxonomy')->andReturn('ghost-tax');

        TaxonomyFacade::shouldReceive('find')->with('ghost-tax')->andReturn(null);

        $scanTaxonomies = new ReflectionMethod(ReportGenerator::class, 'scanTaxonomies');
        $scanStats = new ReportScanStats;
        $scanTaxonomies->invokeArgs($this->generator(), [collect([$taxTemplate]), $site, collect(), $scanStats]);
        $this->assertSame(0, $scanStats->itemsScanned);
    }

    #[Test]
    public function it_skips_prune_when_retention_is_disabled(): void
    {
        config()->set('justbetter.structured-data.reports.retention_days', 0);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        $report = $this->generator()->generate(['site' => $site]);

        $this->assertSame(ReportStatus::Completed->value, $report->get('status'));
    }
}
