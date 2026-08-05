<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Resolvers\EntryResolver;
use Justbetter\StatamicStructuredData\Resolvers\ResourceResolver;
use Justbetter\StatamicStructuredData\Resolvers\RunwayResolver;
use Justbetter\StatamicStructuredData\Resolvers\TaxonomyResolver;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

class InjectStructuredDataAction
{
    /** @var array<int, ResourceResolver> */
    protected array $resolvers;

    public function __construct(protected StructuredDataService $structuredDataService)
    {
        $this->resolvers = [
            new EntryResolver($structuredDataService),
            new TaxonomyResolver($structuredDataService),
            new RunwayResolver($structuredDataService),
        ];
    }

    public function execute(): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $item = $resolver->resolveCurrent();

            if ($item) {
                return $resolver->handle($item);
            }
        }

        return null;
    }

    /**
     * @param  EntryContract|Page|LocalizedTerm|Model  $item
     */
    public function executeForItem($item, ?string $resourceHandle = null): ?string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($item)) {
                return $resolver->handle($item, $resourceHandle);
            }
        }

        return null;
    }
}
