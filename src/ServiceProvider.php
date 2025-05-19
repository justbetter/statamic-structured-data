<?php

namespace Justbetter\StatamicStructuredData;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Fieldtypes\AvailableVariablesFieldtype;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataBuilder;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataObjectBuilder;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataPreview;
use Justbetter\StatamicStructuredData\Listeners\AddStructuredDataTabListener;
use Justbetter\StatamicStructuredData\Tags\StructuredData;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Providers\AddonServiceProvider;
use Symfony\Component\Yaml\Yaml;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/statamic-structured-data.js',
            'resources/css/statamic-structured-data.css',
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
    ];

    public function bootAddon(): void
    {
        $this->bootCollections()
            ->bootTaxonomies()
            ->bootConfig()
            ->bootActions()
            ->bootEvents();
    }

    public function bootEvents(): self
    {
        Event::listen(EntryBlueprintFound::class, AddStructuredDataTabListener::class);
        Event::listen(TermBlueprintFound::class, AddStructuredDataTabListener::class);

        return $this;
    }

    public function bootCollections(): self
    {
        if ($this->app->runningInConsole() || Collection::find('structured_data_templates')) {
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

    public function bootTaxonomies(): self
    {
        if ($this->app->runningInConsole() || Taxonomy::find('structured_data_objects')) {
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

    protected function bootConfig(): self
    {
        $this->publishes([
            __DIR__.'/../config/structured-data.php' => config_path('justbetter/structured-data.php'),
        ], 'justbetter-structured-data');

        return $this;
    }
}
