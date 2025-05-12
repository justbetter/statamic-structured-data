<?php

namespace Justbetter\StatamicStructuredData;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Fieldtypes\AvailableVariablesFieldtype;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataBuilder;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataPreview;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataObjectBuilder;
use Justbetter\StatamicStructuredData\Http\Middleware\InjectStructuredData;
use Justbetter\StatamicStructuredData\Listeners\AddStructuredDataTab;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Providers\AddonServiceProvider;
use Symfony\Component\Yaml\Yaml;
use Statamic\Facades\Taxonomy;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/statamic-structured-data.js',
            'resources/css/statamic-structured-data.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $fieldtypes = [
        StructuredDataBuilder::class,
        StructuredDataPreview::class,
        StructuredDataObjectBuilder::class,
        AvailableVariablesFieldtype::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            InjectStructuredData::class,
        ],
    ];

    public function bootAddon()
    {
        $this->bootCollections()
            ->bootTaxonomies()
            ->bootConfig()
            ->bootEvents();
    }

    public function bootEvents()
    {
        Event::listen(EntryBlueprintFound::class, AddStructuredDataTab::class);
        Event::listen(TermBlueprintFound::class, AddStructuredDataTab::class);

        return $this;
    }

    public function bootCollections()
    {
        if($this->app->runningInConsole() || Collection::find('structured_data_templates')) {
            return $this;
        }

        Collection::make('structured_data_templates')
            ->title('Structured Data Templates')
            ->sites(Site::all()->keys()->all())
            ->save();

        $blueprintPath = __DIR__.'/../resources/blueprints/collections/structured_data_templates/structured_data_templates.yaml';
        $blueprintContents = Yaml::parse(File::get($blueprintPath));

        Blueprint::make('structured_data_templates')
            ->setNamespace('collections.structured_data_templates')
            ->setContents($blueprintContents)
            ->save();

        return $this;
    }

    public function bootTaxonomies()
    {
        if($this->app->runningInConsole() || Taxonomy::find('structured_data_objects')) {
            return $this;
        }

        Taxonomy::make('structured_data_objects')
            ->title('Structured Data Objects')
            ->sites(Site::all()->keys()->all())
            ->save();

        $blueprintPath = __DIR__.'/../resources/blueprints/taxonomies/structured_data_objects/structured_data_object.yaml';
        $blueprintContents = Yaml::parse(File::get($blueprintPath));

        Blueprint::make('structured_data_object')
            ->setNamespace('taxonomies.structured_data_objects')
            ->setContents($blueprintContents)
            ->save();

        return $this;
    }

    protected function bootConfig()
    {
        $this->publishes([
            __DIR__.'/../config/structured-data.php' => config_path('justbetter/structured-data.php'),
        ], 'justbetter-structured-data');

        return $this;
    }
}
