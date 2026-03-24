<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Justbetter\StatamicStructuredData\ServiceProvider;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Taxonomies\Taxonomy as TaxonomyModel;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function boot_collections_skips_when_running_in_console(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(true);
        });

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootCollections();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }

    #[Test]
    public function boot_collections_skips_when_collection_exists(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(false);
        });
        $existingCollection = CollectionFacade::make('structured_data_templates');
        $existingCollection->save();
        CollectionFacade::shouldReceive('find')->with('structured_data_templates')->andReturn($existingCollection);

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootCollections();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }

    #[Test]
    public function boot_collections_creates_collection_when_not_exists(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(false);
        });
        CollectionFacade::shouldReceive('find')->with('structured_data_templates')->andReturn(null);
        $newCollection = $this->mock(Collection::class, function ($mock): void {
            $mock->shouldReceive('title')->andReturnSelf();
            $mock->shouldReceive('sites')->andReturnSelf();
            $mock->shouldReceive('save')->andReturnSelf();
        });
        CollectionFacade::shouldReceive('make')->with('structured_data_templates')->andReturn($newCollection);
        $site = $this->mock(\Statamic\Sites\Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        Site::shouldReceive('all')->andReturn(collect([
            'default' => $site,
        ]));

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootCollections();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }

    #[Test]
    public function boot_taxonomies_skips_when_running_in_console(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(true);
        });

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootTaxonomies();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }

    #[Test]
    public function boot_taxonomies_skips_when_taxonomy_exists(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(false);
        });
        /** @var TaxonomyModel $existingTaxonomy */
        $existingTaxonomy = Taxonomy::make('structured_data_objects');
        $existingTaxonomy->save();
        Taxonomy::shouldReceive('find')->with('structured_data_objects')->andReturn($existingTaxonomy);

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootTaxonomies();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }

    #[Test]
    public function boot_taxonomies_creates_taxonomy_when_not_exists(): void
    {
        $appMock = $this->mock(Application::class, function ($mock): void {
            $mock->shouldReceive('runningInConsole')->andReturn(false);
        });
        Taxonomy::shouldReceive('find')->with('structured_data_objects')->andReturn(null);
        $newTaxonomy = $this->mock(TaxonomyModel::class, function ($mock): void {
            $mock->shouldReceive('title')->andReturnSelf();
            $mock->shouldReceive('sites')->andReturnSelf();
            $mock->shouldReceive('save')->andReturnSelf();
        });
        Taxonomy::shouldReceive('make')->with('structured_data_objects')->andReturn($newTaxonomy);
        $site = $this->mock(\Statamic\Sites\Site::class, function ($mock): void {
            $mock->shouldReceive('handle')->andReturn('default');
        });
        Site::shouldReceive('all')->andReturn(collect([
            'default' => $site,
        ]));

        /** @var Application $appMock */
        $provider = new ServiceProvider($appMock);
        $result = $provider->bootTaxonomies();

        $this->assertInstanceOf(ServiceProvider::class, $result);
    }
}
