<?php

namespace Justbetter\StatamicStructuredData;

use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Actions\ApplyTemplateAction;
use Justbetter\StatamicStructuredData\Actions\ResolveReportRepository;
use Justbetter\StatamicStructuredData\Commands\StructuredDataReportCommand;
use Justbetter\StatamicStructuredData\Fieldtypes\AvailableVariablesFieldtype;
use Justbetter\StatamicStructuredData\Fieldtypes\RunwayResourcesFieldtype;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataBuilder;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataObjectBuilder;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataPreview;
use Justbetter\StatamicStructuredData\Listeners\AddStructuredDataTabListener;
use Justbetter\StatamicStructuredData\Listeners\EntryCreatedListener;
use Justbetter\StatamicStructuredData\Listeners\TermCreatedListener;
use Justbetter\StatamicStructuredData\Services\PresetService;
use Justbetter\StatamicStructuredData\Services\Report\ReportGenerator;
use Justbetter\StatamicStructuredData\Tags\StructuredData;
use Statamic\Auth\Permission;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\CP\Navigation\NavItem;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntryCreated;
use Statamic\Events\TermBlueprintFound;
use Statamic\Events\TermCreated;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\CP\Nav as NavFacade;
use Statamic\Facades\Permission as PermissionFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Sites\Site;
use Statamic\Taxonomies\Taxonomy;
use Symfony\Component\Yaml\Yaml;

class ServiceProvider extends AddonServiceProvider
{
    /** @phpstan-ignore-next-line */
    protected $vite = [
        'input' => [
            'resources/js/statamic-structured-data.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $tags = [
        StructuredData::class,
    ];

    protected $fieldtypes = [
        StructuredDataBuilder::class,
        StructuredDataPreview::class,
        StructuredDataObjectBuilder::class,
        AvailableVariablesFieldtype::class,
        RunwayResourcesFieldtype::class,
    ];

    protected $actions = [
        ApplyTemplateAction::class,
    ];

    protected $commands = [
        StructuredDataReportCommand::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $listen = [
        EntryCreated::class => [
            EntryCreatedListener::class,
        ],
        TermCreated::class => [
            TermCreatedListener::class,
        ],
        EntryBlueprintFound::class => [
            AddStructuredDataTabListener::class,
        ],
        TermBlueprintFound::class => [
            AddStructuredDataTabListener::class,
        ],
    ];

    public function register(): void
    {
        parent::register();

        ResolveReportRepository::bind();
        ReportGenerator::bind();
    }

    public function bootAddon(): void
    {
        $this->bootCollections()
            ->bootTaxonomies()
            ->bootConfig()
            ->bootTranslations()
            ->bootMigrations()
            ->bootPermissions()
            ->bootNavigation()
            ->bootServices();
    }

    public function bootTranslations(): self
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        return $this;
    }

    public function bootServices(): self
    {
        $this->app->singleton(PresetService::class);

        return $this;
    }

    protected function bootMigrations(): self
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function bootPermissions(): self
    {
        PermissionFacade::extend(function (): void {
            PermissionFacade::group('justbetter', 'JustBetter', function (): void {
                $permission = config()->string('justbetter.structured-data.reports.permissions.view');

                PermissionFacade::register($permission, function (Permission $permission): void {
                    $permission
                        ->label('View Structured Data reports')
                        ->description('Gives the user access to structured data coverage reports.');
                });
            });
        });

        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function bootNavigation(): self
    {
        NavFacade::extend(function (Navigation $nav): void {
            $justBetter = $nav->find('Tools', 'JustBetter');

            if (! $justBetter) {
                return;
            }

            $reports = $nav->item(__('Structured Data Reports'));
            $reports->route('justbetter.structured-data.reports.index');
            $reports->icon('charts');
            $reports->can(config()->string('justbetter.structured-data.reports.permissions.view'));

            /** @var SupportCollection<int, NavItem> $children */
            $children = $justBetter->resolveChildren()->children() ?? collect();

            $justBetter->children($children->push($reports)->all());
        });

        return $this;
    }

    public function bootCollections(): self
    {
        if ($this->app->runningInConsole() || CollectionFacade::find('structured_data_templates')) {
            return $this;
        }

        /** @var SupportCollection<string, Site> $sites */
        $sites = SiteFacade::all();

        CollectionFacade::make('structured_data_templates')
            ->title('Structured Data Templates')
            ->sites($sites->keys()->all())
            ->save();

        $blueprintPath = __DIR__.'/../resources/blueprints/collections/structured_data_templates/structured_data_templates.yaml';
        $blueprintContents = Yaml::parse(File::get($blueprintPath));

        BlueprintFacade::make('structured_data_templates')
            ->setNamespace('collections.structured_data_templates')
            ->setContents($blueprintContents)
            ->save();

        return $this;
    }

    public function bootTaxonomies(): self
    {
        if ($this->app->runningInConsole() || TaxonomyFacade::find('structured_data_objects')) {
            return $this;
        }

        /** @var SupportCollection<string, Site> $sites */
        $sites = SiteFacade::all();

        /** @var Taxonomy $taxonomy */
        $taxonomy = TaxonomyFacade::make('structured_data_objects');
        $taxonomy->title('Structured Data Objects')
            ->sites($sites->keys()->all())
            ->save();

        $blueprintPath = __DIR__.'/../resources/blueprints/taxonomies/structured_data_objects/structured_data_object.yaml';
        $blueprintContents = Yaml::parse(File::get($blueprintPath));

        BlueprintFacade::make('structured_data_object')
            ->setNamespace('taxonomies.structured_data_objects')
            ->setContents($blueprintContents)
            ->save();

        return $this;
    }

    protected function bootConfig(): self
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/structured-data.php',
            'justbetter.structured-data'
        );

        $this->publishes([
            __DIR__.'/../config/structured-data.php' => config_path('justbetter/structured-data.php'),
        ], 'justbetter-structured-data');

        return $this;
    }
}
