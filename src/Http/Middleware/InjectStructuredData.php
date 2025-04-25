<?php

namespace Justbetter\StatamicStructuredData\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Structures\Page;

class InjectStructuredData
{
    protected $structuredDataService;

    public function __construct(StructuredDataService $structuredDataService)
    {
        $this->structuredDataService = $structuredDataService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $response instanceof Response || ! $this->shouldInject($response)) {
            return $response;
        }

        $entry = $this->getCurrentEntry();

        if (! $entry) {
            return $response;
        }

        if ($entry instanceof Page) {
            $entry = $entry->entry();
        }

        $enabledCollections = config('justbetter.structured-data.collections', []);

        if (! in_array($entry?->collection()?->handle(), $enabledCollections)) {
            return $response;
        }

        $scripts = $this->structuredDataService->getJsonLdScripts($entry);

        if (!$scripts ?? false) {
            return $response;
        }

        $content = $response->getContent();
        $scriptsHtml = implode("\n", $scripts);

        $content = str_replace('</head>', "\n".$scriptsHtml."\n</head>", $content);
        $response->setContent($content);

        return $response;
    }

    protected function shouldInject($response): bool
    {
        $content = $response->getContent();

        return
            str_contains($response->headers->get('Content-Type', ''), 'text/html') &&
            str_contains($content, '</head>');
    }

    protected function getCurrentEntry(): Page|Entry|null
    {
        $url = URL::getCurrent();
        return EntryFacade::findByUri($url, Site::current()->handle());
    }
}
