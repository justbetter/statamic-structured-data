<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use StatamicRadPack\Runway\ModelRepository;
use StatamicRadPack\Runway\Resource;

class InjectStructuredDataActionRunwayTest extends TestCase
{
    #[Test]
    public function execute_for_item_handles_runway_model(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn(['<script>product</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->executeForItem($model, 'product');

        $this->assertSame('<script>product</script>', $result);
    }

    #[Test]
    public function execute_for_item_returns_null_when_resource_not_enabled(): void
    {
        Config::set('justbetter.structured-data.runway', []);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getJsonLdScripts');
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertNull($action->executeForItem($model, 'product'));
    }

    #[Test]
    public function execute_for_item_returns_null_when_scripts_empty(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn([]);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertNull($action->executeForItem($model, 'product'));
    }

    #[Test]
    public function execute_for_item_handles_entry(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($entry)
                ->andReturn(['<script>entry</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertSame('<script>entry</script>', $action->executeForItem($entry));
    }

    #[Test]
    public function execute_for_item_handles_term(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->save();

        $term = $this->mock(LocalizedTerm::class, function (MockInterface $mock) use ($taxonomy): void {
            $mock->shouldReceive('taxonomy')->andReturn($taxonomy);
        });

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($term): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($term)
                ->andReturn(['<script>term</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertSame('<script>term</script>', $action->executeForItem($term));
    }

    #[Test]
    public function execute_for_item_returns_null_for_unsupported_type(): void
    {
        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getJsonLdScripts');
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        /** @phpstan-ignore-next-line argument.type */
        $this->assertNull($action->executeForItem('not-supported'));
    }

    #[Test]
    public function execute_handles_runway_model_from_uri(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', ['product']);

        $resource = new Resource;
        $resource->resourceHandle = 'product';

        $model = new class extends Model
        {
            public Resource $resource;

            protected $table = 'products';

            public function runwayResource(): Resource
            {
                return $this->resource;
            }
        };
        $model->resource = $resource;

        ModelRepository::$findByUriResult = $model;

        URL::shouldReceive('getCurrent')->andReturn('/product-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->andReturn(null);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn(['<script>runway</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertSame('<script>runway</script>', $action->execute());
    }

    #[Test]
    public function execute_returns_null_when_runway_uri_model_not_enabled(): void
    {
        RunwaySupport::fakeInstalled(true);
        Config::set('justbetter.structured-data.runway', []);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        ModelRepository::$findByUriResult = $model;

        URL::shouldReceive('getCurrent')->andReturn('/product-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->andReturn(null);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getJsonLdScripts');
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertNull($action->execute());
    }

    #[Test]
    public function execute_returns_null_when_runway_uri_result_is_not_model(): void
    {
        RunwaySupport::fakeInstalled(true);
        ModelRepository::$findByUriResult = 'not-a-model';

        URL::shouldReceive('getCurrent')->andReturn('/product-url');
        $site = $this->mock(Site::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        SiteFacade::shouldReceive('current')->andReturn($site);
        SiteFacade::shouldReceive('multiEnabled')->andReturn(false);
        EntryFacade::shouldReceive('findByUri')->andReturn(null);
        Term::shouldReceive('findByUri')->andReturn(null);

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getJsonLdScripts');
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertNull($action->execute());
    }
}
