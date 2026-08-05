<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\EntryVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\GlobalVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\RunwayVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\SiteVariableProvider;
use Justbetter\StatamicStructuredData\Services\AvailableVariables\Providers\TermVariableProvider;

class AvailableVariablesService
{
    protected SiteVariableProvider $site;

    protected GlobalVariableProvider $globals;

    protected EntryVariableProvider $entry;

    protected TermVariableProvider $term;

    protected RunwayVariableProvider $runway;

    public function __construct(
        protected BlueprintVariableMapper $mapper = new BlueprintVariableMapper,
        protected SeoProVariables $seoProVariables = new SeoProVariables,
    ) {
        $this->site = new SiteVariableProvider;
        $this->globals = new GlobalVariableProvider($this->mapper);
        $this->entry = new EntryVariableProvider($this->mapper, $this->seoProVariables);
        $this->term = new TermVariableProvider($this->mapper, $this->seoProVariables);
        $this->runway = new RunwayVariableProvider($this->mapper);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function all(mixed $parent = null): array
    {
        return [
            'site' => $this->site->variables($parent),
            'globals' => $this->globals->variables($parent),
            'entry' => $this->entry->variables($parent),
            'term' => $this->term->variables($parent),
            'runway' => $this->runway->variables($parent),
        ];
    }

    public function seoPro(): SeoProVariables
    {
        return $this->seoProVariables;
    }

    public function entry(): EntryVariableProvider
    {
        return $this->entry;
    }

    public function term(): TermVariableProvider
    {
        return $this->term;
    }

    public function globals(): GlobalVariableProvider
    {
        return $this->globals;
    }

    public function runway(): RunwayVariableProvider
    {
        return $this->runway;
    }
}
